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
			self::$instance = new self();
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
	 * @param order_persistentdocument_order $order
	 * @return order_persistentdocument_orderline
	 */
	public function createFromCartLineInfo($cartLine, $orderLine = null, $order)
	{
		if ($orderLine === null)
		{
			$orderLine = $this->getNewDocumentInstance();
		}
		else
		{
			$orderLine->setGlobalPropertyArray(array());
		}
		$cpf = catalog_PriceFormatter::getInstance();
		$currencyCode = $order->getCurrencyCode();
		$product = $cartLine->getProduct();
		$orderLine->setProductId($product->getId());
		$orderLine->setLabel($product->getLabel());
		$orderLine->setOrderLabel($product->getOrderLabel());
		$orderLine->setOrderLabelAsHtml($product->getOrderLabelAsHtml());
		$orderLine->setCodeReference($product->getCodeReference());
		$orderLine->setQuantity($cartLine->getQuantity());
		$orderLine->setUnitPriceWithTax($cpf->round($cartLine->getValueWithTax(), $currencyCode));
		$orderLine->setUnitPriceWithoutTax($cpf->round($cartLine->getValueWithoutTax(), $currencyCode));
		
		// If there is no old price, duplicate the normal price.
		if (!is_null($cartLine->getOldValueWithTax()))
		{
			$orderLine->setBaseUnitPriceWithTax($cpf->round($cartLine->getOldValueWithTax(), $currencyCode));
			$orderLine->setBaseUnitPriceWithoutTax($cpf->round($cartLine->getOldValueWithoutTax(), $currencyCode));
		}
		else
		{
			$orderLine->setBaseUnitPriceWithTax($cpf->round($cartLine->getValueWithTax(), $currencyCode));
			$orderLine->setBaseUnitPriceWithoutTax($cpf->round($cartLine->getValueWithoutTax(), $currencyCode));
		}

		$orderLine->setAmountWithTax($cpf->round($cartLine->getTotalValueWithTax(), $currencyCode));
		$orderLine->setAmountWithoutTax($cpf->round($cartLine->getTotalValueWithoutTax(), $currencyCode));
		
		$orderLine->setTaxCode($cartLine->getTaxCode());
		$orderLine->setTaxRate($cartLine->getTaxRate());		
		$orderLine->setTaxAmount($cartLine->getTotalTax());
		
		$cartLineProperties = $cartLine->getPropertiesArray();
		$product->getDocumentService()->updateOrderLineProperties($product, $cartLineProperties, $orderLine, $order);
		
		// Properties.
		$orderLine->mergeGlobalProperties($cartLineProperties);
		return $orderLine;
	}
}