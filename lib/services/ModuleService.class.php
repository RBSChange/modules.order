<?php
/**
 * order_ModuleService
 * @package modules.order.lib.services
 */
class order_ModuleService extends ModuleBaseService
{
	const ORDER_SESSION_NAMESPACE = 'ecommerce-order';
	
	/**
	 * @var order_ModuleService
	 */
	private static $instance;
	
	/**
	 * @return order_ModuleService
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
	 * Clears the service cached data.
	 * This method should be used only by unit tests.
	 */
	public function clearCache()
	{
		$this->preferencesDocument = null;
	}
		
	/**
	 * Checks if the order process is enable or not.
	 * 
	 * @return Boolean
	 */
	public function isProcessOpen()
	{
		$pref = $this->getPreferencesDocument();
		
		// No preferences set: order process is open.
		if ($pref === null)
		{
			return true;
		}
		
		// If current customer is allowed, return true.
		if (!is_null($customer = $this->getCustomer()))
		{
			$allowedCustomerArray = $pref->getOrderProcessAllowedCustomerArray();
			foreach ($allowedCustomerArray as $allowedCustomer)
			{
				if ($allowedCustomer->getId() == $customer->getId())
				{
					return true;
				}
			}
		}
		
		// If order process is closed temporarily, return false.
		if ($pref->getOrderProcessClosed())
		{
			return false;
		}
		
		$now = date_Calendar::now();
		$beginDate = $pref->getOrderProcessClosedBeginDate();
		if ($beginDate !== null)
		{
			$beginDate = date_Calendar::getInstance($beginDate);
		}
		$endDate = $pref->getOrderProcessClosedEndDate();
		if ($endDate !== null)
		{
			$endDate = date_Calendar::getInstance($endDate);
		}
		
		// Check dates.
		if ($beginDate !== null && $endDate !== null)
		{
			return ! $now->isBetween($beginDate, $endDate, true);
		}
		else if ($beginDate !== null)
		{
			return $now->isBefore($beginDate);
		}
		else if ($endDate !== null)
		{
			return $now->isAfter($endDate);
		}
		
		return true;
	}
	
	/**
	 * @return String
	 */
	public function getProcessClosedMessage()
	{
		$pref = $this->getPreferencesDocument();
		if ($pref !== null)
		{
			// Choose the message, depending on the closing type (planned or temporarily).
			if ($pref->getOrderProcessClosed())
			{
				return $pref->getOrderProcessClosedMessage();
			}
			$lang = RequestContext::getInstance()->getLang();
			$message = $pref->getOrderProcessClosedDateMessage();
			return str_replace(array('{beginDate}', '{endDate}'), array(date_DateFormat::format(date_Calendar::getInstance($pref->getOrderProcessClosedBeginDate()), date_DateFormat::getDateFormatForLang($lang)), date_DateFormat::format(date_Calendar::getInstance($pref->getOrderProcessClosedEndDate()), date_DateFormat::getDateFormatForLang($lang))), $message);
		}
		return null;
	}
		
	/**
	 * Returns the currently logged customer.
	 *
	 * @return customer_persistentdocument_customer
	 */
	public final function getCustomer()
	{
		return customer_CustomerService::getInstance()->getCurrentCustomer();
	}	
	

	
	/**
	 * @return Boolean
	 */
	public function areCommentsEnabled()
	{
		return catalog_ModuleService::getInstance()->areCommentsEnabled();
	}
	
	/**
	 * @return Boolean
	 */
	public function areCommentRemindersEnabled()
	{
		return $this->areCommentsEnabled() && $this->getEnableCommentReminderPreference();
	}
		
	/**
	 * @var order_persistentdocument_preferences
	 */
	private $preferencesDocument = null;
	
	/**
	 * @return order_persistentdocument_preferences
	 */
	private function getPreferencesDocument()
	{
		if (is_null($this->preferencesDocument))
		{
			$this->preferencesDocument = order_PreferencesService::getInstance()->createQuery()->findUnique();
		}
		return $this->preferencesDocument;
	}
	
	/**
	 * @return boolean
	 */
	private function getEnableCommentReminderPreference()
	{
		$pref = $this->getPreferencesDocument();
		if ($pref !== null)
		{
			return $pref->getEnableCommentReminder();
		}
		return true;
	}
	
	/**
	 * @return array
	 */
	private function getOrderConfirmedNotificationUserPreference()
	{
		$pref = $this->getPreferencesDocument();
		if ($pref !== null)
		{
			return $pref->getOrderConfirmedNotificationUserArray();
		}
		return array();
	}	
		
