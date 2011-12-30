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
		return LocaleService::getInstance()->transBO('m.order.frontoffice.status.' . $this->getOrderStatus(), array('ucf', 'html'));
	}

	/**
	 * @return String
	 */
	public function getFoOrderStatusLabel()
	{
		return LocaleService::getInstance()->transFO('m.order.frontoffice.status.' . $this->getOrderStatus(), array('ucf', 'html'));
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
		$taxRates = $this->getTaxRates();
		if (count($taxRates))
		{
			foreach ($taxRates as $rate => $value)
			{
				$taxInfoArray[$rate] = array('formattedTaxRate' => $rate, 'taxAmount' => $value);
			}
			return $taxInfoArray;
		}
		
		//Old Tax evaluation
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
		$taxCode = $this->getOrderProperty('shippingModeTaxCode');
		if ($taxCode)
		{
			if (!isset($taxInfoArray[$taxCode]))
			{
				$taxInfoArray[$taxCode] = array('taxAmount' => 0, 'formattedTaxRate' => catalog_PriceHelper::formatTaxRate($this->getShippingModeTaxRate()));
			}
			$taxInfoArray[$taxCode]['taxAmount'] += ($this->getShippingFeesWithTax() - $this->getShippingFeesWithoutTax());
		}
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
	 * @param array $couponData For example: array<id => integer, code => string, valueWithTax => double, valueWithoutTax => double>
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
	 * @return array<'label' => string, valueWithTax => string, valueWithoutTax => string>
	 */
	public function getDiscountDataArrayForDisplay()
	{
		$result = array();
		foreach ($this->getDiscountDataArray() as $discount) 
		{
			if (f_util_StringUtils::isNotEmpty($discount['label']) && (abs($discount['valueWithTax']) > 0.01))
			{
				$result[] = array('label' => $discount['label'],  
					'valueWithTax' => $this->formatPrice(-$discount['valueWithTax']),
					'valueWithoutTax' => $this->formatPrice(-$discount['valueWithoutTax'])
				);
			}
		}
		return $result;
	}
	
	/**
	 * @param array $discountDataArray For example: array<array<id => integer, label => string, valueWithTax => double, valueWithoutTax => double>> 
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
		foreach ($this->getDiscountDataArray() as $discount)
		{
			$value += $discount['valueWithTax'];
		}
		return $value;
	}
	
	/**
	 * @param array $feesDataArray For example array<array<id => integer, label => string, valueWithTax => double, valueWithoutTax => double>> 
	 */
	public function setFeesDataArray($feesDataArray)
	{
		$this->setGlobalProperty('__fees', $feesDataArray);
	}
	
	/**
	 * @return array<array<id => integer, label => string, valueWithTax => double, valueWithoutTax => double>>
	 */
	public function getFeesDataArray()
	{
		$result = $this->getGlobalProperty('__fees');
		if (!is_array($result))
		{
			//TODO Default Sheaping Info
			$label = LocaleService::getInstance()->transFO('m.order.frontoffice.shipping-fees', array('ucf', 'lab'));
			$result = array(array('id' => 0, 'label' => $label,  
				'valueWithTax' => $this->getShippingFeesWithTax(), 
				'valueWithoutTax' => $this->getShippingFeesWithoutTax()));
		}
		return $result;
	}

	/**
	 * @return double
	 */
	public function getFeesTotalWithTax()
	{
		$value = 0.0;
		foreach ($this->getFeesDataArray() as $fees)
		{
			$value += $fees['valueWithTax'];
		}
		return $value;
	}

	/**
	 * @return double
	 */
	public function getFeesTotalWithoutTax()
	{
		$value = 0.0;
		foreach ($this->getFeesDataArray() as $fees)
		{
			$value += $fees['valueWithoutTax'];
		}
		return $value;
	}
	
	/**
	 * @return array<'label' => string, valueWithTax => string, valueWithoutTax => string>
	 */
	public function getFeesDataArrayForDisplay()
	{
		$result = array();
		foreach ($this->getFeesDataArray() as $fees) 
		{
			if (f_util_StringUtils::isNotEmpty($fees['label']) && abs($fees['valueWithTax']) > 0.01)
			{
				$result[] = array('label' => $fees['label'],  
					'valueWithTax' => $this->formatPrice($fees['valueWithTax']),
					'valueWithoutTax' => $this->formatPrice($fees['valueWithoutTax']));
			}
		}
		return $result;
	}
	
	/**
	 * @param array $taxDataArray For example: array<rateFormated => value> 
	 */
	public function setTaxDataArray($taxDataArray)
	{
		$this->setGlobalProperty('__tax', $taxDataArray);
	}
	
	/**
	 * @return  array<rateFormated => value>
	 */
	public function getTaxRates()
	{
		$result = $this->getGlobalProperty('__tax');
		if (!is_array($result))
		{
			$result = array();
		}
		return $result;		
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
		if (is_array($shippingArray) && count($shippingArray) > 0)
		{
			$modeIds = array_keys($shippingArray);
			$modes = shipping_ModeService::getInstance()->createQuery()->add(Restrictions::in('id', $modeIds))->find();
			foreach ($modes as $mode) 
			{
				$shippingArray[$mode->getId()]['mode'] = array('label' => $mode->getLabel(), 'code' => $mode->getCode(), 'id' => $mode->getId());
			}
		}
		
		$this->setGlobalProperty('__shipping', $shippingArray);
	}	
	
	/**
	 * @param shipping_persistentdocument_mode $shippingMode
	 */
	public function setShippingModeDocument($shippingMode)
	{
		if ($shippingMode instanceof shipping_persistentdocument_mode)
		{
			$this->setShippingModeId($shippingMode->getId());
			$this->setOrderProperty('shippingModeLabel', $shippingMode->getLabel());
			$this->setOrderProperty('shippingModeCode', $shippingMode->getCode());
		}
		else 
		{
			$this->setShippingModeId(null);
			$this->setOrderProperty('shippingModeLabel', null);
			$this->setOrderProperty('shippingModeCode', null);
		}
	}
	
	/**
	 * @return shipping_persistentdocument_mode || null
	 */
	protected function getShippingModeDocument()
	{
		if (intval($this->getShippingModeId()) > 0)
		{
			return shipping_ModeService::getInstance()->createQuery()->add(Restrictions::eq('id', $this->getShippingModeId()))->findUnique();
		}
		return null;
	}
	
	/**
	 * @return string
	 */
	public function getShippingModeLabel()
	{
		$result = array();
		$label = $this->getOrderProperty('shippingModeLabel');
		if ($label !== null)
		{
			$result[] = $label;
		}
		elseif (intval($this->getShippingModeId()) > 0)
		{
			$shippingMode = shipping_ModeService::getInstance()->createQuery()->add(Restrictions::eq('id', $this->getShippingModeId()))->findUnique();
			if ($shippingMode) {$result[] = $shippingMode->getLabel();}
		}

		$shippingDataArray = $this->getShippingDataArray();
		if (is_array($shippingDataArray))
		{
			foreach ($shippingDataArray as $shippingModeId => $data) 
			{
				if ($shippingModeId > 0)
				{
					if (isset($data['mode']))
					{
						$result[] = $data['mode']['label'];
					}
					else
					{
						$shippingMode = shipping_ModeService::getInstance()->createQuery()->add(Restrictions::eq('id', $shippingModeId))->findUnique();
						if ($shippingMode) {$result[] = $shippingMode->getLabel();}
					}
				}
			}
		} 
		return implode(', ', $result);		
	}
	
	/**
	 * @return string
	 */
	public function getShippingModeCode()
	{
		$result = array();
		$label = $this->getOrderProperty('shippingModeCode');
		if ($label !== null)
		{
			$result[] = $label;
		}
		elseif (intval($this->getShippingModeId()) > 0)
		{
			$shippingMode = shipping_ModeService::getInstance()->createQuery()->add(Restrictions::eq('id', $this->getShippingModeId()))->findUnique();
			if ($shippingMode) {$result[] = $shippingMode->getCode();}
		}

		
		$shippingDataArray = $this->getShippingDataArray();
		if (is_array($shippingDataArray))
		{
			foreach ($shippingDataArray as $shippingModeId => $data) 
			{
				if ($shippingModeId > 0)
				{
					if (isset($data['mode']))
					{
						$result[] = $data['mode']['code'];
					}
					else
					{
						$shippingMode = shipping_ModeService::getInstance()->createQuery()->add(Restrictions::eq('id', $shippingModeId))->findUnique();
						if ($shippingMode) {$result[] = $shippingMode->getCode();}
					}
				}
			}
		} 
		return implode(', ', $result);
	}
	
	/**
	 * @param payment_persistentdocument_connector $billingMode
	 */
	public function setBillingModeDocument($billingMode)
	{
		if ($billingMode instanceof payment_persistentdocument_connector)
		{
			$this->setBillingModeId($billingMode->getId());
			$this->setOrderProperty('paymentConnectorLabel', $billingMode->getLabel());
			$this->setOrderProperty('paymentConnectorCode', $billingMode->getCode());
		}
		else
		{
			$this->setBillingModeId(null);
			$this->setOrderProperty('paymentConnectorLabel', null);
			$this->setOrderProperty('paymentConnectorCode', null);			
		}
	}
	
	/**
	 * @return payment_persistentdocument_connector || null
	 */
	protected function getBillingModeDocument()
	{
		if (intval($this->getBillingModeId()) > 0)
		{
			return payment_ConnectorService::getInstance()->createQuery()->add(Restrictions::eq('id', $this->getBillingModeId()))->findUnique();
		}
		return null;
	}
	
	/**
	 * @return string
	 */
	public function getPaymentConnectorLabel()
	{
		$label = $this->getOrderProperty('paymentConnectorLabel');
		if ($label !== null)
		{
			return $label;
		}
		$connector = $this->getBillingModeDocument();
		return ($connector !== null) ? $connector->getLabel() : null;
	}

	/**
	 * @return string
	 */
	public function getPaymentConnectorCode()
	{
		$label = $this->getOrderProperty('paymentConnectorCode');
		if ($label !== null)
		{
			return $label;
		}
		$connector = $this->getBillingModeDocument();
		return ($connector !== null) ? $connector->getCode() : null;
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
		$value = 0.0;
		foreach ($this->getTotalTaxInfoArray() as $data) 
		{
			$value += $data['taxAmount'];
		}
		return $value;
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
	 * @return array For example: array<creditNoteId => amount>
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
	 * @param array $creditNoteDataArray For example: array<creditNoteId => amount> 
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
	
	/**
	 * @return double
	 */
	public function getTotalAmountWithTaxAndCreditNotes()
	{
		return $this->getTotalAmountWithTax() + $this->getTotalCreditNoteAmount();
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
	 * @deprecated  use getPaymentConnectorLabel
	 * @return String
	 */
	public function getBillingMode()
	{
		return $this->getPaymentConnectorLabel();
	}
	
	/**
	 * @deprecated use getPaymentConnectorCode
	 * @return String
	 */
	public function getBillingModeCodeReference()
	{
		return $this->getPaymentConnectorCode();
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
	
	/**
	 * @deprecated use getShippingModeLabel
	 */
	public function getShippingMode()
	{
		return $this->getShippingModeLabel();
	}
	
	/**
	 * @deprecated
	 */
	public function setShippingModeTaxCode($shippingModeTaxCode)
	{
		$this->setOrderProperty('shippingModeTaxCode', $shippingModeTaxCode);
	}
	
	/**
	 * @deprecated
	 */
	public function setShippingModeTaxRate($shippingModeTaxRate)
	{
		$this->setOrderProperty('shippingModeTaxRate', $shippingModeTaxRate);
	}
	
	/**
	 * @deprecated
	 */
	public function getShippingModeTaxCode()
	{
		return $this->getOrderProperty('shippingModeTaxCode');
	}
	
	/**
	 * @deprecated
	 */
	public function getShippingModeTaxRate()
	{
		return $this->getOrderProperty('shippingModeTaxRate');
	}	
}