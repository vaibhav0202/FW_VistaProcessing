<?php
/**
 * @category    FW
 * @package     FW_VistaProcessing_Inventory
 * @copyright   Copyright (c) 2012 F+W Media, Inc. (http://www.fwmedia.com)
 * @author		Allen Cook (allen.cook@fwmedia.com); Mike Godfrey (mike.godfrey@fwmedia.com); 
 */
 
class FW_VistaProcessing_Model_Inventoryupdate extends Mage_Core_Model_Abstract
{
    /**
     * Updates inventory, pricing from file(s). 
     * @param $addProducts bool  		--> add new products (from file)
     * @param $updateProducts bool 		--> update existing products (from file)
     * @param $processFullFile bool 	--> process full inventory file
     * @param $processUpdateFile bool 	--> process update files
     * @param $getNewFiles bool 		--> retrieve files from FTP server
     * @param $deleteFiles bool 		--> delete files on FTP server
	 * @param $sendSucessLog bool 		--> send success email
     */
    public function import($addProducts, $updateProducts, $processFullFile, $processUpdateFile, $getNewFiles, $deleteFiles, $sendSucessLog)
    {
    	Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
    	$startTime = time();
    	$successCount = 0;
    	$errorCount = 0;
    	$myEditionTypes = array();
		$myAnswerCodes = array();
		$taxClassIds = array();
		$inventoryFiles = array();
    	$logFile = 'Vista_Inventory_Update.log';
    	$errorFile =  'Vista_Inventory_Error'.date("YmdHis", time()).'.log';
        $isSaoEnabled = Mage::getStoreConfig('vistainventory_section/vistainventory_group/vistainventory_enablesao');
        if ($isSaoEnabled) $saoCodes = $this->getSaoCodes();

        $inventoryDir = Mage::getBaseDir() . '/var/importexport/vista_inventory';

		//create folders
		$baseDir = Mage::getBaseDir() . '/var/importexport';
		if (!file_exists($baseDir)) mkdir($baseDir, 0777);
    	
        $inventoryDir = $baseDir . '/vista_inventory';
        if (!file_exists($inventoryDir)) mkdir($inventoryDir, 0777);
        
        $inventoryExecutedDir = $inventoryDir.'/executed';
        if (!file_exists($inventoryExecutedDir)) mkdir($inventoryExecutedDir, 0777);
        
        //DOWNLOAD Inventory Files from VISTA Ftp server
        if($getNewFiles == true)
        {
           	try 
	        {
	        	$this->getInventoryUpdateFiles($processFullFile, $processUpdateFile, $deleteFiles, $errorFile);
	        } 
	        catch (Exception $e) 
	        {
				Mage::log('FTP Error: '.$e,null,$errorFile);
				$errorCount++;
	        }
        }
        
        $this->initGlobals($myEditionTypes,$myAnswerCodes, $taxClassIds);
        $this->initInventoryFiles($inventoryDir, $inventoryFiles);

	    //data[1] = sku
    	//data[2] = edition type
    	//data[3] = answer code
    	//data[4] = quantity
    	//data[5] = price
    	//data[6] = publication date 
    	//data[7] = isbn 12
    	//data[8] = isbn 10
    	//data[9] = weight
    	//data[10] = number of pages
    	//data[11] = warehouse availability date
    	//data[12] = Short Name ==> used for new products
    	//data[13] = Magento Flag
    	//data[14] = Cost
	    //data[18] = Taxware Taxcode
        //data[19] and up = SAO code
    	
	   //Process each found file
		foreach ($inventoryFiles AS $inventoryFile) 
        {   

        	//Open the Sku tracking file for this specific update file
        	unset($processedSkus); 
           	$slashPosition = strripos($inventoryFile,'/');
        	
        	if($slashPosition == false)
        	{
        		$slashPosition = strripos($inventoryFile,'\\');
        	}
        	
        	$fileName = substr($inventoryFile, $slashPosition + 1);
        	$fileName = str_replace('.DWF', '.log', $fileName); 
        	$processedSkuFile = $inventoryDir.'/Processed_'.$fileName;
        	
        	$deadLockfileName = substr($inventoryFile, $slashPosition + 1);
        	$deadLockSkuFile = $inventoryDir.'/DEADLOCK_'.$deadLockfileName;
        	
        	if(file_exists($processedSkuFile))
        	{
        		if (($handle = fopen($processedSkuFile, "r")) !== FALSE)
				{
					while (($data = fgetcsv($handle, 10000, ",")) !== FALSE)
					{
						$processedSkus[$data[0]] = $data[0];
					}
				}
				fclose($handle);
        	}
	  
        	Mage::log("Processing File:".$inventoryFile."\r\n",null,$logFile);
        	if (($handle = fopen($inventoryFile, "r")) !== FALSE) 
        	{
				while (($data = fgetcsv($handle, 100000, "|")) !== FALSE) 
				{
					$sku = $data[1];
					
					if(!isset($processedSkus[$sku]))
					{
			        	try 
						{	
							if($sku != "")
							{
                                //Might be able to just remove this if block, since both "V" and "D" will be considered non managed and the qty will get set to one in that check.
								if($data[13] == "V" || $data[13] == "D")//for virtual/download products this qty has to always be at least 1 even for products that we dont manage the stock for bundled products will function correctly on front end
					        	{
                                    //Overwrite $data[4] = 1 as that is where the inventory is stored
                                    $data[4] = 1;
					        	}
				
								$inventoryProduct = Mage::getModel('catalog/product')->loadByAttribute('sku',$sku);

                                //Check SAO Codes
                                if(!empty($saoCodes) && $data[13] != "N" && $data[13] != "") {
                                    $productSaoCodes = array_filter(array_slice($data, 19), 'trim');
                                    $match = array_intersect($productSaoCodes, $saoCodes);

                                    if (empty($productSaoCodes) && empty($inventoryProduct)) {
                                        Mage::log("No SAO codes on new product. Sku:".$sku."\r\n", null, $logFile);
                                    } elseif (empty($productSaoCodes) && !empty($inventoryProduct)) {
                                        Mage::log("No SAO codes on existing product. Sku:".$inventoryProduct->getsku()."\r\n", null, $logFile);
                                    } elseif (!empty($productSaoCodes) && !empty($inventoryProduct) && empty($match)) {
                                        Mage::log("Existing product does not have SAO codes that match those set in admin. Sku:".$inventoryProduct->getsku()."\r\n", null, $logFile);
                                    }

                                    if (empty($match)) { //Product does not have SAO codes matching those set in Magento Admin, skip product
                                        continue;
                                    }
                                }

								if(!$inventoryProduct)
								{
									if(isset($data[13]))
									{
										if($addProducts == true && $data[13] != "N" && $data[13] != "")//dont add non-magento products if the magento field is populated, the add products flag was included and the field value is not N
										{
											$inventoryProduct = $this->createNewProduct($data, $taxClassIds, $logFile);
											$newProduct = true;
										}
									}
								}
								else
								{
									$newProduct = false;
								}
		
								if($inventoryProduct)
								{	
									if($newProduct || ($newProduct == false && $updateProducts == true))
									{
										//Product Update
										$this->updateProduct($inventoryProduct, $myEditionTypes, $myAnswerCodes, $data);

										//Stock Update
										if(isset($data[15]) && $data[15] == "N")
										{
											$manageStock = 0;
                                            //Force qty to be 1 on a product where stock isn't managed.
                                            $data[4] = 1;
										}
										else 
										{
											$manageStock = 1;
										}
										$this->updateStockItem($inventoryProduct,$data[3], $data[4], $manageStock, $logFile);
					    				
										$fh = fopen($processedSkuFile, 'a'); 
										fwrite($fh, $sku."\r\n"); 
										fclose($fh);
										$successCount++;
										
										Mage::log("Updated sku:".$inventoryProduct->getsku()."\r\n",null,$logFile);
									}
								}
							}
						}		
						catch (Exception $e)
						{
							Mage::log($sku.":".$e->getMessage(),null,$errorFile);
							
							if(strstr($e->getMessage(), "1205 Lock wait timeout exceeded"))
							{
									$fh2 = fopen($deadLockSkuFile, 'a'); 
									foreach($data as $field)
									{
										fwrite($fh2, $field."|"); 
									}
									fwrite($fh2, PHP_EOL); 
									fclose($fh2);	
							}
							else
							{
								$errorCount++;
								if($errorCount > 0)
						    	{
									$this->sendEmail("Vista Inventory Update Error", "There was an error(s) in the Vista Inventory Update process", Mage::getBaseDir()."/var/log/", $errorFile);
						    	}
						    	
								if($sendSucessLog && $successCount > 0)
						    	{
									$this->sendEmail("Vista Inventory Update", "Metrics for the Vista Inventory Update process", Mage::getBaseDir()."/var/log/", $logFile);
						    	}
						    	fclose($handle);
						    	return;
							}
						
						}
						unset($inventoryProduct,$sku, $data);
					} 
				}
	    	}
	    	fclose($handle);
	    	unset($handle);
	    	$fileNameExecuted= str_replace('.dwf', '.executed.' . date('Ymd-His') . '.dwf', $inventoryFile);
	    	$fileNameExecuted= str_replace('.DWF', '.executed.' . date('Ymd-His') . '.DWF', $inventoryFile);
            $fileNameExecuted = str_replace($inventoryDir, $inventoryExecutedDir, $fileNameExecuted);
            rename($inventoryFile, $fileNameExecuted);
            
            unlink($processedSkuFile);  
        }
        
		Mage::log("************************ Inventory Completed ************************\r\n",null,$logFile);
		Mage::log("Time Elapsed :".round(abs(time() - $startTime) /60,2)." minutes\r\n",null,$logFile);
		Mage::log("Successes : ".$successCount."\r\n",null,$logFile);
		Mage::log("Errors : ".$errorCount."\r\n",null,$logFile);
		Mage::log("*********************************************************************\r\n",null,$logFile);

    	if($errorCount > 0)
    	{
			$this->sendEmail('Vista Inventory Update Error', 'There was an error(s) in the Vista Inventory Update process', Mage::getBaseDir().'/var/log/', $errorFile);
    	}
    	
    	if($sendSucessLog && $successCount > 0)
    	{
			$this->sendEmail('Vista Inventory Update', 'Metrics for the Vista Inventory Update process', Mage::getBaseDir().'/var/log/', $logFile);
    	}
    	
     	if(count($inventoryFiles) == 0)
    	{
			$this->sendEmail('Vista Inventory Update - NO FILES FOUND', 'There were no inventory files found to process', Mage::getBaseDir().'/var/log/', null);
    	}
    }
    
