<?php
/**
 * order_CartInfo
 * @package modules.order
 */
class order_CartInfo
{
	
	//SHOP INFORMATION
	
	/**
	 * @var Integer
	 */
	private $shopId = null;
	
	/**
	 * @return Integer
	 */
	public function getShopId()
	{
		return $this->shopId;
	}

	/**
	 * @param Integer $shopId
	 */
	public function setShopId($shopId)
	{
		$this->shopId = $shopId;
	}

	/**
	 * @return catalog_persistentdocument_shop
	 */
	function getShop()
	{
		return DocumentHelper::getDocumentInstance($this->shopId, "modules_catalog/shop");
	}
	
	/**
	 * @param catalog_persistentdocument_shop $shop
	 */
	function setShop($shop)
	{
		if (!$shop instanceof catalog_persistentdocument_shop)
		{
			throw new Exception('Invalid shop');
		}
		$this->shopId = $shop->getId();
	}
	
	/**
	 * @return zone_persistentdocument_zone
	 */
	public function getShippingZone()
	{
		return $this->getShop()->getShippingZone();
	}

	/**
	 * @return zone_persistentdocument_zone
	 */
	public function getBillingZone()
	{
		return $this->getShop()->getBillingZone();
	}
	
	

	//ORDER MANIPULATION

	/**
	 * @var integer
	 */
	private $orderId;
	
	/**
	 * @return integer
	 */
	public function getOrderId()
	{
		return $this->orderId;
	}
	
	/**
	 * @param integer $orderId
	 */
	public function setOrderId($orderId)
	{
		return $this->orderId = $orderId;
	}
	
	/**
	 * @return order_persistentdocument_order
	 */
	public function getOrder()
	{
		return $this->orderId ? DocumentHelper::getDocumentInstance($this->orderId, "modules_order/order") : null;
	}
	
	
	//CART DETAIL MANIPULATION

	/**
	 * @var order_CartLineInfo[]
	 */
	private $cartLine = array();

	/**
	 * @return order_CartLineInfo[]
	 */
	public function getCartLineArray()
	{
		return $this->cartLine;
	}

	/**
	 * @param order_CartLineInfo[] $cartLine
	 */
	public function setCartLineArray($cartLine)
	{
		$this->cartLine = $cartLine;
	}

	/**
	 * @return Integer
	 */
	public function getCartLineCount()
	{
		return count($this->cartLine);
	}

	/**
	 * @return Boolean
	 */
	public function isEmpty()
	{
		return $this->getCartLineCount() == 0;
	}

	/**
	 * @param unknown_type $key
	 * @return Boolean
	 */
	public function hasCartLine($key)
	{
		return isset($this->cartLine[$key]);
	}

	/**
	 * @param Integer $key
	 * @return order_CartLineInfo
	 */
	public function getCartLine($key)
	{
		return $this->cartLine[$key];
	}

	/**
	 * @param unknown_type $key
	 * @param order_CartLineInfo $value
	 */
	public function setCartLine($key, $value)
	{
		$this->cartLine[$key] = $value;
	}

	/**
	 * @param order_CartLine $cartLine
	 */
	public function addCartLine($cartLine)
	{
		$this->cartLine[] = $cartLine;
	}

	/**
	 * @param Integer $key
	 */
	public function removeCartLine($key)
	{
		unset($this->cartLine[$key]);
		$this->cartLine = array_values($this->cartLine);
	}

	/**
	 * @param Array<Integer> $key
	 * @return void
	 */
	public function removeCartLines($keys)
	{
		foreach ($keys as $key)
		{
			unset($this->cartLine[$key]);
		}
		$this->cartLine = array_values($this->cartLine);
	}

	
	//ADDRESS MANIPULATION
	
	/**
	 * @var Integer
	 */
	private $shippingAddressId = null;
	
	/**
	 * @var Integer
	 */
	private $billingAddressId = null;
	
	/**
	 * @var order_ShippingStepBean
	 */
	private $addressInfo;
	
	/**
	 * @return Integer
	 */
	public function getShippingAddressId()
	{
		return $this->shippingAddressId;
	}

	/**
	 * @param Integer shippingAddressId
	 */
	public function setShippingAddressId($shippingAddressId)
	{
		$this->shippingAddressId = $shippingAddressId;
	}
	


