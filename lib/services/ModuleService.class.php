<?php
/**
 * @package modules.order
 * @method order_ModuleService getInstance()
 */
class order_ModuleService extends ModuleBaseService
{
	const ORDER_SESSION_NAMESPACE = 'ecommerce-order';
		
	/**
	 * Clears the service cached data.
	 * This method should be used only by unit tests.
	 */
	public function clearCache()
	{
		$this->preferencesDocument = null;
	}
	
	/**
	 * @param f_persistentdocument_PersistentDocument $document
	 * @param string[] $subModelNames
	 * @param integer $locateDocumentId null if use startindex
	 * @param integer $pageSize
	 * @param integer $startIndex
	 * @param integer $totalCount
	 * @return f_persistentdocument_PersistentDocument[]
	 */
	public function getVirtualChildrenAt($document, $subModelNames, $locateDocumentId, $pageSize, &$startIndex, &$totalCount)
	{
		if ($document->getDocumentModelName() === 'modules_generic/folder')
		{
			$dateLabel = $document->getLabel();
			$matches = null;
			if (preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $dateLabel, $matches) && in_array('modules_order/order', $subModelNames))
			{
				$startdate = date_Converter::convertDateToGMT($matches[0] . ' 00:00:00');
				$endate = date_Calendar::getInstance($startdate)->add(date_Calendar::DAY, 1)->toString();
				if ($locateDocumentId !== null)
				{
					$startIndex = 0;
					$idsArray = order_OrderService::getInstance()->createQuery()
					->add(Restrictions::between('creationdate', $startdate, $endate))
					->addOrder(Order::desc('id'))
					->setProjection(Projections::property('id', 'id'))->findColumn('id');
					$totalCount = count($idsArray);
					foreach ($idsArray as $index => $id)
					{
						if ($id == $locateDocumentId)
						{
							$startIndex = $index - ($index % $pageSize);
							break;
						}
					}
				}
				else
				{
					$countQuery = order_OrderService::getInstance()->createQuery()
					->add(Restrictions::between('creationdate', $startdate, $endate))
					->setProjection(Projections::rowCount('countItems'));
					$totalCount = intval(f_util_ArrayUtils::firstElement($countQuery->findColumn('countItems')));
				}
				$query = order_OrderService::getInstance()->createQuery()
				->add(Restrictions::between('creationdate', $startdate, $endate))
				->addOrder(Order::desc('id'))
				->setFirstResult($startIndex)->setMaxResults($pageSize);
				return $query->find();
			}
			else
			{
				return generic_FolderService::getInstance()->createQuery()
				->add(Restrictions::childOf($document->getId()))
				->addOrder(Order::desc('label'))
				->find();
			}
		}
		return null;
	}
	
	/**
	 * @param f_persistentdocument_PersistentDocument $document
	 * @return f_persistentdocument_PersistentDocument or null
	 */
	public function getVirtualParentForBackoffice($document)
	{
		if ($document instanceof order_persistentdocument_order)
		{
			$folderName = date_Formatter::format($document->getUICreationdate(), 'Y-m-d');
			return generic_FolderService::getInstance()->createQuery()
				->add(Restrictions::descendentOf(ModuleService::getInstance()->getRootFolderId('order')))
				->add(Restrictions::eq('label', $folderName))->setMaxResults(1)->findUnique();
		}
		return null;
	}
		
	/**
	 * Checks if the order process is enable or not.
	 * 
	 * @return boolean
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
	 * @return string
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
			return str_replace(
				array('{beginDate}', '{endDate}'),
				array(
					date_Formatter::toDefaultDate($pref->getOrderProcessClosedBeginDate()), 
					date_Formatter::toDefaultDate($pref->getOrderProcessClosedEndDate())
				),
				$message
			);
		}
		return null;
	}
		
	/**
	 * Returns the currently logged customer.
	 * @return customer_persistentdocument_customer
	 */
	public final function getCustomer()
	{
		return customer_CustomerService::getInstance()->getCurrentCustomer();
	}	
	
	/**
	 * @return boolean
	 */
	public function areCommentsEnabled()
	{
		return catalog_ModuleService::getInstance()->areCommentsEnabled();
	}
	
	/**
	 * @return boolean
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
	 * @param array $specificParams
 	 * @return boolean
	 */
	public function sendCustomerNotification($notifCodeName, $order, $bill = null, $expedition = null, $specificParams = null)
	{
		$ns = notification_NotificationService::getInstance();
		$configuredNotif = $ns->getConfiguredByCodeName($notifCodeName, $order->getWebsiteId(), $order->getLang());
		return $this->doSendCustomerNotification($configuredNotif, $order, $bill, $expedition, $specificParams);
	}
		
	/**
	 * @param string $notifCodeName
	 * @param string $suffix
	 * @param order_persistentdocument_order $order
	 * @param order_persistentdocument_bill $bill
	 * @param order_persistentdocument_expedition $expedition
	 * @param array $specificParams
 	 * @return boolean
	 */
	public function sendCustomerSuffixedNotification($notifCodeName, $suffix, $order, $bill = null, $expedition = null, $specificParams = null)
	{
		$ns = notification_NotificationService::getInstance();
		$configuredNotif = $ns->getConfiguredByCodeNameAndSuffix($notifCodeName, $suffix, $order->getWebsiteId(), $order->getLang());
		return $this->doSendCustomerNotification($configuredNotif, $order, $bill, $expedition, $specificParams);
	}
	
	/**
	 * @param string $notifCodeName
	 * @param order_persistentdocument_order $order
	 * @param order_persistentdocument_bill $bill
	 * @param order_persistentdocument_expedition $expedition
	 * @param array $specificParams
	 * @return boolean
	 */
	public function sendAdminNotification($notifCodeName, $order, $bill = null, $expedition = null, $specificParams = null)
	{
		$ns = notification_NotificationService::getInstance();
		$configuredNotif = $ns->getConfiguredByCodeName($notifCodeName, $order->getWebsiteId(), $order->getLang());
		return $this->doSendAdminNotification($configuredNotif, $order, $bill, $expedition, $specificParams);
	}
	
	/**
	 * @param string $notifCodeName
	 * @param string $suffix
	 * @param order_persistentdocument_order $order
	 * @param order_persistentdocument_bill $bill
	 * @param order_persistentdocument_expedition $expedition
	 * @param array $specificParams
	 * @return boolean
	 */
	public function sendAdminSuffixedNotification($notifCodeName, $suffix, $order, $bill = null, $expedition = null, $specificParams = null)
	{
		$ns = notification_NotificationService::getInstance();
		$configuredNotif = $ns->getConfiguredByCodeNameAndSuffix($notifCodeName, $suffix, $order->getWebsiteId(), $order->getLang());
		return $this->doSendAdminNotification($configuredNotif, $order, $bill, $expedition, $specificParams);
	}
	
	/**
	 * @param notification_persistentdocument_notification $configuredNotif
	 * @param order_persistentdocument_order $order
	 * @param order_persistentdocument_bill $bill
	 * @param order_persistentdocument_expedition $expedition
	 */
	public function registerNotificationCallback($configuredNotif, $order, $bill, $expedition)
	{
		if ($configuredNotif instanceof notification_persistentdocument_notification)
		{
			if ($order instanceof order_persistentdocument_order)
			{
				$configuredNotif->registerCallback($order->getDocumentService(), 'getNotificationParameters', $order);
				$customer = $order->getCustomer();
				if ($customer instanceof customer_persistentdocument_customer)
				{
					$configuredNotif->registerCallback($customer->getDocumentService(), 'getNotificationParameters', $customer);
				}
			}
			if ($bill instanceof order_persistentdocument_bill)
			{
				$configuredNotif->registerCallback($bill->getDocumentService(), 'getNotificationParameters', $bill);
			}
	
			if ($expedition instanceof order_persistentdocument_expedition)
			{
				$configuredNotif->registerCallback($expedition->getDocumentService(), 'getNotificationParameters', $expedition);
			}
		}
	}	
		
	/**
	 * @param notification_persistentdocument_notification $configuredNotif
	 * @param order_persistentdocument_order $order
	 * @param order_persistentdocument_bill $bill
	 * @param order_persistentdocument_expedition $expedition
	 * @param array $specificParams
 	 * @return boolean
	 */
	protected function doSendCustomerNotification($configuredNotif, $order, $bill, $expedition, $specificParams)
	{
		if ($configuredNotif instanceof notification_persistentdocument_notification)
		{
			$configuredNotif->setSendingModuleName('order');
			if (is_array($specificParams) && count($specificParams))
			{
				foreach ($specificParams as $key => $value)
				{
					$configuredNotif->addGlobalParam($key, $value);
				}
			}
			$this->registerNotificationCallback($configuredNotif, $order, $bill, $expedition);
			$user = $order->getCustomer()->getUser();			
			return $configuredNotif->sendToUser($user);
		}
		else if (Framework::isInfoEnabled())
		{
			Framework::info(__METHOD__ . " No notification found");
		}
		return true;
	}
		
	/**
	 * @param notification_persistentdocument_notification $configuredNotif
	 * @param order_persistentdocument_order $order
	 * @param order_persistentdocument_bill $bill
	 * @param order_persistentdocument_expedition $expedition
	 * @param array $specificParams
	 * @return boolean
	 */
	protected function doSendAdminNotification($configuredNotif, $order, $bill, $expedition, $specificParams)
	{
		$result = true;
		if ($configuredNotif instanceof notification_persistentdocument_notification)
		{
			$users = $this->getOrderConfirmedNotificationUserPreference();
			if (is_array($users) && count($users))
			{
				$configuredNotif->setSendingModuleName('order');
				if (is_array($specificParams) && count($specificParams))
				{
					foreach ($specificParams as $key => $value)
					{
						$configuredNotif->addGlobalParam($key, $value);
					}
				}
				$this->registerNotificationCallback($configuredNotif, $order, $bill, $expedition);
				$result = true;
				foreach ($users as $user)
				{
					$result = $result && $configuredNotif->sendToUser($user);
				}
			}
		}
		else if (Framework::isInfoEnabled())
		{
			Framework::info(__METHOD__ . " No notification found");
		}
		return $result;			
	}
		
	/**
	 * @return boolean
	 */
	public function useOrderPreparationEnabled()
	{
		return Framework::getConfigurationValue('modules/order/useOrderPreparation', 'false') == 'true';
	}

	
	/**
	 * @return boolean
	 */
	public function isDefaultExpeditionGenerationEnabled()
	{
		return !$this->useOrderPreparationEnabled() && 
			Framework::getConfigurationValue('modules/order/generateDefaultExpedition', 'true') == 'true';
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @param integer $maxAge in minutes
	 */	
	public function checkOrderProcessing($order)
	{
		$generateDefaultExpedition = $this->isDefaultExpeditionGenerationEnabled();
		$maxAge = intval(Framework::getConfigurationValue('modules/order/maxDraftBillAge', '60'));
		
		$oos = $order->getDocumentService();
		$oess = order_ExpeditionService::getInstance();
		
		$orderStatus = $order->getOrderStatus();
		if ($orderStatus == order_OrderService::IN_PROGRESS)
		{
			foreach ($this->getDraftBills($order) as $bill) 
			{
				$this->clearDraftBill($bill);
			}
			
			if ($oess->isCompleteForOrder($order))
			{
				if ($oess->hasShippedExpeditionFromOrder($order))
				{
					$oos->completeOrder($order);
				}
				else
				{
					$oos->cancelOrder($order);
				}
			}
			elseif ($generateDefaultExpedition && order_BillService::getInstance()->hasPublishedBill($order))
			{
				//Génération de l'expedition par défaut en PREPARATION
				$oess->createForOrder($order);
			}
		}
		else if ($orderStatus == order_OrderService::INITIATED && $maxAge > 0)
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
	 * @param boolean $sendNotification
	 * @throws Exception
	 */
	public function finalizeOrder($order, $sendNotification = true)
	{
		if ($order->getOrderStatus() === order_OrderService::IN_PROGRESS)
		{
			$oos = $order->getDocumentService();
			$tm = $this->getTransactionManager();
			try
			{
				$tm->beginTransaction();
				if ($this->useOrderPreparationEnabled())
				{
					//Suppression des bon de preparation
					$oops = order_OrderpreparationService::getInstance();
					$opArray = order_OrderpreparationService::getInstance()->getByOrder($order);
					foreach ($opArray as $op)
					{
						$oops->delete($op);
					}
				}
				$oes = order_ExpeditionService::getInstance();
				$oes->cleanUpExpeditionsForOrder($order);
				$expArray = $oes->getShippedByOrder($order);
				if (count($expArray))
				{
					$oos->completeOrder($order, $sendNotification);
				}
				else
				{
					$oos->cancelOrder($order, $sendNotification);
				}
				$tm->commit();
			}
			catch (Exception $e)
			{
				$tm->rollback($e);
				throw $e;
			}
		}
		else
		{
			throw new BaseException('Invalid Order Status', 'm.order.bo.actions.invalid-order-status');
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
		$user = users_UserService::getInstance()->getCurrentBackEndUser();
		
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
	
	/**
	 * @deprecated (will be removed in 4.0) with no replacement
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
	 * @deprecated (will be removed in 4.0) with no replacement
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
	 * @deprecated  (will be removed in 4.0) with no replacement
	 */
	public function getNotificationParameters($params)
	{
		return array();
	}
}