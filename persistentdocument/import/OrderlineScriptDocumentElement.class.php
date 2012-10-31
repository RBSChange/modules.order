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
    
    protected function getDocumentProperties()
    {
    	$properties = parent::getDocumentProperties();
    	if (isset($properties['product-refid']))
    	{
    		$product = $this->getComputedAttribute('product');
    		
    		if ($product instanceof catalog_persistentdocument_product)
    		{
    			$properties['productId'] = $product->getId();
    			$properties['label'] = $product->getLabel();
    			$properties['codeReference'] = $product->getCodeReference();
    			$quantity = $properties['quantity'];
    			$price = $product->getPriceForCurrentShopAndCustomer($quantity);
    			$properties['unitPriceWithTax'] = $price->getValueWithTax();
    			$properties['unitPriceWithoutTax'] = $price->getValueWithoutTax();
    			$properties['amountWithTax'] = $quantity * $price->getValueWithTax();
    			$properties['amountWithoutTax'] = $quantity * $price->getValueWithoutTax();
    			
    			unset($properties['product-refid']);
    		}
    		else
    		{
    			throw new Exception("Attribute product doesn't match a product");
    		}
    	}
    	
    	return $properties;    	
    }

    public function endProcess()
    {
    	$order = $this->getOrderDocument();
    	$order->addLine($this->getObject());
    	$order->save();
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
    
    

}