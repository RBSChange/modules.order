<?php
/**
 * order_persistentdocument_order
 * @package modules.order
 */
class order_persistentdocument_order extends order_persistentdocument_orderbase implements payment_Order
{
	/**
	 * @see f_persistentdocument_PersistentDocumentImpl::addTreeAttributes()
	 *
	 * @param string $moduleName
	 * @param string $treeType
	 * @param unknown_type $nodeAttributes
	 */
	protected function addTreeAttributes($moduleName, $treeType, &$nodeAttributes)
	{
		$nodeAttributes['label'] = $this->getOrderNumber();
		
		if ($treeType === 'wtree' || $treeType === 'wlist')
		{
			$nodeAttributes['orderStatus'] = $this->getOrderStatus();
			if ($treeType === 'wlist')
			{
				$nodeAttributes['date'] = date_DateFormat::format($this->getUICreationdate());
				$nodeAttributes['orderStatusLabel'] = $this->getOrderStatusLabel();
				$nodeAttributes['formattedTotalAmountWithTax'] = $this->formatPrice($this->getTotalAmountWithTax());
				$user = $this->getCustomer()->getUser();
				$nodeAttributes['customer'] = $user->getFullName() . ' (' . $user->getEmail() . ')';
				$nodeAttributes['canBeCanceled'] = $this->canBeCanceled();
			}
		}
	}
	
	/**
	 * @param string[] $propertiesNames
	 * @param array $formProperties
	 */
	public function addFormProperties($propertiesNames, &$formProperties)
	{
		$infos = $this->getDocumentService()->getInfo($this);
		foreach ($infos as $key => $value)
		{
			$formProperties[$key] = $value;
		}
	}

	/**
	 * @return String
	 */
	public function getOrderStatusLabel()
	{
		if (!is_null($this->getOrderStatus()))
		{
			return order_OrderService::getInstance()->getStatusLabel($this->getOrderStatus());
		}
		else
		{
			return null;
		}
	}
	
	/**
	 * @return String
	 */
	public function getBoOrderStatusLabel()
	{
		if (!is_null($this->getOrderStatus()))
		{
			return order_OrderService::getInstance()->getBoStatusLabel($this->getOrderStatus());
		}
		else
		{
			return null;
		}
	}
	
	/**
	 * @return string
	 */
	public function getBoStatusAsJSON()
	{
		//PAYMENT_SUCCESS, PAYMENT_WAITING, PAYMENT_FAILED, PAYMENT_DELAYED, CANCELED, SHIPPED
		$status = array(
			'order' => $this->getOrderStatus(),
			'payment' => $this->getPaymentStatus(),
			'shipping' => $this->getShippingStatus()
		);
		
		return JsonService::getInstance()->encode($status);
	}
	
	
	/**
	 * @param string $json
	 */
	public function setBoStatusAsJSON($json)
	{
		if (f_util_StringUtils::isNotEmpty($json))
		{
			$status = JsonService::getInstance()->decode($json);
			if (isset($status['order']))
			{
				$this->setOrderStatus($status['order']);
			}
			if (isset($status['payment']))
			{
				$this->setPaymentStatus($status['payment']);
			}
			if (isset($status['shipping']))
			{
				$this->setShippingStatus($status['shipping']);
			}
		}
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
		$properties = $this->setOrderProperty($propertyName, $value, $this->getGlobalProperties());
		$this->setGlobalProperties(serialize($properties));
	}
	
	/**
	 * @param String $propertyName
	 * @return Mixed
	 */
	public function getGlobalProperty($propertyName)
	{
		return $this->getOrderProperty($propertyName, $this->getGlobalProperties());
	}
	

	/**
	 * @return Array<Mixed>
	 */
	public function getBankResponse()
	{
		return $this->getBillingProperty('bankresponse');
	}
	
	/**
	 * @param Array<Mixed>
	 */
	public function setBankResponse($value)
	{
		$this->setBillingProperty('bankresponse', $value);
	}
	
	/**
	 * @return string
	 */
	public function getBankTransactionId()
	{
		return $this->getBillingProperty('banktrsid');
	}
	
	/**
	 * @param string $value
	 */
	public function setBankTransactionId($value)
	{
		$this->setBillingProperty('banktrsid', $value);
	}
	
	/**
	 * @return string
	 */
	public function getBankTransaction()
	{
		return $this->getBillingProperty('banktrs');
	}
	
	/**
	 * @return string
	 */
	public function getBankTransactionAsHtml()
	{
		return f_util_HtmlUtils::textToHtml($this->getBankTransaction());
	}
	
	/**
	 * @param string $value
	 */
	public function setBankTransaction($value)
	{
		$this->setBillingProperty('banktrs', $value);
	}
	
