<?php
$installer = $this;

$installer->startSetup();

/**
 * Add Vista Order attributes for sales entities
 */
$entityAttributesCodes = array(
    'vistaorder_id' => Varien_Db_Ddl_Table::TYPE_VARCHAR,
    'vistaorder_status' => Varien_Db_Ddl_Table::TYPE_VARCHAR
);

foreach ($entityAttributesCodes as $code => $type) {
    //$installer->addAttribute('quote', $code, array('type' => $type, 'visible' => false));
    $installer->addAttribute('order', $code, array('type' => $type, 'visible' => false));
}

/**
* Add Vista Customer attributes for customer entities
*/
$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

$entityTypeId     = $setup->getEntityTypeId('customer');
$attributeSetId   = $setup->getDefaultAttributeSetId($entityTypeId);
$attributeGroupId = $setup->getDefaultAttributeGroupId($entityTypeId, $attributeSetId);

$setup->addAttribute('customer', 'vistacustomer_id', array(
    'input'         => 'text',
    'type'          => 'int',
    'label'         => 'Vista Customer ID',
    'visible'       => 1,
    'required'      => 0,
    'user_defined' => 1,
));

$setup->addAttributeToGroup(
$entityTypeId,
$attributeSetId,
$attributeGroupId,
 'vistacustomer_id',
 '999'  //sort_order
);

$oAttribute = Mage::getSingleton('eav/config')->getAttribute('customer', 'vistacustomer_id');
$oAttribute->setData('used_in_forms', array('adminhtml_customer'));
$oAttribute->save();


$installer->endSetup();