    /**
     * Send email success and/or failure
     * @param $subject string
     * @param $bodyMsg string
	 * @param $filePath string
	 * @param $file string
     */
	private function sendEmail($subject, $bodyMsg, $filePath, $file)
	{
		$email_from = "Magento/VISTA Inventory Update Processer";
		$fileatt = $filePath.$file; // full Path to the file 
		$fileatt_type = "application/text"; // File Type 
		
		$to =  Mage::getStoreConfig('vistainventory_section/vistainventory_group/vistainventory_emailnotice', 1);
		$subject = $subject;
		$fileatt_name = $file;
		$file = fopen($fileatt,'rb'); 
		$data = fread($file,filesize($fileatt)); 
		fclose($file); 
		$semi_rand = md5(time()); 
		$mime_boundary = "==Multipart_Boundary_x{$semi_rand}x"; 
		$headers = "From:".$email_from;
		$headers .= "\nMIME-Version: 1.0\n" . 
			"Content-Type: multipart/mixed;\n" . 
			" boundary=\"{$mime_boundary}\""; 
		$email_message = $bodyMsg;
		$email_message .= "This is a multi-part message in MIME format.\n\n" . 
			"--{$mime_boundary}\n" . 
			"Content-Type:text/html; charset=\"iso-8859-1\"\n" . 
			"Content-Transfer-Encoding: 7bit\n\n" . 
		$email_message .= "\n\n"; 
		$data = chunk_split(base64_encode($data)); 
		$email_message .= "--{$mime_boundary}\n" . 
			"Content-Type: {$fileatt_type};\n" . 
			" name=\"{$fileatt_name}\"\n" . 
			"Content-Transfer-Encoding: base64\n\n" . 
			$data .= "\n\n" . 
			"--{$mime_boundary}--\n"; 

		//Send email
		$ok = @mail($to, $subject, $email_message, $headers); 
	}

