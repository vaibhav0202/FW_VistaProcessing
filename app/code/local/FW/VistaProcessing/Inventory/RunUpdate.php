<?php
    chdir(dirname(__FILE__));  // Change working directory to script location
    require_once '../../../../../Mage.php';  // Include Mage
    require_once 'Inventoryupdate.php';
    Mage::app('admin');  // Run Mage app() and set scope to admin


   	$sendSucessLog = false;
  	$addProducts = false;
  	$updateProducts = false;
  	$processFullFile = false;  	
  	$processUpdateFile = false;  	
  	$getNewFiles = false;
  	$deleteFiles = false;
  	  	
    foreach ($argv as $arg)
    {
    	if($arg == "-a") //Add Products
    	{
    		$addProducts = true;
    	}
    	
    	if($arg == "-u") //update products
    	{
    		$updateProducts = true;
    	}
    	
    	if($arg == "-s") //Send Success Log
    	{
    		$sendSucessLog = true;
    	}
    	
     	if($arg == "-ff") //process full file
    	{
    		$processFullFile = true;
    	}
    	
      	if($arg == "-uf") //process update file
    	{
    		$processUpdateFile = true;
    	}
    	
     	if($arg == "-r") //get new files
    	{
    		$getNewFiles = true;
    	}
    	
       	if($arg == "-d") //delete source file from ftp server
    	{
    		$deleteFiles = true;
    	}
    }

	$update = new FW_VistaProcessing_Model_Inventoryupdate();
	$update->import($addProducts, $updateProducts, $processFullFile, $processUpdateFile, $getNewFiles, $deleteFiles, $sendSucessLog);
?>