	/**
	 * @param string $notifCodeName
	 * @param order_persistentdocument_order $order
	 * @param order_persistentdocument_bill $bill
	 * @param order_persistentdocument_expedition $expedition
 	 * @return boolean
	 */
	public function sendCustomerNotification($notifCodeName, $order, $bill = null, $expedition = null)
	{
		$this->setCurrentWebsiteIfNeeded($order);
		$ns = notification_NotificationService::getInstance();	
		$notif = $ns->getNotificationByCodeName($notifCodeName);
		if ($notif)
		{
			$recipents = $this->getMessageRecipients($order);
			if ($recipents)
			{
				$parameters = $order->getDocumentService()->getNotificationParameters($order);
				if ($bill instanceof order_persistentdocument_bill)
				{
					$parameters = array_merge($parameters, $bill->getDocumentService()->getNotificationParameters($bill));
				}
				if ($expedition instanceof order_persistentdocument_expedition)
				{
					$parameters = array_merge($parameters, $expedition->getDocumentService()->getNotificationParameters($expedition));
				}
				if (Framework::isInfoEnabled())
				{
					Framework::info(__METHOD__ . ' ' . $notifCodeName);
				}
				$ns->setMessageService(MailService::getInstance());		
				return $ns->send($notif, $recipents, $parameters, 'order');
			}
			else
			{
				if (Framework::isInfoEnabled())
				{
					Framework::info(__METHOD__ . " $notifCodeName has no customer email");
				}				
			}
		}
		else
		{
			if (Framework::isInfoEnabled())
			{
				Framework::info(__METHOD__ . " $notifCodeName not found");
			}
		}
		return true;
	}

	/**
	 * @param string $notifCodeName
	 * @param order_persistentdocument_order $order
	 * @param order_persistentdocument_bill $bill
	 * @param order_persistentdocument_expedition $expedition
	 * @return boolean
	 */
	public function sendAdminNotification($notifCodeName, $order, $bill = null, $expedition = null)
	{
		$this->setCurrentWebsiteIfNeeded($order);
		$ns = notification_NotificationService::getInstance();
		$notif = $ns->getNotificationByCodeName($notifCodeName);
		if ($notif)
		{
			$recipents = $this->getAdminRecipients();
			if ($recipents !== null)
			{
				$parameters = $order->getDocumentService()->getNotificationParameters($order);
				if ($bill instanceof order_persistentdocument_bill)
				{
					$parameters = array_merge($parameters, $bill->getDocumentService()->getNotificationParameters($bill));
				}
				if ($expedition instanceof order_persistentdocument_expedition)
				{
					$parameters = array_merge($parameters, $expedition->getDocumentService()->getNotificationParameters($expedition));
				}
				
				if (Framework::isInfoEnabled())
				{
					Framework::info(__METHOD__ . ' ' . $notifCodeName . ' '. var_export($parameters, true));
				}			
				$ns->setMessageService(MailService::getInstance());		
				return $ns->send($notif, $recipents, $parameters, 'order');				
			}
			else
			{
				if (Framework::isInfoEnabled())
				{
					Framework::info(__METHOD__ . " $notifCodeName has no admin recipient");
				}
			}
		}
		else
		{
			if (Framework::isInfoEnabled())
			{
				Framework::info(__METHOD__ . " $notifCodeName not found");
			}
		}	
		return true;			
	}	
	
	
	/**
	 * @param order_persistentdocument_order $order
	 * @return mail_MessageRecipients
	 */
	public function getMessageRecipients($order)
	{
		if ($order->getCustomer() && $order->getCustomer()->getUser())
		{
			$recipients = new mail_MessageRecipients();
			$recipients->setTo($order->getCustomer()->getUser()->getEmail());
			return $recipients;
		}
		return null;
	}
	
