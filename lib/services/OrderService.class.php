<?php
/**
 * order_OrderService
 * @package modules.order
 */
class order_OrderService extends f_persistentdocument_DocumentService
{
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
				$couponDocument = DocumentHelper::getDocumentInstance($couponId, 'modules_customer/coupon');
				$couponLabel = $couponDocument->getLabel();
			}
			catch (Exception $e)
			{
				$couponLabel = $coupon['code'];
				Framework::info("Coupon $couponId not found :" . $e->getMessage());
			}
			
			$informations['couponName'] = $couponLabel;
			$informations['couponValue'] = $order->formatPrice($coupon['valueWithTax']);
				
			$informations['couponSectionName'] = $couponLabel;
			$informations['couponSectionCode'] = $coupon['code'];
			$informations['couponSectionValue'] = $informations['couponValue'];
		}
		
		if ($order->hasDiscount())
		{
			$informations['discountTotal'] = $order->formatPrice($order->getDiscountTotalWithTax());
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
	 * @return order_persistentdocument_order
	 */
	public function createFromCartInfo($cartInfo)
	{
		try
		{
			$this->tm->beginTransaction();
			
			$shop = $cartInfo->getShop();
			$cartLineCount = $cartInfo->getCartLineCount();
			
			$orderDocument = $cartInfo->getOrder();
			if ($orderDocument === null)
			{
				$orderDocument = $this->getNewDocumentInstance();				
				$shippingAddress = customer_AddressService::getNewDocumentInstance();
				$orderDocument->setShippingAddress($shippingAddress);
				
				$billingAddress = customer_AddressService::getNewDocumentInstance();
				$orderDocument->setBillingAddress($billingAddress);
				
				for($i = 0; $i< $cartLineCount; $i++)
				{
					$orderDocument->addLine(order_OrderlineService::getInstance()->getNewDocumentInstance());
				}
			}
			else
			{
				$orderDocument->setLabel(date_Calendar::now()->toString());
				$shippingAddress = $orderDocument->getShippingAddress();
				$billingAddress = $orderDocument->getBillingAddress();
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
					for($i = $cartLineCount; $i< $currentOrderLine; $i++)
					{
						$orderDocument->removeLineByIndex($i);
					}					
				}
			}
			$orderDocument->setOrderStatus(null);
			
			$orderDocument->setCurrencyCode($shop->getCurrencyCode());
			$orderDocument->setPriceFormat($shop->getDocumentService()->getPriceFormat($shop));
					
			$orderDocument->setTotalAmountWithTax(catalog_PriceHelper::roundPrice($cartInfo->getTotalWithTax()));
			$orderDocument->setTotalAmountWithoutTax(catalog_PriceHelper::roundPrice($cartInfo->getTotalWithoutTax()));
			
			$customer = $cartInfo->getCustomer();
			$orderDocument->setCustomer($customer);
			$orderDocument->setShopId($shop->getId());
			$orderDocument->setWebsiteId($shop->getWebsite()->getId());
	
			// Adresse de livraison.
			$cartInfo->getAddressInfo()->exportShippingAddress($shippingAddress);
			$shippingAddress->setPublicationstatus('FILED');
			$shippingAddress->save();
			$cartInfo->setShippingAddressId($shippingAddress->getId());
			
			$shippingModeId = intval($cartInfo->getShippingModeId()) > 0 ? intval($cartInfo->getShippingModeId()) : -1;
			$orderDocument->setShippingModeId($shippingModeId);
			$orderDocument->setShippingModeTaxCode($cartInfo->getShippingTaxCode());
			
			$orderDocument->setShippingModeTaxRate($cartInfo->getShippingTaxRate());
			$orderDocument->setShippingFeesWithTax(catalog_PriceHelper::roundPrice($cartInfo->getShippingPriceWithTax()));
			$orderDocument->setShippingFeesWithoutTax(catalog_PriceHelper::roundPrice($cartInfo->getShippingPriceWithoutTax()));
			$orderDocument->setShippingDataArray($cartInfo->getShippingArray());
			
			// Adresse de facturation.
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
			
			// Adresse par defaut.
			if ($customer->getDefaultAddress() === null)
			{
				$defaultAddress = customer_AddressService::getNewDocumentInstance();
				$cartInfo->getAddressInfo()->exportShippingAddress($defaultAddress);
				$defaultAddress->setLabel(f_Locale::translate('&modules.customer.frontoffice.Primary-address;'));
				$customer->addAddress($defaultAddress);
				$customer->save();
			}
			
			$billingMode = $cartInfo->getBillingMode();
			$orderDocument->setBillingModeDocument($billingMode);
			
			$orderlineService = order_OrderlineService::getInstance();
			foreach ($cartInfo->getCartLineArray() as $index => $cartLine)
			{
				$orderLine = $orderDocument->getLine($index);
				$orderlineService->createFromCartLineInfo($cartLine, $orderLine);
				$orderLine->save();
			}
	
			// Sauvegarde du coupon.
			if ($cartInfo->hasCoupon())
			{
				$coupon = $cartInfo->getCoupon();
				$couponData = array('id' => $coupon->getId(), 
									'code' => $coupon->getLabel(),
									'valueWithTax' => $coupon->getValueWithTax(),
									'valueWithoutTax' => $coupon->getValueWithoutTax());			
				$orderDocument->setCouponData($couponData);
			}
			else
			{
				$orderDocument->setCouponData(null);
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
				$discountDataArray[] = $discountData;
			}
			$orderDocument->setDiscountDataArray($discountDataArray);
			
			// Save the cart properties.
			$orderDocument->setGlobalProperty(self::PROPERTIES_CART_PROPERTIES, $cartInfo->getPropertiesArray());
			if ($cartInfo->hasProperties('creationdate'))
			{
				$orderDocument->setCreationdate($cartInfo->getProperties('creationdate'));
			}
			
			$folder = $this->getFolderOfDay($orderDocument->getCreationdate());
			$orderDocument->save($folder->getId());
			$cartInfo->setOrderId($orderDocument->getId());
			$this->tm->commit();			
		}
		catch (Exception $e)
		{
			$cartInfo->addErrorMessage('Impossible de creer la commande');
			$this->tm->rollBack($e);
			$orderDocument = null;			
		}
		return $orderDocument;
	}

	/**
	 * @param order_persistentdocument_order $order
	 * @return void
	 */
	public function appendOrderToCart($order)
	{
		$cartService = order_CartService::getInstance();
		$cartInfo = $cartService->getDocumentInstanceFromSession();
		foreach ($order->getLineArray() as $line)
		{
			$product = $line->getProduct();
			if (!is_null($product))
			{
				$cartService->addProductToCart($cartInfo, $product, $line->getQuantity(), $line->getGlobalPropertyArray());
			}
		}
		$cartInfo->refresh();
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
			$orderAmount = $orderAmountWithTax." ".f_locale::translate("&modules.catalog.frontoffice.ttc;");	
		}
		elseif ($shop->getDisplayPriceWithoutTax())
		{
			$orderAmount = $orderAmountWithoutTax." ".f_locale::translate("&modules.catalog.frontoffice.ht;");
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
	 * @return MailService
	 */
	protected function getMessageService()
	{
		return MailService::getInstance();
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
		$query->add(Restrictions::eq('orderStatus', self::PAYMENT_SUCCESS));
		$query->addOrder(Order::desc('document_creationdate'));
		return $query->find();
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
		if ($order->getOrderStatus() != self::CANCELED)
		{
			Framework::info(__METHOD__ . ' '. $order->__toString());
			$order->setOrderStatus(self::CANCELED);
			$this->save($order);
			if ($sendNotification)
			{
				order_ModuleService::getInstance()->sendCustomerNotification('modules_order/order_canceled', $order);
			}		
			f_event_EventManager::dispatchEvent(self::ORDER_STATUS_MODIFIED_EVENT, $this, array('document' => $order));
		}
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @param boolean $sendNotification
	 */
	public function processOrder($order, $sendNotification = true)
	{
		if ($order->getOrderStatus() != self::IN_PROGRESS)
		{
			Framework::info(__METHOD__ . ' '. $order->__toString());
			$order->setOrderStatus(self::IN_PROGRESS);
			$this->save($order);
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
		if ($order->getOrderStatus() != self::COMPLETE)
		{
			Framework::info(__METHOD__ . ' '. $order->__toString());
			$order->setOrderStatus(self::COMPLETE);
			if ($sendNotification)
			{
				order_ModuleService::getInstance()->sendCustomerNotification('modules_order/order_complete', $order);
			}
			$this->save($order);
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
			$params['orderStatus'] = $document->getOrderStatusLabel();
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
		if (!is_null($coupon))
		{
			$customer = $document->getCustomer();
			if ($document->getOrderStatus() == self::CANCELED)
			{
				$customer->removeUsedCoupon($coupon);
			}
			else
			{
				$customer->addUsedCoupon($coupon);
			}
			$customer->save();
		}
	}

	/**
	 * @param order_persistentdocument_order $order
	 * @param String $content
	 * @return Boolean
	 */
	public function sendMessageFromCustomer($order, $content)
	{
		$recipients = order_ModuleService::getInstance()->getAdminRecipients();
		if ($recipients && $order->getCustomer() && $order->getCustomer()->getUser())
		{
			return $this->execSendMessage($order, $content, self::MESSAGE_FROM_USER, $recipients, $order->getCustomer()->getUser());
		}
		return false;
	}

	/**
	 * @param order_persistentdocument_order $order
	 * @param String $content
	 * @return Boolean
	 */
	public function sendMessageToCustomer($order, $content, $sender)
	{
		$recipients = order_ModuleService::getInstance()->getMessageRecipients($order);
		if ($recipients)
		{
			return $this->execSendMessage($order, $content, self::MESSAGE_TO_USER, $recipients, $sender);
		}
		return false;
	}

	/**
	 * @param order_persistentdocument_order $order
	 * @param String $content
	 * @param String $notificationCode
	 * @param mail_MessageRecipients $recipients
	 * @param user_persistentdocument_user $sender
	 * @return Boolean
	 */
	protected function execSendMessage($order, $content, $notificationCode, $recipients, $sender)
	{
		$notificationService = notification_NotificationService::getInstance();
		$notificationService->setMessageService(MailService::getInstance());
		$notification = $notificationService->getNotificationByCodeName($notificationCode);
		$parameters = array_merge($this->getNotificationParameters($order), array('content' => $content));
		if ($notificationService->send($notification, $recipients, $parameters, 'order'))
		{
			$date = date_DateFormat::format(date_Calendar::getInstance(), 'd/m/Y');
			$message = order_MessageService::getInstance()->getNewDocumentInstance();
			$message->setLabel(f_Locale::translate('&modules.order.mail.Message-label;', array('orderId' => $order->getId(), 'date' => $date)));
			$message->setSender($sender);
			$message->setContent($content);
			$message->setOrder($order);
			$message->save();
			return true;
		}
		return false;
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
	 * @return order_persistentdocument_coupon
	 */
	public function getUsedCouponByOrder($order)
	{
		$couponId = $order->getCouponId();
		if ($couponId !== null)
		{
			try
			{
				return $this->getDocumentInstance($couponId, 'modules_marketing/coupon');
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
			
			$data['properties']['orderStatus'] = $this->getStatusLabel($document->getOrderStatus());
			$obs = order_BillService::getInstance();
			$dateTimeFormat = customer_ModuleService::getInstance()->getUIDateTimeFormat();
			$bills = $obs->getByOrder($document);
			if (count($bills))
			{
				$bill = f_util_ArrayUtils::lastElement($bills);
				$data['properties']['paymentStatus'] = $bill->getBoStatusLabel();
				if ($bill->getTransactionDate())
				{
					
					$data['properties']['paymentStatus'] .= ' '	. date_DateFormat::format($bill->getUITransactionDate(), $dateTimeFormat);
				}
			}
			
			$expeditions = order_ExpeditionService::getInstance()->getByOrder($document);
			if (count($expeditions))
			{
				$expedition = f_util_ArrayUtils::lastElement($expeditions);
				$data['properties']['shippingStatus'] = $expedition->getBoStatusLabel();
				if ($expedition->getShippingDate())
				{
					$data['properties']['shippingStatus'] .= ' ' . date_DateFormat::format($expedition->getUIShippingDate(), $dateTimeFormat);
				}
			}
			
			$data['properties']['customerFullName'] = $billingAddress->getDocumentService()->getFullName($billingAddress);
			$data['properties']['customerCode'] = $document->getCustomer()->getUser()->getEmail();
			$data['properties']['totalAmount'] = $document->formatPrice($document->getTotalAmountWithTax());

			// Messages.
			$data['messages'] = order_MessageService::getInstance()->getInfosByOrder($document);
				
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
		$ns = notification_NotificationService::getInstance();
		$ns->setMessageService($this->getMessageService());
		$codeName = 'modules_order/comment-reminder';
		foreach ($this->getOrdersToRemind() as $order)
		{
			$notification = $ns->getNotificationByCodeName($codeName, $order->getWebsiteId());
			if ($notification)
			{
				$recipients = order_ModuleService::getInstance()->getMessageRecipients($order);
				if ($recipients)
				{
					$user = $order->getCustomer()->getUser();
					$products = $this->getNotCommentedProducts($order, $user);
					if (count($products) > 0)
					{
						$products = $this->filterProductsForCommentReminder($products);	
						$parameters = $this->getNotificationParameters($order);
						$parameters['reminderProductBlock'] = $this->renderReminderProductBlock($products);
						$ns->send($notification, $recipients, $parameters, 'order');
					}
					$order->setLastCommentReminder(date_calendar::getInstance()->toString());
					$order->save();
				}
			}
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
			$query->add(Restrictions::ne('orderStatus', 'canceled'));
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
	 * @param order_persistentdocument_order $order
	 */
	public function updateStock($order)
	{
		if (Framework::isInfoEnabled())
		{
			Framework::info(__METHOD__);
		}
		foreach ($order->getLineArray() as $line) 
		{
			try 
			{
				$product = DocumentHelper::getDocumentInstance($line->getProductId(), 'modules_catalog/product');
				if ($product instanceof catalog_StockableDocument) 
				{
					Framework::info(__METHOD__ . " decreaseQuantity " . $product->getId() . ' ' . $line->getQuantity());
					catalog_StockService::getInstance()->decreaseQuantity($product, $line->getQuantity());
				}
			}
			catch (Exception $e)
			{
				Framework::exception($e);
			}
		}
	}
	
	//DEPRECATED
	
	/**
	 * @deprecated use order_BillService::getInstance()->generateBillIsActive()
	 */
	public function generateBillIsActive()
	{
		return order_BillService::getInstance()->generateBillIsActive();
	}
	
	/**
	 * @deprecated use order_BillService::getInstance()->genBills();
	 */
	public function genBills()
	{
		order_BillService::getInstance()->genBills();
	}
	
	/**
	 * @deprecated use order_BillService::getInstance()->genBill()
	 */
	public function genBill($order)
	{	
		$billArray = $order->getBillArrayInverse();
		order_BillService::getInstance()->genBill($billArray[0]);
	}
	
	/**
	 * @deprecated use order_BillService::getInstance()->createBill()
	 */
	public function createBill($order)
	{
		$billArray = $order->getBillArrayInverse();
		order_BillService::getInstance()->createBill($billArray[0]);
	}
	
	/**
	 * @deprecated 
	 */
	public function getStatusLabel($orderStatus)
	{
		$key = '&modules.order.frontoffice.status.' . ucfirst($orderStatus) . ';';
		return f_Locale::translate($key);
	}
	
	/**
	 * @deprecated 
	 */
	public function getBoStatusLabel($orderStatus)
	{
		$key = '&modules.order.frontoffice.status.' . ucfirst($orderStatus) . ';';
		return f_Locale::translateUI($key);
	}
}