	/**
     * Update Mage Product 
     * @param $data inventory file data row
	 * @param $myAnswerCodes array
	 * @param $myEditionTypes array
	 * @param $logFile string
     */
	private function updateProduct($product, $myEditionTypes, $myAnswerCodes, $data)
	{	
		$answerCodeId = "";
		foreach ($myAnswerCodes as $answerCode)
		{
			if($answerCode['label'] == $data[3])
			{
				$answerCodeId = $answerCode['value'];
			}
		}
		
		$editionTypeId = "";
		foreach ($myEditionTypes as $editionType)
		{
			if($editionType['label'] == $data[2])
			{
				$editionTypeId = $editionType['value'];
			}					
		}
		
		if(isset($data[6]) && $data[6] != "")
		{
			$pubDateValue = strtotime($data[6]);
			$product->setPublicationDate($pubDateValue);
		}
		
		if(isset($data[11]) && $data[11] != "")
		{
			$warehouseDateValue = strtotime($data[11]);
			$product->setWarehouseAvailDate($warehouseDateValue);
		}
		

		$product->setPrice($data[5]);
		$product->setIsbn13($data[7]);
		$product->setIsbn10($data[8]);
		$product->setWeight($data[9]);
		$product->setNumberOfPages($data[10]);
		$product->setCost($data[14]);
		$product->setVistaAnswerCode($answerCodeId);
		$product->setVistaEditionType($editionTypeId);
		$product->setData('taxware_taxcode',$data[18]);
		$product->save();
	}
	