	/**
	 * @return String
	 */
	public function getPackageTrackingNumber()
	{
		return $this->getShippingProperty('packageTrackingNumber');
	}
	
	/**
	 * @param String $value
	 */
	public function setPackageTrackingNumber($value)
	{
		$this->setShippingProperty('packageTrackingNumber', $value);
	}
	
	/**
	 * @return String
	 */
	public function getPackageTrackingURL()
	{
		$url = $this->getShippingProperty('packageTrackingURL');
		if (!is_null($number = $this->getPackageTrackingNumber()))
		{
			$url = str_replace('{NumeroColis}', $number, $url);
		}
		return $url;
	}
	
	/**
	 * @param String $value
	 */
	public function setPackageTrackingURL($value)
	{
		$this->setShippingProperty('packageTrackingURL', $value);
	}
	
	/**
	 * @param String $propertyName
	 * @param String $value
	 */
	protected function setShippingProperty($propertyName, $value)
	{
		$properties = $this->setOrderProperty($propertyName, $value, $this->getShippingProperties());
		$this->setShippingProperties(serialize($properties));
	}
	
	/**
	 * @param String $propertyName
	 * @param String $value
	 */
	protected function setBillingProperty($propertyName, $value)
	{
		$properties = $this->setOrderProperty($propertyName, $value, $this->getBillingProperties());
		$this->setBillingProperties(serialize($properties));
	}
	
	/**
	 * @param String $propertyName
	 * @return mixed
	 */
	protected function getShippingProperty($propertyName)
	{
		return $this->getOrderProperty($propertyName, $this->getShippingProperties());
	}
	
	/**
	 * @param String $propertyName
	 * @return mixed
	 */
	protected function getBillingProperty($propertyName)
	{
		return $this->getOrderProperty($propertyName, $this->getBillingProperties());
	}
	
	/**
	 * @param String $name
	 * @param Array $properties
	 * @return mixed
	 */
	private function getOrderProperty($propertyName, $properties)
	{
		if (!is_null($properties))
		{
			$properties = unserialize($properties);
			if (isset($properties[$propertyName]))
			{
				$value = $properties[$propertyName];
			}
			else
			{
				$value = null;
			}
		}
		else
		{
			$value = null;
		}
		return $value;
	}
	
	/**
	 * @param String $propertyName
	 * @param mixed $value
	 * @param Array $properties
	 */
	private function setOrderProperty($propertyName, $value, $properties)
	{
		if (!is_null($properties))
		{
			$properties = unserialize($properties);
		}
		else
		{
			$properties = array();
		}
		if (is_null($value))
		{
			unset($properties[$propertyName]);
		}
		else
		{
			$properties[$propertyName] = $value;
		}
		return $properties;
	}
	
	/**
	 * @var Boolean
	 */
	private $notificationPending = false;
	
	/**
	 * @param Boolean $bool
	 * @return Boolean
	 */
	public function hasNotificationPending($bool = null)
	{
		if (!is_null($bool) && is_bool($bool))
		{
			$this->notificationPending = $bool;
		}
		return $this->notificationPending;
	}
	
	/**
	 * @var Boolean
	 */
	private $orderStatusChanged = false;
	
	/**
	 * @param Boolean $bool
	 * @return Boolean
	 */
	public function hasOrderStatusChanged($bool = null)
	{
		if (!is_null($bool) && is_bool($bool))
		{
			$this->orderStatusChanged = $bool;
		}
		return $this->orderStatusChanged;
	}
	
	/**
	 * @return catalog_persistentdocument_shop
	 */
	public function getShop()
	{
		return DocumentHelper::getDocumentInstance($this->getShopId(), 'modules_catalog/shop');
	}
	
