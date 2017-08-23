<?php
class FW_VistaProcessing_Block_Adminhtml_Order_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
	public function __construct()
	{
		parent::__construct();
		$this->setId('sales_order_grid');
		$this->setUseAjax(true);
		$this->setDefaultSort('created_at');
		$this->setDefaultDir('DESC');
		$this->setSaveParametersInSession(true);
	}

	/**
	 * Retrieve collection class
	 *
	 * @return string
	 */
	protected function _getCollectionClass()
	{
		return 'sales/order_grid_collection';
	}

	protected function _prepareCollection()
	{
	
            $collection = Mage::getResourceModel($this->_getCollectionClass())->addAttributeToSelect('*');
            $select = $collection->getSelect();
            $select->join(array('sales'=>$collection->getTable('sales/order')), 'sales.entity_id=main_table.entity_id',array('customer_email','vistaorder_status','vistaorder_id' ));
            	
	//	$collection = Mage::getResourceModel('sales/order_collection')->addAttributeToSelect('*');
	//	$collection->getSelect()->joinLeft(array('sfog' => 'sales_flat_order_grid'),'main_table.entity_id = sfog.entity_id',array('sfog.shipping_name','sfog.billing_name'));
	//	$collection->getSelect()->joinLeft(array('sfo'=>'sales_flat_order'),'sfo.entity_id=main_table.entity_id',array('sfo.base_grand_total','sfo.grand_total'));
	      $collection->addFilterToMap('store_id', 'main_table.store_id');
        $this->setCollection($collection);
        return parent::_prepareCollection();
		
	}

	protected function _prepareColumns()
	{

		$this->addColumn('real_order_id', array(
            'header'=> Mage::helper('sales')->__('Order #'),
            'width' => '80px',
            'type'  => 'text',
            'index' => 'increment_id',
			'filter_index' => 'main_table.increment_id'
		));

		if (!Mage::app()->isSingleStoreMode()) {
			$this->addColumn('store_id', array(
                'header'    => Mage::helper('sales')->__('Purchased from (store)'),
                'index'     => 'store_id',
                'type'      => 'store',
                'store_view'=> true,
                'display_deleted' => true,
                'filter_index' => 'main_table.store_id'
			));
		}

		$this->addColumn('created_at', array(
            'header' => Mage::helper('sales')->__('Purchased On'),
            'index' => 'created_at',
            'type' => 'datetime',
            'width' => '100px',
            'filter_index' => 'main_table.created_at'
		));

		$this->addColumn('billing_name', array(
            'header' => Mage::helper('sales')->__('Bill to Name'),
            'index' => 'billing_name',
            'filter_index' => 'billing_name',
		));
		
		$this->addColumn('customer_email', array(
		     'header' => Mage::helper('sales')->__('Customer Email'),
		     'index' => 'customer_email',
		     'filter_index' => 'customer_email',
		     'width' => '50px',
		));

		$this->addColumn('base_grand_total', array(
            'header' => Mage::helper('sales')->__('G.T. (Base)'),
            'index' => 'base_grand_total',
			'filter_index' => 'main_table.base_grand_total',
            'type'  => 'currency',
            'currency' => 'base_currency_code',
		));

		$this->addColumn('grand_total', array(
            'header' => Mage::helper('sales')->__('G.T. (Purchased)'),
            'index' => 'grand_total',
			'filter_index' => 'main_table.grand_total',
            'type'  => 'currency',
            'currency' => 'order_currency_code',
		));

		$this->addColumn('status', array(
            'header' => Mage::helper('sales')->__('Magento Order Status'),
            'index' => 'status',
            'type'  => 'options',
            'width' => '70px',
            'options' => Mage::getSingleton('sales/order_config')->getStatuses(),
			'filter_index' => 'main_table.status'
		));
		
		$this->addColumn('vistaorder_status', array(
            'header'    => Mage::helper('sales')->__('Vista Order Status'),
            'index'     => 'vistaorder_status',
			'filter_index' => 'vistaorder_status',
            'type' => 'text'
		));
		
		$this->addColumn('vistaorder_id', array(
            'header'    => Mage::helper('sales')->__('Vista Order ID'),
            'index'     => 'vistaorder_id',
			'filter_index' => 'vistaorder_id',
            'type' => 'text'
		));
		
		

		return $this;
	}

	protected function _prepareMassaction()
	{
		$this->setMassactionIdField('entity_id');
		$this->getMassactionBlock()->setFormFieldName('order_ids');
		$this->getMassactionBlock()->setUseSelectAll(false);

		if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/cancel')) {
			$this->getMassactionBlock()->addItem('cancel_order', array(
                 'label'=> Mage::helper('sales')->__('Cancel'),
                 'url'  => $this->getUrl('*/sales_order/massCancel'),
			));
		}

		if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/hold')) {
			$this->getMassactionBlock()->addItem('hold_order', array(
                 'label'=> Mage::helper('sales')->__('Hold'),
                 'url'  => $this->getUrl('*/sales_order/massHold'),
			));
		}

		if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/unhold')) {
			$this->getMassactionBlock()->addItem('unhold_order', array(
                 'label'=> Mage::helper('sales')->__('Unhold'),
                 'url'  => $this->getUrl('*/sales_order/massUnhold'),
			));
		}

		$this->getMassactionBlock()->addItem('pdfinvoices_order', array(
             'label'=> Mage::helper('sales')->__('Print Invoices'),
             'url'  => $this->getUrl('*/sales_order/pdfinvoices'),
		));

		$this->getMassactionBlock()->addItem('pdfshipments_order', array(
             'label'=> Mage::helper('sales')->__('Print Packingslips'),
             'url'  => $this->getUrl('*/sales_order/pdfshipments'),
		));

		$this->getMassactionBlock()->addItem('pdfcreditmemos_order', array(
             'label'=> Mage::helper('sales')->__('Print Credit Memos'),
             'url'  => $this->getUrl('*/sales_order/pdfcreditmemos'),
		));

		$this->getMassactionBlock()->addItem('pdfdocs_order', array(
             'label'=> Mage::helper('sales')->__('Print All'),
             'url'  => $this->getUrl('*/sales_order/pdfdocs'),
		));

		return $this;
	}

	public function getRowUrl($row)
	{
		if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/view')) {
			return $this->getUrl('*/sales_order/view', array('order_id' => $row->getId()));
		}
		return false;
	}
	public function getGridUrl()
	{
		return $this->getUrl('*/*/grid', array('_current'=>true));
	}
}
