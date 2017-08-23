<?php
class FW_VistaProcessing_Model_Observer {
	
	protected function _construct()
    {
        $this->_init('vistaprocessing/observer');
    }
    
    public function process_vista_order_accept(Varien_Event_Observer $observer)
	{
		//GET ORDER IDs
		$orderIds = $observer->getOrderIds();
		
		if (!empty($orderIds) && is_array($orderIds))
		{
			foreach ($orderIds as $oid){
				
				//LOAD ORDER MAGE MODEL
				$order = Mage::getSingleton('sales/order');
				//$_order = Mage::getModel('sales/order')->load($oid); 

				if ($order->getId() != $oid) $order->reset()->load($oid);
				
				//POST XML
				Mage::getModel('vistaprocessing/vista')->processVistaOrder($order);
			}
	   }
	}
	
    /**
     * Locates incomplete paypal orders to finish processing
     */
    public function completePayPalFailedOrders(Mage_Cron_Model_Schedule $schedule){
        //GATHER ORDERS THAT ARE VISTA STATUS EQUAL TO IN PROGRESS
        $orders = Mage::getModel('sales/order')->getCollection()->addAttributeToFilter('vistaorder_status', 'IN PROGRESS');
		
        //LOOP THRU ORDERS
        foreach ($orders as $order){
                //LOAD PAYMENT MAGE MODEL
                $payment = $order->getPayment();

                //PAYMENT METHOD
                $payment_check = explode("_", $payment->getMethod());
                $payment_method = $payment_check[0];
                $paypal_status = $payment->getAdditionalInformation('paypal_payment_status');

                //CHECK FOR PAYPAL PAYMENT METHOD AND COMPLETED PAYPAL TRANSACTION
                if($payment_method == "paypal" && $paypal_status == "completed"){

                    //FIRE CUSTOM EVENT SO OTHER MODULES CAN USE. Drop Ship Email/VIP is one of these
                    Mage::dispatchEvent('vistaprocessing_complete_paypal_order', array('order' => $order));

                    //START / POSTING OF VISTA ORDER XML
                    Mage::getModel('vistaprocessing/vista')->createVistaQueueItem($order);

                    //SET VISTA ORDER STATUS
                    $order->setVistaorderStatus('PENDING');
                    $order->save();

                    //LOG PAYPAL AUTO FIXED OCCUR
                    Mage::log('PAYPAL AUTO-COMPLETED CRON FIRED - ORDER #'.$order->getIncrementId().'- PAYMENT: '.$payment_method.'', null, 'FW_PAYPAL_AUTOCOMPLETE.log');
                }

                //DESTROY ALL CONDITIONAL VARIABLES
                unset($payment);
                unset($payment_check);
                unset($payment_method);
                unset($paypal_status);
        }
    }
}