	/**
	 * @return website_persistentdocument_website
	 */
	public function getWebsite()
	{
		return DocumentHelper::getDocumentInstance($this->getWebsiteId(), 'modules_website/website');
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
	 * @param array $couponData
	 * @example array<id => integer, code => string, valueWithTax => double, valueWithoutTax => double>
	 */
	public function setCouponData($couponData)
	{
		if (is_array($couponData))
		{
			$this->setGlobalProperty('__coupon', $couponData);
			$this->setCouponId($couponData['id']);
			$this->setCouponValueWithTax($couponData['valueWithTax']);
		}
		else
		{
			$this->setGlobalProperty('__coupon', null);
			$this->setCouponId(null);
			$this->setCouponValueWithTax(null);
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
	 * @param shipping_persistentdocument_mode $shippingMode
	 */
	public function setShippingModeDocument($shippingMode)
	{
		$this->setShippingMode($shippingMode->getLabel());
		$this->setShippingModeId($shippingMode->getId());
		$this->setShippingModeCodeReference($shippingMode->getCodeReference());
		// If the shipping mode provides a package tracking URL, we must store it
		// into the shipping properties of the order.
		if (!is_null($trackingUrl = $shippingMode->getTrackingUrl()))
		{
			$this->setPackageTrackingURL($trackingUrl);
		}
	}
	
	/**
	 * @param payment_persistentdocument_connector $billingMode
	 */
	public function setBillingModeDocument($billingMode)
	{
		$this->setBillingMode($billingMode->getLabel());
		$this->setBillingModeId($billingMode->getId());
		$this->setBillingModeCodeReference($billingMode->getCode());
	}
	
	// MOVED Properties 
	

	/**
	 * @return String
	 */
	public function getBillingMode()
	{
		return $this->getBillingProperty('billingMode');
	}
	
	/**
	 * @return String
	 */
	public function getBillingModeCodeReference()
	{
		return $this->getBillingProperty('billingModeCodeReference');
	}
	
	/**
	 * @param String $billingMode
	 */
	public function setBillingMode($billingMode)
	{
		$this->setBillingProperty('billingMode', $billingMode);
	}
	
	/**
	 * @param String $billingModeCodeReference
	 */
	public function setBillingModeCodeReference($billingModeCodeReference)
	{
		$this->setBillingProperty('billingModeCodeReference', $billingModeCodeReference);
	}
	
	/**
	 * @return String
	 */
	public function getShippingMode()
	{
		return $this->getShippingProperty('shippingMode');
	}
	
	/**
	 * @return String
	 */
	public function getShippingModeCodeReference()
	{
		return $this->getShippingProperty('shippingModeCodeReference');
	}
	
	/**
	 * @return String
	 */
	public function getShippingModeTaxCode()
	{
		return $this->getShippingProperty('shippingModeTaxCode');
	}
	
	/**
	 * @return Double
	 */
	public function getShippingModeTaxRate()
	{
		return $this->getShippingProperty('shippingModeTaxRate');
	}
	
	/**
	 * @return string
	 */
	public function getShippingStatus()
	{
		return $this->getShippingProperty('shippingStatus');
	}

	
	/**
	 * @param String $shippingMode
	 */
	public function setShippingMode($shippingMode)
	{
		$this->setShippingProperty('shippingMode', $shippingMode);
	}
	
	/**
	 * @param String $shippingModeCodeReference
	 */
	public function setShippingModeCodeReference($shippingModeCodeReference)
	{
		$this->setShippingProperty('shippingModeCodeReference', $shippingModeCodeReference);
	}
	
	/**
	 * @param String $shippingModeTaxCode
	 */
	public function setShippingModeTaxCode($shippingModeTaxCode)
	{
		$this->setShippingProperty('shippingModeTaxCode', $shippingModeTaxCode);
	}
	
	/**
	 * @param Double $shippingModeTaxRate
	 */
	public function setShippingModeTaxRate($shippingModeTaxRate)
	{
		$this->setShippingProperty('shippingModeTaxRate', $shippingModeTaxRate);
	}
	
	/**
	 * @param string $status
	 */
	public function setShippingStatus($status)
	{
		$this->setShippingProperty('shippingStatus', $status);
		$this->refreshOrderStatus();
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
	
	// REMOVED METHOD
	/*
	 * billingModeTaxCode
	 * billingFeesWithTax
	 * billingFeesWithoutTax
	 * 
	 * amountWithModificatorsWithTax
	 * amountWithModificatorsWithoutTax
	 * 
	 * amountWithTax
	 * amountWithoutTax
	 * discountLine
	 * currencySymbol
	 * synchroStatus
	 */
	
	/** payment_Order **/
	
	/**
	 * @see payment_Order::getPaymentId()
	 * @return integer
	 */
	function getPaymentId()
	{
		return $this->getId();
	}
	
	/**
	 * @see payment_Order::getPaymentReference()
	 * @return string
	 */
	function getPaymentReference()
	{
		return $this->getOrderNumber();
	}
	
	/**
	 * @see payment_Order::getPaymentAmount()
	 * @return double
	 */
	function getPaymentAmount()
	{
		return $this->getTotalAmountWithTax();
	}
	
	/**
	 * @see payment_Order::getPaymentUser()
	 * @return users_persistentdocument_frontenduser
	 */
	function getPaymentUser()
	{
		return $this->getCustomer()->getUser();
	}
	
	/**
	 * @see payment_Order::getPaymentBillingAddress()
	 *
	 * @return customer_persistentdocument_address
	 */
	function getPaymentBillingAddress()
	{
		return $this->getBillingAddress();
	}
	
	/**
	 * @see payment_Order::getPaymentShippingAddress()
	 *
	 * @return customer_persistentdocument_address
	 */
	function getPaymentShippingAddress()
	{
		return $this->getShippingAddress();
	}
	
	/**
	 * @see payment_Order::getPaymentCurrency()
	 * @return string "EUR", "GBP", "CHF"
	 */
	function getPaymentCurrency()
	{
		return $this->getCurrencyCode();
	}
	
	/**
	 * @see payment_Order::getPaymentConnector()
	 *
	 * @return payment_persistentdocument_connector
	 */
	function getPaymentConnector()
	{
		$id = $this->getBillingModeId();
		if ($id)
		{
			return DocumentHelper::getDocumentInstance($id);
		}
		return null;
	}
	
	/**
	 * @see payment_Order::getPaymentLang()
	 *
	 * @return string
	 */
	function getPaymentLang()
	{
		return $this->getLang();
	}
	
	/**
	 * @see payment_Order::getPaymentDate()
	 * @return string 
	 */
	function getPaymentDate()
	{
		return parent::getPaymentDate();
	}
	
	/**
	 * @see payment_Order::getPaymentResponse()
	 *
	 * @return string
	 */
	function getPaymentResponse()
	{
		return $this->getBillingProperty('bankresponse');
	}
	
	/**
	 * @see payment_Order::getPaymentStatus()
	 *
	 * @return string
	 */
	function getPaymentStatus()
	{
		return $this->getBillingProperty('bankstatus');
	}
	
	/**
	 * @see payment_Order::getPaymentTransactionId()
	 *
	 * @return string
	 */
	function getPaymentTransactionId()
	{
		return $this->getBillingProperty('banktrsid');
	}
	
	/**
	 * @see payment_Order::getPaymentTransactionText()
	 * @return string
	 */
	function getPaymentTransactionText()
	{
		return $this->getBillingProperty('banktrs');
	}
	
	/**
	 * @see payment_Order::setPaymentResponse()
	 *
	 * @param string $response
	 */
	function setPaymentResponse($response)
	{
		$this->setBillingProperty('bankresponse', $response);
	}
	
	/**
	 * @see payment_Order::setPaymentStatus()
	 *
	 * @param string $status
	 */
	function setPaymentStatus($status)
	{
		$this->setBillingProperty('bankstatus', $status);
		$this->refreshOrderStatus();
	}
	
	/**
	 * @see payment_Order::setPaymentTransactionId()
	 *
	 * @param string $transactionId
	 */
	function setPaymentTransactionId($transactionId)
	{
		$this->setBillingProperty('banktrsid', $transactionId);
	}
	
	/**
	 * @see payment_Order::setPaymentTransactionText()
	 *
	 * @param string $transactionText
	 */
	function setPaymentTransactionText($transactionText)
	{
		$this->setBillingProperty('banktrs', $transactionText);
	}
	
	/**
	 * @see payment_Order::getPaymentCallbackURL()
	 * @return string
	 */
	function getPaymentCallbackURL()
	{
		$orderProcess = order_OrderProcess::getInstance();
		return $orderProcess->getStepURL($orderProcess->getLastStep());
	}
	
	public function getBillURL()
	{
		if ($this->getBill() !== null)
		{
			return LinkHelper::getDocumentUrl($this->getBill());
		}
		return null;
	}
	
	public function getBillBoURL()
	{
		if (!$this->getDocumentService()->generateBillIsActive())
		{
			return "-2";
		}
		if (!in_array($this->getOrderStatus(), array(order_OrderService::PAYMENT_SUCCESS, order_OrderService::SHIPPED)))
		{
			return "-1";
		}
		if ($this->getBill() !== null)
		{
			$actionUrl = LinkHelper::getUIActionLink("media", "BoDisplay");
			$actionUrl->setQueryParameter('cmpref', $this->getBill()->getId());
			return $actionUrl->getUrl();
		}
		return "";
	}
	
	public function getTotalTax()
	{
		return $this->getTotalAmountWithTax()-$this->getTotalAmountWithoutTax();
	}

	/**
	 * @return string
	 */
	public function refreshOrderStatus()
	{
		if ($this->getOrderStatus() == order_OrderService::CANCELED)
		{
			return;
		}
		else if ($this->getShippingStatus() == order_OrderService::SHIPPED)
		{
			$this->setOrderStatus(order_OrderService::SHIPPED);
		}
		else
		{
			$this->setOrderStatus($this->getPaymentStatus());
		}
	}
	
	/**
	 * @return boolean
	 */
	public function canBeCanceled()
	{
		$orderStatus = $this->getOrderStatus();
		return $orderStatus != order_OrderService::CANCELED && $orderStatus != order_OrderService::SHIPPED;
	}
}