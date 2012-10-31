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
	
	protected function getDocumentProperties()
	{
		$properties = parent::getDocumentProperties();
		if (!isset($properties['order']))
		{
			$order = $this->getOrderDocument();
			$properties['order'] = $order;
		}
		if (!isset($properties['label']))
		{
			$properties['label'] = order_ExpeditionNumberGenerator::getInstance()->generate($this->getPersistentDocument());
		}
		if (isset($properties['storeStep']))
		{
			switch ($properties['storeStep'])
			{
				case 'ship':
					if (!isset($properties['shippingdate']))
					{
						throw new Exception("shippingdate required when storeStep = ship");
					}
					if (!isset($properties['trackingnumber']))
					{
						throw new Exception("trackingnumber required when storeStep = ship");
					}
					order_ExpeditionService::getInstance()->shipExpedition($this->getPersistentDocument(), $properties['shippingdate'], $properties['trackingnumber']);
					break;
				default:
					throw new Exception("unknow store step attribute : " . $properties['storeStep']);
			}
			unset($properties['storeStep']);
		}
		return $properties;
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
		/* @var $document order_persistentdocument_expedition */
		$document->setPublicationstatus('ACTIVE');
		$document->save();
		if ($document->getStatus() == order_ExpeditionService::SHIPPED)
		{
			order_OrderService::getInstance()->completeOrder($this->getOrderDocument(), false);
		}
	}
}