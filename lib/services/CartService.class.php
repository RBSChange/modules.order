<?php
/**
 * order_CartService
 * @package modules.order
 */
class order_CartService extends BaseService
{
	const CART_SESSION_NAMESPACE = 'order_cart';
	const CARTLINE_NUMBER_LIMIT = 100;
	
	const REFERENCE_WEIGHT_UNIT = 'g';
	
	/**
	 * Singleton
	 * @var order_CartService
	 */
	private static $instance = null;
	
	/**
	 * @return order_CartService
	 */
	public static function getInstance()
	{
		if (is_null(self::$instance))
		{
			self::$instance = self::getServiceClassInstance(get_class());
		}
		return self::$instance;
	}
	
	/**
	 * @return order_CartInfo
	 */
	public function getDocumentInstanceFromSession()
	{	
		$cart = null;
		$user = Controller::getInstance()->getContext()->getUser();
		if ($user->hasAttribute($this->getSessionKey('CartInfo'), self::CART_SESSION_NAMESPACE))
		{
			if (Framework::isDebugEnabled())
			{
				Framework::debug(__METHOD__ ." (FROM SESSION)");
			}
			$cart = $user->getAttribute($this->getSessionKey('CartInfo'), self::CART_SESSION_NAMESPACE);
		}
		
		if (!($cart instanceof order_CartInfo))
		{
			$cart = $this->initNewCart();
			$this->saveToSession($cart);
		}
		return $cart;
	}
	
	/**
	 * 
	 * @param order_CartInfo $cart
	 */
	protected function clearCart(&$cart)
	{
		if (Framework::isInfoEnabled())
		{
			Framework::info(__METHOD__ . ' CLEAR CART');
		}
		
		try 
		{
			$this->getTransactionManager()->beginTransaction();
			
			// The order process is ended, so clear lastAbandonedOrderDate.
			$customer = $cart->getCustomer();
			$customer->setLastAbandonedOrderDate(null);
			$cart = $this->initNewCart();
			$this->saveToSession($cart);
			
			if ($customer->isModified())
			{
				$customer->save();
			}
			$this->getTransactionManager()->commit();
		}
		catch (Exception $e)
		{
			$this->getTransactionManager()->rollBack($e);
		}
	}
	
	/**
	 * @param order_CartInfo $cart
	 */
	public function clearCartIfNeeded(&$cart)
	{
		$order = $cart->getOrder();
		var_dump($order);
		if ($order !== null && order_BillService::getInstance()->hasPublishedBill($order))
		{
			$this->clearCart($cart);
		}
	}
	
	/**
	 * Initialize a new cart
	 * @return order_CartInfo
	 */
	protected function initNewCart()
	{
		if (Framework::isInfoEnabled())
		{
			Framework::info(__METHOD__);
		}
		$cartInfo = new order_CartInfo();
		if (!is_null($customer = customer_CustomerService::getInstance()->getCurrentCustomer()))
		{
			$cartInfo->setCustomerId($customer->getId());
		}

		$cartInfo->setShop(catalog_ShopService::getInstance()->getCurrentShop());
		return $cartInfo;	
	}
	
	/**
	 * Verification du cart en fonction de l'état de la commande
	 * @param order_CartInfo $cart
	 */
	protected function checkCartValidity(&$cart)
	{
		if ($cart instanceof order_CartInfo && !$cart->isEmpty())
		{
			$order = $cart->getOrder();
			if ($order !== null && order_BillService::getInstance()->hasValidBill($order) && $cart->isModified())
			{
				if (Framework::isInfoEnabled())
				{
					Framework::info(__METHOD__ . ' RESET CART');
				}
				
				try 
				{
					$this->getTransactionManager()->beginTransaction();
					
					// The order process is ended, so clear lastAbandonedOrderDate.
					$customer = $cart->getCustomer();
					$customer->setLastAbandonedOrderDate(null);
					$cart = $this->initNewCart();
					$this->saveToSession($cart);
					
					if ($customer->isModified())
					{
						$customer->save();
					}
					$this->getTransactionManager()->commit();
				}
				catch (Exception $e)
				{
					$this->getTransactionManager()->rollBack($e);
				}
			}
		}
	}
	
