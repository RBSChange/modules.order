<?php
/**
 * order_OrderScriptDocumentElement
 * @package modules.order.persistentdocument.import
 */
class order_OrderScriptDocumentElement extends import_ScriptDocumentElement
{
	/**
	 * @var boolean
	 */
	private $autoTotalAmount = false;
	
    /**
     * @return order_persistentdocument_order
     */
    protected function initPersistentDocument()
    {
    	$cart = $this->getComputedAttribute("cart");
    	if ($cart)
    	{
    		return order_OrderService::getInstance()->createFromCartInfo($cart);
    	}
    	else
    	{
    		return order_OrderService::getInstance()->getNewDocumentInstance();
    	}
    }
    
    protected function getDocumentProperties()
    {
    	$properties = parent::getDocumentProperties();
    	if (isset($properties['autoTotalAmount']))
    	{
    		//if autoTotalAmount is true, total amount is calculated in the endProcess() method with order lines
    		//otherwise you have to include attributes totalAmountWithTax and totalAmountWithoutTax
    		$properties['totalAmountWithTax'] = 0;
    		$properties['totalAmountWithoutTax'] = 0;
    		$this->autoTotalAmount = true;
    		unset($properties['autoTotalAmount']);
    	}
    	if (isset($properties['shippingDataSerialized']))
    	{
    		$serializedInfo = $properties['shippingDataSerialized'];
			$shippingDataArray = json_decode($this->replaceRefIdInString($serializedInfo), true);
    		$properties['shippingDataArray'] = $shippingDataArray;
    		    		
    		unset($properties['shippingDataSerialized']);
    	}
    	if (isset($properties['addressIdByModeIdSeralized']))
    	{
    		$serializedInfo = $properties['addressIdByModeIdSeralized'];
			$addressIdByModeId = json_decode($this->replaceRefIdInString($serializedInfo), true);
			$modeId = $addressIdByModeId['modeId'];
			$addressId = $addressIdByModeId['addressId'];
    		$this->getPersistentDocument()->setAddressIdByModeId($modeId, $addressId);
			
    		unset($properties['addressIdByModeIdSeralized']);
    	}
    	
    	return $properties;
    }

	/**
	 * @return void
	 */
	protected function saveDocument()
	{
		$document = $this->getPersistentDocument();
		$document->save();
	}
	
	public function endProcess()
	{
		if ($this->autoTotalAmount)
		{
			$order = $this->getPersistentDocument();
			$properties = $this->getDocumentProperties();
			/* @var $order order_persistentdocument_order */
			$order->setTotalAmountWithoutTax($order->getLinesAmountWithoutTax());
			$order->setTotalAmountWithTax($order->getLinesAmountWithTax());
			if ($this->getComputedAttribute('cart'))
			{
				$order->save();
			}
			else
			{
				$folder = order_OrderService::getInstance()->getFolderOfDay($order->getUICreationdate());
				$order->save($folder->getId());
			}
		}
	}
	
}