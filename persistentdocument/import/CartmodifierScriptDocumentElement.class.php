<?php
/**
 * order_CartmodifierScriptDocumentElement
 * @package modules.order.persistentdocument.import
 */
class order_CartmodifierScriptDocumentElement extends import_ScriptDocumentElement
{
	/**
	 * @return order_persistentdocument_cartmodifier
	 */
	protected function initPersistentDocument()
	{
		return order_CartmodifierService::getInstance()->getNewDocumentInstance();
	}
	
	/**
	 * @return f_persistentdocument_PersistentDocumentModel
	 */
	protected function getDocumentModel()
	{
		return f_persistentdocument_PersistentDocumentModel::getInstanceFromDocumentModelName('modules_order/cartmodifier');
	}
}