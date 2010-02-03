<?php
/**
 * order_PreferencesScriptDocumentElement
 * @package modules.order.persistentdocument.import
 */
class order_PreferencesScriptDocumentElement extends import_ScriptDocumentElement
{
    /**
     * @return order_persistentdocument_preferences
     */
    protected function initPersistentDocument()
    {
    	$document = ModuleService::getInstance()->getPreferencesDocument('order');
    	return ($document !== null) ? $document : order_PreferencesService::getInstance()->getNewDocumentInstance();
    }
}