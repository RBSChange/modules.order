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
	 * Returns the URL of the order process page.
	 * 
	 * @return String
	 */
	public function getPageLink()
	{
		$page = $this->getPage();
		$currentStep = $this->getCurrentStep();
		if ($currentStep > 0)
		{
			$parameters = array($this->getFormFieldName('nextStep') => $currentStep);
			return LinkHelper::getUrl($page, null, $parameters);
		}
		return LinkHelper::getUrl($page);
	}
	
	/**
	 * This method should be overloaded it there are several different order
	 * processes in your project: each order process must consist in a distinct
	 * block in a distinc page.  
	 * 
	 * @return website_persistentdocument_page
	 */
	public function getPage()
	{
		return TagService::getInstance()->getDocumentByContextualTag('contextual_website_website_modules_order_process', website_WebsiteModuleService::getInstance()->getCurrentWebsite());
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
	 * The order process is canceled and must be restarted from the begining.
	 *
	 * @return void
	 */
	public function cancelProcess()
	{
		$this->setCurrentOrderProcessId(null);
	}
	
	/**
	 * @return Boolean
	 */
	public function isProcessStarted()
	{
		return ($this->getCurrentOrderProcessId() !== null);
	}
		
	/**
	 * @return String
	 */
	public final function getCurrentOrderProcessId()
	{
		return $this->getSessionAttribute('orderProcessId');
	}
	
	/**
	 * @param String $orderProcessId
	 */
	public final function setCurrentOrderProcessId($orderProcessId)
	{
		if (!$this->hasSessionAttribute('orderProcessId') || $this->getSessionAttribute('orderProcessId') != $orderProcessId)
		{
			$this->setSessionAttribute('orderProcessId', $orderProcessId);
			
			if ($orderProcessId !== null)
			{
				UserActionLoggerService::getInstance()->addCurrentUserDocumentEntry('order-process-start', null, null, 'customer');
			}
		}
	}
	
	/**
	 * @return Integer
	 */
	public final function getCurrentStep()
	{
		$wizardSessionData = f_mvc_HTTPRequest::getInstance()->getSession()->getAttribute($this->getCurrentOrderProcessId());
		if (is_array($wizardSessionData) && isset($wizardSessionData['currentStep']))
		{
			return $wizardSessionData['currentStep'];
		}
		return 0;
	}
	
	/**
	 * @return Integer
	 */
	public final function getCurrentOrderId()
	{
		return $this->getSessionAttribute('orderId');
	}
	
	/**
	 * @param Integer $orderId
	 */
	public final function setCurrentOrderId($orderId)
	{
		$this->setSessionAttribute('orderId', $orderId);
	}
	
	/**
	 * @return order_CartInfo
	 */
	public final function getCartInfo()
	{
		$cartInfo = order_CartService::getInstance()->getDocumentInstanceFromSession();
		$customer = $this->getCustomer();
		if (!is_null($customer))
		{
			$cartInfo->setBillingAddressId($customer->getBillingAddress()->getId());
			$cartInfo->setCustomerId($customer->getId());
		}
		return $cartInfo;
	}
	
	/**
	 * @return catalog_persistentdocument_catalog
	 */
	public final function getCatalog()
	{
		return DocumentHelper::getDocumentInstance($this->getCartInfo()->getCatalogId());
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
	 * @param String $name
	 * @return String
	 */
	public final function getFormFieldName($name)
	{
		return 'orderParam[' . $name . ']';
	}
	
	/**
	 * @return Boolean
	 */
	public function areCommentsEnabled()
	{
		$cms = ModuleBaseService::getInstanceByModuleName('catalog');
		return $cms !== null && $cms->areCommentsEnabled();
	}
	
	/**
	 * @return Boolean
	 */
	public function areCommentRemindersEnabled()
	{
		return $this->areCommentsEnabled() && ModuleService::getInstance()->getPreferenceValue('order', 'enableCommentReminder');
	}
	
	// Protected stuff.
	
	/**
	 * @param String $name
	 * @param String $value
	 */
	protected final function setSessionAttribute($name, $value)
	{
		$this->getSession()->setAttribute($name, $value, self::ORDER_SESSION_NAMESPACE);
	}
	
	/**
	 * @param String $name
	 * @return String
	 */
	protected final function getSessionAttribute($name)
	{
		return $this->getSession()->getAttribute($name, self::ORDER_SESSION_NAMESPACE);
	}
	
	/**
	 * @param String $name
	 * @return String
	 */
	protected final function hasSessionAttribute($name)
	{
		return $this->getSession()->hasAttribute($name, self::ORDER_SESSION_NAMESPACE);
	}
	
	// Private stuff.
	
	/**
	 * @return User
	 */
	private final function getSession()
	{
		return Controller::getInstance()->getContext()->getUser();
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
			$this->preferencesDocument = ModuleService::getInstance()->getPreferencesDocument('order');
		}
		return $this->preferencesDocument;
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
		$admins = ModuleService::getPreferenceValue('order', 'orderConfirmedNotificationUser');
		if (is_array($admins))
		{
			foreach ($admins as $admin)
			{
				$emails[] = $admin->getEmail();
			}
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
	 * @param order_persistentdocument_order $order
	 */	
	public function checkOrderProcessing($order)
	{
		if ($order->getOrderStatus() == order_OrderService::IN_PROGRESS)
		{
			if (order_BillService::getInstance()->hasPublishedBill($order))
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
		else if (f_util_StringUtils::isEmpty($order->getOrderStatus()))
		{
			//Payment interompu en cours de processus
			if (date_Calendar::getInstance()->sub(date_Calendar::MINUTE, 10)->isAfter(date_Calendar::getInstance($order->getCreationdate())))
			{
				$cancel = true;
				$bill = new order_persistentdocument_bill();
				foreach ($order->getBillArrayInverse() as $bill) 
				{
					if ($bill->getPublicationstatus() == 'DRAFT')
					{
						$cancel = false;
						break;
					}
				}
				if ($cancel)
				{
					$order->getDocumentService()->cancelOrder($order);
				}
			}
		}
	}
}