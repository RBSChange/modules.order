<?php
/**
 * order_FeesScriptDocumentElement
 * @package modules.order.persistentdocument.import
 */
class order_FeesScriptDocumentElement extends import_ScriptDocumentElement
{
	/**
	 * @return order_persistentdocument_fees
	 */
	protected function initPersistentDocument()
	{
		return order_FeesService::getInstance()->getNewDocumentInstance();
	}
	
	/**
	 * @return f_persistentdocument_PersistentDocumentModel
	 */
	protected function getDocumentModel()
	{
		return f_persistentdocument_PersistentDocumentModel::getInstanceFromDocumentModelName('modules_order/fees');
	}
}