	/**
	 * @return Integer
	 */
	public function getBillingAddressId()
	{
		return $this->billingAddressId;
	}

	/**
	 * @param Integer billingAddressId
	 */
	public function setBillingAddressId($billingAddressId)
	{
		$this->billingAddressId = $billingAddressId;
	}	
	
	/**
	 * @return order_ShippingStepBean
	 */
	public function getAddressInfo()
	{
		return $this->addressInfo;
	}
	
	/**
	 * @param order_ShippingStepBean $addressInfo
	 */
	public function setAddressInfo($addressInfo)
	{
		$this->addressInfo = $addressInfo;
	}	
	
	
	//Shipping mode PaymentMode
	
	/**
	 * @var Integer
	 */
	private $billingModeId = null;
	
	/**
	 * @return Integer
	 */
	public function getShippingModeId()
	{
		return $this->addressInfo ? $this->addressInfo->shippingModeId : null;
	}
	
	/**
	 * @return shipping_persistentdocument_mode
	 */
	function getShippingMode()
	{
		$id = $this->getShippingModeId();	
		return $id ? DocumentHelper::getDocumentInstance($id, "modules_shipping/mode") : null;
	}
	
	/**
	 * @return String
	 */
	public function getShippingLabel()
	{
		return $this->getShippingModeId() ? $this->getShippingMode()->getLabel() : null;
	}
	
	/**
	 * @return string
	 */
	function getShippingTaxCode()
	{
		return $this->addressInfo ? $this->addressInfo->shippingTaxCode : '0';
	}
	
	/**
	 * @return Integer
	 */
	public function getBillingModeId()
	{
		return $this->billingModeId;
	}

	/**
	 * @param Integer billingModeId
	 */
	public function setBillingModeId($billingModeId)
	{
		$this->billingModeId = $billingModeId;
	}
	
	/**
	 * @return payment_persistentdocument_connector
	 */
	public function getBillingMode()
	{
		return $this->billingModeId ? DocumentHelper::getDocumentInstance($this->billingModeId, "modules_payment/connector") : null;
	}

	/**
	 * @param payment_persistentdocument_connector billingMode
	 */
	public function setBillingMode($billingMode)
	{
		if ($billingMode !== null)
		{
			$this->billingModeId = $billingMode->getId();
		}
		else
		{
			$this->billingModeId = null;
		}
	}
	
	/**
	 * @return String
	 */
	public function getBillingLabel()
	{
		return $this->billingModeId ? $this->getBillingMode()->getLabel() : null;
	}
	
	//CUSTOMER INFORMATIONS
 
	/**
	 * @var Integer
	 */
	private $customerId = null;

	/**
	 * @return Integer
	 */
	public function getCustomerId()
	{
		return $this->customerId;
	}

	/**
	 * @param Integer customerId
	 */
	public function setCustomerId($customerId)
	{
		$this->customerId = $customerId;
	}
	
	/**
	 * @return customer_persistentdocument_customer
	 */
	public function getCustomer()
	{
		if ($this->customerId === null)
		{
			return null;
		}
		return DocumentHelper::getDocumentInstance($this->customerId, "modules_customer/customer");
	}
	
	/**
	 * @param customer_persistentdocument_customer $customer
	 */
	public function setCustomer($customer)
	{
		if ($customer !== null)
		{
			$this->setCustomerId($customer->getId());	
		}
		else
		{
			$this->setCustomerId(null);
		}
	}
	
	/**
	 * @var integer
	 */
	private $userId = null;
	
	
	/**
	 * @var boolean
	 */
	private $mergeWithUserCart = true;
	
	/**
	 * @return boolean
	 */
	public function getMergeWithUserCart()
	{
		return $this->mergeWithUserCart;
	}
	
	/**
	 * @param boolean $mergeWithUserCart
	 */
	public function setMergeWithUserCart($mergeWithUserCart)
	{
		$this->mergeWithUserCart = $mergeWithUserCart;
	}
	
	/**
	 * @return integer
	 */
	public function getUserId()
	{
		return $this->userId;
	}
	
	/**
	 * @param integer $userId
	 */
	public function setUserId($userId)
	{
		$this->userId = $userId;
	}
	
	/**
	 * @return users_persistentdocument_user
	 */
	public function getUser()
	{
		if ($this->userId)
		{
			return DocumentHelper::getDocumentInstance($this->userId);
		}
		return null;
	}
	

