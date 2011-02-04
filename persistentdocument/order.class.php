<?php
/**
 * order_persistentdocument_order
 * @package modules.order
 */
class order_persistentdocument_order extends order_persistentdocument_orderbase
{
	/**
	 * @return String
	 */
	public function getBoOrderStatusLabel()
	{
		$status = (!$this->getOrderStatus()) ? 'Unknown' : ucfirst($this->getOrderStatus());
		$key = '&modules.order.frontoffice.status.' . $status . ';';
		return f_Locale::translateUI($key);
	}

	/**
	 * @return String
	 */
	public function getFoOrderStatusLabel()
	{
		$status = (!$this->getOrderStatus()) ? 'Unknown' : ucfirst($this->getOrderStatus());
		$key = '&modules.order.frontoffice.status.' . $status . ';';
		return f_Locale::translate($key);
	}
		
	/**
	 * @param double $value
	 * @return string
	 */
	public function formatPrice($value)
	{
		$priceFormat = $this->getPriceFormat();
		return catalog_PriceHelper::applyFormat($value, $priceFormat ? $priceFormat : "%s €");
	}
	
	/**
	 * @return string
	 */
	public function getPriceFormat()
	{
		return $this->getGlobalProperty('priceFormat');
	}
	
	/**
	 * @param string $priceFormat
	 */
	public function setPriceFormat($priceFormat)
	{
		return $this->setGlobalProperty('priceFormat', $priceFormat);
	}
	/**
	 * @return Array<String, Array<String, String>>
	 */
	public function getSubTotalTaxInfoArray()
	{
		$taxInfoArray = array();
		foreach ($this->getLineArray() as $line)
		{
			if (!isset($taxInfoArray[$line->getTaxCode()]))
			{
				$taxInfoArray[$line->getTaxCode()] = array('formattedTaxRate' => catalog_PriceHelper::formatTaxRate($line->getTaxRate()), 'taxAmount' => 0);
			}
			$taxInfoArray[$line->getTaxCode()]['taxAmount'] += $line->getTaxAmount();
		}
		return $taxInfoArray;
	}
	
	/**
	 * @return Array<String, Array<String, String>>
	 */
	public function getTotalTaxInfoArray()
	{
		$taxInfoArray = $this->getSubTotalTaxInfoArray();
		$this->completeTaxInfoArrayWithShippingFees($taxInfoArray);
		return $taxInfoArray;
	}
	
	/**
	 * @param Array $taxInfoArray
	 */
	private function completeTaxInfoArrayWithShippingFees(&$taxInfoArray)
	{		
		$taxCode = $this->getShippingModeTaxCode();
		if (!isset($taxInfoArray[$taxCode]))
		{
			$taxInfoArray[$taxCode] = array('taxAmount' => 0, 'formattedTaxRate' => catalog_PriceHelper::formatTaxRate($this->getShippingModeTaxRate()));
		}
		$taxInfoArray[$taxCode]['taxAmount'] += ($this->getShippingFeesWithTax() - $this->getShippingFeesWithoutTax());
	}
	
	/**
	 * @param String $propertyName
	 * @param Mixed $value serializable data.
	 */
	public function setGlobalProperty($propertyName, $value)
	{
		$this->setOrderProperty($propertyName, $value);
	}
	
	/**
	 * @param String $propertyName
	 * @return Mixed
	 */
	public function getGlobalProperty($propertyName)
	{
		return $this->getOrderProperty($propertyName);
	}
	
	private $globalPropertiesArray;
	
	/**
	 * @param String $name
	 * @return mixed
	 */
	private function getOrderProperty($propertyName)
	{
		if ($this->globalPropertiesArray === null)
		{
			$str = $this->getGlobalProperties();
			if ($str !== null && $str !== '')
			{
				$this->globalPropertiesArray = unserialize($str);
			}
			else
			{
				$this->globalPropertiesArray = array();
			}
		}
		
		if (isset($this->globalPropertiesArray[$propertyName]))
		{
			return $this->globalPropertiesArray[$propertyName];
		}
		else
		{
			return null;
		}
	}
	