	/**
	 * @param order_CartInfo $cart
	 */
	public function resetCartOrder(&$cart)
	{	
		$this->clearCartIfNeeded($cart);
		if (Framework::isInfoEnabled())
		{
			Framework::info(__METHOD__);
		}
		$cart->setOrderId(null);
	}
	
	/**
	 * @param order_CartInfo $cart
	 */
	public function saveToSession($cart)
	{
		if ($cart->isModified())
		{
			$cart->setOrderId(null);
		}
		if (Framework::isDebugEnabled())
		{
			Framework::debug(__METHOD__);
		}
		$user = Controller::getInstance()->getContext()->getUser();
		$user->setAttribute($this->getSessionKey('CartInfo'), $cart, self::CART_SESSION_NAMESPACE);
		
		$customer = customer_CustomerService::getInstance()->getCurrentCustomer();
		if ($customer !== null)
		{
			$this->saveToDatabase($customer, $cart);
		}
	}
	
	/**
	 * @param customer_persistentdocument_customer $customer
	 * @param order_CartInfo $cart
	 */
	private function saveToDatabase($customer, $cart)
	{
		if (Framework::isDebugEnabled())
		{
			Framework::debug(__METHOD__);
		}
		$tm = f_persistentdocument_TransactionManager::getInstance();
		try
		{
			$tm->beginTransaction();
			$customer->setCart($cart);
			$customer->save();
			$tm->commit();
		}
		catch (Exception $e)
		{
			throw $tm->rollBack($e);
		}
	}
	

	
	
	/**
	 * @param order_CartInfo $cart
	 * @param catalog_persistentdocument_product $product
	 * @param Double $quantity
	 * @param Array<String, Mixed> $properties
	 * @return boolean;
	 */
	public function addProductToCart($cart, $product, $quantity = 1, $properties = array())
	{
		if (!($cart instanceof order_CartInfo) || !($product instanceof catalog_persistentdocument_product) || $quantity <= 0)
		{
			Framework::warn(__METHOD__ . ' Invalid arguments');
			return false;
		}
		
		try 
		{
			 $this->validateCartLineCount($cart);
			 $this->resetCartOrder($cart);
		}
		catch (Exception $e)
		{
			$cart->addWarningMessage(f_Locale::translate('&modules.order.frontoffice.cart-validation-error-max-lines;'));
			return false;
		}
		
		$product = $product->getDocumentService()->getProductToAddToCart($product, $cart->getShop(), $quantity, $properties);
		$product->getDocumentService()->updateProductFromCartProperties($product, $properties);
		
		if ($this->validateProduct($cart, $product, $quantity))
		{
			$key = $product->getCartLineKey();
			Framework::info(__METHOD__ . ' Check Key:' . $key);
			$cartLine = $this->getCartLineByKey($cart, $key);
			if ($cartLine === null)
			{
				Framework::info(__METHOD__ . ' Key not found:');
				$cartLine = new order_CartLineInfo();
				$cartLine->setProduct($product);
				$cartLine->setQuantity($quantity);
				$cartLine->mergePropertiesArray($properties);								
				$cart->addCartLine($cartLine);
			}
			else
			{
				$cartLine->addToQuantity($quantity);
			}
			
			// Log action.
			$params = array('product' => $product->getLabel());
			UserActionLoggerService::getInstance()->addCurrentUserDocumentEntry('add-product-to-cart', null, $params, 'customer');
		}
		// If the article has no price the line is not added and a warning message is displayed.
		else
		{
			$replacements = array('articleLabel' => $product->getLabel());
			$cart->addWarningMessage(f_Locale::translate('&modules.order.frontoffice.cart-validation-error-unavailable-article-price;', $replacements));
			return false;
		}
		
		return true;
	}	
	
	
	/**
	 * @param order_CartInfo $cart
	 * @param catalog_persistentdocument_product $product
	 * @param double $quantity
	 */
	private function validateProduct($cart, $product, $quantity)
	{
		if ($product->isPublished() && $product->canBeOrdered($cart->getShop()) && 
			$product->getPrice($cart->getShop(), $cart->getCustomer(), $quantity) != null)
		{
			if ($product instanceof catalog_StockableDocument)
			{
				if (!catalog_StockService::getInstance()->isAvailable($product, $quantity))
				{
					$replacements = array('articleLabel' => $product->getLabel(), 
						'quantity' => $quantity, 'unit' => '', 
						'availableQuantity' => $product->getStockQuantity(), 'availableUnit' => '');
					$cart->addErrorMessage(f_Locale::translate('&modules.order.frontoffice.cart-validation-error-unavailable-article-quantity;', $replacements));
					return false;					
				}
			}
			return true;
		}
		return false;
	}
	
