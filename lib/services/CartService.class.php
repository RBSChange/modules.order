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
	 * @return boolean
	 */
	public function hasCartInSession()
	{
		$session = Controller::getInstance()->getContext()->getUser();
		$key = $this->getSessionKey('CartInfo');
		$ns = self::CART_SESSION_NAMESPACE;
		return ($session->hasAttribute($key, $ns) && $session->getAttribute($key, $ns) instanceof order_CartInfo);
	}
	
	/**
	 * @return order_CartInfo
	 */
	public function getDocumentInstanceFromSession()
	{	
		if ($this->hasCartInSession())
		{
			$session = Controller::getInstance()->getContext()->getUser();
			$cart = $session->getAttribute($this->getSessionKey('CartInfo'), self::CART_SESSION_NAMESPACE);
			$this->clearCartIfNeeded($cart);
		}
		else
		{
			$cart = $this->initNewCart();
			$this->saveToSession($cart);
		}
		$this->initContextCartInfo($cart);
		return $cart;
	}

	/**
	 * @param order_CartInfo $cart
	 */
	public function getCartUrl($cart)
	{
		if ($cart->getShopId())
		{
			$website = $cart->getShop()->getWebsite();
		}
		else
		{
			$website = website_WebsiteModuleService::getInstance()->getCurrentWebsite();
		}
		
		$page = null;
		if ($cart->isEmpty())
		{
			$page = TagService::getInstance()->getDocumentByContextualTag('contextual_website_website_modules_order_cart-empty', $website, false);
		}
		if ($page === null)
		{
			$page = TagService::getInstance()->getDocumentByContextualTag('contextual_website_website_modules_order_cart', $website, true);
		}
		
		return LinkHelper::getDocumentUrl($page === null ? $website : $page);
	}
	
	/**
	 * @param order_CartInfo $cart
	 */
	protected function clearCartIfNeeded(&$cart)
	{
		$orderId = $cart->getOrderId();
		if (intval($orderId) > 0 && order_BillService::getInstance()->hasBillInTransactionByOrderId($orderId))
		{
			$this->clearCart($cart);
		}
	}
	
	/**
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
			if ($customer !== null)
			{
				$customer->setLastAbandonedOrderDate(null);
			}
			
			$cartId = $cart->getUid();
			$cart = $this->initNewCart();
			$this->initContextCartInfo($cart);
			$cart->setUid($cartId);
			
			$this->saveToSession($cart);
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
		$cartInfo->setShop(null);
		$cartInfo->setOrderId(null);	
		$cartInfo->setUid(session_id());	
		return $cartInfo;	
	}
	
	/**
	 * @param order_CartInfo $cart
	 */
	protected function initContextCartInfo($cart)
	{
		$customer = customer_CustomerService::getInstance()->getCurrentCustomer();
		if ($customer !== null)
		{
			$cart->setCustomerId($customer->getId());
			$cart->setUserId($customer->getUser()->getId());
		}		
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
		$order = DocumentHelper::getDocumentInstanceIfExists($cart->getOrderId());
		if ($order instanceof order_persistentdocument_order)
		{
			$order->getDocumentService()->resetForCart($order, $cart);
		}
		else
		{
			$cart->setOrderId(null);
		}
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
	 * @return boolean
	 */
	public function addProductToCart($cart, $product, $quantity = 1)
	{	
		$ls = LocaleService::getInstance();
		if (!($cart instanceof order_CartInfo) || !($product instanceof catalog_persistentdocument_product) || $quantity <= 0)
		{
			Framework::warn(__METHOD__ . ' Invalid arguments');
			return false;
		}

		// Get properties and line key.
		$properties = array();
		$product->getDocumentService()->getProductToAddToCart($product, $cart->getShop(), $quantity, $properties);
		$cartLineKey = $product->getCartLineKey();
		
		// Check if the product we're trying to add to the cart is not already there with a quantity than can't be changed.
		if (!$product->updateCartQuantity() && $this->getCartLineByKey($cart, $cartLineKey) !== null )
		{
			$cart->addTransientErrorMessage($ls->transFO('m.order.frontoffice.cart-validation-quantity-fixed-for-product', array('ucf')));
			return false;
		}
		
		try 
		{
			 $this->validateCartLineCount($cart);
			 $this->resetCartOrder($cart);
		}
		catch (Exception $e)
		{
			$cart->addTransientErrorMessage($ls->transFO('m.order.frontoffice.cart-validation-error-max-lines'));
			return false;
		}
		
		// If the article has no price the line is not added and a warning message is displayed.
		if (!$this->validateProduct($cart, $product, $quantity))
		{
			return false;
		}
		else
		{
			$cartLine = $this->getCartLineByKey($cart, $cartLineKey);
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
		return true;
	}	
	
	
	/**
	 * @param order_CartInfo $cart
	 * @param catalog_persistentdocument_product $product
	 * @param double $quantity
	 */
	private function validateProduct($cart, $product, $quantity)
	{
		$shop = $cart->getShop();
		$billingArea =  $cart->getBillingArea();
		if ($product->isPublished() && $product->canBeAddedToCart($product->getPrice($shop, $billingArea, $cart->getCustomer(), $quantity)))
		{
			$stDoc = catalog_StockService::getInstance()->getStockableDocument($product);
			if ($stDoc !== null)
			{
				if (!$shop->getAllowOrderOutOfStock() && !catalog_StockService::getInstance()->isAvailable($product, $quantity))
				{
					$replacements = array('articleLabel' => $product->getLabelAsHtml(), 
						'quantity' => $quantity, 'availableQuantity' => $stDoc->getCurrentStockQuantity());
					$cart->addTransientErrorMessage(LocaleService::getInstance()->transFO('m.order.frontoffice.cart-validation-error-unavailable-article-quantity', array('ucf'), $replacements));
					return false;					
				}
			}
			return true;
		}
		
		$replacements = array('articleLabel' => $product->getLabelAsHtml());
		$cart->addTransientErrorMessage(LocaleService::getInstance()->transFO('m.order.frontoffice.cart-validation-error-unavailable-article-price', array('ucf'), $replacements));
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
	 * @param customer_persistentdocument_coupon $coupon
	 */
	public function setCoupon($cart, $coupon)
	{
		if ($coupon === null)
		{
			if ($cart->hasCoupon())
			{
				$cart->setCoupon(null);
				$this->refreshModifiers($cart);
			}
		}
		else
		{
			$couponinfo = new order_CouponInfo();
			$couponinfo->setId($coupon->getId());
			$cart->setCoupon($couponinfo);
			$this->refreshCoupon($cart);
			$this->refreshModifiers($cart);
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
	 * @param boolean $resetSessionOrderProcess
	 * @throws order_ValidationException
	 */
	public function refresh($cart, $resetSessionOrderProcess = true)
	{
		if (Framework::isInfoEnabled())
		{
			Framework::info(__METHOD__ . ' uid: '. $cart->getUid() . ' resetSession:' . $resetSessionOrderProcess);
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
			
			$this->refreshModifiers($cart);
			Framework::bench('refreshModifiers');
			
			$this->refreshTax($cart);
			Framework::bench('refreshTax');
			
			$this->refreshCreditNote($cart);
			Framework::bench('refreshCreditNote');
				
		if ($resetSessionOrderProcess)
		{
			order_OrderProcessService::getInstance()->resetSessionOrderProcess();
		}
	
		$this->saveToSession($cart);
		
		Framework::endBench(__METHOD__);
	}
	
	/**
	 * @param order_CartInfo $cart
	 */
	protected function refreshCartPrice($cart)
	{
		$shop = $cart->getShop();
		if ($shop)
		{
			foreach ($cart->getCartLineArray() as $cartLine)
			{
				/* @var $cartLine order_CartLineInfo */
				$cartLine->importPrice($cartLine->getPrice());
			}
		}
		else
		{
			$cart->setTaxZone(null);
			$cart->setCartLineArray(array());
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
	protected function refreshModifiers($cart)
	{
		order_CartmodifierService::getInstance()->refreshModifiersForCart($cart);
	}

	/**
	 * @param order_CartInfo $cart
	 */
	protected function refreshTax($cart)
	{
		$result = array();
		$valueWithTax = 0.0;
		
		foreach ($cart->getCartLineArray() as $cartLineInfo) 
		{
			/* @var $cartLineInfo order_CartLineInfo */
			$valueWithTax += $cartLineInfo->getTotalValueWithTax();			
			foreach ($cartLineInfo->getTaxArray() as $rateKey => $value)
			{
				if (isset($result[$rateKey]))
				{
					$result[$rateKey] += $value;
				}
				else
				{
					$result[$rateKey] = $value;
				}
			}			
		}
		
		foreach ($cart->getFeesArray() as $fees)
		{
			/* @var $fees order_CartModifierInfo */
			
			$value = $fees->getValueWithTax() - $fees->getValueWithoutTax();
			if ($value > 0)
			{
				$rateKey = $fees->getFormattedTaxRate();
				$valueWithTax += $fees->getValueWithTax();
				
				if (isset($result[$rateKey]))
				{
					$result[$rateKey] += $value;
				}
				else
				{
					$result[$rateKey] = $value;
				}
			}			
		}
		
		$totalTax = array_sum($result);
		$valueWithoutTax = $valueWithTax - $totalTax;
		
		if ($valueWithoutTax > 0)
		{
			$globalTaxRate = ($valueWithTax / $valueWithoutTax) - 1;
			foreach ($cart->getDiscountArray() as $discountInfo) 
			{
				$value = $discountInfo->getValueWithTax();
				if ($value > 0)
				{
					$wwt = $value/(1+$globalTaxRate);
					$taxe = $value - $wwt;
					$discountInfo->setValueWithoutTax($wwt);
					foreach ($result as $rateKey => $rateValue) 
					{
						$result[$rateKey] -= ($taxe / $totalTax) * $rateValue;
					}
					$totalTax = array_sum($result);
				}
			}
		}
		$cart->setTaxRates($result);
	}	
	
	/**
	 * @param order_CartInfo $cart
	 */
	protected function refreshCreditNote($cart)
	{
		order_CreditnoteService::getInstance()->refreshCreditnoteArrayForCart($cart);	
	}
	
	/**
	 * @param order_CartInfo $cart
	 */
	protected function refreshCoupon($cart)
	{
		if ($cart->hasCoupon())
		{
			$coupon = $cart->getCoupon();
			$document = customer_persistentdocument_coupon::getInstanceById($coupon->getId());
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
		// Clear persistent error messages (they are re-generated by this method each times it is called).
		$cart->clearPersistentErrorMessages();	
		
		// Set billing area and tax zone
		$shop = $cart->getShop();
		$billingArea = $shop->getCurrentBillingArea(true);
		$cart->setBillingArea($billingArea);
		$cart->setTaxZone(catalog_TaxService::getInstance()->getCurrentTaxZone($shop, $cart, true));
		
		// If there are lines, clean them.
		$removeCartLineIndex = array();
		foreach ($cart->getCartLineArray() as $index => $cartLine)
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
		
		// Check stocks.
		$unavailableProducts = catalog_StockService::getInstance()->validateCart($cart);
		foreach ($unavailableProducts as $productInfo)
		{
			$product = $productInfo[0];
			$stDoc = catalog_StockService::getInstance()->getStockableDocument($product);
			$stockQuantity = $stDoc !== null ? $stDoc->getCurrentStockQuantity() : 0;
			$replacements = array('articleLabel' => $product->getLabelAsHtml(), 
				'quantity' => $productInfo[1], 'availableQuantity' => $stockQuantity);
			$cart->addPersistentErrorMessage(LocaleService::getInstance()->transFO('m.order.frontoffice.cart-validation-error-unavailable-article-quantity', array('ucf'), $replacements));
		}
		
		// Check minimum quantities.
		foreach ($cart->getCartLineArray() as $index => $cartLine)
		{
			/* @var $cartLine order_CartLineInfo */
			$product = $cartLine->getProduct();
			if ($product->getMinOrderQuantity() > $cartLine->getQuantity())
			{
				$replacements = array('articleLabel' => $product->getLabelAsHtml(), 
					'quantity' => $cartLine->getQuantity(), 'minQuantity' => $product->getMinOrderQuantity());
				$cart->addPersistentErrorMessage(LocaleService::getInstance()->transFO('m.order.frontoffice.cart-validation-error-min-quantity', array('ucf'), $replacements));
			}
		}
		
		return count($cart->getPersistentErrorMessages()) == 0 && count($cart->getTransientErrorMessages()) == 0;
	}
	
	/**
	 * @param order_CartLineInfo $cartLine
	 * @param order_CartInfo $cart
	 * @return Boolean
	 */
	protected function validateCartLine($cartLine, $cart)
	{
		$ls = LocaleService::getInstance();
		try 
		{
			$product = $cartLine->getProduct();
			if ($product !== null && $product->isPublished())
			{
				$shop = $cart->getShop();
				$compiledProduct = $product->getDocumentService()->getPrimaryCompiledProductForShop($product, $shop);
				if ($compiledProduct === null || ((!$compiledProduct->isPublished()) && ($compiledProduct->getPublicationCode() != 5)))
				{
					$replacements = array('articleLabel' => $product->getLabelAsHtml());
					$cart->addTransientErrorMessage($ls->transFO('m.order.frontoffice.cart-validation-error-unavailable-article-price', array('ucf'), $replacements));
					return false;
				}
				
				$price = $product->getPrice($shop, $cart->getBillingArea(), $cart->getCustomer(), $cartLine->getQuantity());
				if ($price === null)
				{
					$cartLine->setPrice(null);
					$replacements = array('articleLabel' => $product->getLabelAsHtml());
					$cart->addPersistentErrorMessage($ls->transFO('m.order.frontoffice.cart-validation-error-unavailable-article-price', array('ucf'), $replacements));
				}
				else
				{
					
					$cartLine->setPrice($price);
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
			$cart->addTransientErrorMessage($ls->transFO('m.order.frontoffice.cart-validation-error-unavailable-article-price', array('ucf'), $replacements));
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
	 * @param order_CartInfo $cart
	 * @return Boolean
	 */
	public function validateBillingAddress($cart)
	{
		$countryId = $cart->getAddressInfo()->billingAddress->CountryId;
		if ($countryId)
		{
			$country = zone_persistentdocument_country::getInstanceById($countryId);
			return zone_ZoneService::getInstance()->isCountryInZone($country, $cart->getBillingArea()->getBillingAddressZone());
		}
		return false;
	}
	
	/**
	 * @param order_CartInfo $cart
	 * @return Boolean
	 */
	public function validateShippingAddress($cart)
	{
		$taxZone = catalog_TaxService::getInstance()->getCurrentTaxZone($cart->getShop(), $cart);
		return $taxZone !== null;
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
		return ($cart !== null && !$cart->isEmpty() && $cart->isValid());
	}
	
	/**
	 * @param order_CartInfo $cart
	 * @return order_persistentdocument_order
	 */
	public function createOrder($cart)
	{
		return order_OrderService::getInstance()->createFromCartInfo($cart);
	}
		
	/**
	 * @param order_CartInfo $cart
	 * @param catalog_persistentdocument_shop $shop
	 * @param catalog_persistentdocument_product[] $products
	 * @param integer[] $quantity
	 * @param boolean $askConfirmation
	 * @param array $paramsToRedirect
	 * @return boolean
	 */
	public function checkAddToCart($cart, $shop, &$products, &$quantities, $askConfirmation, $paramsToRedirect = array())
	{
		$ls = LocaleService::getInstance();
		
		// Check website.
		$currentWebsite = website_WebsiteModuleService::getInstance()->getCurrentWebsite();
		if ($currentWebsite->getId() != $shop->getWebsite()->getId())
		{
			$cart->addTransientErrorMessage($ls->transFO('m.order.fo.shop-in-other-website', array('ucf')));
			return false;
		}
		
		if (!$cart->getShopId() || $cart->isEmpty())
		{
			$cart->setShop($shop);
		}
		else if ($cart->getShopId() !== $shop->getId())
		{
			if (!$askConfirmation)
			{
				return false;
			}
			
			if (!isset($paramsToRedirect['website_BlockAction_submit']))
			{
				$paramsToRedirect['website_BlockAction_submit'] = array();
			}
			if (!isset($paramsToRedirect['website_BlockAction_submit']['cart']))
			{
				$paramsToRedirect['website_BlockAction_submit']['cart'] = array();
			}
			$paramsToRedirect['website_BlockAction_submit']['cart']['confirmClear'] = true;
			$paramsToRedirect['message'] = $ls->transFO('m.order.fo.confirm-incompatible-shop', array('ucf'));
			
			// Get the page tag to redirect.
			if (isset($paramsToRedirect['confirmPageTag']))
			{
				$tag = $paramsToRedirect['confirmPageTag'];
				unset($paramsToRedirect['confirmPageTag']);
			}
			else 
			{
				$tag = 'contextual_website_website_modules_order_cart';
			}
			
			// Generate the URL to redirect.
			if (isset($paramsToRedirect['confirmPagePopin']) && $paramsToRedirect['confirmPagePopin'] == 'true')
			{
				$website = website_WebsiteModuleService::getInstance()->getCurrentWebsite();
				$page = TagService::getInstance()->getDocumentByContextualTag($tag, $website);
				$url = LinkHelper::getActionUrl('website', 'PopIn', array('pageref' => $page->getId(), 'orderParam' => $paramsToRedirect));
			}
			else
			{
				$url = LinkHelper::getTagUrl($tag, null, array('orderParam' => $paramsToRedirect));
			}
			unset($paramsToRedirect['confirmPagePopin']);
			
			HttpController::getInstance()->redirectToUrl($url);
		}
		return true;
	}
	
	/**
	 * 
	 * @param order_CartInfo $sessionCart
	 * @param order_CartInfo $cart
	 * @param customer_persistentdocument_customer $customer
	 */
	public function mergeCustomerCart($sessionCart, $cart, $customer)
	{
		if (Framework::isInfoEnabled())
		{
			Framework::info(__METHOD__);
		}
		$shopId = intval($cart->getShopId());
		$shop = catalog_ShopService::getInstance()->createQuery()->add(Restrictions::eq('id', $shopId))->findUnique();
		if ($shop === null)
		{
			Framework::warn(__METHOD__ . ' Invalid cart ' . $shopId .' cleaned');
			$customer->setCart(null);
			$customer->save();
			return;
		}	

		$sessionCart->setMergeWithUserCart(false);
		$recup = $sessionCart->isEmpty();				
		$products = array();
		$quantities = array();
		foreach ($cart->getCartLineArray() as $line)
		{
			try 
			{
				$product = $line->getProduct();
				if ($product !== null)
				{
					$products[] = $product;
					$quantities[] = $line->getQuantity();
				}
			}
			catch (Exception $e)
			{
				Framework::warn(__METHOD__ . ' ' . $e->getMessage());
			}
		}
		
		// Add products.
		
		if ($this->checkAddToCart($sessionCart, $shop, $products, $quantities, false))
		{
			$added = false;
			foreach ($products as $key => $product)
			{
				if ($this->addProductToCart($sessionCart, $product, $quantities[$key]))
				{
					$added = true;
				}
			}
			
			if ($added)
			{
				$key = ($recup) ? 'm.order.frontoffice.cart-recup' : 'm.order.frontoffice.cart-fusion';
				$sessionCart->addSuccessMessage(LocaleService::getInstance()->transBO($key, array('ucf')));
			}
		}
		
		$this->refresh($sessionCart);
	}
	
	// Deprecated
	
	/**
	 * @deprecated (will be removed in 4.0) use addProductToCart
	 */
	public function addProduct($product, $quantity, $properties = array())
	{
		$cart = $this->getDocumentInstanceFromSession();
		if (!$this->addProductToCart($cart, $product, $quantity))
		{
			throw new order_Exception('Unable-to-add-product-to-cart');
		}
		return $cart;
	}
}