<?php
/**
 * order_CartInfo
 * @package modules.order
 */
class order_CartInfo
{
	private $isModified = false;
	
	private $checkSum = null;
	
	protected function getCheckSum()
	{
		$keys = array($this->shopId, $this->customerId, $this->getCartLineCount());
		foreach ($this->getCartLineArray() as $cartLine) 
		{
			$keys[] = $cartLine->getKey();
			$keys[] = $cartLine->getQuantity();
		}
		
		foreach ($this->getDiscountArray() as $discount) 
		{
			$keys[] = $discount->getId();
			$keys[] = round($discount->getValueWithTax());
		}
		$keys[] = $this->getShippingModeId();
		
		$addressInfos = $this->getAddressInfo();
		if ($addressInfos !== null)
		{
			if ($addressInfos->shippingAddress) 
			{
				$keys[] = $addressInfos->shippingAddress->getKey();
			}
			if ($addressInfos->useSameAddressForBilling) 
			{
				$keys[] = 'idem';
			}
			else if ($addressInfos->billingAddress) 
			{
				$keys[] = $addressInfos->billingAddress->getKey();
			}
		}
		$keys[] = $this->getBillingModeId();
		$checkSum = md5(implode('|', $keys));
		if (Framework::isInfoEnabled())
		{
			Framework::info(__METHOD__ .': ' . $checkSum);
		}
		return $checkSum;
	}
	
	//SHOP INFORMATION
	
	/**
	 * @var Integer
	 */
	private $shopId = null;
	
	public function isModified()
	{
		if (!$this->isModified)
		{
			$newCheckSum = $this->getCheckSum();		
			if ($this->checkSum === null)
			{
				$this->checkSum = $newCheckSum;
			}	
			
			if ($this->checkSum !== $newCheckSum)
			{
				$this->checkSum = $newCheckSum;
				$this->isModified = true;
			}
			if (Framework::isInfoEnabled())
			{
				Framework::info(__METHOD__ .':' . $newCheckSum . ', '.$this->isModified);
			}
		}
		return $this->isModified;
	}
	
