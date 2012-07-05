<?php
/**
 * order_ExpeditionlineScriptDocumentElement
 * @package modules.order.persistentdocument.import
 */
class order_ExpeditionlineScriptDocumentElement extends import_ScriptDocumentElement
{
	/**
	 * @return order_persistentdocument_expeditionline
	 */
	protected function initPersistentDocument()
	{
		return order_ExpeditionlineService::getInstance()->getNewDocumentInstance();
	}
	
	/**
	 * @return f_persistentdocument_PersistentDocumentModel
	 */
	protected function getDocumentModel()
	{
		return f_persistentdocument_PersistentDocumentModel::getInstanceFromDocumentModelName('modules_order/expeditionline');
	}
}