	/**
	 * 
	 * @param order_CartInfo $cart
	 * @param string $key
	 * @return order_CartLineInfo
	 */
	private function getCartLineByKey($cart, $key)
	{
		foreach ($cart->getCartLineArray() as $cartLine) 
		{
			if ($cartLine->getKey() == $key)
			{
				return $cartLine;
			}
		}
		return null;
	}
	
	/**
	 * @param order_CartInfo $cart
	 * @param marketing_persistentdocument_coupon $coupon
	 */
	public function setCoupon($cart, $coupon)
	{
		if ($coupon === null)
		{
			$cart->setCoupon(null);
		}
		else
		{
			$couponinfo = new order_CouponInfo();
			$couponinfo->setId($coupon->getId());
			$cart->setCoupon($couponinfo);
			$this->refreshCoupon($cart);
		}
		return $cart->getCoupon();
	}
	
	/**
	 * @param order_CartLineInfo $cartLine
	 * @param Double $weight
	 * @param String $referenceUnit
	 */
	public function setWeightProperties($cartLine, $weight, $unit)
	{
		$quantity = $cartLine->getQuantity();
		if (!is_null($weight) && !is_null($unit))
		{
			$cartLine->setProperties('_weight', $weight);
			$cartLine->setProperties('_weightUnit', $unit);
			$cartLine->setProperties('_formattedWeight', catalog_QuantityHelper::formatWeight($weight, $unit));
			$cartLine->setProperties('_totalWeight', $quantity * $weight);
			$cartLine->setProperties('_formattedTotalWeight', catalog_QuantityHelper::formatWeight($quantity * $weight, $unit));
			$convertedWeight = catalog_QuantityHelper::convertWeight($weight, $unit, self::REFERENCE_WEIGHT_UNIT);
			if (!is_null($convertedWeight))
			{
				$cartLine->setProperties('_referenceWeight', $quantity * $convertedWeight);
			}
			else
			{
				$cartLine->setProperties('_referenceWeight', null);
			}
		}
		else
		{
			$cartLine->setProperties('_weight', null);
			$cartLine->setProperties('_weightUnit', null);
			$cartLine->setProperties('_formattedWeight', null);
			$cartLine->setProperties('_totalWeight', null);
			$cartLine->setProperties('_formattedTotalWeight', null);
			$cartLine->setProperties('_referenceWeight', null);
		}
	}	
	
	/**
	 * @param order_CartInfo $cart
	 */
	protected function refreshTotalWeights($cart)
	{
		//TODO retrouve les informations dans le produit ?
		/*
		foreach ($cart->getCartLineArray() as $cartLine)
		{
			$this->setWeightProperties($cartLine, null, null);
		}
		*/
	}
	
	/**
	 * This method is meant to be called one or several time,
	 * then the cart needs to be refreshed by calling the refresh($cart)
	 * method.
	 *
	 * @param order_CartInfo $cart
	 * @param Integer $cartLineIndex
	 * @param catalog_persistentdocument_product $product
	 * @param Double $quantity
	 * @param Array<String, Mixed> $properties
	 */
	public function updateLine($cart, $cartLineIndex, $product, $quantity, $properties = array())
	{
		$cartLine = $cart->getCartLine($cartLineIndex);
		if (is_null($cartLine))
		{
			throw new order_Exception('The line with index "' . $cartLineIndex . '" doesn\'t exist.');
		}
		$cartLine->setQuantity($quantity);
		$cartLine->setProduct($product);
		$cartLine->mergePropertiesArray($properties);
	}