	private function setModified($value)
	{
		$this->isModified = $value;
		if (!$this->isModified)
		{
			$this->checkSum = $this->getCheckSum();
		}
	}
	
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
		if ($this->shopId !== null)
		{
			return DocumentHelper::getDocumentInstance($this->shopId, "modules_catalog/shop");
		}
		else 
		{
			return catalog_ShopService::getInstance()->getCurrentShop();
		}
	}
	
	/**
	 * @param catalog_persistentdocument_shop $shop
	 */
	function setShop($shop)
	{
		if ($shop === null)
		{
			$this->shopId = null;
		}
		else if (!$shop instanceof catalog_persistentdocument_shop)
		{
			throw new Exception('Invalid shop');
		}
		else 
		{
			$this->shopId = $shop->getId();
		}
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
		$this->setModified(false);
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
	 * @param integer $index
	 * @return Boolean
	 */
	public function hasCartLine($index)
	{
		return isset($this->cartLine[$index]);
	}

	/**
	 * @param integer $index
	 * @return order_CartLineInfo
	 */
	public function getCartLine($index)
	{
		return $this->cartLine[$index];
	}
	
	/**
	 * @param order_CartLineInfo $cartLine
	 * @return integer
	 */
	public function getCartLineIndex($cartLine)
	{
		$count = count($this->cartLine);
		for ($i = 0; $i < $count; $i++) 
		{
			if ($this->cartLine[$i] === $cartLine)
			{
				return $i;
			}
		}
		return -1;
	}

	/**
	 * @param integer $index
	 * @param order_CartLineInfo $cartLine
	 */
	public function setCartLine($index, $cartLine)
	{
		if ($index < 0 || $index >= count($this->cartLine))
		{
			$this->addCartLine($cartLine);
			return;
		}
		$this->cartLine[$index] = $cartLine;
	}

	/**
	 * @param order_CartLineInfo $cartLine
	 */
	public function addCartLine($cartLine)
	{
		$this->cartLine[] = $cartLine;
	}

	/**
	 * @param order_CartLineInfo $cartLine
	 * @param integer $index
	 */
	public function addCartLineAt($cartLine, $index = 0)
	{
		$count = count($this->cartLine);
		if ($index < 0 || $index >= $count)
		{
			$this->addCartLine($cartLine);
			return;
		}
		$cartLines = array();
		for ($i = 0; $i < $count; $i++) 
		{
			if ($i == $index)
			{
				$cartLines[] = $cartLine;
			}
			$cartLines[] = $this->cartLine[$i];
		}
		$this->cartLine = $cartLines;
	}
	
	/**
	 * @param integer $index
	 */
	public function removeCartLine($index)
	{
		unset($this->cartLine[$index]);
		$this->cartLine = array_values($this->cartLine);
	}
	
	/**
	 * @param order_CartLineInfo $cartLine
	 */
	public function removeCartLineObj($cartLine)
	{
		$count = count($this->cartLine);
		for ($i = 0; $i < $count; $i++) 
		{
			if ($this->cartLine[$i] === $cartLine)
			{
				$this->removeCartLine($i);
				return;
			}
		}
	}

	/**
	 * @param integer[] $indexes
	 */
	public function removeCartLines($indexes)
	{
		foreach ($indexes as $index)
		{
			unset($this->cartLine[$index]);
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
	
	
	//Shipping mode
	
	/**
	 * @var array[]
	 */
	private $shippingArray;
	
	
	/**
	 * 
	 * @param array $shippingArray
	 */
	public function setShippingArray($shippingArray)
	{
		$this->shippingArray = $shippingArray;
	}
	
	/**
	 * @return array
	 */
	public function getShippingArray()
	{
		if (!is_array($this->shippingArray))
		{
			Framework::info(__METHOD__ . ' OLD CartInfo assume no required shipping mode!');
			$this->shippingArray = array(0 => array('lines' => array()));
			for ($i = 0; $i < count($this->cartLine); $i++)
			{
				$this->shippingArray[0]['lines'][] = $i;
			}
		}
		return $this->shippingArray;
	}
	
	/**
	 * @return boolean
	 */
	function canSelectShippingModeId()
	{
		$shippingArray = $this->getShippingArray();
		return isset($shippingArray[0]) && count($shippingArray[0]['lines']) > 0;
	}
	
	/**
	 * @param shipping_persistentdocument_mode $shippingMode
	 * @return order_CartLineInfo[]
	 */
	function getCartLineArrayByShippingMode($shippingMode)
	{
		$shippingModeId = $shippingMode === null ? 0 : $shippingMode->getId();
		$result = array();
		$shippingArray = $this->getShippingArray();
		if (isset($shippingArray[$shippingModeId]))
		{
			foreach ($shippingArray[$shippingModeId]['lines'] as $index) 
			{
				$result[] = $this->getCartLine($index);
			}	
		}
		return $result;
	}
	
	/**
	 * @return integer[]
	 */
	function getRequiredShippingModeIds()
	{
		$result = array();
		$shippingModeIds = array_keys($this->getShippingArray());
		foreach ($shippingModeIds as $shippingModeId) 
		{
			if ($shippingModeId != 0)
			{
				$result[] = $shippingModeId;
			}
		}
		return $result;
	}	
	
	/**
	 * @param integer $shippingModeId;
	 * @param catalog_persistentdocument_shippingfilter $filter
	 */	
	function setRequiredShippingFilter($shippingModeId, $filter)
	{
		
		if ($filter === null)
		{
			if (isset($this->shippingArray[$shippingModeId]['filter']))
			{
				unset($this->shippingArray[$shippingModeId]['filter']);
			}
		}
		else
		{
			$this->shippingArray[$shippingModeId]['filter'] = 
				array('id' => $filter->getId(), 
				'modeId' => $filter->getMode()->getId(),
				'shippingvalueWithTax' => $filter->getValueWithTax(),
				'shippingvalueWithoutTax' => $filter->getValueWithoutTax(),
				'shippingTaxCode' => $filter->getTaxCode());
		}	
	}

	/**
	 * @return boolean
	 */
	function hasPredefinedShippingMode()
	{
		return count($this->getRequiredShippingModeIds()) > 0;
	}
	
	/**
	 * @param catalog_persistentdocument_shippingfilter $filter
	 */
	function setCurrentTestFilter($filter)
	{
		$this->currentTestFilter = $filter;
	}
	
	/**
	 * @return catalog_persistentdocument_shippingfilter
	 */
	function getCurrentTestFilter()
	{
		return $this->currentTestFilter;
	}	
	
	/**
	 * @return Integer
	 */
	public function getShippingModeId()
	{
		$shippingArray = $this->getShippingArray();
		if (isset($shippingArray[0]) && isset($shippingArray[0]['filter']))
		{
			return $shippingArray[0]['filter']['modeId'];
		}
		return null;
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
		$mode = $this->getShippingMode();	
		return $mode ? $mode->getLabel() : null;
	}
	
	/**
	 * @return string
	 */
	function getShippingTaxCode()
	{
		$taxCode = null;
		foreach ($this->getShippingArray() as $datas) 
		{
			if (isset($datas['filter']))
			{
				$modeTaxCode = $datas['filter']['shippingTaxCode'];
				if ($taxCode === null)
				{
					$taxCode = $modeTaxCode;
				}
				else if ($taxCode != $modeTaxCode)
				{
					$taxCode = '0';
				}
			}
		}
		return $taxCode;
	}
	
	/**
	 * @return string
	 */
	function getShippingTaxRate()
	{
		$withTaxe = 0;
		$withouTaxe = 0;
		foreach ($this->getShippingArray() as $datas) 
		{
			if (isset($datas['filter']))
			{
				$withTaxe += $datas['filter']['shippingvalueWithTax'];
				$withouTaxe += $datas['filter']['shippingvalueWithoutTax'];
			}
		}
		return catalog_PriceHelper::getTaxRateByValue($withTaxe, $withouTaxe);
	}	
	
	/**
	 * @return double
	 */
	function getShippingPriceWithTax()
	{
		$result = 0;
		foreach ($this->getShippingArray() as $datas) 
		{
			if (isset($datas['filter']))
			{
				$result += $datas['filter']['shippingvalueWithTax'];
			}
		}
		return $result;
	}
	
	/**
	 * @return double
	 */
	function getShippingPriceWithoutTax()
	{
		$result = 0;
		foreach ($this->getShippingArray() as $datas) 
		{
			if (isset($datas['filter']))
			{
				$result += $datas['filter']['shippingvalueWithoutTax'];
			}
		}
		return $result;
	}
	
	//Payment Mode

	/**
	 * @var Integer
	 */
	private $billingModeId = null;
	
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
	 * @return array<'label' => string, valueWithTax => string>
	 */
	public function getDiscountDataArrayForDisplay()
	{
		$result = array();
		foreach ($this->getDiscountArray() as $discount) 
		{
			if (f_util_StringUtils::isNotEmpty($discount->getLabel()) && $discount->getValueWithTax() > 0)
			{
				$result[] = array('label' => $discount->getLabel(),  
					'valueWithTax' => '-' . $this->formatValue($discount->getValueWithTax()));
			}
		}
		return $result;
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
	 * @return array<rate => value>
	 */
	public function getSubTotalTaxByRate()
	{
		$result = array();
		foreach ($this->cartLine as $cartLineInfo) 
		{
			$rate = $cartLineInfo->getFormattedTaxCode();
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
	public function getTotalExcludingFeesWithoutTax()
	{
		$total = $this->getSubTotalWithoutTax();
		if ($this->hasDiscount())
		{
			$total -= $this->getDiscountTotalWithoutTax();
		}
		return $total;
	}
	
	/**
	 * @return double
	 */
	public function getTotalExcludingFeesWithTax()
	{
		$total = $this->getSubTotalWithTax();
		if ($this->hasDiscount())
		{
			$total -= $this->getDiscountTotalWithTax();
		}
		return $total;
	}	
	
	/**
	 * @return double
	 */
	public function getTotalWithoutTax()
	{
		return $this->getTotalExcludingFeesWithoutTax() + $this->getShippingPriceWithoutTax();
	}
	
	/**
	 * @return double
	 */
	public function getTotalWithTax()
	{
		return $this->getTotalExcludingFeesWithTax() + $this->getShippingPriceWithTax();
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
			$result[] = array('formattedTaxRate' => $rate, 'formattedTaxAmount' => $this->formatValue($value));	
		}
		return $result;
	}
	
	/**
	 * String
	 */
	function getFormattedShippingPriceWithTax()
	{
		return $this->formatValue($this->getShippingPriceWithTax());
	}	
	
	/**
	 * String
	 */
	function getFormattedShippingPriceWithoutTax()
	{
		return $this->formatValue($this->getShippingPriceWithoutTax());
	}
	
	/**
	 * @return String
	 */
	public function getFormattedTotalExcludingFeesWithoutTax()
	{
		return $this->formatValue($this->getTotalExcludingFeesWithoutTax());
	}	
	
	/**
	 * @return String
	 */
	public function getFormattedTotalExcludingFeesWithTax()
	{
		return $this->formatValue($this->getTotalExcludingFeesWithTax());
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
	private $successMessage = null;

	/**
	 * @return Array<String>
	 */
	public function getSuccessMessageArray()
	{
		return $this->successMessage;
	}

	/**
	 * @param Array<String> $successMessage
	 */
	public function setSuccessMessageArray($successMessage)
	{
		$this->successMessage = $successMessage;
	}

	/**
	 * @param unknown_type $key
	 * @return Boolean
	 */
	public function hasSuccessMessage($key)
	{
		return isset($this->successMessage[$key]);
	}

	/**
	 * @param unknown_type $key
	 * @return String
	 */
	public function getSuccessMessage($key)
	{
		return $this->successMessage[$key];
	}

	/**
	 * @param unknown_type $key
	 * @param String $value
	 */
	public function setSuccessMessage($key, $value)
	{
		$this->successMessage[$key] = $value;
	}

	/**
	 * @param String $message
	 */
	public function addSuccessMessage($message)
	{
		if (!is_array($this->successMessage))
		{
			$this->successMessage = array();
		}
		$this->successMessage[] = $message;
	}

	/**
	 * @return void
	 */
	public function clearSuccessMessages()
	{
		$this->successMessage = array();
	}
	
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
		return order_OrderProcessService::getInstance()->loadFromSession();
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
		if ($this->canSelectShippingModeId())
		{
			return catalog_ShippingfilterService::getInstance()->getCurrentShippingModes($this);
		}
		return array();
	}
	
	/**
	 * @return catalog_persistentdocument_shippingfilter[];
	 */
	public function getRequiredShippingModes()
	{
		$result = array();
		if ($this->hasPredefinedShippingMode())
		{
			foreach ($this->getShippingArray() as $shippingModeId => $value) 
			{
				if ($shippingModeId != 0 && isset($value['filter']))
				{
					$result[] = DocumentHelper::getDocumentInstance($value['filter']['id']);
				}
			}
		}
		return $result;
	}
	
	
	/**
	 * @return catalog_persistentdocument_paymentfilter[]
	 */
	public function getPaymentConnectors()
	{
		return catalog_PaymentfilterService::getInstance()->getCurrentPaymentConnectors($this);
	}
	
	/**
	 * @return order_CartService
	 */
	public function getCartService()
	{
		return order_CartService::getInstance();
	}

	public function save()
	{
		$this->getCartService()->saveToSession($this);
	}	
	
	/**
	 * Refresh cart content.
	 * @return void 
	 */
	public function refresh()
	{
		return $this->getCartService()->refresh($this);
	}
	
	function canBeShipped()
	{
		if ($this->hasPredefinedShippingMode() && count($this->getRequiredShippingModes()) == 0)
		{
			return false;
		}
		if ($this->canSelectShippingModeId() && count($this->getShippingModes()) == 0)
		{
			return false;
		}
		return true;
	}
	
	// Deprecated
		
	/**
	 * @deprecated
	 */
	public function getFormattedTotalAmount()
	{
		return $this->formatValue($this->getTotalAmount());
	}
	
	/**
	 * @deprecated use getFormattedShippingPriceWithTax
	 */
	function getFormatedShippingPriceWithTax()
	{
		return $this->getFormattedShippingPriceWithTax();
	}	
	
	/**
	 * @depreacated use getFormattedShippingPriceWithoutTax
	 */
	function getFormatedShippingPriceWithoutTax()
	{
		return $this->getFormattedShippingPriceWithoutTax();
	}
}