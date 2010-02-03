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
		if (is_null($pref))
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
		if (! is_null($beginDate))
		{
			$beginDate = date_Calendar::getInstance($beginDate);
		}
		$endDate = $pref->getOrderProcessClosedEndDate();
		if (! is_null($endDate))
		{
			$endDate = date_Calendar::getInstance($endDate);
		}
		
		// Check dates.
		if (! is_null($beginDate) && ! is_null($endDate))
		{
			return ! $now->isBetween($beginDate, $endDate, true);
		}
		else if (! is_null($beginDate))
		{
			return $now->isBefore($beginDate);
		}
		else if (! is_null($endDate))
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
		$message = null;
		$pref = $this->getPreferencesDocument();
		if (!is_null($pref))
		{
			// Choose the message, depending on the closing type (planned or temporarily).
			if ($pref->getOrderProcessClosed())
			{
				return $pref->getOrderProcessClosedMessage();
			}
			$lang = RequestContext::getInstance()->getLang();
			$message = $pref->getOrderProcessClosedDateMessage();
			$message = str_replace(array('{beginDate}', '{endDate}'), array(date_DateFormat::format(date_Calendar::getInstance($pref->getOrderProcessClosedBeginDate()), date_DateFormat::getDateFormatForLang($lang)), date_DateFormat::format(date_Calendar::getInstance($pref->getOrderProcessClosedEndDate()), date_DateFormat::getDateFormatForLang($lang))), $message);
		}
		return $message;
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
		Framework::debug(__METHOD__ . ' : ' . var_export($this->areCommentsEnabled(), true));
		Framework::debug(__METHOD__ . ' : ' . var_export(ModuleService::getInstance()->getPreferenceValue('order', 'enableCommentReminder'), true));
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
}