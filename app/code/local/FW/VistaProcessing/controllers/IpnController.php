<?php

/**
 * Unified IPN controller for all supported PayPal methods
 */
require_once 'Mage/Paypal/controllers/IpnController.php';
class FW_VistaProcessing_IpnController extends Mage_Paypal_IpnController
{
    /**
     * Instantiate IPN model and pass IPN request to it
     */
    public function indexAction()
    {
      
      parent::indexAction();
      $data = $this->getRequest()->getPost();

      if( isset($data['invoice']))
      {
	$id = $data['invoice'];
      } else {
	return;
      }

      $order = Mage::getModel('sales/order')->loadByIncrementId($id);

      //IF ORDER DOESN'T LOAD, RETURN SO THAT WE DONT CREATE BLANK QUEUE ITEMS
      if( !$order->getId())
	return;

      $orderCanceled = true;

      foreach( $order->getAllItems() as $item )
      {
	  if( $item->getQtyCanceled() != $item->getQtyOrdered())
	  {
		$orderCanceled = false;
	  }
      }

      //IF ORDER ALREADY IS CANCELED, REFUND THE IPN Immediately - Customer should make a new order
      if ($order->hasInvoices() && $orderCanceled )
      {
        foreach ($order->getInvoiceCollection() as $invoice)
        {
                //Find the matching invoice with the IPN Amount - Refund this invoice
                if( $invoice->getState() == Mage_Sales_Model_Order_Invoice::STATE_PAID && isset($data['mc_gross']) && $invoice->getGrandTotal() == $data['mc_gross'] )
                { 
                        $creditmemo = $service->prepareInvoiceCreditmemo($invoice);
                        $creditmemo->setRefundRequested(true)
                                    ->setOfflineRequested(false) // request to refund online
                                    ->register();

                        Mage::getModel('core/resource_transaction')
                            ->addObject($creditmemo)
                            ->addObject($creditmemo->getOrder())
                            ->addObject($creditmemo->getInvoice())
                            ->save();

                        Mage::log('VISTA PROCESS IPN CONTROLLER REFUNDED ORDER / CANCEL CAME BEFORE IPN - ORDER #'. $id, null, 'FW_VistaProcessing.log');
                        // Add the comment and save the order (last parameter will determine if comment will be sent to customer)
                        $order->addStatusHistoryComment('This order was canceled before the Paypal IPN was received. It should not be fulfilled. Please reorder if the customer wishes to pass this to fulfillment!');
                        $order->save();

                        return;
                }
        }
      }
      
      //LOG VISTA POSTING PROCESS HAS STARTED
      Mage::log('VISTA PROCESS IPN CONTROLLER FIRED - ORDER #'.$id, null, 'FW_VistaProcessing.log');

      if (empty($order->getVistaorderStatus()) || $order->getVistaorderStatus() == "IN PROGRESS") {
        //INIT VISTA ORDER STATUS
        $order->setVistaorderStatus('PENDING');
              
        Mage::getModel('vistaprocessing/vista')->createVistaQueueItem($order);
        
        
        //CLOSE AND SAVE THIS ORDER
        //CHECK FOR PAYPAL PAYMENT METHOD AND SAVE VISTA STATUS
        $status = 'complete';
        $comment = 'Vista Changing state to processing and status to Complete Status';
        $isCustomerNotified = true;
        $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, $status, $comment, $isCustomerNotified);
        $order->save();
      }
    }
}
