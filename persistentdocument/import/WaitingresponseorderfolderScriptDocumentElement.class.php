<?php
/**
 * @package modules.order.persistentdocument.import
 */
class order_WaitingresponseorderfolderScriptDocumentElement extends import_ScriptDocumentElement
{
    /**
     * @return order_persistentdocument_waitingresponseorderfolder
     */
    protected function initPersistentDocument()
    {
    	return order_WaitingresponseorderfolderService::getInstance()->getNewDocumentInstance();
    }
    
    /**
	 * @return f_persistentdocument_PersistentDocumentModel
	 */
	protected function getDocumentModel()
	{
		return f_persistentdocument_PersistentDocumentModel::getInstanceFromDocumentModelName('modules_order/waitingresponseorderfolder');
	}
}