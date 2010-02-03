<?php
/**
 * order_MessageScriptDocumentElement
 * @package modules.order.persistentdocument.import
 */
class order_MessageScriptDocumentElement extends import_ScriptDocumentElement
{
    /**
     * @return order_persistentdocument_message
     */
    protected function initPersistentDocument()
    {
    	return order_MessageService::getInstance()->getNewDocumentInstance();
    }
}