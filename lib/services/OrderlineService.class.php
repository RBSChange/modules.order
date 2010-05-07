<?php
/**
 * order_OrderlineService
 * @package modules.order
 */
class order_OrderlineService extends f_persistentdocument_DocumentService
{
	/**
	 * @var order_OrderlineService
	 */
	private static $instance;

	/**
	 * @return order_OrderlineService
	 */
	public static function getInstance()
	{
		if (self::$instance === null)
		{
			self::$instance = self::getServiceClassInstance(get_class());
		}
		return self::$instance;
	}

	/**
	 * @return order_persistentdocument_orderline
	 */
	public function getNewDocumentInstance()
	{
		return $this->getNewDocumentInstanceByModelName('modules_order/orderline');
	}

	/**
	 * Create a query based on 'modules_order/orderline' model
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createQuery()
	{
		return $this->pp->createQuery('modules_order/orderline');
	}

	/**
	 * @param order_CartLineInfo $cartLine
	 * @param order_persistentdocument_orderline $orderLine
	 * @return order_persistentdocument_orderline
	 */
	public function createFromCartLineInfo($cartLine, $orderLine = null)
	{
		if ($orderLine === null)
		{
			$orderLine = $this->getNewDocumentInstance();
		}
		else
		{
			$orderLine->setGlobalPropertyArray(array());
		}
		
		$product = $cartLine->getProduct();
		$orderLine->setLabel($product->getLabel());
		$orderLine->setOrderLabel($product->getOrderLabel());
		$orderLine->setOrderLabelAsHtml($product->getOrderLabelAsHtml());
		
		$orderLine->setCodeReference($product->getCodeReference());
		$orderLine->setProductId($cartLine->getProductId());
		$orderLine->setQuantity($cartLine->getQuantity());
		$orderLine->setUnitPriceWithTax(catalog_PriceHelper::roundPrice($cartLine->getValueWithTax()));
		$orderLine->setUnitPriceWithoutTax(catalog_PriceHelper::roundPrice($cartLine->getValueWithoutTax()));
		// If there is no old price, duplicate the normal price.
		if (!is_null($cartLine->getOldValueWithTax()))
		{
			$orderLine->setBaseUnitPriceWithTax(catalog_PriceHelper::roundPrice($cartLine->getOldValueWithTax()));
			$orderLine->setBaseUnitPriceWithoutTax(catalog_PriceHelper::roundPrice($cartLine->getOldValueWithoutTax()));
		}
		else
		{
			$orderLine->setBaseUnitPriceWithTax(catalog_PriceHelper::roundPrice($cartLine->getValueWithTax()));
			$orderLine->setBaseUnitPriceWithoutTax(catalog_PriceHelper::roundPrice($cartLine->getValueWithoutTax()));
		}

		$orderLine->setAmountWithTax(catalog_PriceHelper::roundPrice($cartLine->getTotalValueWithTax()));
		$orderLine->setAmountWithoutTax(catalog_PriceHelper::roundPrice($cartLine->getTotalValueWithoutTax()));
		
		$orderLine->setTaxCode($cartLine->getTaxCode());
		$orderLine->setTaxRate($cartLine->getTaxRate());		
		$orderLine->setTaxAmount($cartLine->getTotalTax());
		
		// Properties.
		$orderLine->mergeGlobalProperties($cartLine->getPropertiesArray());
		return $orderLine;
	}
}