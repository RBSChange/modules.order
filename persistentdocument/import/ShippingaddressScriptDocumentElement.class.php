<?php
/**
 * order_ShippingaddressScriptDocumentElement
 * @package modules.order.persistentdocument.import
 */
class order_ShippingaddressScriptDocumentElement extends import_ScriptDocumentElement
{
    /**
     * @return order_persistentdocument_shippingaddress
     */
    protected function initPersistentDocument()
    {
    	return order_ShippingaddressService::getInstance()->getNewDocumentInstance();
    }
    
    /**
	 * @return f_persistentdocument_PersistentDocumentModel
	 */
	protected function getDocumentModel()
	{
		return f_persistentdocument_PersistentDocumentModel::getInstanceFromDocumentModelName('modules_order/shippingaddress');
	}
}