	/**
     * Update Mage Stock Item 
     * @param $data inventory file data row
	 * @param $myAnswerCode string
	 * @param $qty string
	 * @param $logFile string
     */
	private function updateStockItem($inventoryProduct,$answerCode,$qty,$manageStock,$logFile)
	{     
		$stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($inventoryProduct->getId());
			
		if($stockItem->getId() == "")
		{
			$stockItem = $this->createStockItem($inventoryProduct, $logFile);
		}

		switch ($answerCode)
		{
			//back order
			//in stock
			case 'NYP':
			case 'BRP': 
				$backorder = 2;
				$isInStock = 1;
				break;
			case 'TOS':
			case 'COS':
				$backorder = 0;
				if ($qty < 1)
					$isInStock = 0;
				else
					$isInStock = 1;
				break;
			case 'REM':
			case 'OSI':
			case 'CNR':
			case 'OOP':
			case 'CFE':
		  	case 'NIP':
		  	case 'POD':
				$backorder = 0;
				if ($qty <=5)
					$isInStock = 0;
				else
					$isInStock = 1;
				break;
			case 'CAN':
			case 'NLA':
				$isInStock = 0;
				$backorder = 0;
				break;
			default:
				$isInStock = 1;
				$backorder = 1;
				break;			
		}
		
		if($isInStock == 0)
		{
			$manageStock = 1;
		}
					
		$stockItem->setData('manage_stock', $manageStock);
		$stockItem->setData('is_in_stock', $isInStock);
		$stockItem->setData('stock_id', 1);
		$stockItem->setData('qty', $qty);
		$stockItem->setData('backorders', $backorder);
		$stockItem->save();
	}

