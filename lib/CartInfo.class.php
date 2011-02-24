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


	/**
	 * @var string
	 */
	private $taxZone;
	
	/**
	 * @return string $taxZone
	 */
	public function getTaxZone()
	{
		return $this->taxZone;
	}

	/**
	 * @param string $taxZone
	 */
	public function setTaxZone($taxZone)
	{
		$this->taxZone = $taxZone;
	}

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
			return  catalog_persistentdocument_shop::getInstanceById($this->shopId);
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
			$this->taxZone = null;
			$this->shopId = null;
		}
		else if ($shop instanceof catalog_persistentdocument_shop)
		{
			$this->shopId = $shop->getId();
			$this->taxZone = $shop->getDefaultTaxZone();
		}
		else 
		{
			throw new Exception('Invalid shop');
		}
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
		return $this->orderId ? order_persistentdocument_order::getInstanceById($this->orderId) : null;
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
					  'feesId' => $filter->getFeesId()
				);
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
	
	//FEES
	
	/**
	 * @var array
	 */
	private $feesArray;
	
	/**
	 * @return order_FeesInfo[]
	 */
	public function getFeesArray()
	{
		if (!is_array($this->feesArray)) {$this->feesArray = array();}
		return $this->feesArray;
	}
	
	/**
	 * @param order_FeesInfo[] $feesArray
	 */
	public function setFeesArray($feesArray)
	{
		$this->feesArray = $feesArray;
	}
	
	public function clearFeesArray()
	{
		$this->feesArray = array();
	}
	
	/**
	 * @param integer $id
	 * @return order_FeesInfo
	 */
	public function getFeesById($id)
	{
		foreach ($this->getFeesArray() as $fees) 
		{
			if ($fees->getId() == $id) 
			{
				return $fees;
			}
		}
		return null;
	}
	
	/**
	 * @param order_FeesInfo $fees
	 */
	public function addFeesInfo($feesInfo)
	{
		if (!is_array($this->feesArray)) {$this->feesArray = array();}
		$this->feesArray[] = $feesInfo;
	}
	
	/**
	 * @param order_FeesInfo $fees
	 */	
	public function removeFeesInfo($feesInfo)
	{
		$result = array();
		foreach ($this->getFeesArray() as $currentFees) 
		{
			if ($currentFees === $feesInfo) {continue;}
			$result[] = $currentFees;
		}
		$this->setFeesArray($result);		
	}
	
	/**
	 * @return boolean
	 */
	public function hasFees()
	{
		return count($this->getFeesArray()) > 0;
	}

	/**
	 * @return array<'label' => string, valueWithTax => string>
	 */
	public function getFeesDataArrayForDisplay()
	{
		$result = array();
		foreach ($this->getFeesArray() as $fees) 
		{
			if ($fees instanceof order_FeesInfo) 
			{
				if (f_util_StringUtils::isNotEmpty($fees->getLabel()) && $fees->getValueWithTax() > 0)
				{
					$result[] = array('label' => $fees->getLabel(),  
						'valueWithTax' => $this->formatPrice($fees->getValueWithTax()),
						'valueWithoutTax' => $this->formatPrice($fees->getValueWithoutTax()));
				}
			}
		}
		return $result;
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
					'valueWithTax' => '-' . $this->formatPrice($discount->getValueWithTax()));
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
	
	//CREDIT NOTE
	
	private $creditNoteInfos = null;
	
	/**
	 * @return boolean
	 */
	public function hasCreditNote()
	{
		return $this->creditNoteInfos !== null && count($this->creditNoteInfos) > 0;
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
		return array_sum($this->creditNoteInfos);
	}
	
	/**
	 * @param integer $creditNoteId
	 * @param double $amount
	 */
	public function setCreditNoteAmount($creditNoteId, $amount)
	{
		if ($amount <= 0) 
		{
			$this->removeCreditNote($creditNoteId);
		}
		
		if ($this->hasCreditNote())
		{
			$this->creditNoteInfos[$creditNoteId] = $amount;
		}
		else
		{
			$this->creditNoteInfos = array($creditNoteId => $amount);
		}
	}
	
	/**
	 * @param integer $creditNoteId
	 * @param double $amount
	 */
	public function removeCreditNote($creditNoteId)
	{
		if ($this->hasCreditNote())
		{
			if (array_key_exists($creditNoteId, $this->creditNoteInfos))
			{
				unset($this->creditNoteInfos[$creditNoteId]);
				if (count($this->creditNoteInfos) === 0)
				{
					$this->creditNoteInfos = null;
				}
			}
		}
	}
	
	/**
	 * @return array<$creditNoteId => $amount>
	 */
	public function getCreditNoteArray()
	{
		return ($this->hasCreditNote()) ? $this->creditNoteInfos : array();
	}
	
	public function removeAllCreditNote()
	{
		$this->creditNoteInfos = null;
	}
	
	
	//AMOUNT
		
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
	public function getFeesTotalWithTax()
	{
		$value = 0.0;
		if ($this->hasFees())
		{
			foreach ($this->getFeesArray() as $fees) 
			{
				$value += $fees->getValueWithTax();
			}
		}
		return $value;
	}
	
	/**
	 * @return double
	 */	
	public function getFeesTotalWithoutTax()
	{
		$value = 0.0;
		if ($this->hasFees())
		{
			foreach ($this->getFeesArray() as $fees) 
			{
				$value += $fees->getValueWithoutTax();
			}
		}
		return $value;
	}
		
	private $taxRates;
	
	public function setTaxRates($taxRates)
	{
		$this->taxRates = $taxRates;
	}
	
	/**
	 * array <taxRate => value>
	 */
	public function getTaxRates()
	{
		return is_array($this->taxRates) ? $this->taxRates : array();
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
		return $this->getSubTotalWithoutTax() - $this->getDiscountTotalWithoutTax();
	}
	
	/**
	 * @return double
	 */
	public function getTotalExcludingFeesWithTax()
	{
		return $this->getSubTotalWithTax() - $this->getDiscountTotalWithTax();
	}	
	

	/**
	 * @return double
	 */
	public function getTotalTax()
	{
		return array_sum($this->getTaxRates());
	}

	/**
	 * @return double
	 */
	public function getTotalWithTax()
	{
		return $this->getSubTotalWithTax() - $this->getDiscountTotalWithTax() + $this->getFeesTotalWithTax();
	}
	
	/**
	 * @return double
	 */
	public function getTotalWithoutTax()
	{
		return $this->getTotalWithTax() - $this->getTotalTax();
	}
		
	/**
	 * @return double
	 */	
	public function getTotalAmount()
	{
		return $this->getTotalWithTax() - $this->getTotalCreditNoteAmount();
	}		
	
	public function formatPrice($value)
	{
		return $this->getShop()->formatPrice($value);
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
		return $this->formatPrice($this->getSubTotalWithTax());
	}
	
	/**
	 * @return String
	 */
	public function getFormattedSubTotalWithoutTax()
	{
		return $this->formatPrice($this->getSubTotalWithoutTax());
	}
	
	/**
	 * @return String
	 */
	public function getFormattedDiscountTotalWithTax()
	{
		return $this->formatPrice($this->getDiscountTotalWithTax());
	}
	
	public function getFormattedSubTotalTaxByRate()
	{
		$result = array();
		foreach ($this->getTaxRates() as $rate => $value) 
		{
			$result[] = array('formattedTaxRate' => $rate, 'formattedTaxAmount' => $this->formatPrice($value));	
		}
		return $result;
	}
	

	/**
	 * @return String
	 */
	public function getFormattedTotalExcludingFeesWithoutTax()
	{
		return $this->formatPrice($this->getTotalExcludingFeesWithoutTax());
	}	
	
	/**
	 * @return String
	 */
	public function getFormattedTotalExcludingFeesWithTax()
	{
		return $this->formatPrice($this->getTotalExcludingFeesWithTax());
	}	

	/**
	 * @return String
	 */
	public function getFormattedTotalWithoutTax()
	{
		return $this->formatPrice($this->getTotalWithoutTax());
	}	
	
	/**
	 * @return String
	 */
	public function getFormattedTotalWithTax()
	{
		return $this->formatPrice($this->getTotalWithTax());
	}	
	
	/**
	 * @return String
	 */
	public function getFormattedTotalTax()
	{
		return $this->formatPrice($this->getTotalTax());
	}
	
	//TEMPLATING MANIPULATION
	
	/**
	 * @var Array<String>
	 */
	private $successMessage = array();

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
		if (!in_array($message, $this->successMessage))
		{
			$this->successMessage[] = $message;
		}
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
	private $warningMessage = array();

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
		if (!in_array($message, $this->warningMessage))
		{
			$this->warningMessage[] = $message;
		}
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
		if (!in_array($message, $this->errorMessage))
		{
			$this->errorMessage[] = $message;
		}
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
			$results = catalog_ShippingfilterService::getInstance()->getCurrentShippingModes($this);
			foreach ($results as $shippingFilter) 
			{
				$shippingFilter->evaluateValue($this);
			}
			usort($results, array($this, 'comprareShippingFilterValue'));
			return $results;
		}
		return array();
	}
	
	/**
	 * @param catalog_persistentdocument_shippingfilter $a
	 * @param catalog_persistentdocument_shippingfilter $b
	 * @return integer
	 */
	protected function comprareShippingFilterValue($a, $b)
	{
		if ($a === $b || intval($a->getValueWithoutTax()) === intval($b->getValueWithoutTax()))
		{
			return 0;
		}
		return (intval($a->getValueWithoutTax()) < intval($b->getValueWithoutTax())) ? -1 : 1;
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
					$shippingFilter = DocumentHelper::getDocumentInstance($value['filter']['id']);
					$shippingFilter->evaluateValue($this);
					$result[] = $shippingFilter;
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
	function getShippingPriceWithTax()
	{
		Framework::warn(f_util_ProcessUtils::getBackTrace());
		return $this->getFeesTotalWithTax();
	}

	/**
	 * @deprecated
	 */
	function getFormattedShippingPriceWithTax()
	{
		return $this->formatPrice($this->getShippingPriceWithTax());
	}	
	
	/**
	 * @deprecated
	 */
	function getFormattedShippingPriceWithoutTax()
	{
		return $this->formatPrice($this->getShippingPriceWithTax());
	}
	
	/**
	 * @deprecated
	 */
	public function getFormattedTotalAmount()
	{
		return $this->formatPrice($this->getTotalAmount());
	}
	
	/**
	 * @deprecated use getFormattedShippingPriceWithTax
	 */
	function getFormatedShippingPriceWithTax()
	{
		return $this->getFormattedShippingPriceWithTax();
	}	
	
	/**
	 * @deprecated use getFormattedShippingPriceWithoutTax
	 */
	function getFormatedShippingPriceWithoutTax()
	{
		return $this->getFormattedShippingPriceWithoutTax();
	}
}