	/**
	 * @param String $propertyName
	 * @param mixed $value
	 */
	private function setOrderProperty($propertyName, $value)
	{
		if ($this->globalPropertiesArray === null)
		{
			$oldValue = $this->getGlobalProperty($propertyName);
		}
		else
		{
			$oldValue = isset($this->globalPropertiesArray[$propertyName]) ? $this->globalPropertiesArray[$propertyName] : null;
		}
		if ($oldValue !== $value)
		{
			if ($value === null)
			{
				unset($this->globalPropertiesArray[$propertyName]);
			}
			else
			{
				$this->globalPropertiesArray[$propertyName] = $value;
			}
			$this->setGlobalProperties(serialize($this->globalPropertiesArray));
		}
	}
		
	/**
	 * @return catalog_persistentdocument_shop
	 */
	public function getShop()
	{
		return DocumentHelper::getDocumentInstance($this->getShopId(), 'modules_catalog/shop');
	}
	
	/**
	 * @return string
	 */
	public function getContextReference()
	{
		return $this->getShop()->getCodeReference();
	}
	
	/**
	 * @return website_persistentdocument_website
	 */
	public function getWebsite()
	{
		return DocumentHelper::getDocumentInstance($this->getWebsiteId(), 'modules_website/website');
	}
	
	/**
	 * @param array $couponData
	 * @example array<id => integer, code => string, valueWithTax => double, valueWithoutTax => double>
	 */
	public function setCouponData($couponData)
	{
		if (is_array($couponData))
		{
			$this->setGlobalProperty('__coupon', $couponData);
			$this->setCouponId($couponData['id']);
		}
		else
		{
			$this->setGlobalProperty('__coupon', null);
			$this->setCouponId(null);
		}
	}
	
	
	
	/**
	 * @return array<id => integer, code => string, valueWithTax => double, valueWithoutTax => double>
	 */
	public function getCouponData()
	{
		return $this->getGlobalProperty('__coupon');
	}
	
	/**
	 * @return array<array<id => integer, label => string, valueWithTax => double, valueWithoutTax => double>>
	 */
	public function getDiscountDataArray()
	{
		$result = $this->getGlobalProperty('__discount');
		if (!is_array($result))
		{
			$result = array();
		}
		return $result;
	}
	
	/**
	 * @return array<'label' => string, valueWithTax => string>
	 */
	public function getDiscountDataArrayForDisplay()
	{
		$result = array();
		foreach ($this->getDiscountDataArray() as $discount) 
		{
			if (f_util_StringUtils::isNotEmpty($discount['label']) && $discount['valueWithTax'] > 0)
			{
				$result[] = array('label' => $discount['label'],  
					'valueWithTax' => '-' . $this->formatPrice($discount['valueWithTax']));
			}
		}
		return $result;
	}
	
	/**
	 * @param array $discountDataArray
	 * @example array<array<id => integer, label => string, valueWithTax => double, valueWithoutTax => double>>
	 */
	public function setDiscountDataArray($discountDataArray)
	{
		$this->setGlobalProperty('__discount', $discountDataArray);
	}
	
	/**
	 * @return boolean
	 */
	public function hasDiscount()
	{
		return count($this->getDiscountDataArray()) > 0;
	}
	
	/**
	 * @return double
	 */
	public function getDiscountTotalWithTax()
	{
		$value = 0.0;
		$discounts = $this->getDiscountDataArray();
		foreach ($discounts as $discount)
		{
			$value += $discount['valueWithTax'];
		}
		return $value;
	}
	
	
	/**
	 * @return array
	 */
	public function getShippingDataArray()
	{
		$result = $this->getGlobalProperty('__shipping');
		if (!is_array($result))
		{
			$result = array();
		}
		return $result;
	}
	
