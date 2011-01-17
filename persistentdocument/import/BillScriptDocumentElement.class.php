<?php
/**
 * order_BillScriptDocumentElement
 * @package modules.order.persistentdocument.import
 */
class order_BillScriptDocumentElement extends import_ScriptDocumentElement
{
    /**
     * @return order_persistentdocument_bill
     */
    protected function initPersistentDocument()
    {
    	return order_BillService::getInstance()->initializeByOrderForPayment($this->getOrderDocument());
    }
    
    /**
	 * @return f_persistentdocument_PersistentDocumentModel
	 */
	protected function getDocumentModel()
	{
		return f_persistentdocument_PersistentDocumentModel::getInstanceFromDocumentModelName('modules_order/bill');
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
		$document->setLabel(order_BillNumberGenerator::getInstance()->generate($document));
		$document->save();
		if ($document->getStatus() == order_BillService::FAILED)
		{
			order_OrderService::getInstance()->cancelOrder($this->getOrderDocument(), false);
		}
		else
		{
			order_OrderService::getInstance()->processOrder($this->getOrderDocument(), false);
		}
	}
}