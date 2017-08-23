<?php
class FW_VistaProcessing_Model_Resource_Setup extends Mage_Sales_Model_Resource_Setup
{
    /**
     * Current entity type id
     *
     * @var string
     */
    protected $_currentEntityTypeId;

    /**
     * Add attribute to an entity type
     * If attribute is system will add to all existing attribute sets
     *
     * @param string|integer $entityTypeId
     * @param string $code
     * @param array $attr
     * @return Mage_Eav_Model_Entity_Setup
     */
    public function addAttribute($entityTypeId, $code, array $attr)
    {
        $this->_currentEntityTypeId = $entityTypeId;
        return parent::addAttribute($entityTypeId, $code, $attr);
    }
}