	/**
     * Create new Mage Product 
     * @param $data inventory file data row
	 * @param $taxClassIds array
	 * @param $logFile string
     */
	private function createNewProduct($data, $taxClassIds, $logFile)
	{	
		$sku = $data[1];
		$inventory = $data[4];
		$answer_code = $data[3];

		$websiteIds[0] = Mage::getModel('core/website')->load('Main Website', 'name')->getId();
		$storeIds[0] = Mage::getModel('core/store')->load('Default Store View', 'name')->getId();
    	
		$product = new Mage_Catalog_Model_Product();
		$product->setWebsiteIDs($websiteIds);
		$product->setStoreIDs($storeIds);
			
		if($data[13] == "V") //Virtual Product
		{
			$product->setTypeId('virtual');
		}
		else if ($data[13] == "D") //Downloadable
		{
			$product->setTypeId('downloadable');	
		}
		else if ($data[13] == "G") //Gift Card
		{
			$product->setTypeId('giftcard');	
		}
		else 
		{
			$product->setTypeId('simple');	
		}

		//Attribute Set Id
		$attrSetName = "Default";
		$entityTypeId = Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId();
		$attributeSetName   = $attrSetName;
		$attributeSetId     = Mage::getModel('eav/entity_attribute_set')
			->getCollection()
			->setEntityTypeFilter($entityTypeId)
			->addFieldToFilter('attribute_set_name', $attributeSetName)
			->getFirstItem()->getAttributeSetId();
					
		$product->setAttributeSetId($attributeSetId);
		$product->setSku($sku);		    	   		
		$product->setName($data[12]);
  		$product->setVisibility(1);
		$product->setStatus(1);

		$urlKey = $product->getName();
		$urlKey = Mage::helper('catalog/product_url')->format($urlKey);
		$urlKey = preg_replace('#[^0-9a-z]+#i', '-',$urlKey);
		$urlKey = $urlKey . '-' . $sku;
		$urlKey = strtolower($urlKey);
		trim($urlKey, '-');
		$product->setUrlKey($urlKey);

	    $product->setTaxClassId($taxClassIds["Taxable Goods"]);
		$product->setData('taxware_taxcode',$data[19]);
			
		$product->save();
		Mage::log("Created Product: ".$product->getSku()."\r\n",null, $logFile);
    					
    	$this->createStockItem($product, $logFile);

		return $product;
	}

	/**
     * Create new Mage Stock Item
     * @param $product Mage Product
	 * @param $logFile string
     */
    private function createStockItem($product, $logFile)
    { 	
    	$stockItem = Mage::getModel('cataloginventory/stock_item');
		$stockItem->assignProduct($product);
		$stockItem->setData('stock_id', 1);
		$stockItem->setData('use_config_manage_stock', 0);
		$stockItem->setData('use_config_min_sale_qty', 0);
		$stockItem->setData('use_config_backorders', 0);
		$stockItem->save();
		Mage::log("Created Stock Item for product: ".$product->getSku()."\r\n",null, $logFile);

		return $stockItem;
    }
 
    /**
     * Retrieve inventory file(s) from Vista FTP Server
     * @param $processFullFile bool
	 * @param $deleteFiles bool
	 * @param $processFullFile bool
     */
    private function getInventoryUpdateFiles($processFullFile, $processUpdateFile, $deleteFiles, $errorFile)
	{
	
		# ftp-login
		$ftp_server = Mage::getStoreConfig('vistainventory_section/vistainventory_group/vistainventory_host');
		$ftp_user = Mage::getStoreConfig('vistainventory_section/vistainventory_group/vistainventory_user');
		$ftp_pw = Mage::getStoreConfig('vistainventory_section/vistainventory_group/vistainventory_password');
		$ftp_folder = Mage::getStoreConfig('vistainventory_section/vistainventory_group/vistainventory_folder');
		
		// set up basic connection
		$conn_id = ftp_connect($ftp_server);
		$errorCount = 0;
		// login with username and password
		if($conn_id == false)
		{
			echo "Connection to ftp server failed\n";
			Mage::log('Connection to ftp server failed\r\n',null,$errorFile);
            $this->sendEmail('Vista Inventory Update - Connection to ftp server failed', 'Connection to ftp server failed', Mage::getBaseDir().'/var/log/', null);
            throw new Exception('Connection to ftp server failed.');
            return;
		}
		$login_result = ftp_login($conn_id, $ftp_user, $ftp_pw);
		
		if($login_result == false) 
		{ 
			echo "Login to ftp server failed\n"; 
			Mage::log('Login to ftp server failed\r\n',null,$errorFile); 
            $this->sendEmail('Vista Inventory Update - Login to ftp server failed', 'Login to ftp server failed', Mage::getBaseDir().'/var/log/', null);
			throw new Exception('Login to ftp server failed.');
			return;
		}
		
		// turn passive mode on
		ftp_pasv($conn_id, true);

		ftp_chdir($conn_id, $ftp_folder);
				
		// get current directory
		$dir = ftp_pwd($conn_id);

		$rawfiles = ftp_rawlist($conn_id, '-1t');
		$filesSorted = array();
		
		foreach($rawfiles as $file)
		{
			$filesSorted[ftp_mdtm($conn_id,$file)] = $file;
		}
		
		ksort($filesSorted);
		
		foreach ($filesSorted as $modDate=>$fileName)
		{
			$local_file = Mage::getBaseDir().'/var/importexport/vista_inventory/'.$fileName;
			$fullPos = strpos(strtoupper($fileName), "FULL");
			$invPos = strpos(strtoupper($fileName), "DWF");
			$retrieveFile = false;

			if($invPos === false) //Not a inventory file
			{
				continue;
			}
			
			if($fullPos === false)//not a full file
			{
				if($processUpdateFile == true)
				{
					$retrieveFile = true;
				}
			}
			else 
			{
				if ($processFullFile == true)
				{
					
					$retrieveFile = true;
				}
			}
			
			if($retrieveFile == true)
			{
				ftp_get($conn_id, $local_file, $fileName, FTP_BINARY);
					
				if($deleteFiles == true)
				{
					ftp_delete($conn_id, $fileName);
				}
			}
		}

		// close the connection
		ftp_close($conn_id);
        return $errorCount;
	}

