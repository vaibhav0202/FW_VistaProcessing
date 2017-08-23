<?php
/**
 * @category    FW
 * @package     FW_VistaProcessing_Inventory
 * @copyright   Copyright (c) 2012 F+W Media, Inc. (http://www.fwmedia.com)
 * @author		Allen Cook (allen.cook@fwmedia.com); Mike Godfrey (mike.godfrey@fwmedia.com); 
 */
 
class FW_VistaProcessing_Model_Taxclassupdate extends Mage_Core_Model_Abstract
{
    public function import($addProducts, $updateProducts, $processFullFile, $processUpdateFile, $getNewFiles, $deleteFiles, $sendSucessLog)
    {
    	Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
    	$startTime = time();
    	$successCount = 0;
    	$errorCount = 0;
    	$logFile = 'Vista_Taxclass_Update'.date("YmdHis", time()).'.log';
    	$errorFile =  'Vista_Taxclass_Error'.date("YmdHis", time()).'.log';

		//Pre-load tax class ids
		$taxClassIds = array();
		$taxClasses = Mage::getModel('tax/class')->getCollection();
		foreach ($taxClasses as $taxClass)
		{
			$taxClassIds[$taxClass->getClassName()] = $taxClass->getId();
		}

		$inventoryFile = Mage::getBaseDir() . '/var/importexport/vista_inventory/SRN_TAX_CODES.csv';

		$products = array_map('str_getcsv', file($inventoryFile));

		//products[0] = VISTA Pin
		//products[1] = SRN
		//products[2] = Medium Code
		//products[3] = AVP Tax Code

		unset($processedSkus);

		Mage::log("Processing File:".$inventoryFile."\r\n",null,$logFile);
		$processedSkuFile = '/var/importexport/vista_inventory/Taxclass_Processed_'.time();
		$deadLockSkuFile = '/var/importexport/vista_inventory/Taxclass_DEADLOCK_'.time();

		foreach($products as &$product){
			$sku = $product[1];
			try{
				if($sku != ""){
					$inventoryProduct = Mage::getModel('catalog/product')->loadByAttribute('sku',$sku);
					if($inventoryProduct){
						//Product Update
						$this->updateProduct($inventoryProduct, $product, $taxClassIds);
						$fh = fopen($processedSkuFile, 'a');
						fwrite($fh, $sku."\r\n");
						fclose($fh);
						$successCount++;
						Mage::log("Updated sku:".$inventoryProduct->getsku()."\r\n",null,$logFile);
					}

				}
			}catch (Exception $e){
				Mage::log($sku.":".$e->getMessage(),null,$errorFile);
				if(strstr($e->getMessage(), "1205 Lock wait timeout exceeded")) {
					$fh2 = fopen($deadLockSkuFile, 'a');
					foreach($product as $field) {
						fwrite($fh2, $field."|");
						fwrite($fh2, PHP_EOL);
						fclose($fh2);
					}
				}else{
					$errorCount++;
					if($errorCount > 0){
						$this->sendEmail("Taxclass Update Error", "There was an error(s) in the Taxclass Update process", Mage::getBaseDir()."/var/log/", $errorFile);
					}
					if($sendSucessLog && $successCount > 0){
						$this->sendEmail("Taxclass Update", "Metrics for the Taxclass Update process", Mage::getBaseDir()."/var/log/", $logFile);
					}
					return;
				}
			}
			unset($inventoryProduct,$sku, $product);
		}

		unlink($processedSkuFile);

		Mage::log("************************ Taxclass Update Completed ************************\r\n",null,$logFile);
		Mage::log("Time Elapsed :".round(abs(time() - $startTime) /60,2)." minutes\r\n",null,$logFile);
		Mage::log("Successes : ".$successCount."\r\n",null,$logFile);
		Mage::log("Errors : ".$errorCount."\r\n",null,$logFile);
		Mage::log("*********************************************************************\r\n",null,$logFile);

		if($errorCount > 0)
		{
			$this->sendEmail('Taxclass Update Update Error', 'There was an error(s) in the Taxclass Update process', Mage::getBaseDir().'/var/log/', $errorFile);
		}

		if($sendSucessLog && $successCount > 0)
		{
			$this->sendEmail('Taxclass Update', 'Metrics for the Taxclass Update process', Mage::getBaseDir().'/var/log/', $logFile);
		}

		if($inventoryFile == NULL)
		{
			$this->sendEmail('Taxclass Update - NO FILES FOUND', 'There were no Taxclass files found to process', Mage::getBaseDir().'/var/log/', null);
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
		$email_from = "Magento Taxclass Update Processer";
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
	private function updateProduct($inventoryProduct, $product, $taxClassIds)
	{
		$inventoryProduct->setTaxClassId($taxClassIds["Taxable Goods"]);
		$inventoryProduct->save();
	}
}