	/**
	 * @param array $shippingArray
	 */
	public function setShippingDataArray($shippingArray)
	{
		$this->setGlobalProperty('__shipping', $shippingArray);
	}	
	
	/**
	 * @param shipping_persistentdocument_mode $shippingMode
	 */
	public function setShippingModeDocument($shippingMode)
	{
		$this->setShippingModeId($shippingMode->getId());
	}
	
	/**
	 * @param String $shippingModeTaxCode
	 */
	public function setShippingModeTaxCode($shippingModeTaxCode)
	{
		$this->setOrderProperty('shippingModeTaxCode', $shippingModeTaxCode);
	}
	
	/**
	 * @param Double $shippingModeTaxRate
	 */
	public function setShippingModeTaxRate($shippingModeTaxRate)
	{
		$this->setOrderProperty('shippingModeTaxRate', $shippingModeTaxRate);
	}
	
	/**
	 * @return String
	 */
	public function getShippingModeTaxCode()
	{
		return $this->getOrderProperty('shippingModeTaxCode');
	}
	
	/**
	 * @return Double
	 */
	public function getShippingModeTaxRate()
	{
		return $this->getOrderProperty('shippingModeTaxRate');
	}	
	
	/**
	 * @param payment_persistentdocument_connector $billingMode
	 */
	public function setBillingModeDocument($billingMode)
	{
		$this->setBillingModeId($billingMode->getId());
	}
	
	/**
	 * @return Double
	 */
	public function getLinesAmountWithTax()
	{
		$result = 0;
		foreach ($this->getLineArray() as $line)
		{
			$result += $line->getAmountWithTax();
		}
		return $result;
	}
	
	/**
	 * @return Double
	 */
	public function getLinesAmountWithoutTax()
	{
		$result = 0;
		foreach ($this->getLineArray() as $line)
		{
			$result += $line->getAmountWithoutTax();
		}
		
		return $result;
	}

	/**
	 * @return double
	 */
	public function getTotalTax()
	{
		return $this->getTotalAmountWithTax()-$this->getTotalAmountWithoutTax();
	}
	
	/**
	 * @return string
	 */
	public function getFormattedTotalTax()
	{
		return $this->formatPrice($this->getTotalTax());
	}
	
	/**
	 * @return string
	 */
	public function getFormattedTotalWithoutTax()
	{
		return $this->formatPrice($this->getTotalAmountWithoutTax());
	}
	
	/**
	 * @return string
	 */
	public function getFormattedTotalWithTax()
	{
		return $this->formatPrice($this->getTotalAmountWithTax());
	}
	
	/**
	 * @return boolean
	 */
	public function canBeCanceled()
	{
		$orderStatus = $this->getOrderStatus();
		return $orderStatus == order_OrderService::IN_PROGRESS;
	}
	
	/**
	 * @return order_persistentdocument_bill[]
	 */
	public function getBillsWithArchive()
	{
		return order_BillService::getInstance()->createQuery()
			->add(Restrictions::published())
			->add(Restrictions::eq('order', $this))
			->add(Restrictions::isNotNull('archive'))
			->find();
	}
	
	/**
	 * @return string
	 */
	public function getShippingMode()
	{
		$result = array();
		if (intval($this->getShippingModeId()) > 0)
		{
			$sm = DocumentHelper::getDocumentInstance($this->getShippingModeId(), 'modules_shipping/mode');
			$result[] = $sm->getLabel();
		} 
		
		$shippingDataArray = $this->getShippingDataArray();
		if (is_array($shippingDataArray))
		{
			foreach (array_keys($shippingDataArray) as $shippingModeId) 
			{
				if ($shippingModeId > 0)
				{
					$sm = DocumentHelper::getDocumentInstance($shippingModeId);
					$result[] = $sm->getLabel();
				}
			}
		} 
		return implode(', ', $result);
	}
	
