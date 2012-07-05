<?php
class order_DefaultShippingStrategy extends order_BaseFeesApplicationStrategy
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
			$shop = $cart->getShop();
			$billingArea = $shop->getCurrentBillingArea();
			$taxZone = catalog_TaxService::getInstance()->getCurrentTaxZone($shop, $cart);
			if ($taxZone === null)
			{
				Framework::warn(__METHOD__ . ' Invalid tax zone');
				return false;
			}
			$feesId = $this->fees->getId();
			$shippingArray = $cart->getShippingArray();
			foreach ($shippingArray as $k => $data) 
			{
				if (isset($data['filter']) && $data['filter']['feesId'] == $feesId)
				{
					$feesInfo = $cart->getFeesById($feesId);
					if ($feesInfo === null)
					{
						$feesInfo = new order_FeesInfo();
						$feesInfo->setId($feesId);
						$feesInfo->setLabel($this->getLabel());
						$cart->addFeesInfo($feesInfo);
					}
					
					$feesInfo->setValueWithoutTax($this->getValueWithoutTax());
					$rate = catalog_TaxService::getInstance()->getTaxRateByKey($billingArea->getId(), $this->getTaxCategory(), $taxZone);
					$feesInfo->setValueWithTax(catalog_TaxService::getInstance()->addTaxByRate($feesInfo->getValueWithoutTax(), $rate));
					$shippingArray[$k]['filter']['shippingvalueWithTax'] = $feesInfo->getValueWithTax();
					$shippingArray[$k]['filter']['shippingvalueWithoutTax'] = $feesInfo->getValueWithoutTax();
					$cart->setShippingArray($shippingArray);
					return true;					
				}
			} 			
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
	 * @return array
	 */
	function getParameters()
	{
		$result = parent::getParameters();
		$result['strategylabel'] = $this->getLabel();
		$result['boValueJSON'] = $this->getBoValueJSON();
		return $result;
	}
	
	/**
	 * @param array $parameters
	 */
	function setParameters($parameters)
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
		return LocaleService::getInstance()->trans('m.order.frontoffice.shipping-fees', array('ucf'));
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
		return $taxCategory === null ? '1' : $taxCategory;
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
		$array = catalog_BillingareaService::getInstance()->buildBoPriceEditInfos($this->getValueWithoutTax(), $this->getShop(), $this->getTaxCategory());
		return JsonService::getInstance()->encode($array);
	}
	
	/**
	 * @param string $value
	 */
	public function setBoValueJSON($value)
	{
		list($valueWithoutTax, $taxCategory) = catalog_BillingareaService::getInstance()->parseBoPriceEditInfos($value, $this->getShop());
		$this->fees->setStrategyParam('taxcategory', $taxCategory);
		$this->fees->setStrategyParam('valuewithouttax', $valueWithoutTax);
	}
}