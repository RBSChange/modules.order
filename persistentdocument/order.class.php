<?php
/**
 * order_persistentdocument_order
 * @package modules.order
 */
class order_persistentdocument_order extends order_persistentdocument_orderbase
{
	/**
	 * @return string
	 */
	public function getBoOrderStatusLabel()
	{
		return LocaleService::getInstance()->transBO('m.order.frontoffice.status.' . $this->getOrderStatus(), array('ucf', 'html'));
	}

	/**
	 * @return string
	 */
	public function getFoOrderStatusLabel()
	{
		return LocaleService::getInstance()->transFO('m.order.frontoffice.status.' . $this->getOrderStatus(), array('ucf', 'html'));
	}
	
	/**
	 * @return string
	 */
	public function getLogisticStatusLabel()
	{
		return LocaleService::getInstance()->transFO('m.order.fo.order-' . order_ExpeditionService::getInstance()->evaluateGlobalStatusForOrder($this));
	}
	
	/**
	 * @param float $value
	 * @return string
	 */
	public function formatPrice($value)
	{
		$rc = RequestContext::getInstance();
		$lang = $rc->getMode() == RequestContext::BACKOFFICE_MODE ? $rc->getUILang() : $rc->getLang();
		return catalog_PriceFormatter::getInstance()->format($value,  $this->getCurrencyCode(), $lang, $this->getCurrencyPosition());
	}
	
	/**
	 * @return string
	 */
	public function getCurrencyPosition()
	{
		return $this->getGlobalProperty('currencyPosition');
	}
	
	/**
	 * @param string $position
	 */
	public function setCurrencyPosition($position)
	{
		return $this->setGlobalProperty('currencyPosition', $position);
	}
	
	/**
	 * @deprecated
	 * @return string
	 */
	public function getPriceFormat()
	{
		return $this->getGlobalProperty('priceFormat');
	}
	
	/**
	 * @deprecated
	 * @param string $priceFormat
	 */
	public function setPriceFormat($priceFormat)
	{
		return $this->setGlobalProperty('priceFormat', $priceFormat);
	}
	
	/**
	 * @return string
	 */
	public function getTaxZone()
	{
		return $this->getGlobalProperty('taxZone');
	}
	
	/**
	 * @param string $taxZone
	 */
	public function setTaxZone($taxZone)
	{
		return $this->setGlobalProperty('taxZone', $taxZone);
	}	
		
	/**
	 * @return Array<String, Array<String, String>>
	 */
	public function getTotalTaxInfoArray()
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
		
		//Deprecated remove in 4.0
		return $this->getSubTotalTaxInfoArray();
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
	
	/**
	 * @var array
	 */
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
		return catalog_persistentdocument_shop::getInstanceById($this->getShopId());
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
		return website_persistentdocument_website::getInstanceById($this->getWebsiteId());
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
	public function getShippingModeLabelAsHtml()
	{
		return f_util_HtmlUtils::textToHtml($this->getShippingModeLabel());
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
		return $this->getDocumentService()->canBeCanceled($this);
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
	
	/**
	 * @return customer_persistentdocument_address
	 */
	public function getShippingAddress()
	{
		$address = parent::getShippingAddress();
		if ($address === null)
		{
			$data = f_util_ArrayUtils::firstElement($this->getShippingDataArray());
			$mId = $data['filter']['modeId'];
			$addressId = $this->getAddressIdByModeId($mId);
			if (intval($addressId))
			{
				$address = DocumentHelper::getDocumentInstanceIfExists($addressId);
			}
		}
		return ($address !== null) ? $address : $this->getBillingAddress();
	}
		
	/**
	 * @param array $addressIdByModeIdArray
	 * @example array<modeId => addressId>
	 */
	private function setAddressIdByModeIdArray($addressIdByModeIdArray)
	{
		$this->setGlobalProperty('__addressIdByModeIdArray', $addressIdByModeIdArray);
	}
	
	/**
	 * @param integer $modeId
	 * @return integer
	 */
	public function getAddressIdByModeId($modeId)
	{
		$array = $this->getGlobalProperty('__addressIdByModeIdArray');
		if (is_array($array) && isset($array[$modeId]))
		{
			return $array[$modeId];
		}
		return null;
	}

	/**
	 * @param integer $modeId
	 * @param integer $addressId
	 */
	public function setAddressIdByModeId($modeId, $addressId)
	{
		$array = $this->getGlobalProperty('__addressIdByModeIdArray');
		if ($addressId !== null)
		{
			if (!is_array($array)) { $array = array(); }
			$array[$modeId] = $addressId;
		}
		elseif (is_array($array) && isset($array[$modeId]))
		{
			unset($array[$modeId]);
		}
		$this->setGlobalProperty('__addressIdByModeIdArray', $array);
	}
	
	/**
	 * @return float
	 */
	public function getTotalProductCount()
	{
		$count = 0;
		foreach ($this->getLineArray() as $line)
		{
			/* @var $line order_persistentdocument_orderline */
			$count += $line->getQuantity();
		}
		return $count;
	}
	
	//DEPRECTAED FUNCTIONS

	/**
	 * @deprecated use getBoOrderStatusLabel or getFoOrderStatusLabel
	 */
	public function getOrderStatusLabel()
	{
		return $this->getFoOrderStatusLabel();
	}
		
	/**
	 * @deprecated use getCreationdate or getUICreationdate
	 */
	public function getOrderDate()
	{
		return $this->getCreationdate();
	}
	
	/**
	 * @deprecated 
	 */
	private $currentBill;
	
	/**
	 * @deprecated 
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
	 */
	public function getBillingMode()
	{
		return $this->getPaymentConnectorLabel();
	}
	
	/**
	 * @deprecated use getPaymentConnectorCode
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
	
	/**
	 * @deprecated
	 */
	public function getSubTotalTaxInfoArray()
	{
		$taxInfoArray = array();
		$ts = catalog_TaxService::getInstance();
		foreach ($this->getLineArray() as $line)
		{
			/* @var $line order_persistentdocument_orderline */
			$formatedTaxRate = $ts->formatRate($line->getTaxRate());
			if (!isset($taxInfoArray[$formatedTaxRate]))
			{
				$taxInfoArray[$formatedTaxRate] = array('formattedTaxRate' => $formatedTaxRate, 'taxAmount' => 0);
			}
			$taxInfoArray[$formatedTaxRate]['taxAmount'] += $line->getTaxAmount();
		}
	
		if ($this->getShippingModeTaxCode())
		{
			$formatedTaxRate = $ts->formatRate($this->getShippingModeTaxRate());
			if (!isset($taxInfoArray[$formatedTaxRate]))
			{
				$taxInfoArray[$formatedTaxRate] = array('taxAmount' => 0, 'formattedTaxRate' => $formatedTaxRate);
			}
			$taxInfoArray[$formatedTaxRate]['taxAmount'] += ($this->getShippingFeesWithTax() - $this->getShippingFeesWithoutTax());
		}
		return $taxInfoArray;
	}
}