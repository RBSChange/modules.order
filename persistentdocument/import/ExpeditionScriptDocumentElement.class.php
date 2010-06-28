<?php
/**
 * order_ExpeditionScriptDocumentElement
 * @package modules.order.persistentdocument.import
 */
class order_ExpeditionScriptDocumentElement extends import_ScriptDocumentElement
{
    /**
     * @return order_persistentdocument_expedition
     */
    protected function initPersistentDocument()
    {
    	return order_ExpeditionService::getInstance()->createForOrder($this->getOrderDocument());
    }
    
    /**
	 * @return f_persistentdocument_PersistentDocumentModel
	 */
	protected function getDocumentModel()
	{
		return f_persistentdocument_PersistentDocumentModel::getInstanceFromDocumentModelName('modules_order/expedition');
	}
	
	/**
	 * @return order_persistentdocument_order
	 */
	private function getOrderDocument()
	{
		$document = $this->getParentDocument()->getPersistentDocument();
		if (!($document instanceof order_persistentdocument_order))
		{
			throw new Exception('Invalid parent document: order required');
		}
		return $document;
	}
	
	/**
	 * @return void
	 */
	protected function saveDocument()
	{
		$document = $this->getPersistentDocument();
		$document->setPublicationstatus('ACTIVE');
		$document->save();
		if ($document->getStatus() == order_ExpeditionService::SHIPPED)
		{
			order_OrderService::getInstance()->completeOrder($this->getOrderDocument(), false);
		}
	}
}