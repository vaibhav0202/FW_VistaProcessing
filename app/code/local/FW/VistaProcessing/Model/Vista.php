<?php
class FW_VistaProcessing_Model_Vista extends Mage_Core_Model_Abstract
{
    public function _construct()
    {
            parent::_construct();
            $this->_init('vistaprocessing/vista');
    }
	
    public function processVistaOrder($order){
            //LOAD PAYMENT MAGE MODEL
            $payment = $order->getPayment();

            //CHECK PAYMENT METHOD
            $payment_check = explode("_", $payment->getMethod());
            $payment_method = $payment_check[0];

            //CHECK FOR PAYPAL PAYMENT METHOD
            $transactionid = "";
            if($payment_method == "paypal"){
                    // WE NO LONGER CREATE A QUEUE ITEM FOR PAYPAL ORDERS ON ORDER complete
                    // WE WAIT FOR THE IPN - See IpnController
                    return;
            }elseif($payment_method == "authnetcim"){
                    //LOAD authnetcim PAYMENT DATA
                    $transactionid = $payment->getLastTransId();
            }

            //LOG VISTA POSTING PROCESS HAS STARTED
            Mage::log('VISTA PROCESS ORDERACCEPT OBSERVER FIRED - ORDER #'.$order->getIncrementId().'- PAYMENT: '.$payment_method.' - TRANSACTIONID: '.$transactionid, null, 'FW_VistaProcessing.log');

            //INIT VISTA ORDER STATUS
            $order->setVistaorderStatus('PENDING');
            
            //Add to queue
            $this->createVistaQueueItem($order);

            //CLOSE AND SAVE THIS ORDER
            //CHECK FOR PAYPAL PAYMENT METHOD AND SAVE VISTA STATUS
            $state = 'processing';
            $status = 'complete';
            $comment = 'Changing state to Processing and status to Complete Status';
            $isCustomerNotified = true;
            $order->setState($state, $status, $comment, $isCustomerNotified);
            $order->save();
            
            if($payment_method != "paypal" && $order->canInvoice())
            {
                //LOAD AND GENERATE THE INVOICE
                $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
                $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
                $invoice->register();

                $transaction = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder());

                $transaction->save();
            }
    }

    public function submitToVista(FW_Queue_Model_Queue $queue)
    {
        //LOAD FW_Queue_Model_Queue STORED DATA
        $queue_data = $queue->getQueueData();
        $order = Mage::getModel('sales/order')->load($queue_data['order_id']); 
        $customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
        $payment = $order->getPayment();

        //CHECK IF ORDER IS 7 DAYS OLD - IF SO - REMOVE CC DATA AND CLEAR VISTA RE-POSTING STATUS
        $orderDate = strtotime($order->getCreatedAt());
                
        if($orderDate <= strtotime('+1 week'))
        {
            //PAYMENT METHOD
            if (empty($payment)) { throw new Exception(' --- NO ORDER FOUND --- '); return; }
            $payment_check = explode("_", $payment->getMethod());
            $payment_method = $payment_check[0];
            $paypal_status = $payment->getAdditionalInformation('paypal_payment_status');
            if(strtolower($payment_method) == "paypal" && strtolower($paypal_status) != "completed") {
              // The paypal order is not complete do not send this to vista
              throw new Exception(' --- PAYPAL PAYMENT NOT CONFIRMED ---');
              return;
            }
          
            //LOGGING FOR RESPONSE TIME COLLECTION
            $time_start = microtime(true);
                
            //CONNECT AND BUILD VISTA GATEWAY XML AND SEND MAGE Varien Objects
            $gw = new FW_VistaProcessing_Model_Gateway($customer, $order, $payment);

            //SCAN XML AND DOUBLE CHECK IF VISTA CUSTOMER ID IS SET
            $xml = simplexml_load_string($gw->_OrderAcceptObject['Input']);
            if (empty($xml)) { throw new Exception(' --- INVALID XML --- '); return; }
            $vcustid = (isset($xml->Orders->Basket->Order->Header->ShipTo->CustomerId)) ? $xml->Orders->Basket->Order->Header->ShipTo->CustomerId : 0;

            //CHECK FOR VISTA CUSTOMER ID
            if($vcustid == 'N/A-ERROR' || $vcustid == NULL || $vcustid == 0)
            {
                $xml = simplexml_load_string($gw->_CustomerInsertObject['Input']);
                if (empty($xml)) { throw new Exception(' --- INVALID XML --- '); return; }
                $order->setVistaorderStatus('ERROR');
                $order->save();
                $this->logEndTime($order, $time_start);
                Mage::log('VISTA ORDER ' . $order->getIncrementId() . ' NEW CUSTOMER OBSERVER XML ERROR POSTED: '.$xml->asXML().'', null, 'VISTA_ORDER_ERROR.log');
                throw new Exception(' --- INVALID VISTA CUSTOMER ID ---');
            }

            //PROCESS OrderAccept_PutXml XML POST WITH THE GATEWAY _OrderAcceptObject OBJECT
            $response = $gw->OrderAccept_PutXml($gw->_OrderAcceptObject);

            //REVIEW RESPONSE
            if(isset($response['OrderBasket']['Orders']['Basket']['Order']['Trailer']['UniqOrderNo']))
            {
                //ORDER SUCCESS SAVE STATUS AND RETURNED ORDERNO
                //SET VISTA ORDER STATUS
                $order->setVistaorderStatus('ACCEPTED');
                //SET VISTA ORDER ID
                $order->setVistaorderId($response['OrderBasket']['Orders']['Basket']['Order']['Trailer']['UniqOrderNo']);
            }
            else
            {
                //SET VISTA ORDER STATUS
                $order->setVistaorderStatus('ERROR');
                //LOG ERRORS
                Mage::log('VISTA ORDER ' . $order->getIncrementId() . ' PROCESS ORDERACCEPT ERROR RESPONSE: '.$gw->response.'', null, 'VISTA_ORDER_ERROR.log');
                //SCAN XML AND REMOVE CC FROM LOG POST
                $xml = simplexml_load_string($gw->_OrderAcceptObject['Input']);
                if (empty($xml)) { throw new Exception(' --- INVALID XML --- '); return; }
                $result = $xml->Orders->Basket->Order->Header->CreditCard->CreditCardNumber = "ACCESSDENIED";
                $order->save();
                $this->logEndTime($order, $time_start);
                throw new Exception('Vista Order Submission Failed');
            }
            
            $this->logEndTime($order, $time_start);
            
        }
        else
        {
            //SET VISTA ORDER STATUS
            $order->setVistaorderStatus('CANCELED');   
        }
        $order->save();

        }
    
    public function createVistaQueueItem($order)
    {
         //INIT A NEW FW_Queue_Model_Queue OBJECT
         $queue = Mage::getModel('fw_queue/queue');
         //BUILD DATA ARRAY TO STORE IN QUEUE
         $queueItemData = array(
                 'type' => 'submission',
                 'order_id' => $order->getId(),
         );

         //SEND QUEUE DATA ARRAY AND SUBMIT A NEW QUEUE RECORD
         $queue->addToQueue('vistaprocessing/vista', 'submitToVista', $queueItemData, 'vista submission', "Vista Submit for Order: " . $order->getIncrementId());

    }

    private function logEndTime($order, $time_start)
    {
            //LOGGING FOR RESPONSE TIME COLLECTION
            $time_end = microtime(true);
            $time = $time_end - $time_start;
            $logwrite = Mage::getSingleton('core/resource')->getConnection('core_write');
            $logwrite->query("insert into fw_responselog(order_id, quote_id, type, time_start, time_end, time_elapsed) values('".$order->getIncrementId()."','".$order->getQuoteId()."','VISTA','".$time_start."','".$time_end."','".$time."')"); 
    }
}
