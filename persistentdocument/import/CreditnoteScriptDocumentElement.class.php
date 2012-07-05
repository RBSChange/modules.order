<?php
/**
 * order_CreditnoteScriptDocumentElement
 * @package modules.order.persistentdocument.import
 */
class order_CreditnoteScriptDocumentElement extends import_ScriptDocumentElement
{
	/**
	 * @return order_persistentdocument_creditnote
	 */
	protected function initPersistentDocument()
	{
		return order_CreditnoteService::getInstance()->getNewDocumentInstance();
	}
	
	/**
	 * @return f_persistentdocument_PersistentDocumentModel
	 */
	protected function getDocumentModel()
	{
		return f_persistentdocument_PersistentDocumentModel::getInstanceFromDocumentModelName('modules_order/creditnote');
	}
}