	//DISCOUNT AND COUPON
	
	public function useCoupon()
	{
		if (ModuleService::getInstance()->isInstalled('marketing'))
		{
			$preferences = order_PreferencesService::getInstance()->createQuery()->findUnique();
			if ($preferences !== null && !$preferences->getDisableCoupons())
			{
				return true;
			}
		}
		return false;
	}
	
	/**
	 * @var order_CouponInfo
	 */
	private $coupon;
	
	/**
	 * @var order_DiscountInfo[]
	 */
	private $discountArray;
	
	/**
	 * @return order_CouponInfo
	 */
	public function getCoupon()
	{
		return $this->coupon;
	}
	
	/**
	 * @param order_CouponInfo $coupon
	 */
	public function setCoupon($coupon)
	{
		$this->coupon = $coupon;
	}
	
	/**
	 * @return order_CouponInfo
	 */
	public function hasCoupon()
	{
		return $this->coupon !== null;
	}	
	
	/**
	 * @return order_DiscountInfo[]
	 */
	public function getDiscountArray()
	{
		if (!is_array($this->discountArray)) {$this->discountArray = array();}
		return $this->discountArray;
	}
	
	/**
	 * @param order_DiscountInfo[] $discountArray
	 */
	public function setDiscountArray($discountArray)
	{
		$this->discountArray = $discountArray;
	}
	
	public function clearDiscountArray()
	{
		$this->discountArray = array();
	}	

	/**
	 * @param order_DiscountInfo $discount
	 */
	public function addDiscount($discount)
	{
		if (!is_array($this->discountArray)) {$this->discountArray = array();}
		$this->discountArray[] = $discount;
	}

	/**
	 * @param order_DiscountInfo $discount
	 */
	public function removeDiscount($discount)
	{
		$result = array();
		foreach ($this->getDiscountArray() as $currentDiscount) 
		{
			if ($currentDiscount === $discount) {continue;}
			$result[] = $currentDiscount;
		}
		$this->setDiscountArray($result);
	}
	
	/**
	 * @param integer $id
	 * @return order_DiscountInfo
	 */
	public function getDiscountById($id)
	{
		foreach ($this->getDiscountArray() as $discount) 
		{
			if ($discount->getId() == $id) 
			{
				return $discount;
			}
		}
		return null;
	}

	/**
	 * @param integer $id
	 * @return order_DiscountInfo
	 */
	public function getDiscountByIndex($index)
	{
		if ($this->hasDiscount())
		{
			$i = intval($index);
			if (isset($this->discountArray[$i]))
			{
				return $this->discountArray[$i];
			}
		}
		return null;
	}
	
	/**
	 * @return boolean
	 */
	public function hasDiscount()
	{
		return count($this->getDiscountArray()) > 0;
	}
	
	/**
	 * @return double
	 */
	function getShippingPriceWithTax()
	{
		return $this->addressInfo ? $this->addressInfo->shippingvalueWithTax : 0;
	}
	
	/**
	 * @return double
	 */
	function getShippingPriceWithoutTax()
	{
		return $this->addressInfo ? $this->addressInfo->shippingValueWithoutTax : 0;
	}
	
	/**
	 * @return array<rate => value>
	 */
	public function getSubTotalTaxByRate()
	{
		$result = array();
		foreach ($this->cartLine as $cartLineInfo) 
		{
			$rate = $cartLineInfo->getTaxCode();
			if (isset($result[$rate]))
			{
				$result[$rate] += $cartLineInfo->getTotalValueWithTax() - $cartLineInfo->getTotalValueWithoutTax();
			}
			else
			{
				$result[$rate] = $cartLineInfo->getTotalValueWithTax() - $cartLineInfo->getTotalValueWithoutTax();
			}
		}
		return $result;
	}
	
	/**
	 * @return double
	 */	
	public function getSubTotalWithoutTax()
	{
		$value = 0.0;
		foreach ($this->cartLine as $cartLineInfo) 
		{
			$value += $cartLineInfo->getTotalValueWithoutTax();
		}
		return $value;
	}
	
