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
			$taxZone = catalog_TaxService::getInstance()->getCurrentTaxZone($shop, $cart);
			if ($taxZone === null)
			{
				Framework::warn(__METHOD__ . ' Invalid tax zone');
				return false;
			}
			$feesId = $this->fees->getId();
			foreach ($cart->getShippingArray() as $data) 
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
					$rate = catalog_TaxService::getInstance()->getTaxRate($shop->getId(), $this->getTaxCategory(), $taxZone);
					$feesInfo->setValueWithTax($feesInfo->getValueWithoutTax() * ( 1 + $rate) );
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
	
	/**
	 * @param string $value
	 */
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