	/**
	 * This method is meant to be called one or several time,
	 * then the cart needs to be refreshed by calling the refresh($cart)
	 * method.
	 *
	 * @param order_CartInfo $cart
	 * @param Integer $cartLineInfoIndex
	 */
	public function removeLine($cart, $cartLineIndex)
	{
		$cart->removeCartLine($cartLineIndex);
	}
	
	/**
	 * Validate the cart.
	 * Update prices info.
	 *
	 * @param order_CartInfo $cart
	 * @throws order_ValidationException
	 */
	public function refresh($cart)
	{
		if (Framework::isDebugEnabled())
		{
			Framework::info(__METHOD__);
			
		}
		Framework::startBench();		
		// Validate the cart.
		$this->validateCart($cart);
		
		Framework::bench('validateCart');
		
		// Refresh the prices infos.
		$this->refreshCartPrice($cart);
		Framework::bench('refreshCartPrice');
		
		$this->refreshDiscount($cart);
		Framework::bench('refreshDiscount');
		
		$this->refreshShipping($cart);
		Framework::bench('refreshShipping');
	
		$this->refreshCoupon($cart);
		Framework::bench('refreshCoupon');	
				
		// Cancel order process.
		order_OrderProcess::getInstance()->setCurrentStep(null);

		$this->saveToSession($cart);
		
		Framework::endBench(__METHOD__);
	}
	
	/**
	 * @param order_CartInfo $cart
	 */
	protected function refreshCartPrice($cart)
	{
		$cartLines = $cart->getCartLineArray();
		$shop = $cart->getShop();
		$customer = $cart->getCustomer();
		foreach ($cartLines as $cartLine)
		{
			$product = $cartLine->getProduct();
			$price = $product->getPrice($shop, $customer, $cartLine->getQuantity());
			$cartLine->importPrice($price);			
		}
	}
	
	/**
	 * @param order_CartInfo $cart
	 */
	public function refreshShipping($cart)
	{	
		$result = array();
		$updateRequiredFilter = false;
		$updateSelectFilter = false;
		foreach ($cart->getCartLineArray() as $index => $cartLine)
		{
			if ($cartLine->getShippingModeId())
			{
				$result[$cartLine->getShippingModeId()]['lines'][] = $index;
				$updateRequiredFilter = true;
			}
			else
			{
				$result[0]['lines'][] = $index;
				$updateSelectFilter = true;
			}		
		}
		$cart->setShippingArray($result);
		if ($updateRequiredFilter)
		{
			catalog_ShippingfilterService::getInstance()->setRequiredShippingModes($cart);	
		}
		
		if ($updateSelectFilter)
		{
			if ($cart->getAddressInfo() && $cart->getAddressInfo()->shippingFilterId)
			{
				$shippingFilter = DocumentHelper::getDocumentInstance($cart->getAddressInfo()->shippingFilterId);
				$cart->setRequiredShippingFilter(0, $shippingFilter);
			}
			else
			{
				$cart->setRequiredShippingFilter(0, null);
			}
		}
	}	

	/**
	 * @param order_CartInfo $cart
	 */
	protected function refreshDiscount($cart)
	{
		$result = array();
		if (ModuleService::getInstance()->isInstalled('marketing'))
		{
			$discountArray = marketing_DiscountService::getInstance()->getDiscountArrayForCart($cart);
			if (count($discountArray) > 0)
			{
				$subTotalWithoutTax = $cart->getSubTotalWithoutTax();
				$subTotalWithTax = $cart->getSubTotalWithTax();
				foreach ($discountArray as $discountDoc) 
				{
					$discount = new order_DiscountInfo();
					$discount->setId($discountDoc->getId());
					$value = $discountDoc->getValue();
					if ($discountDoc->getIsRate())
					{
						$discount->setValueWithoutTax($subTotalWithoutTax * $value);
						$discount->setValueWithTax($subTotalWithTax * $value);
					}
					else
					{
						$discount->setValueWithTax($value);
						$discount->setValueWithoutTax(($subTotalWithoutTax / $subTotalWithTax) * $value);
					}				
					$result[] = $discount;
				}
			}
		}
		$cart->setDiscountArray($result);
	}
	