	/**
	 * @return double
	 */	
	public function getSubTotalWithTax()
	{
		$value = 0.0;
		foreach ($this->cartLine as $cartLineInfo) 
		{
			$value += $cartLineInfo->getTotalValueWithTax();
		}
		return $value;
	}
	
	/**
	 * @return double
	 */	
	public function getDiscountTotalWithTax()
	{
		$value = 0.0;
		if ($this->hasDiscount())
		{
			foreach ($this->getDiscountArray() as $discount) 
			{
				$value += $discount->getValueWithTax();
			}
		}
		return $value;
	}
	
	/**
	 * @return double
	 */	
	public function getDiscountTotalWithoutTax()
	{
		$value = 0.0;
		if ($this->hasDiscount())
		{
			foreach ($this->getDiscountArray() as $discount) 
			{
				$value += $discount->getValueWithoutTax();
			}
		}
		return $value;
	}
	
	/**
	 * @return double
	 */
	public function getTotalWithoutTax()
	{
		$total = $this->getSubTotalWithoutTax();
		if ($this->hasDiscount())
		{
			$total -= $this->getDiscountTotalWithoutTax();
		}
		if ($this->hasCoupon()) 
		{	
			$total -= $this->getCoupon()->getValueWithoutTax();
		}
		return $total + $this->getShippingPriceWithoutTax();
	}
	
	/**
	 * @return double
	 */
	public function getTotalWithTax()
	{
		$total = $this->getSubTotalWithTax();
		if ($this->hasDiscount())
		{
			$total -= $this->getDiscountTotalWithTax();
		}
		if ($this->hasCoupon()) 
		{	
			$total -= $this->getCoupon()->getValueWithTax();
		}
		return $total + $this->getShippingPriceWithTax();
	}	
	
	/**
	 * @return double
	 */
	public function getTotalTax()
	{
		return $this->getTotalWithTax() - $this->getTotalWithoutTax();
	}
		
	/**
	 * @return double
	 */	
	public function getTotalAmount()
	{
		return $this->getTotalWithTax();
	}		
	
	/**
	 * @param double value
	 * @return string
	 */
	private function formatValue($value)
	{
		return $this->getShop()->formatPrice($value);
	}

	/**
	 * @return String
	 */
	public function getFormattedSubTotalWithTax()
	{
		return $this->formatValue($this->getSubTotalWithTax());
	}
	
	/**
	 * @return String
	 */
	public function getFormattedDiscountTotalWithTax()
	{
		return $this->formatValue($this->getDiscountTotalWithTax());
	}
	
	/**
	 * @return String
	 */
	public function getFormattedSubTotalWithoutTax()
	{
		
		return $this->formatValue($this->getSubTotalWithoutTax());
	}
	
	public function getFormattedSubTotalTaxByRate()
	{
		$result = array();
		foreach ($this->getSubTotalTaxByRate() as $rate => $value) 
		{
			$result[] = array('formattedTaxRate' => catalog_PriceHelper::formatTaxRate(catalog_PriceHelper::getTaxRateByCode($rate)), 
			'formattedTaxAmount' => $this->formatValue($value));	
		}
		return $result;
	}
	
	/**
	 * String
	 */
	function getFormatedShippingPriceWithTax()
	{
		return $this->formatValue($this->getShippingPriceWithTax());
	}	
	
	/**
	 * String
	 */
	function getFormatedShippingPriceWithoutTax()
	{
		return $this->formatValue($this->getShippingPriceWithoutTax());
	}
	
	/**
	 * @return String
	 * @deprecated
	 */
	public function getFormattedTotalAmount()
	{
		return $this->formatValue($this->getTotalAmount());
	}

	/**
	 * @return String
	 */
	public function getFormattedTotalWithoutTax()
	{
		return $this->formatValue($this->getTotalWithoutTax());
	}	
	
	
	/**
	 * @return String
	 */
	public function getFormattedTotalWithTax()
	{
		return $this->formatValue($this->getTotalWithTax());
	}	
	
	/**
	 * @return String
	 */
	public function getFormattedTotalTax()
	{
		return $this->formatValue($this->getTotalTax());
	}
	
	//TEMPLATING MANIPULATION
	
    /**
	 * @var Array<String>
	 */
	private $warningMessage = null;

	/**
	 * @return Array<String>
	 */
	public function getWarningMessageArray()
	{
		return $this->warningMessage;
	}

