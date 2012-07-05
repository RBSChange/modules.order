<?php
/**
 * order_OrderpreparationScriptDocumentElement
 * @package modules.order.persistentdocument.import
 */
class order_OrderpreparationScriptDocumentElement extends import_ScriptDocumentElement
{
	/**
	 * @return order_persistentdocument_orderpreparation
	 */
	protected function initPersistentDocument()
	{
		return order_OrderpreparationService::getInstance()->getNewDocumentInstance();
	}
	
	/**
	 * @return f_persistentdocument_PersistentDocumentModel
	 */
	protected function getDocumentModel()
	{
		return f_persistentdocument_PersistentDocumentModel::getInstanceFromDocumentModelName('modules_order/orderpreparation');
	}
}