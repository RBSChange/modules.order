<?php
/**
 * order_OrderService
 * @package modules.order
 */
class order_OrderService extends f_persistentdocument_DocumentService
{
	const INITIATED = "initiated";
	const IN_PROGRESS = "in_progress";
	const CANCELED = "canceled";
	const COMPLETE = "complete";
		
	const MESSAGE_FROM_USER = 'modules_order/messageFromCustomer';
	const MESSAGE_TO_USER = 'modules_order/messageToCustomer';

	const PROPERTIES_CART_MODIFICATOR = '__cartModificators';
	const PROPERTIES_CART_PROPERTIES = '__cartProperties';

	const ORDER_STATUS_MODIFIED_EVENT = 'order_orderStatusChanged';

	/**
	 * @var order_OrderService
	 */
	private static $instance;

	/**
	 * @return order_OrderService
	 */
	public static function getInstance()
	{
		if (self::$instance === null)
		{
			self::$instance = self::getServiceClassInstance(get_class());
		}
		return self::$instance;
	}

	/**
	 * @return order_persistentdocument_order
	 */
	public function getNewDocumentInstance()
	{
		return $this->getNewDocumentInstanceByModelName('modules_order/order');
	}

	/**
	 * Create a query based on 'modules_order/order' model
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createQuery()
	{
		return $this->pp->createQuery('modules_order/order');
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 */
	public function getFinancialInfos($order)
	{
		$result = array();
		
		$billingAddress = $order->getBillingAddress();
		$address = array();
		$address['label'] = $billingAddress->getDocumentService()->getFullName($billingAddress);
		$address['line1'] = $billingAddress->getAddressLine1();
		$address['line2'] = $billingAddress->getAddressLine2();
		$address['line3'] = $billingAddress->getAddressLine3();
		$address['zipAndCity'] = $billingAddress->getZipcode() . ' ' . $billingAddress->getCity();
		$address['province'] = $billingAddress->getProvince();
		$address['country'] = $billingAddress->getCountry()->getLabel();
		$address['phone'] = $billingAddress->getPhone();
		$address['fax'] = $billingAddress->getFax();	
		$result['address'] = $address;
		$result['totalAmount'] = $order->formatPrice($order->getTotalAmountWithTax());
		
		$result['billArray'] = order_BillService::getInstance()->getBoList($order);
		$result['creditnoteArray'] = order_CreditnoteService::getInstance()->getBoList($order);
		
		return $result;
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 */
	public function getShippingInfos($order)
	{
		$result = array();
		$shippingAddress = $order->getShippingAddress();
		$address = array();
		$address['label'] = $shippingAddress->getDocumentService()->getFullName($shippingAddress);
		$address['line1'] = $shippingAddress->getAddressLine1();
		$address['line2'] = $shippingAddress->getAddressLine2();
		$address['line3'] = $shippingAddress->getAddressLine3();
		$address['zipAndCity'] = $shippingAddress->getZipcode() . ' ' . $shippingAddress->getCity();
		$address['province'] = $shippingAddress->getProvince();
		$address['country'] = $shippingAddress->getCountry()->getLabel();
		$address['phone'] = $shippingAddress->getPhone();
		$address['fax'] = $shippingAddress->getFax();	
		$result['address'] = $address;
		
		$result['expeditionArray'] = order_ExpeditionService::getInstance()->getBoList($order);
		if (f_util_ArrayUtils::isEmpty($result['expeditionArray']))
		{
			$status = $order->getOrderStatus();
			if ($status == order_OrderService::IN_PROGRESS && order_BillService::getInstance()->hasPublishedBill($order))
			{
				$result['showExpeditionMessage'] = true;
				if (order_ModuleService::getInstance()->isDefaultExpeditionGenerationEnabled())
				{
					$result['allowCreatedefaultExpedition'] = true;
				}
			}
		}
		
		if (ModuleService::getInstance()->moduleExists('productreturns'))
		{
			$result['returnsArray'] = productreturns_BasereturnService::getInstance()->getBoList($order);
		}
		
		return $result;
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 */
	public function getPropertyInfos($order)
	{
		$result = array();
		$informations = array();
				
		$informations['canBeCanceled'] = $order->canBeCanceled();
		$informations['reference'] = $order->getOrderNumber();
		$dateTimeFormat = customer_ModuleService::getInstance()->getUIDateTimeFormat();
		$informations['creationdate'] = date_DateFormat::format($order->getUICreationdate(), $dateTimeFormat);
		
		try 
		{
		 	$informations['website'] = $order->getWebsite()->getLabel();
		}
		catch (Exception $e)
		{
			$informations['website'] = '';
		}
		
		$customer = $order->getCustomer();
		$informations['customerId'] = $customer->getId();
		$informations['email'] = $customer->getUser()->getEmail();	

		
		$informations['subTotal'] =  $order->formatPrice($order->getLinesAmountWithTax());
		
		$couponId = $order->getCouponId();
		if (intval($couponId) > 0)
		{
			$coupon = $order->getCouponData();
			try 
			{
				$couponDocument = customer_persistentdocument_coupon::getInstanceById($couponId);
				$couponLabel = $couponDocument->getLabel();
			}
			catch (Exception $e)
			{
				$couponLabel = $coupon['code'];
				Framework::info("Coupon $couponId not found :" . $e->getMessage());
			}
		
			$informations['couponName'] = $couponLabel;
			$informations['couponSectionName'] = $couponLabel;
			$informations['couponSectionCode'] = $coupon['code'];
		}	
		
		$result['discountDataArray'] = array();
		if ($order->hasDiscount())
		{
			$informations['discountTotal'] = $order->formatPrice($order->getDiscountTotalWithTax());
			foreach ($order->getDiscountDataArray() as $data) 
			{
				$result['discountDataArray'][] = array(
					'label' => 	$data['label'],
					'valueWithTax' => 	$order->formatPrice($data['valueWithTax']),
					'valueWithoutTax' => $order->formatPrice($data['valueWithoutTax']),
				);
			}
		}

		$tvaAmounts = array();
		foreach ($order->getTotalTaxInfoArray() as $subTotal)
		{
			if ($subTotal['taxAmount'] > 0)
			{
				$tvaAmounts[] = $subTotal['formattedTaxRate'] . ' : ' . $order->formatPrice($subTotal['taxAmount']);
			}
		}
		$informations['tvaAmounts'] = implode(', ', $tvaAmounts);
		$usedCreditNote = $order->getTotalCreditNoteAmount();
		$informations['usedCreditNote'] = ($usedCreditNote) ? $order->formatPrice($usedCreditNote) : null;
		$informations['totalAmount'] = $order->formatPrice($order->getTotalAmountWithTax());
		
		$result['informations'] = $informations;
		
		$result['lines'] = array();
		foreach ($order->getLineArray() as $line)
		{
			$lineInfo = $this->getLineInfo($line, 'cart-line', $order);
			if ($lineInfo !== null)
			{
				$result['lines'][] = $lineInfo;
			}
		}
		return $result;
	}

	/**
	 * @param order_persistentdocument_order $order
	 * @return array the order info
	 */
	public function getInfo($order)
	{
		$customer = $order->getCustomer();
		
		$dateTimeFormat = customer_ModuleService::getInstance()->getUIDateTimeFormat();

		$result = array();
		$result['packageTrackingNumber'] = $order->getPackageTrackingNumber();

		// Read-only informations.
		$informations = array();
		$informations['canBeCanceled'] = $order->canBeCanceled();
		$informations['customerId'] = $customer->getId();
		$informations['email'] = $customer->getUser()->getEmail();

		$billingAddress = $order->getBillingAddress();
		$informations['billingAddressLabel'] = $billingAddress->getDocumentService()->getFullName($billingAddress);
		$informations['billingAddressLine1'] = $billingAddress->getAddressLine1();
		$informations['billingAddressLine2'] = $billingAddress->getAddressLine2();
		$informations['billingAddressLine3'] = $billingAddress->getAddressLine3();
		$informations['billingAddressZipAndCity'] = $billingAddress->getZipcode() . ' ' . $billingAddress->getCity();
		$informations['billingAddressProvince'] = $billingAddress->getProvince();
		$informations['billingAddressCountry'] = $billingAddress->getCountry()->getLabel();
		$informations['billingAddressPhone'] = $billingAddress->getPhone();
		$informations['billingAddressFax'] = $billingAddress->getFax();

		$shippingAddress = $order->getShippingAddress();
		$informations['shippingAddressLabel'] = $shippingAddress->getDocumentService()->getFullName($shippingAddress);
		$informations['shippingAddressLine1'] = $shippingAddress->getAddressLine1();
		$informations['shippingAddressLine2'] = $shippingAddress->getAddressLine2();
		$informations['shippingAddressLine3'] = $shippingAddress->getAddressLine3();
		$informations['shippingAddressZipAndCity'] = $shippingAddress->getZipcode() . ' ' . $shippingAddress->getCity();
		$informations['shippingAddressProvince'] = $shippingAddress->getProvince();
		$informations['shippingAddressCountry'] = $shippingAddress->getCountry()->getLabel();
		$informations['shippingAddressPhone'] = $shippingAddress->getPhone();
		$informations['shippingAddressFax'] = $shippingAddress->getFax();

		if (count(website_WebsiteService::getInstance()->getAll()) > 0)
		{
			$informations['website'] = $customer->getWebsite()->getLabel();
		}
		$informations['reference'] = $order->getOrderNumber();
		$informations['creationdate'] = date_DateFormat::format($order->getUICreationdate(), $dateTimeFormat);
		$informations['shippingMode'] = $order->getShippingMode();

		$informations['subTotal'] =  $order->formatPrice($order->getLinesAmountWithTax());
		$couponId = $order->getCouponId();
		if (intval($couponId) > 0)
		{
			$coupon = $order->getCouponData();
			try 
			{
				$couponDocument = customer_persistentdocument_coupon::getInstanceById($couponId);
				$couponLabel = $couponDocument->getLabel();
			}
			catch (Exception $e)
			{
				$couponLabel = $coupon['code'];
				Framework::info("Coupon $couponId not found :" . $e->getMessage());
			}
		
			$informations['couponName'] = $couponLabel;
			$informations['couponSectionName'] = $couponLabel;
			$informations['couponSectionCode'] = $coupon['code'];
		}
		
		if ($order->hasDiscount())
		{
			$informations['discountTotal'] = $order->formatPrice($order->getDiscountTotalWithTax());
			$result['discountDataArray'] = $order->getDiscountDataArray();
		}
		
		$informations['subTotalWithModificators'] = $order->formatPrice(-1);
		$informations['shippingMode'] = $order->getShippingMode();
		$informations['shippingFees'] = $order->formatPrice($order->getShippingFeesWithTax());

		$informations['billingMode'] = $order->getBillingMode();
		$tvaAmounts = array();
		foreach ($order->getTotalTaxInfoArray() as $subTotal)
		{
			$tvaAmounts[] = $subTotal['formattedTaxRate'] . ' : ' . $order->formatPrice($subTotal['taxAmount']);
		}
		$informations['tvaAmounts'] = implode(', ', $tvaAmounts);
		$informations['totalAmount'] = $order->formatPrice($order->getTotalAmountWithTax());

		$result['informations'] = $informations;

		// Lines.
		$result['lines'] = array();
		foreach ($order->getLineArray() as $line)
		{
			$lineInfo = $this->getLineInfo($line, 'cart-line', $order);
			if ($lineInfo !== null)
			{
				$result['lines'][] = $lineInfo;
			}
		}
		return $result;
	}

	/**
	 * @param order_persistentdocument_orderline $line
	 * @param String $type
	 * @param order_persistentdocument_order $order
	 * @return Array<String => String>
	 */
	private function getLineInfo($line, $type, $order)
	{
		$lineInfo = array();
		$lineInfo['linetype'] = $type;
		$product = $line->getProduct();
		if ($product === null)
		{
			$lineInfo['productLabel'] = f_Locale::translateUI('&module.customer.bo.doceditor.panel.carts.Unexisting-product;');
		}
		else
		{
			$lineInfo['productLabel'] = $product->getLabel();
		}
		$lineInfo['codeReference'] = $line->getCodeReference();

		$lineInfo['unitPriceWithoutTax'] = $order->formatPrice($line->getUnitPriceWithoutTax());
		$lineInfo['unitPriceWithTax'] = $order->formatPrice($line->getUnitPriceWithTax());
		$lineInfo['quantity'] = $line->getQuantity();
		$lineInfo['totalPriceWithoutTax'] = $order->formatPrice($line->getAmountWithoutTax());
		$lineInfo['totalPriceWithTax'] = $order->formatPrice($line->getAmountWithTax());

		return $lineInfo;
	}

	/**
	 * @param order_persistentdocument_order $order
	 * @return catalog_persistentdocument_shop
	 */
	public function getShopByOrder($order)
	{
		try
		{
			return $order->getShop();
		}
		catch (Exception $e)
		{
			Framework::exception($e);
		}
		return catalog_ShopService::getInstance()->getCurrentShop();
	}

	/**
	 * @param order_CartInfo $cartInfo
	 */
	protected function prepareCartForOrder($cartInfo)
	{
		// Prepare cart for order generation.
	}
	
	/**
	 * @param order_persistentdocument_order $orderDocument
	 * @param order_CartInfo $cartInfo
	 */	
	protected function finalizeOrderAndCart($orderDocument, $cartInfo)
	{
		//N ettoyage du panier et de la commande.
		if ($orderDocument === null)
		{
			// La commande n'a pas pu être créée.
		}
		else
		{
			// Tout s'est bien passé.
		}
	}
	
	/**
	 * @param order_persistentdocument_order $orderDocument
	 * @param order_CartInfo $cartInfo
	 */
	protected function populateOrderAddresses($orderDocument, $cartInfo)
	{
		if ($orderDocument->getShippingAddress() === null)
		{
			$shippingAddress = customer_AddressService::getNewDocumentInstance();
			$orderDocument->setShippingAddress($shippingAddress);
		}
		if ($orderDocument->getBillingAddress() === null)		
		{
			$billingAddress = customer_AddressService::getNewDocumentInstance();
			$orderDocument->setBillingAddress($billingAddress);
		}
	}
	
	/**
	 * @param order_persistentdocument_order $orderDocument
	 * @param order_CartInfo $cartInfo
	 */
	protected function populateOrderLines($orderDocument, $cartInfo)
	{
		$cartLineCount = $cartInfo->getCartLineCount();
		if ($orderDocument->isNew())
		{
			for($i = 0; $i< $cartLineCount; $i++)
			{
				$orderDocument->addLine(order_OrderlineService::getInstance()->getNewDocumentInstance());
			}			
		}
		else
		{
			$currentOrderLine = $orderDocument->getLineCount();
			if ($currentOrderLine < $cartLineCount)
			{
				for($i = $currentOrderLine; $i< $cartLineCount; $i++)
				{
					$orderDocument->addLine(order_OrderlineService::getInstance()->getNewDocumentInstance());
				}					
			} 
			else if ($currentOrderLine > $cartLineCount)
			{
				$lines = $orderDocument->getLineArray();
				$orderDocument->setLineArray(array_slice($lines, 0, $cartLineCount));
				foreach (array_slice($lines, $cartLineCount) as $line) 
				{
					$line->delete();
				}					
			}
		}
	}
	
	/**
	 * @param order_persistentdocument_order orderDocument
	 * @param order_CartInfo $cartInfo
	 */	
	protected function fillOrderShippingAddress($orderDocument, $cartInfo)
	{
		$shippingAddress = $orderDocument->getShippingAddress();
		$cartInfo->getAddressInfo()->exportShippingAddress($shippingAddress);
		$shippingAddress->setPublicationstatus('FILED');
		$shippingAddress->save();	
		$cartInfo->setShippingAddressId($shippingAddress->getId());
	}
	
	/**
	 * @param order_persistentdocument_order orderDocument
	 * @param order_CartInfo $cartInfo
	 */	
	protected function fillOrderBillingAddress($orderDocument, $cartInfo)
	{
		$billingAddress = $orderDocument->getBillingAddress();
		if ($cartInfo->getAddressInfo()->useSameAddressForBilling)
		{
			$cartInfo->getAddressInfo()->exportShippingAddress($billingAddress);
		}
		else
		{
			$cartInfo->getAddressInfo()->exportBillingAddress($billingAddress);
		}
		$billingAddress->setPublicationstatus('FILED');
		$billingAddress->save();
		$cartInfo->setBillingAddressId($billingAddress->getId());
	}
	
	/**
	 * @param order_persistentdocument_order orderDocument
	 * @param order_CartInfo $cartInfo
	 */
	protected function generateDefaultAddressByOrder($orderDocument, $cartInfo)
	{
		$customer = $orderDocument->getCustomer();
		if ($customer->getDefaultAddress() === null)
		{
			$defaultAddress = customer_AddressService::getNewDocumentInstance();
			$cartInfo->getAddressInfo()->exportShippingAddress($defaultAddress);
			$defaultAddress->setLabel(f_Locale::translate('&modules.customer.frontoffice.Primary-address;'));
			$customer->addAddress($defaultAddress);
			$customer->save();
		}
	}
	/**
	 * /!\ Do not call this method directly, call order_CartService::createOrder instead. 
	 * @param order_CartInfo $cartInfo
	 * @return order_persistentdocument_order
	 */
	public function createFromCartInfo($cartInfo)
	{
		try
		{
			Framework::info(__METHOD__);
			
			$this->tm->beginTransaction();
			$this->prepareCartForOrder($cartInfo);
						
			$orderDocument = $cartInfo->getOrder();
			if ($orderDocument === null)
			{
				$orderDocument = $this->getNewDocumentInstance();				
				
			}
			else
			{
				$orderDocument->setLabel(date_Calendar::now()->toString());
			}
			
			$this->populateOrderAddresses($orderDocument, $cartInfo);
			$this->populateOrderLines($orderDocument, $cartInfo);
			
			$orderDocument->setOrderStatus(self::INITIATED);
			
			$shop = $cartInfo->getShop();
			$orderDocument->setCurrencyCode($shop->getCurrencyCode());
			$orderDocument->setPriceFormat($shop->getDocumentService()->getPriceFormat($shop));
			
			if ($cartInfo->hasCreditNote())
			{
				order_CreditnoteService::getInstance()->setOrderInfoFromCart($orderDocument, $cartInfo);
			}
			else
			{		
				$orderDocument->setTotalAmountWithTax(catalog_PriceHelper::roundPrice($cartInfo->getTotalWithTax()));
				$orderDocument->setTotalAmountWithoutTax(catalog_PriceHelper::roundPrice($cartInfo->getTotalWithoutTax()));
			}
						
			$customer = $cartInfo->getCustomer();
			$orderDocument->setCustomer($customer);
			$orderDocument->setShopId($shop->getId());
			$orderDocument->setWebsiteId($shop->getWebsite()->getId());
	
			// Adresse de livraison.
			$this->fillOrderShippingAddress($orderDocument, $cartInfo);
			
			// Frais de livraison.
			$shippingModeId = intval($cartInfo->getShippingModeId()) > 0 ? intval($cartInfo->getShippingModeId()) : -1;
			$orderDocument->setShippingModeId($shippingModeId);

			$orderDocument->setShippingFeesWithTax(catalog_PriceHelper::roundPrice($cartInfo->getFeesTotalWithTax()));
			$orderDocument->setShippingFeesWithoutTax(catalog_PriceHelper::roundPrice($cartInfo->getFeesTotalWithoutTax()));
			$orderDocument->setShippingDataArray($cartInfo->getShippingArray());
			
			// Adresse de facturation.
			$this->fillOrderBillingAddress($orderDocument, $cartInfo);
			
			// Adresse par defaut.
			$this->generateDefaultAddressByOrder($orderDocument, $cartInfo);
			
			// Mode de facturation.
			$billingMode = $cartInfo->getBillingMode();
			$orderDocument->setBillingModeDocument($billingMode);
			
			// Traitements des lignes de la commande.
			$orderlineService = order_OrderlineService::getInstance();
			foreach ($cartInfo->getCartLineArray() as $index => $cartLine)
			{
				$orderLine = $orderDocument->getLine($index);
				$orderlineService->createFromCartLineInfo($cartLine, $orderLine, $orderDocument);
			}
	
			// Sauvegarde du coupon.
			if ($cartInfo->hasCoupon())
			{
				$coupon = $cartInfo->getCoupon();
				$couponData = array('id' => $coupon->getId(), 
									'code' => $coupon->getLabel());			
				$orderDocument->setCouponData($couponData);
			}
			else
			{
				$orderDocument->setCouponData(null);
			}
			
			// Save the cart properties.
			$orderDocument->setGlobalProperty(self::PROPERTIES_CART_PROPERTIES, $cartInfo->getPropertiesArray());
			if ($cartInfo->hasProperties('creationdate'))
			{
				$orderDocument->setCreationdate($cartInfo->getProperties('creationdate'));
			}
			else
			{
				$orderDocument->setCreationDate(date_Calendar::getInstance()->toString());
			}
			
			// Sauvegarde des réductions.
			$discountArray = $cartInfo->getDiscountArray();
			$discountDataArray = array();
			foreach ($discountArray as $discount) 
			{
				$discountData = array('id' => $discount->getId(), 
									'label' => $discount->getLabel(),
									'valueWithTax' => $discount->getValueWithTax(),
									'valueWithoutTax' => $discount->getValueWithoutTax());
				
				$discountDocument = DocumentHelper::getDocumentInstance($discount->getId());				
				if (f_util_ClassUtils::methodExists($discountDocument, 'updateOrder'))
				{
					$extraData = $discountDocument->updateOrder($orderDocument, $discount);
					$discountData = array_merge($discountData, $extraData);
				}
				$discountDataArray[] = $discountData;
			}
			$orderDocument->setDiscountDataArray($discountDataArray);

			// Sauvegarde des frais.
			$feesDataArray = array();
			foreach ($cartInfo->getFeesArray() as $fees) 
			{
				$feesData = array('id' => $fees->getId(), 
									'label' => $fees->getLabel(),
									'valueWithTax' => $fees->getValueWithTax(),
									'valueWithoutTax' => $fees->getValueWithoutTax());
				
				$feesDocument = DocumentHelper::getDocumentInstance($fees->getId());				
				if (f_util_ClassUtils::methodExists($feesDocument, 'updateOrder'))
				{
					$extraData = $feesDocument->updateOrder($orderDocument, $fees);
					$feesData = array_merge($feesData, $extraData);
				}
				$feesDataArray[] = $feesData;
			}
			$orderDocument->setFeesDataArray($feesDataArray);			
			
			// Sauvegarde des taxes
			$orderDocument->setTaxDataArray($cartInfo->getTaxRates());
			

			// Save the Order.
			$folder = $this->getFolderOfDay($orderDocument->getUICreationdate());
			$orderDocument->save($folder->getId());
			foreach ($orderDocument->getLineArray() as $orderLine)
			{
				if ($orderLine->isModified() || $orderLine->isNew())
				{
					$orderLine->save();
				}
			}

			$cartInfo->setOrderId($orderDocument->getId());
			catalog_StockService::getInstance()->orderInitializedFromCart($cartInfo, $orderDocument);
			
			$this->finalizeOrderAndCart($orderDocument, $cartInfo);
			$this->tm->commit();			
		}
		catch (Exception $e)
		{
			$cartInfo->addTransientErrorMessage(LocaleService::getInstance()->transFO('m.order.fo.cant-create-order'));
			$this->tm->rollBack($e);
			$orderDocument = null;	
			$this->finalizeOrderAndCart($orderDocument, $cartInfo);		
		}
		return $orderDocument;
	}

	/**
	 * @param order_persistentdocument_order $order
	 * @return void
	 */
	public function appendOrderToCart($order)
	{
		$parameters = array();
		$parameters['shopId'] = $order->getShopId();
		
		$productIds = array();
		$quantities = array();
		foreach ($order->getLineArray() as $line)
		{
			$productId = $line->getProductId();
			$productIds[] = $productId;
			$quantities[$productId] = $line->getQuantity();
		}
		$parameters['productIds'] = $productIds;
		$parameters['quantities'] = $quantities;
		
		$website = website_WebsiteModuleService::getInstance()->getCurrentWebsite();
		$page = TagService::getInstance()->getDocumentByContextualTag('contextual_website_website_modules_order_cart', $website);
		$url = str_replace('&amp;', '&', LinkHelper::getDocumentUrl($page, RequestContext::getInstance()->getLang()));
		$parameters['backUrl'] = $url;
		
		HttpController::getInstance()->redirectToUrl(LinkHelper::getActionUrl('order', 'AddToCartMultiple', $parameters));
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @return Array<String=>String>
	 */
	public function getNotificationParameters($order)
	{
		$shop = $order->getShop();
		
		$orderAmountWithTax = $shop->formatPrice($order->getTotalAmountWithTax());
		$orderAmountWithoutTax = $shop->formatPrice($order->getTotalAmountWithoutTax());
		
		if ($shop->getDisplayPriceWithTax() || !$shop->getDisplayPriceWithoutTax())
		{
			$orderAmount = $orderAmountWithTax." ".f_Locale::translate("&modules.catalog.frontoffice.ttc;");	
		}
		elseif ($shop->getDisplayPriceWithoutTax())
		{
			$orderAmount = $orderAmountWithoutTax." ".f_Locale::translate("&modules.catalog.frontoffice.ht;");
		}
		
		$shippingFeesWithTax = $shop->formatPrice($order->getShippingFeesWithTax());
		$shippingFeesWithoutTax = $shop->formatPrice($order->getShippingFeesWithoutTax());
		
		$template = TemplateLoader::getInstance()->setPackageName('modules_order')->setMimeContentType(K::HTML)
			->setDirectory('templates/mails')->load('Order-Inc-Lines');
		
		$template->setAttribute('order', $order);
		$template->setAttribute('shop', $shop);
		$template->setAttribute('orderAmount', $orderAmount);
		$template->setAttribute('orderAmountWithTax', $orderAmountWithTax);
		$template->setAttribute('orderAmountWithoutTax', $orderAmountWithoutTax);
		$template->setAttribute('shippingMode', $order->getShippingMode());
		$template->setAttribute('shippingFeesWithTax', $shippingFeesWithTax);
		$template->setAttribute('shippingFeesWithoutTax', $shippingFeesWithoutTax);

		$user = $order->getCustomer()->getUser();
		return array(
			'orderId' => $order->getOrderNumber(), 
			'orderAmount' => $orderAmount,
		    'orderAmountWithTax' => $orderAmountWithTax, 
		    'orderAmountWithoutTax' => $orderAmountWithoutTax,
			'title' => (!is_null($user->getTitle())) ? $user->getTitle()->getLabel() : '', 
			'fullname' => $user->getFullname(), 
			'orderDetail' => $template->execute(), 
			'billingMode' => $order->getBillingMode(), 
			'shippingMode' => $order->getShippingMode(), 
			'shippingFeesWithTax' => $shippingFeesWithTax, 
			'shippingFeesWithoutTax' => $shippingFeesWithoutTax, 
			'date' => date_DateFormat::format($order->getOrderDate(), f_Locale::translate('&framework.date.date.localized-user-time-format;'))
		);
	}

	/**
	 * @param customer_persistentdocument_customer $customer
	 * @return Integer
	 */
	public function getCountByCustomer($customer)
	{
		$query = $this->createQuery();
		$query->add(Restrictions::eq('customer.id', $customer->getId()));
		$query->setProjection(Projections::rowCount('orderCount'));
		$result = $query->findUnique();
		if (isset($result['orderCount']))
		{
			return intval($result['orderCount']);
		}
		else
		{
			return 0;
		}
	}

	/**
	 * @param customer_persistentdocument_customer $customer
	 * @return Array<order_persistentdocument_order>
	 */
	public function getByCustomer($customer)
	{
		$query = $this->createQuery();
		$query->add(Restrictions::ne('orderStatus', self::INITIATED));
		$query->add(Restrictions::eq('customer.id', $customer->getId()));
		$query->addOrder(Order::desc('document_creationdate'));
		return $query->find();
	}

	/**
	 * @param customer_persistentdocument_customer $customer
	 * @return Array<order_persistentdocument_order>
	 */
	public function getSuccessOrderByCustomer($customer)
	{
		$query = $this->createQuery();
		$query->add(Restrictions::eq('customer.id', $customer->getId()));
		$query->add(Restrictions::eq('bill.status', order_BillService::SUCCESS));
		$query->addOrder(Order::desc('document_creationdate'));
		return $query->find();
	}
	
	/**
	 * Returns the number of "paid"/"waiting" orders for customer
	 * 
	 * @param customer_persistentdocument_customer $customer
	 * @return Integer
	 */
	public function getOrderCountByCustomer($customer)
	{
		$query = $this->createQuery();
		$query->add(Restrictions::eq('customer.id', $customer->getId()));
		$query->add(Restrictions::in('bill.status', array(order_BillService::SUCCESS, order_BillService::WAITING)));
		$query->setProjection(Projections::count('id', 'count'));		
		return intval(f_util_ArrayUtils::firstElement($query->findColumn('count')));
	}

	/**
	 * @param customer_persistentdocument_customer $customer
	 * @return Array<order_persistentdocument_order>
	 */
	public function getWaitingOrderByCustomer($customer)
	{
		$query = $this->createQuery();
		$query->add(Restrictions::eq('customer.id', $customer->getId()));
		$query->add(Restrictions::eq('orderStatus', self::IN_PROGRESS));
		$query->addOrder(Order::desc('document_creationdate'));
		return $query->find();
	}

	/**
	 * Return the of folder where the message must be saved
	 *
	 * @return generic_persistentdocument_folder
	 */
	public function getFolderOfDay($date = null)
	{
		$folder = TreeService::getInstance()->getFolderOfDate(ModuleService::getInstance()->getRootFolderId('order'), $date);
		return $folder;
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @param boolean $sendNotification
	 */
	public function cancelOrder($order, $sendNotification = true)
	{
		$oldStatus = $order->getOrderStatus();
		if ($oldStatus != self::CANCELED)
		{
			Framework::info(__METHOD__ . ' '. $order->__toString());
			try
			{
				$this->tm->beginTransaction();
				$this->preCancelOrder($order);
				$order->setOrderStatus(self::CANCELED);
				
				// Cancel waiting bills and destroy null bills.
				order_BillService::getInstance()->cleanByOrder($order);
							
				// Get amount to recredit.
				$amount = $this->getAmountToRecredit($order);
				
				// Recredit used credit notes.
				$cns = order_CreditnoteService::getInstance();		
				if ($order->hasCreditNote())
				{
					$amount = $cns->removeFromOrder($order, $amount);
				}
				
				// Create new credit note.
				if ($amount >= 0.01)
				{
					$creditNote = $cns->createForOrder($order, $amount);
					if ($creditNote)
					{
						$this->handleNewCreditNoteAfterCancel($creditNote);
					}
					
				}

				// Cancel waiting expeditions.
				order_ExpeditionService::getInstance()->cancelPrepareByOrder($order);
				
				$this->save($order);
				catalog_StockService::getInstance()->orderStatusChanged($order, $oldStatus);
				
				$this->postCancelOrder($order);
				$this->tm->commit();
			}
			catch (Exception $e)
			{
				$this->tm->rollBack($e);
				throw $e;
			}
			
			if ($sendNotification)
			{
				order_ModuleService::getInstance()->sendCustomerNotification('modules_order/order_canceled', $order);
			}
			f_event_EventManager::dispatchEvent(self::ORDER_STATUS_MODIFIED_EVENT, $this, array('document' => $order));
		}
	}

	/**
	 * @param order_persistentdocument_order $order
	 */
	protected function preCancelOrder($order)
	{

	}

	/**
	 * @param order_persistentdocument_order $order
	 */
	protected function postCancelOrder($order)
	{

	}
	
	/**
	 * @param order_persistentdocument_creditnote $creditNote
	 */
	protected function handleNewCreditNoteAfterCancel($creditNote)
	{
		// Nothing to do by default.
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @return double
	 */
	protected function getAmountToRecredit($order)
	{
		// Récupération du montant des factures payées par un "vrai" mode de paiement (i.e. hors avoir)
		$paidAmount = order_BillService::getInstance()->getPaidAmountByOrder($order);

		// Ajout des avoir
		$paidAmount += $order->getTotalCreditNoteAmount();
		$shippedAmount = 0;
		foreach (order_ExpeditionService::getInstance()->getShippedByOrder($order) as $expedition)
		{
			foreach ($expedition->getLineArray() as $line)
			{
				$shippedAmount += $line->getQuantity() *  $line->getUnitPriceWithTax() ;
			}
		}
		return $paidAmount - $shippedAmount;
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @param boolean $sendNotification
	 */
	public function processOrder($order, $sendNotification = true)
	{
		$oldStatus = $order->getOrderStatus();
		if ($oldStatus != self::IN_PROGRESS)
		{
			Framework::info(__METHOD__ . ' '. $order->__toString());
			$order->setOrderStatus(self::IN_PROGRESS);
			$this->save($order);
			
			catalog_StockService::getInstance()->orderStatusChanged($order, $oldStatus);
			if ($sendNotification)
			{
				order_ModuleService::getInstance()->sendCustomerNotification('modules_order/order_in_progress', $order);
			}
			f_event_EventManager::dispatchEvent(self::ORDER_STATUS_MODIFIED_EVENT, $this, array('document' => $order));
		}
	}		
	
	/**
	 * @param order_persistentdocument_order $order
	 * @param boolean $sendNotification
	 */
	public function completeOrder($order, $sendNotification = true)
	{
		$oldStatus = $order->getOrderStatus();
		if ($oldStatus != self::COMPLETE)
		{
			Framework::info(__METHOD__ . ' '. $order->__toString());
			$order->setOrderStatus(self::COMPLETE);
			$this->save($order);
			catalog_StockService::getInstance()->orderStatusChanged($order, $oldStatus);
			if ($sendNotification)
			{
				order_ModuleService::getInstance()->sendCustomerNotification('modules_order/order_complete', $order);
			}
			f_event_EventManager::dispatchEvent(self::ORDER_STATUS_MODIFIED_EVENT, $this, array('document' => $order));
		}
	}
	
	/**
	 * @param order_persistentdocument_order $document
	 * @param Integer $parentNodeId Parent node ID where to save the document (optionnal => can be null !).
	 * @return void
	 */
	protected function preInsert($document, $parentNodeId)
	{
		if ($document->getLabel() === null)
		{
			$document->setLabel(date_Calendar::now()->toString());
		}
		
		if ($document->getOrderNumber() === null)
		{
			$document->setOrderNumber(order_OrderNumberGenerator::getInstance()->generate($document));
		}
	}

	/**
	 * @param order_persistentdocument_order $document
	 * @param Integer $parentNodeId Parent node ID where to save the document (optionnal => can be null !).
	 * @return void
	 */
	protected function postInsert($document, $parentNodeId)
	{
		// Log action.
		$params = array('orderNumber' => $document->getOrderNumber(), 'customerFullName' => $document->getBillingAddress()->getFullName(false));
		UserActionLoggerService::getInstance()->addCurrentUserDocumentEntry('create-new-order', $document, $params, 'order');
	}

	/**
	 * @param order_persistentdocument_order $document
	 * @param Integer $parentNodeId Parent node ID where to save the document (optionnal => can be null !).
	 * @return void
	 */
	protected function postUpdate($document, $parentNodeId)
	{
		// Log action.
		$params = array('orderNumber' => $document->getOrderNumber(), 'customerFullName' => $document->getBillingAddress()->getFullName(false));
		
		if ($document->isPropertyModified('orderStatus'))
		{
			$params['orderStatus'] = $document->getBoOrderStatusLabel();
			UserActionLoggerService::getInstance()->addCurrentUserDocumentEntry('update-order-status', $document, $params, 'order');
		}
		else
		{
			UserActionLoggerService::getInstance()->addCurrentUserDocumentEntry('update-order', $document, $params, 'order');
		}
	}

	/**
	 * @param order_persistentdocument_order $document
	 * @param Integer $parentNodeId Parent node ID where to save the document (optionnal => can be null !).
	 * @return void
	 */
	protected function postSave($document, $parentNodeId)
	{		
		// If there is a coupon, add it in the list of coupons used by the customer.
		$coupon = $this->getUsedCouponByOrder($document);
		if ($coupon !== null)
		{
			$customer = $document->getCustomer();
			if ($document->getOrderStatus() == self::CANCELED)
			{
				$customer->getDocumentService()->removeUsedCoupon($customer, $coupon);
			}
			else
			{
				$customer->getDocumentService()->addUsedCoupon($customer, $coupon);
			}
		}
	}

	/**
	 * @param order_persistentdocument_order $order
	 * @param String $content
	 * @return Boolean
	 */
	public function sendMessageFromCustomer($order, $content)
	{
		$this->execSendMessage($order, $content, $order->getCustomer()->getUser());
		$params = array('content' => $content);
		return order_ModuleService::getInstance()->sendAdminNotification(self::MESSAGE_FROM_USER, $order, null, null, $params);
	}

	/**
	 * @param order_persistentdocument_order $order
	 * @param String $content
	 * @return Boolean
	 */
	public function sendMessageToCustomer($order, $content, $sender)
	{
		$this->execSendMessage($order, $content, $sender);
		$params = array('content' => $content);
		return order_ModuleService::getInstance()->sendCustomerNotification(self::MESSAGE_TO_USER, $order, null, null, $params);
	}

	/**
	 * @param order_persistentdocument_order $order
	 * @param String $content
	 * @param user_persistentdocument_user $sender
	 */
	protected function execSendMessage($order, $content, $sender)
	{
		$date = date_DateFormat::format(date_Calendar::getInstance(), 'd/m/Y');
		$message = order_MessageService::getInstance()->getNewDocumentInstance();
		$message->setLabel(f_Locale::translate('&modules.order.mail.Message-label;', array('orderId' => $order->getId(), 'date' => $date)));
		$message->setSender($sender);
		$message->setContent($content);
		$message->setOrder($order);
		$message->save();
	}

	/**
	 * @param customer_persistentdocument_customer
	 * @return Array<order_persistentdocument_order>
	 */
	public function getOrdersHavingMessages($customer)
	{
		$orderArray = array();
		$query = order_MessageService::getInstance()->createQuery();
		$query->createCriteria('order')->add(Restrictions::eq('customer.id', $customer->getId()));
		$query->setProjection(Projections::property('order'));
		$results = $query->find();
		foreach ($results as $result)
		{
			$orderArray[$result['order_id']] = $result['order'];
		}
		return array_values($orderArray);
	}

	/**
	 * @param String $number
	 * @return order_persistentdocument_order
	 */
	public function getByNumber($number)
	{
		return $this->createQuery()->add(Restrictions::eq('orderNumber', $number))->findUnique();
	}

	/**
	 * @param customer_persistentdocument_customer $customer
	 * @return String date
	 */
	public function getFirstOrderDateByCustomer($customer)
	{
		if ($customer === null)
		{
			return null;
		}

		$query = $this->getOrderDatesByCustomerQuery($customer, 1);
		$query->addOrder(Order::asc('document_creationdate'));
		$row = $query->findUnique();
		if ($row !== null)
		{
			return $row['creationdate'];
		}
		else
		{
			return null;
		}
	}

	/**
	 * @param customer_persistentdocument_customer $customer
	 * @return String date
	 */
	public function getLastOrderDateByCustomer($customer)
	{
		if ($customer === null)
		{
			return null;
		}

		$query = $this->getOrderDatesByCustomerQuery($customer, 1);
		$query->addOrder(Order::desc('document_creationdate'));
		$row = $query->findUnique();
		if ($row !== null)
		{
			return $row['creationdate'];
		}
		else
		{
			return null;
		}
	}

	/**
	 * @param customer_persistentdocument_customer $customer
	 * @return Array<String> dates
	 */
	public function getOrderDatesByCustomer($customer)
	{
		if ($customer === null)
		{
			return array();
		}

		$query = $this->getOrderDatesByCustomerQuery($customer);
		$query->addOrder(Order::asc('document_creationdate'));
		$rows = $query->find();
		$dates = array();
		foreach ($rows as $row)
		{
			$dates[] = $row['creationdate'];
		}
		return $dates;
	}

	/**
	 * @param customer_persistentdocument_customer $customer
	 * @param Integer $maxresult
	 * @return f_persistentdocument_criteria_Query
	 */
	private function getOrderDatesByCustomerQuery($customer, $maxresult = 0)
	{
		$query = $this->createQuery();
		$query->add(Restrictions::eq('customer.id', $customer->getId()));
		$query->setProjection(Projections::property('creationdate'));
		if ($maxresult > 0)
		{
			$query->setMaxResults($maxresult);
		}
		return $query;
	}

	/**
	 * @param String $beginOrderInterval
	 * @param String $endOrderInterval
	 * @param String $customer
	 * @return Boolean
	 */
	public function hasOrderedDuringTheInterval($beginOrderInterval, $endOrderInterval, $customer)
	{
		// No interval defined : OK.
		if ($endOrderInterval === null && $beginOrderInterval === null)
		{
			return true;
		}
		// Only one point of the interval defined.
		else if ($endOrderInterval === null)
		{
			$lastOrderDate = $this->getLastOrderDateByCustomer($customer);
			return ($lastOrderDate !== null && $beginOrderInterval < $lastOrderDate);
		}
		else if ($beginOrderInterval === null)
		{
			$firstOrderDate = $this->getFirstOrderDateByCustomer($customer);
			return ($firstOrderDate !== null && $endOrderInterval > $firstOrderDate);
		}
		// An interval is defined.
		else if ($beginOrderInterval !== null && $endOrderInterval !== null)
		{
			$orderDates = $this->getOrderDatesByCustomer($customer);
			foreach ($orderDates as $date)
			{
				if ($beginOrderInterval < $date && $date < $endOrderInterval)
				{
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @param order_persistentdocument_order $order
	 * @return customer_persistentdocument_coupon
	 */
	public function getUsedCouponByOrder($order)
	{
		$couponId = $order->getCouponId();
		if ($couponId !== null)
		{
			try
			{
				return customer_persistentdocument_coupon::getInstanceById($couponId);
			}
			catch (Exception $e)
			{
				Framework::exception($e);
			}
		}
		// If there is no used coupon or if the coupon doesn't exist any more, return null.
		return null;
	}
		
	/**
	 * @see f_persistentdocument_DocumentService::getWebsiteId()
	 *
	 * @param order_persistentdocument_order $document
	 * @return integer
	 */
	public function getWebsiteId($document)
	{
		return $document->getWebsiteId();
	}
	
	/**
	 * @param website_persistentdocument_website $website
	 * @param string $lang
	 * @param string $modelName
	 * @param integer $offset
	 * @param integer $chunkSize
	 * @return order_persistentdocument_order[]
	 */
	public function getDocumentForSitemap($website, $lang, $modelName, $offset, $chunkSize)
	{
		return array();
	}

	/**
	 * @param order_persistentdocument_order $document
	 * @param string $forModuleName
	 * @param array $allowedSections
	 * @return array
	 */
	public function getResume($document, $forModuleName, $allowedSections = null)
	{
		$data = parent::getResume($document, $forModuleName, $allowedSections);
		$rc = RequestContext::getInstance();
		$contextlang = $rc->getLang();
		$usecontextlang = $document->isLangAvailable($contextlang);
		$lang = $usecontextlang ? $contextlang : $document->getLang();

		try
		{
			$rc->beginI18nWork($lang);
				
			// Informations.
			$billingAddress = $document->getBillingAddress();
			$data['properties']['orderNumber'] = $document->getOrderNumber();	
			$data['properties']['orderStatus'] = $document->getBoOrderStatusLabel();
			$data['properties']['customerFullName'] = $billingAddress->getDocumentService()->getFullName($billingAddress);
			$data['properties']['customerCode'] = $document->getCustomer()->getUser()->getEmail();
			
			$usedCreditNote = $document->getTotalCreditNoteAmount();
			$data['financial']['usedCreditNote'] = ($usedCreditNote) ? $document->formatPrice($usedCreditNote) : null;
			$data['financial']['totalAmount'] = $document->formatPrice($document->getTotalAmountWithTax());			
			$obs = order_BillService::getInstance();
			$dateTimeFormat = customer_ModuleService::getInstance()->getUIDateTimeFormat();
			$bills = $obs->getByOrder($document);
			if (count($bills))
			{
				foreach ($bills as $bill) 
				{
					if ($bill->getStatus() == order_BillService::SUCCESS || $bill->getStatus() == order_BillService::WAITING)
					{
						break;
					}
				}				
				$data['financial']['paymentStatus'] = $bill->getBoStatusLabel();
				if ($bill->getTransactionDate())
				{
					$data['financial']['paymentStatus'] .= ' '	. date_DateFormat::format($bill->getUITransactionDate(), $dateTimeFormat);
				}
			}
			
			$expeditions = order_ExpeditionService::getInstance()->getByOrder($document);
			if (count($expeditions))
			{
				$expedition = f_util_ArrayUtils::lastElement($expeditions);
				$data['shipping']['shippingStatus'] = $expedition->getBoStatusLabel();
				if ($expedition->getShippingDate())
				{
					$data['shipping']['shippingStatus'] .= ' ' . date_DateFormat::format($expedition->getUIShippingDate(), $dateTimeFormat);
				}
			}
			
			// Messages.
			$data['messages'] = order_MessageService::getInstance()->getInfosByOrder($document);
			$data['messages']['needsAnswer'] = f_Locale::translateUI('&modules.uixul.bo.general.' . ($document->getNeedsAnswer() ? 'Yes' : 'No') . ';');
			
			$rc->endI18nWork();
		}
		catch (Exception $e)
		{
			$rc->endI18nWork($e);
		}

		return $data;
	}
	
	/**
	 * @return void
	 */
	public function sendCommentReminders()
	{
		$codeName = 'modules_order/comment-reminder';
		foreach ($this->getOrdersToRemind() as $order)
		{
			$user = $order->getCustomer()->getUser();
			$products = $this->getNotCommentedProducts($order, $user);
			if (count($products) > 0)
			{
				$products = $this->filterProductsForCommentReminder($products);	
				$params = $this->getNotificationParameters($order);
				$params['reminderProductBlock'] = $this->renderReminderProductBlock($products);
				order_ModuleService::getInstance()->sendCustomerNotification($codeName, $order, null, null, $params);
			}
			$order->setLastCommentReminder(date_calendar::getInstance()->toString());
			$order->save();
		}
	}

	/**
	 * @param catalog_persistentdocument_shop $shop
	 * @param date_Calendar $fromDate
	 * @param date_Calendar $toDate
	 * @return Array<String, Double>
	 */
	public function getStatisticsByShop($shop, $fromDate, $toDate)
	{
		$totalAmount = $this->findProjectedTotal($shop, $fromDate, $toDate, Projections::sum('totalAmountWithTax', 'projection'));
		return array(
			'monthLabel' => ucfirst(date_DateFormat::format($fromDate, 'F Y')),
			'monthShortLabel' => date_DateFormat::format($fromDate, 'm/Y'),
			'totalCount' => $this->findProjectedTotal($shop, $fromDate, $toDate, Projections::rowCount('projection')),
			'totalAmount' => catalog_PriceHelper::roundPrice($totalAmount),
			'totalAmountFormatted' => $shop->formatPrice($totalAmount),
			'to' => $shop->formatPrice($totalAmount),
			'toDeliver' => $this->findProjectedTotal($shop, $fromDate, $toDate, Projections::rowCount('projection'), array('in_progress'))
		);
	}
	
	/**
	 * @param catalog_persistentdocument_shop $shop
	 * @param date_Calendar $fromDate
	 * @param date_Calendar $toDate
	 * @param f_persistentdocument_criteria_OperationProjection $projection
	 * @param String $orderStatus
	 * @return Mixed
	 */
	private function findProjectedTotal($shop, $fromDate, $toDate, $projection, $orderStatues = null)
	{
		$dbFormat = 'Y-m-d H:i:s';
		$query = $this->createQuery()->add(Restrictions::between(
			'creationdate',
			date_DateFormat::format($fromDate, $dbFormat),
			date_DateFormat::format($toDate, $dbFormat)
		));
		$query->add(Restrictions::eq('shopId', $shop->getId()));
		if ($orderStatues === null)
		{
			$query->add(Restrictions::ne('orderStatus', self::CANCELED));
		}
		else 
		{
			$query->add(Restrictions::in('orderStatus', $orderStatues));
		}
		return f_util_ArrayUtils::firstElement($query->setProjection($projection)->findColumn('projection'));
	}

	/**
	 * @param order_persistentdocument_order
	 * @return catalog_persistentdocument_product[]
	 */
	private function getNotCommentedProducts($order, $user)
	{
		$products = array();
		$cs = comment_CommentService::getInstance();
		foreach ($order->getLineArray() as $line)
		{
			$product = $line->getProduct();
			if ($product !== null && !$cs->hasCommented($product->getId(), $user))
			{
				$products[$product->getId()] = $product;
			}
		}
		return array_values($products);
	}

	/**
	 * @return order_persistentdocument_order[]
	 */
	private function getOrdersToRemind()
	{
		$ms = ModuleService::getInstance();
		switch ($ms->getPreferenceValue('order', 'commentReminderReference'))
		{
			case 'payment' :
				$referenceProperty = 'bill.transactionDate';
				break;
					
			default :
			case 'shipment' :
				$referenceProperty = 'expedition.shippingDate';
				break;
		}
		$referenceDate = date_Calendar::getInstance();
		$referenceDate->sub(date_Calendar::DAY, $ms->getPreferenceValue('order', 'commentReminderPeriod'));
		$query = $this->createQuery()->add(Restrictions::isNull('lastCommentReminder'));
		$query->add(Restrictions::lt($referenceProperty, $referenceDate->toString()));
		return $query->find();
	}

	/**
	 * @param catalog_persistentdocument_product[] $products
	 * @return catalog_persistentdocument_product[]
	 */
	private function filterProductsForCommentReminder($products)
	{
		$ms = ModuleService::getInstance();
		switch ($ms->getPreferenceValue('order', 'commentReminderSelection'))
		{
			case 'random' :
				shuffle($products);
				$products = array_slice($products, 0, $ms->getPreferenceValue('order', 'commentReminderCount'));
				break;
					
			case 'all' :
			default :
				// Nothing to do, return all products.
				break;
		}
		return $products;
	}

	/**
	 * @param catalog_persistentdocument_product[] $products
	 * @return String
	 */
	private function renderReminderProductBlock($products)
	{
		$template = TemplateLoader::getInstance()->setPackageName('modules_order')
			->setMimeContentType(K::HTML)->setDirectory('templates/mails')->load('Order-Inc-CommentReminderProducts');
		$template->setAttribute('products', $products);
		return $template->execute();
	}
		
	/**
	 * @param order_persistentdocument_order $document
	 * @param String[] $propertiesName
	 * @param Array $datas
	 */
	public function addFormProperties($document, $propertiesNames, &$datas)
	{
		if (in_array('financial', $propertiesNames))
		{
			$infos = $this->getFinancialInfos($document);
			foreach ($infos as $key => $value)
			{
				$datas[$key] = $value;
			}
		}
		else if (in_array('shipping', $propertiesNames))
		{
			$infos = $this->getShippingInfos($document);
			foreach ($infos as $key => $value)
			{
				$datas[$key] = $value;
			}
		}
		else
		{
			//Global Infos
			$infos = $this->getPropertyInfos($document);
			foreach ($infos as $key => $value)
			{
				$datas[$key] = $value;
			}
		}
	}

	/**
	 * @param order_persistentdocument_order $document
	 * @param string $moduleName
	 * @param string $treeType
	 * @param array<string, string> $nodeAttributes
	 */
	public function addTreeAttributes($document, $moduleName, $treeType, &$nodeAttributes)
	{
		$nodeAttributes['label'] = $document->getOrderNumber();	
		if ($treeType === 'wtree' || $treeType === 'wlist')
		{
			$nodeAttributes['orderStatus'] = $document->getOrderStatus();
			if ($treeType === 'wlist')
			{
				$nodeAttributes['date'] =  date_Formatter::toDefaultDateTimeBO($document->getUICreationdate());
				$nodeAttributes['orderStatusLabel'] = $document->getBoOrderStatusLabel();
				$nodeAttributes['formattedTotalAmountWithTax'] = $document->formatPrice($document->getTotalAmountWithTax());
				$user = $document->getCustomer()->getUser();
				$nodeAttributes['customer'] = $user->getFullName() . ' (' . $user->getEmail() . ')';
				$nodeAttributes['canBeCanceled'] = $document->canBeCanceled();
				$messages = order_MessageService::getInstance()->getByOrder($document);
				if (count($messages) > 0)
				{
					$message = f_util_ArrayUtils::firstElement($messages);
					$nodeAttributes['lastMessageDate'] = date_Formatter::toDefaultDateTimeBO($message->getUICreationdate());
				}
				else
				{
					$nodeAttributes['lastMessageDate'] = LocaleService::getInstance()->transBO('m.order.bo.general.na', array('ucf'));
				}
			}
		}
	}

	// Deprecated
	
	/**
	 * @deprecated (will be removed in 4.0) use order_BillService::getInstance()->generateBillIsActive()
	 */
	public function generateBillIsActive()
	{
		return order_BillService::getInstance()->generateBillIsActive();
	}
	
	/**
	 * @deprecated (will be removed in 4.0) use order_BillService::getInstance()->genBills();
	 */
	public function genBills()
	{
		order_BillService::getInstance()->genBills();
	}
	
	/**
	 * @deprecated (will be removed in 4.0) use order_BillService::getInstance()->genBill()
	 */
	public function genBill($order)
	{	
		$billArray = $order->getBillArrayInverse();
		order_BillService::getInstance()->genBill($billArray[0]);
	}
	
	/**
	 * @deprecated (will be removed in 4.0) use order_BillService::getInstance()->createBill()
	 */
	public function createBill($order)
	{
		$billArray = $order->getBillArrayInverse();
		order_BillService::getInstance()->createBill($billArray[0]);
	}
	
	/**
	 * @deprecated (will be removed in 4.0)
	 */
	public function updateStock($order)
	{
	}
	
	/**
	 * @deprecated
	 */
	public function getStatusLabel($orderStatus)
	{
		return LocaleService::getInstance()->transFO('m.order.frontoffice.status.' . $orderStatus, array('ucf', 'html'));
	}
	
	/**
	 * @deprecated
	 */
	public function getBoStatusLabel($orderStatus)
	{
		return LocaleService::getInstance()->transBO('m.order.frontoffice.status.' . $orderStatus, array('ucf', 'html'));
	}
}