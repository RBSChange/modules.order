<?php
class order_CartPriceShippingStrategy extends order_BaseFeesApplicationStrategy
{
	/**
	 * @param order_CartInfo $cart
	 * @return boolean
	 */
	function applyToCart($cart)
	{
		$isNew = false;
		$feesInfo = $this->getFeesInfo($cart, $isNew);
		if ($feesInfo !== null)
		{
			if ($isNew)
			{
				$cart->addFeesInfo($feesInfo);
			}
			return true;
		}
		return false;
	}
	
	/**
	 * @param order_CartInfo $cart
	 * @param boolean $isNew
	 * @return order_FeesInfo
	 */
	protected function getFeesInfo($cart, &$isNew)
	{
		$shop = $cart->getShop();
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
					$isNew = true;
				}
				else
				{
					$isNew = false;
				}
				$rate = catalog_TaxService::getInstance()->getTaxRate($shop->getId(), $this->getTaxCategory(), $taxZone);
				
				$cartValue = $cart->getSubTotalWithTax();
				foreach ($this->getRanges() as $checkValue => $feesValue) 
				{
					if ($cartValue >= doubleval($checkValue))
					{
						$feesInfo->setValueWithTax($feesValue);
						$feesInfo->setValueWithoutTax($feesValue / ( 1 + $rate));
						$shippingArray[$k]['filter']['shippingvalueWithTax'] = $feesInfo->getValueWithTax();
						$shippingArray[$k]['filter']['shippingvalueWithoutTax'] = $feesInfo->getValueWithoutTax();
						$cart->setShippingArray($shippingArray);
						return $feesInfo;
					}
				}
				$feesInfo->setValueWithoutTax($this->getValueWithoutTax());
				$feesInfo->setValueWithTax($feesInfo->getValueWithoutTax() * ( 1 + $rate) );
				$shippingArray[$k]['filter']['shippingvalueWithTax'] = $feesInfo->getValueWithTax();
				$shippingArray[$k]['filter']['shippingvalueWithoutTax'] = $feesInfo->getValueWithoutTax();
				$cart->setShippingArray($shippingArray);
				return $feesInfo;
			}
		}
		return null;
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
		$shop = $cart->getShop();
		$taxZone = catalog_TaxService::getInstance()->getCurrentTaxZone($shop, $cart);
		if ($taxZone === null)
		{
			Framework::warn(__METHOD__ . ' Invalid tax zone');
			return;
		}
		
		$rate = catalog_TaxService::getInstance()->getTaxRate($shop->getId(), $this->getTaxCategory(), $taxZone);
		$shippingFilter->setTaxCategory($this->getTaxCategory());
		$shippingFilter->setValueWithoutTax($this->getValueWithoutTax());
				
		$cartValue = $cart->getSubTotalWithTax();
		foreach ($this->getRanges() as $checkValue => $feesValue) 
		{
			if ($cartValue >= doubleval($checkValue))
			{
				$shippingFilter->setValueWithoutTax($feesValue / ( 1 + $rate));
				return;
			}
		}
	}
	
	
	/**
	 * @return array
	 */
	function getParameters()
	{
		$result = parent::getParameters();
		$result['strategylabel'] = $this->getLabel();
		$result['boValueJSON'] = $this->getBoValueJSON();
		$result['ranges'] = $this->getBoRanges();
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
		if (isset($parameters['ranges']))
		{
			$this->fees->setStrategyParam('ranges', $parameters['ranges']);
		}
	}
	
	/**
	 * @return array
	 */
	protected function getRanges()
	{
		$string = $this->getBoRanges();
		if (f_util_StringUtils::isEmpty($string))
		{
			return array();
		}
		$result = array();
		foreach (explode(',', $string) as $data) 
		{
			$values = explode('=', $data);
			if (count($values) == 2)
			{
				$check = trim($values[0]);
				$value = doubleval(trim($values[1]));
				if (!empty($check) && $value >= 0)
				{
					$result[$check] = $value;
				}
			}
		}
		return $result;
	}
	
	private function getBoRanges()
	{
		return $this->fees->getStrategyParam('ranges');
	}
	
	/**
	 * @return string
	 */
	private function getDefaultLabel()
	{
		return LocaleService::getInstance()->transFO('m.order.frontoffice.shipping-fees', array('ucf'));
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
		$valueHT = $this->getValueWithoutTax();
		$shop = $this->getShop();
		$taxZone = $shop->getBoTaxZone();
		$currencyDoc = catalog_CurrencyService::getInstance()->getByCode($shop->getCurrencyCode());
		$editTTC = $taxZone !== null;
		$taxCategory = $this->getTaxCategory();
		$taxCategories = catalog_TaxService::getInstance()->getBoTaxeInfoForShop($shop);
		if ($taxZone !== null && $valueHT > 0)
		{
			$valueTTC = catalog_PriceFormatter::getInstance()->round($valueHT * (1 + $taxCategories[$taxCategory]['rate']), $currencyDoc->getCode());
		}
		else
		{
			$valueTTC = $valueHT;
		}
		
		$array = array('value' => $editTTC ? $valueTTC : $valueHT, 'valueTTC' => $valueTTC, 'valueHT' => $valueHT , 'editTTC' => $editTTC, 'taxCategory' => $taxCategory, 
			'taxCategories' => $taxCategories, 'currency' => $currencyDoc->getSymbol(), 'currencyCode' => $currencyDoc->getCode());
		
		return JsonService::getInstance()->encode($array);
	}
	
	public function setBoValueJSON($value)
	{
		$parts = explode(',', $value);
		if (count($parts) != 2 || $parts[0] == '' || $parts[1] == '')
		{
			return;
		}
		$this->fees->setStrategyParam('taxcategory', $parts[1]);
		$shop = $this->getShop();
		$taxZone = $shop->getBoTaxZone();
		if ($taxZone === null)
		{
			$valueHT = doubleval($parts[0]);
		}
		else
		{
			$rate = catalog_TaxService::getInstance()->getTaxRate($shop->getId(), $this->getTaxCategory(), $taxZone);
			$valueHT = doubleval($parts[0]) / (1 + $rate);
		}
		$this->fees->setStrategyParam('valuewithouttax', $valueHT);
	}
}