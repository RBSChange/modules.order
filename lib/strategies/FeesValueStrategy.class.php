<?php
class order_FeesValueStrategy extends order_BaseFeesApplicationStrategy
{
	/**
	 * @param order_CartInfo $cart
	 * @return boolean
	 */
	function applyToCart($cart)
	{
		$value = $this->getValueWithoutTax();		
		if ($value > 0)
		{
			$value = $this->getValueWithoutTax();
			if ($value <= 0)
			{
				Framework::warn(__METHOD__ . ' Invalid value');
				return false;
			}	
					
			$shop = $cart->getShop();
			$billingArea = $shop->getCurrentBillingArea();
			$taxZone = catalog_TaxService::getInstance()->getCurrentTaxZone($shop, $cart);
			if ($taxZone === null)
			{
				Framework::warn(__METHOD__ . ' Invalid tax zone');
				return false;
			}
			$feesId = $this->fees->getId();

			$feesInfo = $cart->getFeesById($feesId);
			if ($feesInfo === null)
			{
				$feesInfo = new order_FeesInfo();
				$feesInfo->setId($this->fees->getId());
				$feesInfo->setLabel($this->getLabel());
				$cart->addFeesInfo($feesInfo);
			}
			$feesInfo->setValueWithoutTax($this->getValueWithoutTax());
			$rate = catalog_TaxService::getInstance()->getTaxRateByKey($billingArea->getId(), $this->getTaxCategory(), $taxZone);
			$feesInfo->setValueWithTax(catalog_TaxService::getInstance()->addTaxByRate($feesInfo->getValueWithoutTax(), $rate));			
			return true;
		}
		return false;
	}
	
	/**
	 * @param order_CartInfo $cart
	 * @return boolean
	 */
	function removeFromCart($cart)
	{
		$feesInfo = $cart->getFeesById($this->fees->getId());
		if ($feesInfo !== null)
		{
			$cart->removeFeesInfo($feesInfo);
		}
		return true;
	}
	
	/**
	 * @param catalog_persistentdocument_shippingfilter $shippingFilter
	 * @param order_CartInfo $cart
	 */
	public function updateShippingFilter($shippingFilter, $cart)
	{
		$shippingFilter->setTaxCategory($this->getTaxCategory());
		$shippingFilter->setValueWithoutTax($this->getValueWithoutTax());
	}

	/**
	 * @return string[]
	 */
	function getParameters()
	{
		$result = parent::getParameters();
		$result['strategylabel'] = $this->getLabel();
		$result['boValueJSON'] = $this->getBoValueJSON();
		return $result;
	}
	
	/**
	 * @param string[] $parameters
	 */
	public function setParameters($parameters)
	{
		parent::setParameters($parameters);
		if (isset($parameters['strategylabel']))
		{
			$this->fees->setStrategyParam('strategylabel', $parameters['strategylabel']);
		}	
		if (isset($parameters['boValueJSON']))
		{
			$this->setBoValueJSON($parameters['boValueJSON']);
		}
	}
		
	/**
	 * @return string
	 */
	private function getDefaultLabel()
	{
		return LocaleService::getInstance()->trans('m.order.frontoffice.fees', array('ucf'));
	}
	
	/**
	 * @return string
	 */
	private function getLabel()
	{
		$label = $this->fees->getStrategyParam('strategylabel');
		if (f_util_StringUtils::isEmpty($label))
		{
			$label = $this->getDefaultLabel();
		}
		return $label;
	}
	
	/**
	 * @return double
	 */
	private function getValueWithoutTax()
	{
		return doubleval($this->fees->getStrategyParam('valuewithouttax'));
	}
	
	/**
	 * @return string
	 */
	private function getTaxCategory()
	{
		$taxCategory  = $this->fees->getStrategyParam('taxcategory');
		return $taxCategory === null ? '0' : $taxCategory;
	}
	
	/**
	 * @return catalog_persistentdocument_shop
	 */
	private function getShop()
	{
		return $this->fees->getShop();
	}
	
	/**
	 * @return string
	 */
	protected function getBoValueJSON()
	{
		$ba = $this->fees->getBillingArea();
		$array = catalog_BillingareaService::getInstance()->buildBoPriceEditInfos($this->getValueWithoutTax(), $this->getShop(), $this->getTaxCategory(), $ba);
		return JsonService::getInstance()->encode($array);
	}
	
	/**
	 * @param string $value
	 */
	protected function setBoValueJSON($value)
	{
		$ba = $this->fees->getBillingArea();
		list($valueWithoutTax, $taxCategory) = catalog_BillingareaService::getInstance()->parseBoPriceEditInfos($value, $this->getShop(), $ba);
		$this->fees->setStrategyParam('taxcategory', $taxCategory);
		$this->fees->setStrategyParam('valuewithouttax', $valueWithoutTax);
	}
}