	/**
	 * @param Array<String> $warningMessage
	 */
	public function setWarningMessageArray($warningMessage)
	{
		$this->warningMessage = $warningMessage;
	}

	/**
	 * @param unknown_type $key
	 * @return Boolean
	 */
	public function hasWarningMessage($key)
	{
		return isset($this->warningMessage[$key]);
	}

	/**
	 * @param unknown_type $key
	 * @return String
	 */
	public function getWarningMessage($key)
	{
		return $this->warningMessage[$key];
	}

	/**
	 * @param unknown_type $key
	 * @param String $value
	 */
	public function setWarningMessage($key, $value)
	{
		$this->warningMessage[$key] = $value;
	}

	/**
	 * @param String $message
	 */
	public function addWarningMessage($message)
	{
		if (!is_array($this->warningMessage))
		{
			$this->warningMessage = array();
		}
		$this->warningMessage[] = $message;
	}

	/**
	 * @return void
	 */
	public function clearWarningMessages()
	{
		$this->warningMessage = array();
	}
	
	/**
	 * @var Array<String>
	 */
	private $errorMessage = array();

	/**
	 * @return Array<String>
	 */
	public function getErrorMessageArray()
	{
		return $this->errorMessage;
	}

	/**
	 * @param Array<String> $errorMessages
	 */
	public function setErrorMessageArray($errorMessages)
	{
		$this->errorMessage = $errorMessages;
	}

	/**
	 * @param Integer $key
	 * @return Boolean
	 */
	public function hasErrorMessage($key)
	{
		return isset($this->errorMessage[$key]);
	}

	/**
	 * @param Integer $key
	 * @return String
	 */
	public function getErrorMessage($key)
	{
		return $this->errorMessage[$key];
	}

	/**
	 * @param Integer $key
	 * @param String $value
	 */
	public function setErrorMessage($key, $value)
	{
		$this->errorMessage[$key] = $value;
	}
	
	/**
	 * @param String $message
	 * @return void
	 */
	public function addErrorMessage($message)
	{
		if (!is_array($this->errorMessage))
		{
			$this->errorMessage = array();
		}
		$this->errorMessage[] = $message;
	}
	
	/**
	 * @return void
	 */
	public function clearErrorMessages()
	{
		$this->errorMessage = array();
	}
	
	/**
	 * @return Boolean
	 */
	public function isValid()
	{
		return count($this->getErrorMessageArray()) == 0;
	}
	
	// CUSTOM PROPERTIES MANIPULATION
	
	/**
	 * @var Array<String, Mixed>
	 */
	private $properties = null;

	/**
	 * @return Array<String, Mixed>
	 */
	public function getPropertiesArray()
	{
		return $this->properties;
	}

	/**
	 * @param Array<String, Mixed> $properties
	 */
	public function setPropertiesArray($properties)
	{
		$this->properties = $properties;
	}

	/**
	 * @param String $key
	 * @return Boolean
	 */
	public function hasProperties($key)
	{
		return isset($this->properties[$key]);
	}

	/**
	 * @param String $key
	 * @return Mixed
	 */
	public function getProperties($key)
	{
		return $this->properties[$key];
	}

	/**
	 * @param String $key
	 * @param Mixed $value
	 */
	public function setProperties($key, $value)
	{
		$this->properties[$key] = $value;
	}

	/**
	 * @return order_OrderProcess
	 */
	public function getOrderProcess()
	{
		return order_OrderProcess::getInstance();
	}
	
	/**
	 * @return string
	 */
	public function getOrderProcessURL()
	{
		return $this->getOrderProcess()->getOrderProcessURL();
	}
	
	/**
	 * @return catalog_persistentdocument_shippingfilter[]
	 */
	public function getShippingModes()
	{
		return catalog_ShippingfilterService::getInstance()->getCurrentShippingModes($this);
	}
	
	/**
	 * @return catalog_persistentdocument_paymentfilter[]
	 */
	public function getPaymentConnectors()
	{
		return catalog_PaymentfilterService::getInstance()->getCurrentPaymentConnectors($this);
	}

	public function save()
	{
		order_CartService::getInstance()->saveToSession($this);
	}	
	
	/**
	 * Refresh cart content.
	 * @return void 
	 */
	public function refresh()
	{
		return order_CartService::getInstance()->refresh($this);
	}	
}