	/**
	 * @param order_CartInfo $cart
	 */
	protected function refreshCoupon($cart)
	{
		if ($cart->hasCoupon())
		{
			$coupon = $cart->getCoupon();
			$document = DocumentHelper::getDocumentInstance($coupon->getId(), 'modules_marketing/coupon');
			$coupon->setLabel($document->getCode());
			if ($document->getIsRate())
			{
				$coupon->setValueWithoutTax($cart->getSubTotalWithoutTax() * $document->getValue());
				$coupon->setValueWithTax($cart->getSubTotalWithTax() * $document->getValue());
			}
			else
			{
				$coupon->setValueWithTax($document->getValue());
				$coupon->setValueWithoutTax(($cart->getSubTotalWithoutTax() / $cart->getSubTotalWithTax()) * $document->getValue());
			}
		}
	}

	/**
	 * Merge equivalent lines (same product/article), with adding quantities.
	 * Remove lines with quantities set to 0.
	 * Check products, cartrules and articles publication.
	 * Check if the qiantities are valid.
	 *
	 * @param order_CartInfo $cart
	 * @return Boolean
	 * @throws order_ValidationException
	 */
	protected function validateCart($cart)
	{
		// Clear error messages (error message are re-generated by this method each times it is called.
		$cart->clearErrorMessages();	
			
		// If there are lines, clean them.
		$cartLines = $cart->getCartLineArray();
		$removeCartLineIndex = array();
		foreach ($cartLines as $index => $cartLine)
		{
			if (!in_array($index, $removeCartLineIndex))
			{
				// Remove line with quantity set to 0.
				if ($cartLine->getQuantity() <= 0)
				{
					$removeCartLineIndex[] = $index;
					continue;
				}
				
				if (!$this->validateCartLine($cartLine, $cart))
				{
					$removeCartLineIndex[] = $index;
					continue;
				}
	
				// Merge equivalent lines.
				$eqCartLines = $this->getEquivalentCartLine($cart, $cartLine);
				foreach ($eqCartLines as $eqIndex => $eqCartLine)
				{
					if ($index != $eqIndex)
					{
						$cartLine->addToQuantity($eqCartLine->getQuantity());
						$removeCartLineIndex[] = $eqIndex;
					}
				}
			}
		}
		
		$cart->removeCartLines($removeCartLineIndex);

		// Refresh total weights.
		$this->refreshTotalWeights($cart);
		
		if (count($cart->getWarningMessageArray()) > 0 || count($cart->getErrorMessageArray()) > 0)
		{
			return false;
		}
		else
		{
			return true;
		}
	}
	
	/**
	 * @param order_CartLineInfo $cartLine
	 * @param order_CartInfo $cart
	 * @return Boolean
	 */
	protected function validateCartLine($cartLine, $cart)
	{
		try 
		{
			$product = $cartLine->getProduct();
			if ($product !== null && $product->isPublished())
			{
				$compiledProduct = $product->getDocumentService()->getPrimaryCompiledProductForWebsite($product, $cart->getShop()->getWebsite());
				if ($compiledProduct === null || !$compiledProduct->isPublished())
				{
					$replacements = array('articleLabel' => $product->getLabel());
					$cart->addWarningMessage(f_Locale::translate('&modules.order.frontoffice.cart-validation-error-unavailable-article-price;', $replacements));
					return false;
				}
				
				if ($product instanceof catalog_StockableDocument)
				{
					if (!catalog_StockService::getInstance()->isAvailable($product, $cartLine->getQuantity()))
					{
						$replacements = array('articleLabel' => $product->getLabel(), 
							'quantity' => $cartLine->getQuantity(), 'unit' => '', 
							'availableQuantity' => $product->getStockQuantity(), 'availableUnit' => '');
						$cart->addErrorMessage(f_Locale::translate('&modules.order.frontoffice.cart-validation-error-unavailable-article-quantity;', $replacements));
						return true;
					}
				}
				return true;
			}
		}			
		catch (Exception $e)
		{
			Framework::exception($e);
		}
		
		if ($product !== null)
		{
			$replacements = array('articleLabel' => $product->getLabel());
			$cart->addWarningMessage(f_Locale::translate('&modules.order.frontoffice.cart-validation-error-unavailable-article-price;', $replacements));
		}
		return false;
	}
	