	/**
	 * @return mail_MessageRecipients
	 */
	public function getAdminRecipients()
	{
		$recipients = new mail_MessageRecipients();
		$emails = array();
		$admins = $this->getOrderConfirmedNotificationUserPreference();
		foreach ($admins as $admin)
		{
			$emails[] = $admin->getEmail();
		}

		if (count($emails))
		{
			$recipients->setTo($emails);
			return $recipients;
		}
		return null;
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 */
	private function setCurrentWebsiteIfNeeded($order)
	{
		$website = $order->getWebsite();
		$websiteModule = website_WebsiteModuleService::getInstance();
		if ($websiteModule->getCurrentWebsite() !== $website)
		{
			$websiteModule->setCurrentWebsite($website);
		}
	}
	
	/**
	 * @return boolean
	 */
	public function isDefaultExpeditionGenerationEnabled()
	{
		return Framework::getConfigurationValue('modules/order/generateDefaultExpedition', 'true') == 'true';
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @param integer $maxAge in minutes
	 */	
	public function checkOrderProcessing($order)
	{
		$generateDefaultExpedition = $this->isDefaultExpeditionGenerationEnabled();
		$maxAge = intval(Framework::getConfigurationValue('modules/order/maxDraftBillAge', '60'));
		
		$orderStatus = $order->getOrderStatus();
		if ($orderStatus == order_OrderService::IN_PROGRESS)
		{
			foreach ($this->getDraftBills($order) as $bill) 
			{
				$this->clearDraftBill($bill);
			}
			
			if ($generateDefaultExpedition && order_BillService::getInstance()->hasPublishedBill($order))
			{
				$result = order_ExpeditionService::getInstance()->createQuery()
						->add(Restrictions::eq('order', $order))
						->add(Restrictions::eq('status', order_ExpeditionService::PREPARE))
						->setProjection(Projections::rowCount('rowcount'))->find();
				
				if ($result[0]['rowcount'] == 0)
				{
					$expedition = order_ExpeditionService::getInstance()->createForOrder($order);
					if ($expedition === null)
					{
						Framework::info(__METHOD__ . ' all expedition shipped for order ' . $order->getOrderNumber());
						order_OrderService::getInstance()->completeOrder($order);					
					}
				}
			}
		}
		else if ($orderStatus == order_OrderService::INITIATED)
		{
			//Payment interompu en cours de processus
			$orderDate = $order->getCreationdate();
			$limitDate = date_Calendar::getInstance()->sub(date_Calendar::MINUTE, $maxAge)->toString();
						
			if ($orderDate < $limitDate)
			{
				$cancel = true;
									
				foreach ($this->getDraftBills($order) as $bill) 
				{
					if ($bill->getCreationdate() > $limitDate)
					{
						$cancel = false;
						break;
					}
					else
					{
						$this->clearDraftBill($bill);
					}
				}
				
				if ($cancel)
				{
					$order->getDocumentService()->cancelOrder($order, false);
				}
			}
		}
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @return order_persistentdocument_bill[]
	 */
	protected function getDraftBills($order)
	{
		return order_BillService::getInstance()->createQuery()
				->add(Restrictions::eq('publicationstatus', 'DRAFT'))
				->add(Restrictions::eq('order', $order))
				->find();
	}
	
	/**
	 * @param order_persistentdocument_bill $bill
	 */
	protected function clearDraftBill($bill)
	{
		$order = $bill->getOrder();
		$user = users_BackenduserService::getInstance()->getCurrentBackEndUser();
		
		if ($bill->getTransactionId())
		{
			Framework::info(__METHOD__ . ' FILED ' . $order->getOrderNumber() . ' / ' .$bill->getId());
			UserActionLoggerService::getInstance()->getInstance()->addUserDocumentEntry($user, 'filed.bill', $order, 
					array('orderNumber' => $order->getOrderNumber(), 'billId' => $bill->getId(), 'billLabel' => $bill->getLabel()), 'order');
			$bill->setPublicationstatus('FILED');
			$bill->save();
		}
		else
		{
			Framework::info(__METHOD__ . ' DELETE ' . $order->getOrderNumber() . ' / ' .$bill->getId());
			UserActionLoggerService::getInstance()->getInstance()->addUserDocumentEntry($user, 'purge.bill', $order, 
					array('orderNumber' => $order->getOrderNumber(), 'billId' => $bill->getId(), 'billLabel' => $bill->getLabel()), 'order');
			$bill->delete();
		}		
	}
	
	/**
	 * @deprecated
	 */
	public function getPageLink()
	{
		$ops = order_OrderProcessService::getInstance()->getInstance();
		$op = $ops->loadFromSession();
		return $op->getOrderProcessURL();
	}
	
	/**
	 * @deprecated
	 */
	public function getPage()
	{
		$ops = order_OrderProcessService::getInstance()->getInstance();
		$op = $ops->loadFromSession();
		return $op->getStepURL($op->getFirstStep());
	}
	
	/**
	 * @deprecated
	 */
	public function cancelProcess()
	{
		order_OrderProcessService::getInstance()->getInstance()->resetSessionOrderProcess();
	}
	
	/**
	 * @deprecated
	 */
	public function isProcessStarted()
	{
		$ops = order_OrderProcessService::getInstance()->getInstance();
		$op = $ops->loadFromSession();
		return $op->inProcess();
	}
		

	
	/**
	 * @deprecated
	 */
	public final function getCurrentStep()
	{
		$ops = order_OrderProcessService::getInstance()->getInstance();
		$op = $ops->loadFromSession();
		return $op->getCurrentStep();
	}
	
	/**
	 * @deprecated
	 */
	public final function getCatalog()
	{
		return catalog_ShopService::getInstance()->getCurrentShop();
	}
	
	/**
	 * @deprecated
	 */
	public final function getCartInfo()
	{
		$cartInfo = order_CartService::getInstance()->getDocumentInstanceFromSession();
		if (!is_null($cartInfo->getCustomerId()) && is_null($cartInfo->getBillingAddressId()))
		{
			$customer = $cartInfo->getCustomer();
			$defaultAddress  = $customer->getDefaultAddress();
			if ($defaultAddress)
			{
				$cartInfo->setBillingAddressId($defaultAddress->getId());
			}
		}
		return $cartInfo;
	}
	
	/**
	 * @deprecated
	 */
	public final function getFormFieldName($name)
	{
		return 'orderParam[' . $name . ']';
	}
	
	/**
	 * @deprecated
	 */
	public final function getCurrentOrderProcessId()
	{
	}
	
	/**
	 * @deprecated
	 */
	public final function setCurrentOrderProcessId($orderProcessId)
	{	
	}
	
	/**
	 * @deprecated
	 */
	public final function getCurrentOrderId()
	{
		return 0;
	}
	
	/**
	 * @deprecated
	 */
	public final function setCurrentOrderId($orderId)
	{
			
	}
}