	 /**
     * Intialize global arrays used for product insert/update
     */
	private function initGlobals(&$myEditionTypes, &$myAnswerCodes, &$taxClassIds)
	{	
	    //Pre-load edition types 
    	$editionTypes = new FW_ProductCustom_Model_Resource_Eav_Source_Vistaeditioncodes();
		$editionTypes->getAllOptions();
		$myEditionTypes = $editionTypes->getAllOptions();
		
		//Pre-load answer codes
		$answerCodes = new FW_ProductCustom_Model_Resource_Eav_Source_Vistaanswercodes();
		$answerCodes->getAllOptions();
		$myAnswerCodes = $answerCodes->getAllOptions();

		//Pre-load tax class ids
    	$taxClassIds = array();
	    $taxClasses = Mage::getModel('tax/class')->getCollection();
		foreach ($taxClasses as $taxClass)
		{
			$taxClassIds[$taxClass->getClassName()] = $taxClass->getId();
		}
	}

	 /**
     * Intialize global file array that will store the inventory files to process
     */
    private function initInventoryFiles($inventoryDir, &$inventoryFiles)
    {
    	//Get the files & order by oldest first
	    foreach (glob($inventoryDir.'/*.{dwf,DWF}', GLOB_BRACE) AS $file) 
        {    
        	$slashPosition = strripos($file,'/');
        	
        	if($slashPosition == false)
        	{
        		$slashPosition = strripos($file,'\\');
        	}
        	
        	$fileName = substr($file, $slashPosition + 1);
        	
        	$currentModified = substr($fileName, strlen($fileName) - 16);
        	$currentModified = substr($currentModified, 0, (strlen($currentModified) - 4));
	        $file_names[] = $file; 
	        $fileDates[] = $currentModified;  

		}
  
	   	//Sort the date array by oldest first
	   	asort($fileDates); 

   		//Match file_names array to file_dates array 
	   	$file_names_Array = array_keys($fileDates); 
	   	foreach ($file_names_Array as $idx => $name) $name=$file_names[$name]; 
	   	$fileDates = array_merge($fileDates); 
	    
	   //Loop through dates array 
	   $i = 0; 
	   foreach ($fileDates as $aFileDate){ 
	       $date = (string) $fileDates[$i]; 
	       $j = $file_names_Array[$i]; 
	       $file = $file_names[$j]; 
	       $i++; 
	            
	       $inventoryFiles[$i] = $file;   
	   } 
    }

    public function getSaoCodes()
    {
        $saoCodes = Mage::getStoreConfig('vistainventory_section/vistainventory_group/vistainventory_saocodes');
        $saoCodes = explode(',', $saoCodes);
        foreach ($saoCodes as $key => $code) {
            $saoCodes[$key] = trim($code);
        }

        return $saoCodes;
    }
}



