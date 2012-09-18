<?php
class order_FeesRangesValueStrategy extends order_BaseFeesApplicationStrategy
{
	/**
	 * @param order_CartInfo $cart
	 * @return boolean
	 */
	function applyToCart($cart)
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
		$isNew = false;
		$feesInfo = $cart->getFeesById($feesId);
		if ($feesInfo === null)
		{
			$feesInfo = new order_FeesInfo();
			$feesInfo->setId($this->fees->getId());
			$feesInfo->setLabel($this->getLabel());
			$isNew = true;
		}
		
		$taxRate = catalog_TaxService::getInstance()->getTaxRateByKey($billingArea->getId(), $this->getTaxCategory(), $taxZone);
		$cartValue = $cart->getSubTotalWithTax();
		foreach ($this->getRanges() as $checkValue => $feesValue) 
		{
			if ($cartValue >= doubleval($checkValue))
			{
				if ($feesValue > 0)
				{

					$feesInfo->setValueWithTax($feesValue);
					$feesInfo->setValueWithoutTax(catalog_TaxService::getInstance()->removeTaxByRate($feesValue, $taxRate));
					if ($isNew) {$cart->addFeesInfo($feesInfo);}
					return true;
				}
				
				return false;
			}
		}
		
		if ($this->getValueWithoutTax() > 0)
		{
			$feesInfo->setValueWithoutTax($this->getValueWithoutTax());
			$feesInfo->setValueWithTax(catalog_TaxService::getInstance()->addTaxByRate($feesInfo->getValueWithoutTax(), $taxRate));
			if ($isNew) {$cart->addFeesInfo($feesInfo);}
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
	 * @return array
	 */
	protected function getRanges()
	{
		$string = $this->fees->getStrategyParam('ranges');
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
	
	/**
	 * @return double
	 */
	protected function getValueWithoutTax()
	{
		return doubleval($this->fees->getStrategyParam('valuewithouttax'));
	}
	
	/**
	 * @return string
	 */
	protected function getTaxCategory()
	{
		$taxCategory  = $this->fees->getStrategyParam('taxcategory');
		return $taxCategory === null ? '0' : $taxCategory;
	}
	
	
	private function getBoRanges()
	{
		return $this->fees->getStrategyParam('ranges');
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
	public function setBoValueJSON($value)
	{
		$ba = $this->fees->getBillingArea();
		list($valueWithoutTax, $taxCategory) = catalog_BillingareaService::getInstance()->parseBoPriceEditInfos($value, $this->getShop(), $ba);
		$this->fees->setStrategyParam('taxcategory', $taxCategory);
		$this->fees->setStrategyParam('valuewithouttax', $valueWithoutTax);
	}
}