	/**
	 * @return Integer
	 */
	protected function getCartLineNumberLimit()
	{
		return self::CARTLINE_NUMBER_LIMIT;
	}
	
	/**
	 * @param order_CartInfo $cart
	 * @param order_CartLineInfo $CartLine
	 * @return Array<order_CartLineInfo>
	 */
	protected function getEquivalentCartLine($cart, $cartLine)
	{
		$result = array();
		foreach ($cart->getCartLineArray() as $index => $otherCartLine)
		{
			if (($cartLine !== $otherCartLine) && $cartLine->getKey() == $otherCartLine->getKey())
			{
				$result[$index] = $otherCartLine;
			}
		}
		return $result;
	}
	
	/**
	 * @param order_CartInfo $cart
	 * @throw order_ValidationException
	 */
	protected function validateCartLineCount($cart)
	{
		$cartLines = $cart->getCartLineArray();		
		if (count($cartLines) >= $this->getCartLineNumberLimit())
		{
			throw new order_ValidationException("The cart can't hold more than " . $this->getCartLineNumberLimit() . " items");
		}
	}
	
	/**
	 * Return an array indexed by product ids and containing the quantities for each article.
	 * @param order_CartInfo $cart
	 * @return Array<Integer, Double>
	 */
	protected function getProductQuantityFromCart($cart)
	{
		$quantities = array();
		$cartLines = $cart->getCartLineArray();
		foreach ($cartLines as $cartLine)
		{
			$productId = $cartLine->getProductId();
			if (!isset($quantities[$productId]))
			{
				$quantities[$productId] = 0;
			}
			$quantities[$productId] += $cartLine->getQuantity();
		}

		return $quantities;
	}
	
	/**
	 * @return String
	 */
	protected function getSessionKey($catalogId)
	{
		return self::CART_SESSION_NAMESPACE . 'order_cart' . $catalogId;
	}
				
	/**
	 * @param customer_persistentdocument_address $address
	 * @param order_CartInfo $cart
	 * @return Boolean
	 */
	public function validateBillingAddress($address, $cart)
	{
		$isCountryInZone = zone_ZoneService::getInstance()->isCountryInZone($address->getCountry(), $cart->getShop()->getBillingZone());
		return $isCountryInZone && zone_CountryService::getInstance()->isZipCodeValid($address->getCountry()->getId(), $address->getZipCode());
	}
	
	/**
	 * @param customer_persistentdocument_address $address
	 * @param order_CartInfo $cart
	 * @return Boolean
	 */
	public function validateShippingAddress($address, $cart)
	{
		$isCountryInZone = zone_ZoneService::getInstance()->isCountryInZone($address->getCountry(), $cart->getShop()->getShippingZone());
		return $isCountryInZone && zone_CountryService::getInstance()->isZipCodeValid($address->getCountry()->getId(), $address->getZipCode());
	}
	
	/**
	 * This method validates that the cart is ready to start order process.
	 * Basically is just checks that the cart contains at least one product
	 * and there is no validation errors.
	 *
	 * @param order_CartInfo $cart
	 * @return Boolean
	 */
	public function canOrder($cart)
	{
		return (!is_null($cart) && !$cart->isEmpty() && count($cart->getErrorMessageArray()) === 0);
	}
	
	
	//DEPRECATED
	
	/**
	 * @deprecated use addProductToCart
	 * 
	 * @param catalog_persistentdocument_product $product
	 * @param Double $quantity
	 * @param Array<String, Mixed> $properties
	 * @return order_CartInfo The related cart
	 * @throws order_Exception
	 */
	public function addProduct($product, $quantity, $properties = array())
	{
		$cart = $this->getDocumentInstanceFromSession();
		if (!$this->addProductToCart($cart, $product, $quantity, $properties))
		{
			throw new order_Exception('Unable-to-add-product-to-cart');
		}
		return $cart;
	}
}
