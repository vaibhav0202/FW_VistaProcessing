<?php
class FW_VistaProcessing_Model_Gateway {
	private $mode		= "TEST";
	private $mode_url   = "https://vwserv.fwmedia.com/i-connect/test/";

public function __construct(Varien_Object $customer = NULL, Varien_Object $order = NULL, Varien_Object $payment = NULL){
		$this->_CustomerCheckObject = $this->_build_CustomerCheckObject($customer, $order);
		$this->_CustomerInsertObject = $this->_build_CustomerInsertObject($customer, $order);
		$this->_OrderAcceptObject = $this->_build_OrderAcceptObject($customer, $order, $payment);
	}
	
	public function methods()
	{
		echo "->Customer_GetListByEmail(\$data[]);<br>\n";
		echo "->CustApproval_Put(\$data[]);<br>\n";
		echo "->OrderAccept_PutXml(\$data[]);<br>\n";
	}
	
	public function __call($name, $request)
	{
		switch($name)
			{
				case "Customer_GetListByEmail":
					$wsurl = $this->mode_url.'WSWordMatch/Customer.asmx/GetListByEmail';
					return $this->send($request[0],$wsurl);
					break;
	
				case "CustApproval_Put":
					$wsurl = $this->mode_url.'WSCustApproval/CustApproval.asmx/Put';
					return $this->send($request[0],$wsurl);
					break;
					
				case "OrderAccept_PutXml":
					$wsurl = $this->mode_url.'WSOrderInput/OrderAccept.asmx/PutXml';
					return $this->send($request[0],$wsurl);
					break;
					
				default:
					throw new Exception('Method Choice Error - '.$name.' is not a valid Gateway method name.');
				return FALSE;
				break;
			}
	}
	