	/**
	 * @example array<creditNoteId => amount>
	 */
	public function getCreditNoteDataArray()
	{
		$result = $this->getGlobalProperty('__creditnote');
		if (!is_array($result))
		{
			$result = array();
		}
		return $result;
	}
	
	/**
	 * @param array $creditNoteDataArray
	 * @example array<creditNoteId => amount>
	 */
	public function setCreditNoteDataArray($creditNoteDataArray)
	{
		$this->setGlobalProperty('__creditnote', $creditNoteDataArray);
	}
	
	/**
	 * @return boolean
	 */
	public function hasCreditNote()
	{
		return $this->getUsecreditnoteCount() > 0;
	}
	
	/**
	 * @return double
	 */
	public function getTotalCreditNoteAmount()
	{
		if (!$this->hasCreditNote())
		{
			return 0;
		}
		return array_sum($this->getCreditNoteDataArray());
	}
	
	//DEPRECTAED FUNCTIONS

	/**
	 * @return String
	 * @deprecated use getBoOrderStatusLabel or getFoOrderStatusLabel
	 */
	public function getOrderStatusLabel()
	{
		return $this->getFoOrderStatusLabel();
	}
		
	/**
	 * Get the readable order date.
	 * @return String
	 * @deprecated use getCreationdate or getUICreationdate
	 */
	public function getOrderDate()
	{
		return $this->getCreationdate();
	}
	
	/**
	 * @deprecated 
	 * @var order_persistentdocument_bill
	 */
	private $currentBill;
	
	/**
	 * @deprecated 
	 * @return order_persistentdocument_bill
	 */
	private function getCurrentBill()
	{
		if ($this->currentBill === null)
		{
			$billArray = $this->getBillArrayInverse();
			$this->currentBill = $billArray[0];
		}
		return $this->currentBill;
	}
		
	/**
	 * @deprecated 
	 * @return payment_persistentdocument_connector
	 */
	protected function getBillingModeDocument()
	{
		return DocumentHelper::getDocumentInstance($this->getBillingModeId(), 'modules_payment/connector');
	}
	
	/**
	 * @deprecated 
	 * @return String
	 */
	public function getBillingMode()
	{
		
		return $this->getBillingModeDocument()->getLabel();
	}
	
	/**
	 * @deprecated 
	 * @return String
	 */
	public function getBillingModeCodeReference()
	{
		return $this->getBillingModeDocument()->getCode();
	}
		
	/**
	 * @deprecated 
	 * @return shipping_persistentdocument_mode
	 */
	private function getShippingModeDocument()
	{
		if (intval($this->getShippingModeId()) > 0)
		{
			return DocumentHelper::getDocumentInstance($this->getShippingModeId(), 'modules_shipping/mode');
		}
		return null;
	}
	
	/**
	 * @var order_persistentdocument_expedition
	 */
	private $currentExpedition;
	
	/**
	 * @deprecated 
	 * @return order_persistentdocument_expedition
	 */
	private function getCurrentExpedition()
	{
		if ($this->currentExpedition === null)
		{
			$expeditionArray = $this->getExpeditionArrayInverse();
			$this->currentExpedition = count($expeditionArray) > 0 ? $expeditionArray[0] : false;
		}
		return $this->currentExpedition ? $this->currentExpedition : null;
	}	
		
	/**
	 * @deprecated 
	 */
	public function getShippingModeCodeReference()
	{
		$sD = $this->getShippingModeDocument();
		return $sD ? $sD->getCodeReference() : null;
	}	

	/**
	 * @deprecated 
	 */
	public function getPackageTrackingNumber()
	{
		$exp = $this->getCurrentExpedition();
		return $exp ? $exp->getTrackingNumber() : null;
	}
	
	/**
	 * @deprecated 
	 */
	public function getPackageTrackingURL()
	{
		$exp = $this->getCurrentExpedition();
		return $exp ? $exp->getTrackingURL() : null;
	}
}