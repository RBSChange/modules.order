<?php
/**
 * order_OrderlineScriptDocumentElement
 * @package modules.order.persistentdocument.import
 */
class order_OrderlineScriptDocumentElement extends import_ScriptDocumentElement
{
	/**
	 * @return order_persistentdocument_orderline
	 */
	protected function initPersistentDocument()
	{
		return order_OrderlineService::getInstance()->getNewDocumentInstance();
	}
}