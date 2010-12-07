<?php
/**
 * order_CartService
 * @package modules.order
 */
class order_CartService extends BaseService
{
	const CART_SESSION_NAMESPACE = 'order_cart';
	const CARTLINE_NUMBER_LIMIT = 100;
	
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
		else
		{
			$this->clearCartIfNeeded($cart);
		}
		return $cart;
	}
	
	/**
	 * @param order_CartInfo $cart
	 */
	protected function clearCartIfNeeded(&$cart)
	{
		if (Framework::isInfoEnabled())
		{
			Framework::info(__METHOD__. '  ' . $cart->getCartLineCount());
		}
		$orderId = $cart->getOrderId();
		if (intval($orderId) > 0 && order_BillService::getInstance()->hasBillInTransactionByOrderId($orderId))
		{
			$this->clearCart($cart);
		}
	}
	
	/**
	 * 
	 * @param order_CartInfo $cart
	 */
	public function clearCart(&$cart)
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
		$cartInfo->setOrderId(null);
		return $cartInfo;	
	}
	
	/**
	 * @param order_CartInfo $cart
	 */
	public function resetCartOrder($cart)
	{	
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
		if ($cart->getOrderId() && $cart->isModified())
		{
			$this->resetCartOrder($cart);
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
		
		// Check if the product we're trying to add to the cart is not already there with a quantity than can't be changed.
		if (!$product->updateCartQuantity() && $this->getCartLineByKey($cart, $product->getCartLineKey()) !== null )
		{
			$cart->addErrorMessage(f_Locale::translate('&modules.order.frontoffice.cart-validation-quantity-fixed-for-product;'));
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
			$replacements = array('articleLabel' => $product->getLabelAsHtml());
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
			$stDoc = catalog_StockService::getInstance()->getStockableDocument($product);
			if ($stDoc !== null)
			{
				if (!catalog_StockService::getInstance()->isAvailable($product, $quantity))
				{
					$replacements = array('articleLabel' => $product->getLabelAsHtml(), 
						'quantity' => $quantity, 'unit' => '', 
						'availableQuantity' => $stDoc->getCurrentStockQuantity(), 'availableUnit' => '');
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
			if ($cart->hasCoupon())
			{
				$cart->setCoupon(null);
				$this->refreshDiscount($cart);
			}
		}
		else
		{
			$couponinfo = new order_CouponInfo();
			$couponinfo->setId($coupon->getId());
			$cart->setCoupon($couponinfo);
			$this->refreshCoupon($cart);
			$this->refreshDiscount($cart);
		}
		return $cart->getCoupon();
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
		
		$this->refreshCoupon($cart);
		Framework::bench('refreshCoupon');			
		
		$this->refreshShipping($cart);
		Framework::bench('refreshShipping');
	
		$this->refreshDiscount($cart);
		Framework::bench('refreshDiscount');
				
		// Cancel order process.
		order_OrderProcessService::getInstance()->resetSessionOrderProcess();

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
		if (ModuleService::getInstance()->isInstalled('marketing'))
		{
			marketing_DiscountService::getInstance()->refreshDiscountArrayForCart($cart);	
		}
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
			if ($document->getDocumentService()->validateForCart($document, $cart))
			{
				$coupon->setLabel($document->getCode());
			}
			else
			{
				$cart->setCoupon(null);						
			}
		}
	}

	/**
	 * Merge equivalent lines (same product/article), with adding quantities.
	 * Remove lines with quantities set to 0.
	 * Check products, publication and stocks.
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
		$globalProductsArray = array();
		
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
				
				if (!$this->validateCartLine($cartLine, $cart, $globalProductsArray))
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
		
		$unavailableProducts = catalog_StockService::getInstance()->validCartQuantities($globalProductsArray, $cart);
		foreach ($unavailableProducts as $productInfo)
		{
			$product = $productInfo[0];
			$stDoc = catalog_StockService::getInstance()->getStockableDocument($product);
			$stockQuantity = $stDoc !== null ? $stDoc->getCurrentStockQuantity() : 0;
			$replacements = array('articleLabel' => $product->getLabelAsHtml(), 
					'quantity' => $productInfo[1], 'unit' => '', 
					'availableQuantity' => $stockQuantity, 'availableUnit' => '');
			$cart->addErrorMessage(f_Locale::translate('&modules.order.frontoffice.cart-validation-error-unavailable-article-quantity;', $replacements));
		}
		
		$cart->removeCartLines($removeCartLineIndex);
		
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
	 * @param array $globalProductsArray
	 * @return Boolean
	 */
	protected function validateCartLine($cartLine, $cart, &$globalProductsArray)
	{
		try 
		{
			$product = $cartLine->getProduct();
			if ($product !== null && $product->isPublished())
			{
				$compiledProduct = $product->getDocumentService()->getPrimaryCompiledProductForWebsite($product, $cart->getShop()->getWebsite());
				if ($compiledProduct === null || !$compiledProduct->isPublished())
				{
					$replacements = array('articleLabel' => $product->getLabelAsHtml());
					$cart->addWarningMessage(f_Locale::translate('&modules.order.frontoffice.cart-validation-error-unavailable-article-price;', $replacements));
					return false;
				}
				
				if ($product instanceof catalog_BundleProduct)
				{
					 foreach ($product->getBundledProducts() as $bundledProduct)
					 {
					 	 $productQty = $bundledProduct->getQuantity() * $cartLine->getQuantity();
					 	 $this->addProductToGlobalProductsArray($bundledProduct->getProduct(), $productQty, $globalProductsArray);
					 }
				}
				else
				{
					 $productQty = $cartLine->getQuantity();
					 $this->addProductToGlobalProductsArray($product, $productQty, $globalProductsArray);
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
			$replacements = array('articleLabel' => $product->getLabelAsHtml());
			$cart->addWarningMessage(f_Locale::translate('&modules.order.frontoffice.cart-validation-error-unavailable-article-price;', $replacements));
		}
		return false;
	}
	
	/**
	 * @param catalog_persistentdocument_product $product
	 * @param double $productQty
	 * @param array $globalProductsArray
	 */
	protected function addProductToGlobalProductsArray($product, $productQty, &$globalProductsArray)
	{
		 $productId = $product->getId();
		 if (!isset($globalProductsArray[$productId]))
		 {
		 	$globalProductsArray[$productId] = array($product, $productQty);
		 }
		 else
		 {
		 	 $globalProductsArray[$productId][1] += $productQty;
		 }
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
