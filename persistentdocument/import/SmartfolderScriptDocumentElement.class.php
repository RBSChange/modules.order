<?php
/**
 * order_SmartfolderScriptDocumentElement
 * @package modules.order.persistentdocument.import
 */
class order_SmartfolderScriptDocumentElement extends import_ScriptDocumentElement
{
	/**
	 * @return order_persistentdocument_smartfolder
	 */
	protected function initPersistentDocument()
	{
		return order_SmartfolderService::getInstance()->getNewDocumentInstance();
	}
	
	/**
	 * @return f_persistentdocument_PersistentDocumentModel
	 */
	protected function getDocumentModel()
	{
		return f_persistentdocument_PersistentDocumentModel::getInstanceFromDocumentModelName('modules_order/smartfolder');
	}
}