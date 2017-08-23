<?php
class FW_VistaProcessing_Adminhtml_VistaprocessingController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction()
    {

    }
    
    protected function _isAllowed()
    {
        return true;
    }
    
	protected function _initOrder()
    {
        $id = $this->getRequest()->getParam('order_id');
        $order = Mage::getModel('sales/order')->load($id);

        if (!$order->getId()) {
            $this->_getSession()->addError($this->__('This order no longer exists.'));
            $this->_redirect('*/*/');
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
            return false;
        }
        Mage::register('sales_order', $order);
        Mage::register('current_order', $order);
        return $order;
    }
    
    public function orderVistaViewAction()
    {
    	$order = $this->_initOrder();
    	
    	$vistaorder_status = $order->getVistaorderStatus();
    	
    	$output = "VISTA OrderAccept XML Post Status: ".$vistaorder_status." ";
    	
    	switch($vistaorder_status){
    		case "IN PROGRESS":
    			$output .= '<br><button type="button" onClick="window.location=';
    			$output .= "'".$this->getVistaStartUrl($order)."'";
    			$output .= '">CREATE QUEUE ITEM FOR VISTA SUBMISION</button>';
    			$output .= '<br>This order was stalled and currently not in line to be re-posted. Clicking START will set the status to PENDING and will try to post to Vista';
    			$output .= '<br><br>'.$this->getXMLData($order);
    			break;
    		default:
    			$output .= '<br><br>'.$this->getXMLData($order);
    	}
        $output .= '<br><br>'.$this->getXMLData($order);   	
    	$output .= "<br>";
    	
    	print $output;
    }
    
    public function startVistaProcessAction()
    {
    	if ($order = $this->_initOrder()) {
    		try {
                        //INIT VISTA ORDER STATUS
                        $order->setVistaorderStatus('PENDING');

                        //INIT A NEW FW_Queue_Model_Queue OBJECT
                        $queue = Mage::getModel('fw_queue/queue');
                        //BUILD DATA ARRAY TO STORE IN QUEUE
                        $queueItemData = array(
                            'type' => 'submission',
                            'order_id' => $order->getId(),
                        );
                
                        //SEND QUEUE DATA ARRAY AND SUBMIT A NEW QUEUE RECORD
                        $queue->addToQueue('vistaprocessing/vista', 'submitToVista', $queueItemData, 'vistaprocessing_vista', "Vista Submit for Order: " . $order->getIncrementId());
                        $this->_getSession()->addSuccess($this->__('The QUEUE Item has been created'));
                        $order->save();
                        
    		} catch (Mage_Core_Exception $e) {
    			$this->_getSession()->addError($e->getMessage());
    		} catch (Exception $e) {
    			$this->_getSession()->addError($this->__('Failed to create the QUEUE Item'));
    			Mage::logException($e);
    		}
    	}
    	$this->_redirect('*/sales_order/view', array('order_id' => $order->getId()));
    }
    
    public function getXMLData($order)
    {
    	$output = "";
    	$output .= '<textarea rows="100" cols="100">';
    	
    	//LOAD PAYMENT MAGE MODEL
    	$payment = $order->getPayment();
    	
    	//LOAD CUSTOMER MAGE MODEL
    	$customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
    	
    	//LOAD PAYMENT DATA
    	$transactionid = $payment->getLastTransId();
    	
    	//CONNECT AND BUILD VISTA GATEWAY XML AND SEND MAGE Varien Objects
    	$gw = new FW_VistaProcessing_Model_Gateway($customer, $order, $payment);
    	
    	//GET LOGGED ADMIN USER SESSION
    	$user = Mage::getSingleton('admin/session')->getUser();
    	
    	//GET THE ROLE ID OF THE USER
    	$roleId = implode('', $user->getRoles());
    	
    	//GET THE ROLE NAME
    	$roleName = Mage::getModel('admin/roles')->load($roleId)->getRoleName();
    	
    	//CHECK IS USER IS
    	if ($roleName == 'Administrators' || $roleName == 'Customer Service') {
    		//LOAD XML WITH THE GATEWAY _OrderAcceptObject OBJECT
    		//APPEND TO OUTPUT
    		$output .= $gw->_OrderAcceptObject['Input'];
    	}else{
    		//SCAN XML AND REMOVE CC FROM LOG POST
    		$xml = simplexml_load_string($gw->_OrderAcceptObject['Input']);
    		$result = $xml->Orders->Basket->Order->Header->CreditCard->CreditCardNumber = "ACCESSDENIED";
    		//LOAD XML WITH THE FILTERED XML
    		//APPEND TO OUTPUT
    		$output .= $xml->asXML();
    	}
    	
    	$output .= '</textarea>';
    	
    	//LOG VISTA ADMIN ACESS VIEW
    	Mage::log('VISTA ADMIN VIEW FIRED - ORDER #'.$order->getIncrementId().'- TRANSACTIONID: '.$transactionid, null, 'FW_VistaProcessing.log');
    	
    	return $output;
    }

    public function getVistaStartUrl($order)
    {
    	return $this->getUrl('*/vistaprocessing/startVistaProcess', array('order_id'=>$order->getId()));
    }
}
