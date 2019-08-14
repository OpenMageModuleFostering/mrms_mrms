<?php
class Mrms_Mrms_Block_Sales_Order_Grid_Renderer_Risk extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract{
    public function render(Varien_Object $row){
	$out = '';

	$order = Mage::getModel('sales/order')->load($row->getId());
        $result = $order->getmrms_response();

	if(!$result){
		$out = Mage::helper('mrms')->__('-');
	}else{	    
		$data = unserialize($result);
		$out .= ($data['PaymentStatus'] == 'Paid' ? 'Review' : $data['PaymentStatus']);
	}
	return $out;
    }
}