	private function send($request, $wsurl)
	{
		$curl_connection = curl_init($wsurl);
		curl_setopt($curl_connection, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($curl_connection, CURLOPT_USERAGENT,"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
		curl_setopt($curl_connection, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl_connection, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl_connection, CURLOPT_FOLLOWLOCATION, 1);

		curl_setopt($curl_connection, CURLOPT_POSTFIELDS, http_build_query($request));
		$result = curl_exec($curl_connection);
		//close the connection
		curl_close($curl_connection);
		
		$format_response = $this->xml2array($result);
		$this->response = $result;
		
		return $format_response;
	}
	
	public function _build_CustomerCheckObject($customer=NULL, $order=NULL){
		//BUILD CUSTOMER CHECK XML MAGE ORDER IS REQUIRED
		if(isset($order)){
			$data = array('Email'=> $order->getCustomerEmail());
			return $data;
		}		
	}
	
	public function _build_CustomerInsertObject($customer=NULL, $order=NULL){

		//BUILD CUSTOMER INSERT XML MAGE QUOTE IS REQUIRED
		if(isset($order)){

			//LOAD QUOTE BILLING ADDRESS AS PRIMARY ACCOUNT ID
			$primaryAddress = $order->getBillingAddress();

			//CHECK IF CUSTOMER NAME IS GUEST NULL IF SO USE BILLING NAME
			if($order->getCustomerFirstname() != NULL){
				//CHECK AND BUILD CUSTOMER NAME - MUST LESS THEN 30 CHARS
				$custname = $order->getCustomerFirstname().' '.$order->getCustomerLastname();
				if(strlen($custname) > 30){
					$custname = substr($custname, 0, 30);
				}
			}else{
				//LOAD BILLING NAME
				$custname = $primaryAddress->getFirstname().' '.$primaryAddress->getLastname();
				if(strlen($custname) > 30){
					$custname = substr($custname, 0, 30);
				}
			}
			
			//FORMAT PRIMARY CUSTOMER ADDRESS LINE4-LINE5 BY COUNTRY RULES
			switch($primaryAddress->getCountryId()){
				case "CA":
					//MEASURE CITY CHARS
					$primaryAddressCharCount = strlen($primaryAddress->getCity());
					//CHECK IF COUNT IS GREATER  OR EQUAL >= 27
					if($primaryAddressCharCount >= 27){
						$primaryAddress_city = substr($primaryAddress->getCity(), 0, 27);
					}else{
						$primaryAddress_city = str_pad($primaryAddress->getCity(), 27);
					}
					$primaryAddress_stateLocation = $primaryAddress->getRegionCode();
					$primaryAddress_line4 = $primaryAddress_city.' '.$primaryAddress->getRegionCode();
					$primaryAddress_line5 = strtoupper($primaryAddress->getCountryModel()->getName()).' '.$primaryAddress->getPostcode();
					break;
				case "US":
					$primaryAddress_stateLocation = $primaryAddress->getRegionCode();
					$primaryAddress_line4 = $primaryAddress->getCity();
					$primaryAddress_line5 = $primaryAddress->getRegionCode().', '.$primaryAddress->getPostcode();
					break;
				default:
					$primaryAddress_stateLocation = '';
					$primaryAddress_line4 = $primaryAddress->getCity().' '.($primaryAddress->getRegionCode() ?: $primaryAddress->getRegion());
					$primaryAddress_line5 = $primaryAddress->getPostcode();
			}
			
			//LOAD CURRENT STORE SAO CODE
			$store_sao = Mage::getStoreConfig('vistaprocessing_section/vistaprocessing_group/vistaprocessing_saocode', $order->getStoreId());
			
			$xml_data = '<CustApp>
			  <Parameters Option="N" Identifier="consumerweb" PreAuth="Y" DefaultMask="CA" WebSiteId="'.$store_sao.'"/>
			  <Records>
			    <Item>
			      <IdentifierSection>
			        <Identifier>consumerweb</Identifier>
			        <Type/>
			        <Suffix/>
			        <MasterCust/>
			        <Cust/>
			        <AltAddressFile/>
			        <MailCust/>
			        <WebSiteId>'.$store_sao.'</WebSiteId>
			        <DevToken/>
			      </IdentifierSection>
			      <Controls/>
			      <Individuals>
			        <Title/>
			        <TitleCode/>
			        <Initials/>
			        <Forename/>
			        <Surname/>
			        <JobTitle/>
			        <Email1><![CDATA['.$order->getCustomerEmail().']]></Email1>
			        <Email2/>
			        <Telephone1><![CDATA['.$primaryAddress->getTelephone().']]></Telephone1>
			        <Telephone2/>
			        <Telephone3/>
			        <Fax1><![CDATA['.$primaryAddress->getFax().']]></Fax1>
			        <AgreeToOffers/>
			      </Individuals>
			      <Company>
			        <BusinessName/>
			      </Company>
			      <Address>
			        <HouseName/>
			        <HouseNumber/>
			        <Street/>
			        <Floor/>
			        <LocalDistrict/>
			        <County/>
			        <Town/>
			        <SpecialInfo>YYYY</SpecialInfo>
			        <State><![CDATA['.$primaryAddress_stateLocation.']]></State>
			        <PoBox/>
			        <PostalCode><![CDATA['.$primaryAddress->getPostcode().']]></PostalCode>
			        <CountryCode><![CDATA['.$primaryAddress->getCountryId().']]></CountryCode>
			        <Country/>
			        <Line1/>
			        <Line2/>
			        <Line3/>
			        <Brick/>
			        <AddressLevel>1</AddressLevel>
			      </Address>
			      <Additional/>
			      <CusmasSection>
			        <Name><![CDATA['.$custname.']]></Name>
			        <AddressLine1><![CDATA['.$primaryAddress->getStreet1().']]></AddressLine1>
			        <AddressLine2><![CDATA['.$primaryAddress->getStreet2().']]></AddressLine2>
			        <AddressLine3><![CDATA['.$primaryAddress->getStreet3().']]></AddressLine3>
			        <AddressLine4><![CDATA['.$primaryAddress_line4.']]></AddressLine4>
			        <AddressLine5><![CDATA['.$primaryAddress_line5.']]></AddressLine5>
			        <PostalCode><![CDATA['.$primaryAddress->getPostcode().']]></PostalCode>
			      </CusmasSection>
			    </Item>
			  </Records>
			</CustApp>';
			$data = array('Input'=> (string) $xml_data);
			return $data;
		}
	}
	
	public function checkVistaCustomerId($customer, $order, $payment){
	
		//CHECK IF CUSTOMER IS GUEST
		$guest = $order->getCustomerIsGuest();
	
		//LOAD CURRENT VISTA IF AVAILABLE
		$vista_customer_id = $customer->getVistacustomer_id();
		
		//LOAD CURRENT VISTA ORDER STATUS
		$vistaorder_status = $order->getData('vistaorder_status');
		
		if(($vista_customer_id == NULL) || ($vista_customer_id == 0) && ($vistaorder_status != "ACCEPTED")){
			//CONNECT AND BUILD VISTA GATEWAY XML AND SEND MAGE Varien Objects
			try{
				//PROCESS CustApproval_Put XML POST WITH THE GATEWAY _CustomerInsertObject OBJECT
				$response = $this->CustApproval_Put($this->_CustomerInsertObject);
					
				//REVIEW VISTA GATEWAY RESPONSE
				if(isset($response['CustApp']['Records']['Item']['Output']['NewAccount'])){
					//CREATE NEW VISTA CUSTOMER ID
					$vista_customer_id = $response['CustApp']['Records']['Item']['Output']['NewAccount'];
					//SAVE TO MAGENTO PAYMENT ORDER RECORD
					$payment->setAdditionalInformation('vista_customer_id', $vista_customer_id);
					$payment->save();
					//SAVE TO MAGENTO CUSTOMER RECORD
					if(!$guest && $vista_customer_id != NULL){
						$customer->setVistacustomerId($vista_customer_id);
						$customer->save();
					}
				}else{
					//LOG ERROR
					Mage::log("VISTA NEW CUSTOMER OBSERVER XML ERROR: - ORDER #".$order->getIncrementId()." - EMAIL: ".$order->getCustomerEmail()."", null, 'FW_VistaProcessing.log');		 
					Mage::log('VISTA ORDER ' .$order->getIncrementId() . ' NEW CUSTOMER OBSERVER XML ERROR POSTED: '.$this->_CustomerInsertObject.'', null, 'VISTA_ORDER_ERROR.log');
						
					//SET ERROR VISTA CUSTOMER ID
					$vista_customer_id = 'N/A-ERROR';
					//SAVE TO MAGENTO PAYMENT ORDER RECORD
					$payment->setAdditionalInformation('vista_customer_id', $vista_customer_id);
					$payment->save();
					//SAVE TO MAGENTO CUSTOMER RECORD
					if(!$guest && $vista_customer_id != NULL){
						$customer->setVistacustomerId($vista_customer_id);
						$customer->save();
					}
				}
			}catch(Exception $e){
				//LOG ERROR
				Mage::log("VISTA CUSTOMER CHECK OBSERVER CONNECTION ERROR: ".$e->getMessage()."", null, 'FW_VistaProcessing.log');
				
				//SET ERROR VISTA CUSTOMER ID
				$vista_customer_id = 'N/A-ERROR';
				//SAVE TO MAGENTO PAYMENT ORDER RECORD
				$payment->setAdditionalInformation('vista_customer_id', $vista_customer_id);
				$payment->save();
				//SAVE TO MAGENTO CUSTOMER RECORD
				if(!$guest && $vista_customer_id != NULL){
					$customer->setVistacustomerId($vista_customer_id);
					$customer->save();
				}
			}
		}
	
		if($vista_customer_id == 0 || $vista_customer_id == NULL){
			//SET ERROR VISTA CUSTOMER ID
			$vista_customer_id = 'N/A-ERROR';
		}
		
		return $vista_customer_id;
		
	}
	
	public function _build_OrderAcceptObject($customer=NULL, $order=NULL, $payment=NULL){
		$identifier = '';
		$authcode = '';
		$transactionid  = '';
		$cc_profileid  = '';
		$cc_paymentid  = '';

		if(isset($customer) && isset($order) && isset($payment)){
			
			//CHECK PAYMENT METHOD
			$payment_check = explode("_", $payment->getMethod());
			$payment_method = $payment_check[0];
			
			if($payment_method == "paypal"){
				//LOAD PAYPAL PAYMENT DATA
				$paymentid = $payment->getEntityId();
				$collection = Mage::getModel('sales/order_payment_transaction')->getCollection()
				   ->setOrderFilter($order)
				   ->addPaymentIdFilter($paymentid)
				   ->setOrder('created_at', Varien_Data_Collection::SORT_ORDER_DESC)
				   ->setOrder('transaction_id', Varien_Data_Collection::SORT_ORDER_DESC);
				foreach ($collection as $txn) {
				    $transactionid = $txn->getTxnId();
				}
				$identifier = 0;
				$authcode = "";
			}elseif($payment_method == "authnetcim"){
                            //LOAD AUTH CIM PAYMENT DATA
                            $transactionid = $payment->getAdditionalInformation('transaction_id');
                            $identifier = $payment->getAdditionalInformation('transaction_id');
                            $authcode = $payment->getAdditionalInformation('approval_code');
                            $cc_profileid = $payment->getAdditionalInformation('profile_id');
                            $cc_paymentid = $payment->getAdditionalInformation('payment_id');
                        }
			
			//CHECK FOR PROMO/COUPON CODE
			$sessionid = ($order->getCouponCode()) ? $order->getCouponCode(): 'consumerweb';
			
			//CHECK IF PROMO/COUPON CODE HAS A FIXED SALESRULE	
			if($order->getCouponCode()){
				$coupon = Mage::getModel('salesrule/coupon');
				$coupon->load($order->getCouponCode(), 'code');
					
				if($coupon->getId()) {
					$ruleid = $coupon->getRuleId();
					$couponAction = Mage::getModel('salesrule/rule')->load($ruleid)->getSimpleAction();
				}
			}else{
				$couponAction = false;
			}
			
			//CHECK IF PROMO IS FIXED DISCOUNT CREATE/BUILD DOLLAR OFF XML
			//CHECK FOR GIFT CARDS USED
			$giftcards = unserialize($order->getGiftCards());
			$gift_cards_amount = $order->getBaseGiftCardsAmount();
			
			//SET BUSINESS TRANSACTION TYPE - BUSINESS LOGIC
			if($couponAction == "by_fixed" || $couponAction == "cart_fixed" || $couponAction == "by_percent" || is_array($giftcards)){
				if($order->getBaseTotalPaid() == "0.00"){
					$businessTransactionType = 'ICCZ';
				}else{
					if($payment_method == "paypal"){
						$businessTransactionType = 'ICCP';
					}else{
						$businessTransactionType = 'ICCC';
					}
				}
			}else{
					if($payment_method == "paypal"){
						$businessTransactionType = 'ICCP';
					}else{
						$businessTransactionType = 'ICCC';
					}
			}
			
			//LOAD GIFT MESSAGE
			$message = Mage::getModel('giftmessage/message');
			$gift_message_id = $order->getGiftMessageId();
			if(!is_null($gift_message_id)) {
				$message->load((int)$gift_message_id);
				$gift_sender = $message->getData('sender');
				$gift_recipient = $message->getData('recipient');
				$gift_message = $message->getData('message');
				
				$gift_message_xml ='<Texts>
					<TextMisc>';
				$gift_message_xml .='<TextMiscLine>'.$gift_sender.'</TextMiscLine>';
				$gift_message_xml .='<TextMiscLine>'.$gift_recipient.'</TextMiscLine>';
				$gift_message_xml .='<TextMiscLine>'.$gift_message.'</TextMiscLine>';
				$gift_message_xml .='</TextMisc>
					</Texts>';
			}else{
				$gift_message_xml = '';
			}
			
			//LOAD CURRENT STORE SAO CODE
			$store_sao = Mage::getStoreConfig('vistaprocessing_section/vistaprocessing_group/vistaprocessing_saocode', $order->getStoreId());

			//INIT VISTA CUSTOMER ID
			//CHECK IF STORED IN PAYMENT TRANSACTION
			$stored_vista_customer_id = $payment->getAdditionalInformation('vista_customer_id');
			
			if($stored_vista_customer_id == 0 || $stored_vista_customer_id == NULL || $stored_vista_customer_id == 'N/A-ERROR'){ 
				$vista_customer_id = $this->checkVistaCustomerId($customer, $order, $payment);
			}else{
				$vista_customer_id = $payment->getAdditionalInformation('vista_customer_id');
			}
			
			//CHECK AND BUILD CUSTOMER NAME - MUST LESS THEN 30 CHARS
			$custname = $order->getCustomerFirstname().' '.$order->getCustomerLastname();
			if(strlen($custname) > 30){
				$custname = substr($custname, 0, 30);	
			}
			
			//LOAD ORDER BILLING ADDRESS
			$order_billing_address = $order->getBillingAddress();
			
			$billing_forename = $order_billing_address->getFirstname()." ".$order_billing_address->getLastname();
			
			//FORMAT BILLING ADDRESS LINE4-LINE5 BY COUNTRY RULE
			switch($order_billing_address->getCountryId()){
				case "CA":
					//MEASURE CITY CHARS
					$billing_cityCharCount = strlen($order_billing_address->getCity());
					//CHECK IF COUNT IS GREATER  OR EQUAL >= 27
					if($billing_cityCharCount >= 27){
						$billing_city = substr($order_billing_address->getCity(), 0, 27);
					}else{
						$billing_city = str_pad($order_billing_address->getCity(), 27);
					}
					$billing_stateLocation = $order_billing_address->getRegionCode();
					$billing_line4 = $billing_city.' '.$order_billing_address->getRegionCode();
					$billing_line5 = strtoupper($order_billing_address->getCountryModel()->getName()).' '.$order_billing_address->getPostcode();
					break;
				case "US":
					$billing_stateLocation = $order_billing_address->getRegionCode();
					$billing_line4 = $order_billing_address->getCity();
					$billing_line5 = $order_billing_address->getRegionCode().', '.$order_billing_address->getPostcode();
					break;
				default:
					$billing_stateLocation = '';
					$billing_line4 = $order_billing_address->getCity().' '.($order_billing_address->getRegionCode() ?: $order_billing_address->getRegion());
					$billing_line5 = $order_billing_address->getPostcode();
			}
			
			
			//CHECK AND LOAD ORDER SHIPPING ADDRESS - IF NOT USE BILLING ADDRESS

			if($order->getShippingAddress()){ 
				$order_shipping_address = $order->getShippingAddress();
			}else{
				$order_shipping_address = $order->getBillingAddress();
			}
			
			$shipping_forename = $order_shipping_address->getFirstname()." ".$order_shipping_address->getLastname();
			
			//FORMAT SHIPPING ADDRESS LINE4-LINE5 BY COUNTRY RULE
			switch($order_shipping_address->getCountryId()){
				case "CA":
					//MEASURE CITY CHARS
					$shipping_cityCharCount = strlen($order_shipping_address->getCity());
					//CHECK IF COUNT IS GREATER  OR EQUAL >= 27
					if($shipping_cityCharCount >= 27){
						$shipping_city = substr($order_shipping_address->getCity(), 0, 27);
					}else{
						$shipping_city = str_pad($order_shipping_address->getCity(), 27);
					}
					$shipping_stateLocation = $order_shipping_address->getRegionCode();
					$shipping_line4 = $shipping_city.' '.$order_shipping_address->getRegionCode();
					$shipping_line5 = strtoupper($order_shipping_address->getCountryModel()->getName()).' '.$order_shipping_address->getPostcode();
					break;
				case "US":
					$shipping_stateLocation = $order_shipping_address->getRegionCode();
					$shipping_line4 = $order_shipping_address->getCity();
					$shipping_line5 = $order_shipping_address->getRegionCode().', '.$order_shipping_address->getPostcode();
					break;
				default:
					$shipping_stateLocation = '';
					$shipping_line4 = $order_shipping_address->getCity().' '.($order_shipping_address->getRegionCode() ?: $order_shipping_address->getRegion());
					$shipping_line5 = $order_shipping_address->getPostcode();
			}
			
			
			//LOAD ORDER SHIPPING METHOD CARRIER CODE
			$carrierCode = $order->getShippingMethod();
			
                        //set cc data to blank
			$cc = "";
			$cardType = "C";
			$ccexp = "";
			$ccname = "";

			if($payment_method == "paypal"){
                            $cardType = "W";
			}else{
				$ccname = $custname;
			}
			
			//GATHER AND LOAD ALL PRODUCTS TO FORMAT
			$order_items = $order->getAllItems();

			//INIT COUNTER AND STRING VARIABLE
			$i = 0;
			$productLines = '';
			
			//CONFIGURABLE ITEMS HAVE 2 LINE ITEMS FOR THE SIMPLE ITEM DATA
			//KEEP TRACK OF CONFIGURABLE TYPES AND THERE SKUS
			$configuableItems = array();

			//CONFIGURABLE ITEMS HAVE MULTIPLE LINE ITEMS FOR THE BUNDLE ITEM DATA
			//KEEP TRACK OF BUNDLE TYPES AND THEIR SKUS
			$bundleItem = array();
			
			//LOOP THROUGH ORDER ITEMS
			foreach($order_items as $item)
			{
				//LOAD PRODUCT
				$_product = Mage::getModel('catalog/product')->load($item->getProductId());
								
				//SET SKU OF ITEM
				//$sku = $item->getSku();
				$sku = htmlentities($item->getSku());
				
				//SET QTY OF ITEM
				$qty = $item->getQtyToShip();
				
				//CHECK IF SIMPLE LINE ITEM ASSOCIATED WITH A CONFIGURABLE PRODUCT OR BUNDLE PRODUCT
				if(in_array($sku, $configuableItems)){
					$productLines .= "";
				}elseif($item->getProductType() == "bundle"){
					$productLines .= "";
					
					$bundle_totalPrice = ($item->getQtyOrdered() * $item->getPrice()) - $item->getDiscountAmount();
					
					$bundleItem[$item->getItemId()]['bundletotalprice'] = $bundle_totalPrice;
					
					$bundleItemTotal = "";
					
					//LOOP THRU BUNDLE CHILDREN AND SUM OF ALL COMPONENTS IN THE BUNDLE RETAIL PRICES
					$bundleChildren = $item->getChildrenItems();
					
					foreach($bundleChildren as $children){
						//GET PRODUCT RETAIL PRICE
						$bundle_product = Mage::getModel('catalog/product')->load($children->getProductId());
						//ADD TO RUNNING TOTAL
						$bundleItemTotal = $bundleItemTotal + ($children->getQtyOrdered() * $bundle_product->getPrice());
					}
					
					//STORE IN BUNDLE ITEM ARRAY KEY
					$bundleItem[$item->getItemId()]['retailitemtotalprice'] = $bundleItemTotal;
					
				}elseif(array_key_exists($item->getParentItemId(), $bundleItem)){
					//RETAIL  PRICE
					$retailPrice = $_product->getPrice();
					 	
					//SET QTY OF ITEM
					$qty = number_format($item->getQtyOrdered(), 0, '.', '');
					
					//CALCULATE WHAT PERCENTAGE EACH COMPONENT RETAIL PRICE IS OF THAT SUM
					//DIVIDE THE RETAIL PRICE BY THE TOTAL BUNDLE RETAIL PRICE
					$bundlePercent = ($qty * $retailPrice) / $bundleItem[$item->getParentItemId()]['retailitemtotalprice'];
										
					//APPLY EACH PERCENTAGE TO THE BUNDLE TOTAL PRICE TO DETERMINE THE PRICE ALLOCATION EACH COMPONENT SHOULD RECIEVE
					//ADJUST & SET NETVALUE 
					$netvalue = number_format($bundleItem[$item->getParentItemId()]['bundletotalprice'] * $bundlePercent, 2, '.', '');
					
					$bundleItemData = array(
						'sku' => $sku,
						'price' => $retailPrice,
						'qty' => $qty,
						'roundedvalue' => $netvalue,
						'bundlePercent' => $bundlePercent,
						'retailitemtotalprice' => $bundleItem[$item->getParentItemId()]['retailitemtotalprice'],
					);
					
					//STORE IN BUNDLE ITEM DATA ARRAY KEY
					$bundleItem[$item->getParentItemId()]['products'][$item->getProductId()] = $bundleItemData;
				}else{
					//FIXED ITEM PRICE/VALUE IF PROMO/COUPON IS VALID
					if($couponAction == "by_fixed" || $couponAction == "cart_fixed" || $couponAction == "by_percent"){
						$discount_calc = number_format($item->getDiscountAmount() / $qty, 4, '.', '');
						$price = $item->getPrice() - $discount_calc;
					}else{
						$discount_calc = number_format($item->getDiscountAmount() / $qty, 2, '.', '');
						if($discount_calc > 0){
							$price = $item->getPrice() - $discount_calc;
						}else{
							$price = $item->getPrice();
						}
					}
					
					//FIX/INCLUDE SHIPPING FORMAT ONLY ON 1ST LINE
					if($i == 0){
             			$shipping_amount = number_format($order->getShippingAmount(), 2, '.', '');
					}else{
						$shipping_amount = "0.00";
					}
						
					//ADJUST & SET NETVALUE
					$netvalue = number_format($price*$qty, 2, '.', '');
						
					//APPEND BUILD LINE ITEM XML
					$productLines .= '
					<Line>
						<Pin>'.$sku.'</Pin>
						<DemandQty>'.$qty.'</DemandQty>
						<CustomerReference/>
						<Override>
							<DespatchValueInd>Y</DespatchValueInd>
							<DespatchValue>'.$shipping_amount.'</DespatchValue>
							<NetValueInd>Y</NetValueInd>
							<NetValue>'.$netvalue.'</NetValue>
						</Override>
					</Line>';
					
					$i++;
				}
				
				if($item->getProductType() == "configurable"){
					$configuableItems[] = $sku;
				}
				
			}
			
			//CHECK IF THERE ARE BUNDLE ITEMS AND APPLY ROUNDING FIX
			//THEN BUILD XML LINE ITEMS	
			if(!empty($bundleItem)){
				
				//LOOP THROUGH THE BUNDLE IN THIS ORDER
				foreach ($bundleItem as $bundle){
					
					//INIT VARS FOR CALCULATED BUNDLE PRICE ALLOCATION TOTAL
					$roundedTotal = "";
					$difference = "";
					
					//LOOP THROUGH THE ITEMS IN THIS BUNDLE
					foreach ($bundle['products'] as $_bundleProductItem){
						//ADD THE CALCULATED PRICE ALLOCATION TOTAL
						$roundedTotal = $roundedTotal + $_bundleProductItem['roundedvalue'];
					}
					//COMPARE CALCULATED BUNDLE TOTAL TO PURCHASED BUNDLE TOTAL
					if($roundedTotal != $bundle['bundletotalprice']){
						$difference = $bundle['bundletotalprice'] - $roundedTotal;
					}else{
						$difference = FALSE;
					}
					
					//LOOP THUR THE ITEMS IN THIS BUNDLE AND BUILD AND CREATE PRODUCT LINE XML
					$lineCounter = 0;
					foreach ($bundle['products'] as $_bundleOrderLineItem){
						
						//ADD THE DIFFERENCE CALCULATED PRICE ALLOCATION ROUNDED DIFFERENCE TO THE 1ST LINE ITEM
						if($lineCounter == 0 && $difference != FALSE){
							$itemValue = number_format($_bundleOrderLineItem['roundedvalue'] + $difference, 2, '.', '');
						}else{
							$itemValue = number_format($_bundleOrderLineItem['roundedvalue'], 2, '.', '');

						}
						
						//FIX/INCLUDE SHIPPING FORMAT ONLY ON 1ST LINE
						if($i == 0){
							//$shipping_amount = substr($order->getShippingAmount(), 0, -2);
							$shipping_amount = number_format($order->getShippingAmount(), 2, '.', '');
						}else{
							$shipping_amount = "0.00";
						}
							
						//APPEND AND BUILD LINE ITEM XML
						$productLines .= '
					<Line>
						<Pin>'.$_bundleOrderLineItem['sku'].'</Pin>
						<DemandQty>'.$_bundleOrderLineItem['qty'].'</DemandQty>
						<CustomerReference/>
						<Override>
							<DespatchValueInd>Y</DespatchValueInd>
							<DespatchValue>'.$shipping_amount.'</DespatchValue>
							<NetValueInd>Y</NetValueInd>
							<NetValue>'.$itemValue.'</NetValue>
						</Override>
					</Line>';
						$i++;
						$lineCounter++;
					}
					
				}
			}

			$xml_data = '<OrderBasket>
  <Parameters Option="P"/>
  <Orders>
    <Basket>
      <Order>
        <Header>
          <SessionId>'.$sessionid.'</SessionId>
          <WebSiteId>'.$store_sao.'</WebSiteId>
          <Identifier>'.$identifier.'</Identifier>
          <ApprovalRequired>N</ApprovalRequired>
          <ISOCurrencySymbol>USD</ISOCurrencySymbol>
          <OrderType/>
          <BillingType>P</BillingType>
          <ShipTo>
            <CustomerId>'.$vista_customer_id.'</CustomerId>
            <Personal>
              <Forename><![CDATA['.$shipping_forename.']]></Forename>
              <Email/>
              <Telephone/>
            </Personal>
            <Location>
              <State>'.$shipping_stateLocation.'</State>
              <PostalCode><![CDATA['.$order_shipping_address->getPostcode().']]></PostalCode>
              <CountryCode><![CDATA['.$order_shipping_address->getCountryId().']]></CountryCode>
              <AddressBlock>
                <Line1><![CDATA['.htmlentities($order_shipping_address->getStreet1()).']]></Line1>
                <Line2><![CDATA['.htmlentities($order_shipping_address->getStreet2()).']]></Line2>
                <Line3><![CDATA['.htmlentities($order_shipping_address->getStreet3()).']]></Line3>
                <Line4><![CDATA['.$shipping_line4.']]></Line4>
                <Line5><![CDATA['.$shipping_line5.']]></Line5>
              </AddressBlock>
            </Location>
          </ShipTo>
          <BillTo>
            <CustomerId>'.$vista_customer_id.'</CustomerId>
            <Personal>
              <Forename><![CDATA['.$billing_forename.']]></Forename>
              <Email><![CDATA['.$order->getCustomerEmail().']]></Email>
              <Telephone><![CDATA['.$order_billing_address->getTelephone().']]></Telephone>
            </Personal>
            <Location>
              <State><![CDATA['.$billing_stateLocation.']]></State>
              <PostalCode><![CDATA['.$order_billing_address->getPostcode().']]></PostalCode>
              <CountryCode><![CDATA['.$order_billing_address->getCountryId().']]></CountryCode>
              <AddressBlock>
                <Line1><![CDATA['.htmlentities($order_billing_address->getStreet1()).']]></Line1>
                <Line2><![CDATA['.htmlentities($order_billing_address->getStreet2()).']]></Line2>
                <Line3><![CDATA['.htmlentities($order_billing_address->getStreet3()).']]></Line3>
                <Line4><![CDATA['.$billing_line4.']]></Line4>
                <Line5><![CDATA['.$billing_line5.']]></Line5>
              </AddressBlock>
            </Location>
          </BillTo>
          <CreditCard>
            <CreditCardType>'.$cardType.'</CreditCardType>
            <CreditCardNumber>'.$cc.'</CreditCardNumber>
            <CreditCardAuth>'.$authcode.'</CreditCardAuth>
            <CreditCardExpiry>'.$ccexp.'</CreditCardExpiry>
            <CreditCardName><![CDATA['.$ccname.']]></CreditCardName>
            <PaymentID>'.$cc_paymentid.'</PaymentID>
            <CreditCardCustNum>'.$cc_profileid.'</CreditCardCustNum>
          </CreditCard>
          <CustomerReference>'.$order->getIncrementId().'</CustomerReference>';
			$xml_data .= '
	<SourceCode>Q</SourceCode>
	<BusinessTransactionType>'.$businessTransactionType.'</BusinessTransactionType>';
	
	$xml_data .= '
	<CashWithOrder>
		<CashWithOrderValue>'.number_format($order->getBaseGrandTotal(), 2, '.', '').'</CashWithOrderValue>
		<CWOReferenceNumber>'.$transactionid.'</CWOReferenceNumber>
	</CashWithOrder>';
			
			$xml_data .= $gift_message_xml;
			
			//Only should happen when cart has all downloadable products.
			if(substr($carrierCode, -2) == "") {
				$carrierCode = "fwmedia_9A";
			}
			
			$xml_data .= '
	<Despatch>
		<OverrideMethodInd>Y</OverrideMethodInd>
		<CarrierCode>'.substr($carrierCode, -2).'</CarrierCode>
	</Despatch>
	</Header>
	<DetailLines>';
			$xml_data .= $productLines;
			
			//IF GIFT CARD IS USED INCLUDE GIFT XML
			if(is_array($giftcards) && $gift_cards_amount > 0){
				$xml_data .= '
					<Line>
						<Pin></Pin>
						<DemandQty></DemandQty>
						<LineType>S</LineType>
						<Sundry>
							<ChargeCode>JFWGFT</ChargeCode>
							<ChargeText>'.$giftcards[0]['c'].'</ChargeText>
							<ChargeNetValue>-'.substr($gift_cards_amount, 0, -2).'</ChargeNetValue>
						</Sundry>
					</Line>';
			}
			
			$xml_data .= '
        </DetailLines>
      </Order>
    </Basket>
  </Orders>
</OrderBasket>';
			$data = array('Input'=> (string) $xml_data);
			return $data;
		}
	}
	
	public function xml2array($contents, $get_attributes=1, $priority = 'tag') {
		if(!$contents) return array();
	
		if(!function_exists('xml_parser_create')) {
			return array();
		}
	
		//Get the XML parser of PHP - PHP must have this module for the parser to work
		$parser = xml_parser_create('');
		xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8");
		xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
		xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
		xml_parse_into_struct($parser, trim($contents), $xml_values);
		xml_parser_free($parser);
	
		if(!$xml_values) return;//Hmm...
	
		//Initializations
		$xml_array = array();
		$parents = array();
		$opened_tags = array();
		$arr = array();
	
		$current = &$xml_array; //Reference
	
		//Go through the tags.
		$repeated_tag_index = array();//Multiple tags with same name will be turned into an array
		foreach($xml_values as $data) {
			unset($attributes,$value);//Remove existing values, or there will be trouble
	
			//This command will extract these variables into the foreach scope
			// tag(string), type(string), level(int), attributes(array).
			extract($data);//We could use the array by itself, but this cooler.
	
			$result = array();
			$attributes_data = array();
	
			if(isset($value)) {
				if($priority == 'tag') $result = $value;
				else $result['value'] = $value; //Put the value in a assoc array if we are in the 'Attribute' mode
			}
	
			//Set the attributes too.
			if(isset($attributes) and $get_attributes) {
				foreach($attributes as $attr => $val) {
					if($priority == 'tag') $attributes_data[$attr] = $val;
					else $result['attr'][$attr] = $val; //Set all the attributes in a array called 'attr'
				}
			}
	
			//See tag status and do the needed.
			if($type == "open") {
				//The starting of the tag '<tag>'
				$parent[$level-1] = &$current;
				if(!is_array($current) or (!in_array($tag, array_keys($current)))) {
					//Insert New tag
					$current[$tag] = $result;
					if($attributes_data) $current[$tag. '_attr'] = $attributes_data;
					$repeated_tag_index[$tag.'_'.$level] = 1;
	
					$current = &$current[$tag];
	
				} else { //There was another element with the same tag name
	
					if(isset($current[$tag][0])) {
						//If there is a 0th element it is already an array
						$current[$tag][$repeated_tag_index[$tag.'_'.$level]] = $result;
						$repeated_tag_index[$tag.'_'.$level]++;
					} else {//This section will make the value an array if multiple tags with the same name appear together
						$current[$tag] = array($current[$tag],$result);//This will combine the existing item and the new item together to make an array
						$repeated_tag_index[$tag.'_'.$level] = 2;
	
						if(isset($current[$tag.'_attr'])) {
							//The attribute of the last(0th) tag must be moved as well
							$current[$tag]['0_attr'] = $current[$tag.'_attr'];
							unset($current[$tag.'_attr']);
						}
	
					}
					$last_item_index = $repeated_tag_index[$tag.'_'.$level]-1;
					$current = &$current[$tag][$last_item_index];
				}
	
			} elseif($type == "complete") {
				//Tags that ends in 1 line '<tag />'
				//See if the key is already taken.
				if(!isset($current[$tag])) {
					//New Key
					$current[$tag] = $result;
					$repeated_tag_index[$tag.'_'.$level] = 1;
					if($priority == 'tag' and $attributes_data) $current[$tag. '_attr'] = $attributes_data;
	
				} else { //If taken, put all things inside a list(array)
					if(isset($current[$tag][0]) and is_array($current[$tag])) {
						//If it is already an array...
	
						// ...push the new element into that array.
						$current[$tag][$repeated_tag_index[$tag.'_'.$level]] = $result;
	
						if($priority == 'tag' and $get_attributes and $attributes_data) {
							$current[$tag][$repeated_tag_index[$tag.'_'.$level] . '_attr'] = $attributes_data;
						}
						$repeated_tag_index[$tag.'_'.$level]++;
	
					} else { //If it is not an array...
						$current[$tag] = array($current[$tag],$result); //...Make it an array using using the existing value and the new value
						$repeated_tag_index[$tag.'_'.$level] = 1;
						if($priority == 'tag' and $get_attributes) {
							if(isset($current[$tag.'_attr'])) {
								//The attribute of the last(0th) tag must be moved as well
	
								$current[$tag]['0_attr'] = $current[$tag.'_attr'];
								unset($current[$tag.'_attr']);
							}
	
							if($attributes_data) {
								$current[$tag][$repeated_tag_index[$tag.'_'.$level] . '_attr'] = $attributes_data;
							}
						}
						$repeated_tag_index[$tag.'_'.$level]++; //0 and 1 index is already taken
					}
				}
	
			} elseif($type == 'close') {
				//End of tag '</tag>'
				$current = &$parent[$level-1];
			}
		}
	
		return($xml_array);
	}
}
