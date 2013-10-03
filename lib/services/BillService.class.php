<?php
/**
 * order_BillService
 * @package modules.order
 */
class order_BillService extends f_persistentdocument_DocumentService
{
	const INITIATED = "initiated";
	const WAITING = "waiting";
	const SUCCESS = "success";
	const FAILED = "failed";
	
	const BILL_STATUS_MODIFIED_EVENT = 'order_billStatusChanged';
		
	/**
	 * @var order_BillService
	 */
	private static $instance;

	/**
	 * @return order_BillService
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
	 * @return order_persistentdocument_bill
	 */
	public function getNewDocumentInstance()
	{
		return $this->getNewDocumentInstanceByModelName('modules_order/bill');
	}

	/**
	 * Create a query based on 'modules_order/bill' model.
	 * Return document that are instance of modules_order/bill,
	 * including potential children.
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createQuery()
	{
		return $this->pp->createQuery('modules_order/bill');
	}
	
	/**
	 * Create a query based on 'modules_order/bill' model.
	 * Only documents that are strictly instance of modules_order/bill
	 * (not children) will be retrieved
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createStrictQuery()
	{
		return $this->pp->createQuery('modules_order/bill', false);
	}
	
	/**
	 * @return boolean
	 */
	public function generateBillIsActive()
	{
		return Framework::getConfigurationValue("modules/order/genBill") == 'true';
	}
	
	public function genBills()
	{
		if (!$this->generateBillIsActive())
		{
			return;
		}
		
		$query = $this->createQuery()->add(Restrictions::published())->add(Restrictions::isNull("archive"));

		foreach ($query->find() as $bill)
		{
			$this->genBill($bill);
		}
	}
	
	/**
	 * @param order_persistentdocument_bill $bill
	 * @throws Exception
	 */
	public function genBill($bill)
	{	
		if (!$this->generateBillIsActive() || !$bill->isPublished() || $bill->hasTemporaryNumber() || $bill->getArchive() !== null)
		{
			return;
		}

		$tmpPath = f_util_FileUtils::getTmpFile();
		$this->createBill($bill, $tmpPath);
		
		try
		{
			$this->tm->beginTransaction();
			$media = media_SecuremediaService::getInstance()->getNewDocumentInstance();
			$label = $bill->getLabel();
			$media->setLabel($label);
			$media->setTitle($label);
			$media->setNewFileName($tmpPath, 'bill-' . $bill->getId() . '.pdf');
			$media->save();
			$bill->setArchive($media);
			$bill->save();
			$this->tm->commit();
		}
		catch (Exception $e)
		{
			$this->tm->rollBack($e);
			throw $e;
		}
	}
	
	/**
	 * @param order_persistentdocument_bill $bill
	 * @params string $filePath
	 * @throws Exception
	 */
	public function createBill($bill, $filePath = null)
	{
		$className = Framework::getConfigurationValue("modules/order/billPDFGenerator");
		if ($className && f_util_ClassUtils::classExists($className))
		{
			$generator = new $className();
			$generator->writePDF($bill, $filePath);
		}
		else
		{
			throw new Exception("Invalid configuration: modules/order/billPDFGenerator. $className class doesn't exist");
		}
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @return order_persistentdocument_bill
	 */
	public function initializeByOrderForPayment($order)
	{
		try
		{
			$this->getTransactionManager()->beginTransaction();
			
			if ($order->getOrderStatus() === order_OrderService::CANCELED)
			{
				$order->setOrderStatus(order_OrderService::INITIATED);
				$order->setLabel(date_Calendar::now()->toString());
				$this->pp->updateDocument($order);
			}			
			
			$bill = $this->createQuery()->add(Restrictions::eq('publicationstatus', 'DRAFT'))
				->add(Restrictions::eq('order', $order))
				->findUnique();
				
			if ($bill === null)
			{
				$bill = $this->getNewDocumentInstance();
			}
			
	
							
			$this->fillBillByOrder($bill, $order);
			$connector = $bill->getPaymentConnector();
			$connector->getDocumentService()->initializePayment($bill);			
			$bill->save();
			

			
			$this->getTransactionManager()->commit();
		}
		catch (Exception $e)
		{
			$this->getTransactionManager()->rollBack($e);
			throw $e;
		}
		return $bill;
	}
	
	/* (non-PHPdoc)
	 * @see f_persistentdocument_DocumentService::preInsert()
	 * @param order_persistentdocument_bill $document
	 * @param integer $parentNodeId
	 */
	protected function preInsert($document, $parentNodeId)
	{
		parent::preInsert($document, $parentNodeId);
		$document->setClientIp(RequestContext::getInstance()->getClientIp());
	}

	/**
	 * @param order_persistentdocument_order $order
	 * @return order_persistentdocument_bill[]
	 */
	public function getByOrder($order)
	{
		$query = $this->createQuery()->add(Restrictions::eq('order', $order))
				->add(Restrictions::ne('publicationstatus', 'DRAFT'))
				->addOrder(Order::asc('id'));
		return $query->find();
	}

	/**
	 * @param order_persistentdocument_order $order
	 * @return order_persistentdocument_bill[]
	 */
	public function getValidByOrder($order)
	{
		$query = $this->createQuery()->add(Restrictions::eq('order', $order))
				->add(Restrictions::ne('publicationstatus', 'DRAFT'))
				->add(Restrictions::in('status', array(self::WAITING, self::SUCCESS)))
				->addOrder(Order::asc('status'))
				->addOrder(Order::asc('id'));
		return $query->find();
	}
		
	/**
	 * @param order_persistentdocument_order $order
	 * @return order_persistentdocument_bill[]
	 */
	public function getByOrderForPayment($order)
	{
		$query = $this->createQuery()->add(Restrictions::eq('order', $order))
				->addOrder(Order::asc('document_id'));
		return $query->find();
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @return double
	 */
	public function getNotPaidAmountByOrder($order)
	{
		$query = order_BillService::getInstance()->createQuery()->add(Restrictions::eq('order', $order));
		$query->add(Restrictions::ne('status', self::SUCCESS))
			->setProjection(Projections::sum('amount', 'amount'));
		return f_util_ArrayUtils::firstElement($query->findColumn('amount'));
	}

	/**
	 * @param order_persistentdocument_order $order
	 * @return double
	 */
	public function getPaidAmountByOrder($order)
	{
		$query = order_BillService::getInstance()->createQuery()->add(Restrictions::eq('order', $order));
		$query->add(Restrictions::eq('status', self::SUCCESS))->setProjection(Projections::sum('amount', 'amount'));
		return f_util_ArrayUtils::firstElement($query->findColumn('amount'));
	}
	
	/**
	 * @param order_persistentdocument_bill $bill
	 * @param order_persistentdocument_order $order
	 */
	protected function fillBillByOrder($bill, $order)
	{
		$bill->setOrder($order);
		$bill->setLabel($order->getOrderNumber());
		$bill->setAddress($order->getBillingAddress());
		$bill->setConnectorId($order->getBillingModeId());
		$bill->setAmount($order->getTotalAmountWithTax());
		$bill->setCurrency($order->getCurrencyCode());
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @return boolean
	 */
	public function hasValidBill($order)
	{		
		$result = $this->createQuery()
			->add(Restrictions::eq('order', $order))
			->add(Restrictions::ne('publicationstatus', 'FILED'))
			->setProjection(Projections::rowCount('rowCount'))->findColumn('rowCount');
		return $result[0] > 0;
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @return boolean
	 */
	public function hasPublishedBill($order)
	{
		$result = $this->createQuery()
			->add(Restrictions::eq('order', $order))
			->add(Restrictions::published())
			->setProjection(Projections::rowCount('rowCount'))->findColumn('rowCount');
		return $result[0] > 0;
	}	
	
	/**
	 * @param integer $orderId
	 * @return boolean
	 */
	public function hasBillInTransactionByOrderId($orderId)
	{
		if (Framework::isInfoEnabled())
		{
			Framework::info(__METHOD__. ' for orderId' . $orderId);
		}
		$result = $this->createQuery()
			->add(Restrictions::eq('order.id', $orderId))
			->add(Restrictions::ne('publicationstatus', 'FILED'))
			->add(Restrictions::ne('publicationstatus', 'DRAFT'))
			->setProjection(Projections::rowCount('rowCount'))->findColumn('rowCount');
		return $result[0] > 0;
	}		
	
	/**
	 * @param order_persistentdocument_bill $bill
	 * @param string $newStatus in waiting, success, failed
	 */
	public function updatePaymentStatus($bill, $newStatus)
	{
		$oldStatus = $bill->getStatus();
		try 
		{
			$this->tm->beginTransaction();
			$bill->setStatus($newStatus);
			switch ($newStatus) 
			{
				case self::FAILED:
					$this->cancelPayment($bill);
					break;
				default: // success, waiting
					$this->confirmPayment($bill);
					break;
			}
			$this->tm->commit();
		}
		catch (Exception $e)
		{
			$this->tm->rollBack($e);
			throw $e;
		}
		if ($oldStatus != $newStatus)
		{
			f_event_EventManager::dispatchEvent(self::BILL_STATUS_MODIFIED_EVENT, $this, array('document' => $bill, 'oldStatus' => $oldStatus));
		}
	}
	
	/**
	 * @param order_persistentdocument_bill $bill
	 */
	protected function cancelPayment($bill)
	{
		$order = $bill->getOrder();	
		order_ModuleService::getInstance()->sendCustomerNotification('modules_order/bill_failed', $order, $bill);
		$this->cancelBill($bill);
		$order->getDocumentService()->cancelOrder($order, false);
	}
	
	/**
	 * @param order_persistentdocument_bill $bill
	 */
	public function cancelBill($bill)
	{
		if ($bill->getTransactionId())
		{
			$bill->setPublicationstatus('FILED');
			$this->save($bill);
		}
		else
		{
			$this->delete($bill);
		}
	}
	
	/**
	 * @see order_OrderService::cancelOrder()
	 * @param order_persistentdocument_order $order
	 */
	public function cleanByOrder($order)
	{
		$query = $this->createQuery()
			->add(Restrictions::eq('order', $order))
			->add(Restrictions::eq('status', self::WAITING));
		foreach ($query->find() as $bill)
		{
			$bill->setTransactionDate(null);
			$bill->setStatus(self::FAILED);
			$backendUser = users_UserService::getInstance()->getCurrentBackEndUser();
			if (f_util_StringUtils::isEmpty($bill->getTransactionId()))
			{
				$bill->setTransactionId('CANCEL-BY-' . (($backendUser) ?  $backendUser->getId() : 'UNKNOWN'));
			}
			$bill->setTransactionText(LocaleService::getInstance()->transBO('m.order.bo.general.canceled-by', array('ucf', 'lab')) . ' ' . (($backendUser) ? $backendUser->getFullname() : 'UNKNOWN'));
			$this->save($bill);
			$this->cancelBill($bill);
			f_event_EventManager::dispatchEvent(self::BILL_STATUS_MODIFIED_EVENT, $this, array('document' => $bill, 'oldStatus' => self::WAITING));
		}

		$this->createQuery()
			->add(Restrictions::eq('order', $order))
			->add(Restrictions::eq('status', self::INITIATED))->delete();
	}
	
	/**
	 * @param order_persistentdocument_bill $bill
	 */
	protected function confirmPayment($bill)
	{
		if ($bill->getPublicationstatus() == 'FILED')
		{
			$bill->setPublicationstatus('DRAFT');
			$this->save($bill);
		}
		$order = $bill->getOrder();
		$this->applyNumber($bill);
		$this->activate($bill->getId());
		if ($bill->getStatus() == self::SUCCESS)
		{
			$this->validatePayment($bill);
		}
		else
		{
			$order->getDocumentService()->processOrder($order);
			order_ModuleService::getInstance()->sendCustomerNotification('modules_order/bill_' . $bill->getStatus(), $order, $bill);
			order_ModuleService::getInstance()->sendAdminNotification('modules_order/bill_admin_' . $bill->getStatus(), $order, $bill);
		}
	}
	
	/**
	 * @param order_persistentdocument_bill $document
	 * @param boolean $forceGeneration
	 */
	public function applyNumber($document, $forceGeneration = false)
	{
		if (!$forceGeneration && order_ModuleService::getInstance()->delayNumberGeneration())
		{
			$document->setLabel(order_ModuleService::TEMPORARY_NUMBER);
		}
		else
		{
			$document->setLabel(order_BillNumberGenerator::getInstance()->generate($document));
		}
	}
	
	/**
	 * @param order_persistentdocument_bill $bill
	 */		
	protected function validatePayment($bill)
	{
		$order = $bill->getOrder();
		if ($order->getOrderStatus() == order_OrderService::INITIATED || $order->getOrderStatus() == order_OrderService::CANCELED)
		{
			$order->getDocumentService()->updateStock($order);
			$order->getDocumentService()->processOrder($order);
			$customer = $order->getCustomer();
			$customer->setCart(null);
			$customer->setLastAbandonedOrderDate(null);
			if ($customer->isModified())
			{
				$this->pp->updateDocument($customer);
			}
			$bill->setPaidByCustomerId($customer->getId());
			$this->pp->updateDocument($bill);
			order_ModuleService::getInstance()->sendCustomerNotification('modules_order/bill_success', $order, $bill);
			order_ModuleService::getInstance()->sendAdminNotification('modules_order/bill_admin_success', $order, $bill);
		}
	}
	
	/**
	 * @param order_persistentdocument_bill $document
	 * @return boolean true if the document is publishable, false if it is not.
	 */
	public function isPublishable($document)
	{
		$result = parent::isPublishable($document);
		if ($result)
		{
			if ($document->getStatus() === self::SUCCESS)
			{
				return true;
			}
			else
			{
				$this->setActivePublicationStatusInfo($document, '&modules.order.document.bill.Not-validated;');	
			}
		}
		return false;
	}
	
	/**
	 * @param order_persistentdocument_bill $bill
	 * @return array<string,string>
	 */
	public function getNotificationParameters($bill)
	{
		$params = array(
			'billnumber' => $bill->getLabelAsHtml(), 
			'billdate' => $bill->getUITransactionDate(),
			'billtransaction' => $bill->getTransactionTextAsHtml()
		);
		return $params;
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @return array
	 */
	public function getBoList($order)
	{
		$result = array();
		foreach ($this->getByOrder($order) as $bill) 
		{
			$result[] = $this->buildBoRow($bill);
		}
		return $result;	
	}
	
	/**
	 * @param order_persistentdocument_bill $bill
	 * @return array
	 */
	private function buildBoRow($bill)
	{
		$result = array(
			'id' => $bill->getId(),
			'lang' => $bill->getLang(),
			'type' => str_replace('/', '_', $bill->getDocumentModelName()),
			'st' => $bill->getStatus(),
			'trsid' => $bill->getTransactionId(),
			'status' => $bill->getBoStatusLabel(),
			'label' => $bill->getLabel(),
			'archive' => $bill->getArchiveBoURL(),
			'amount' => $bill->getAmountFormated(),
		);
		return $result;
	}
	
	/**
	 * @param order_persistentdocument_bill $bill
	 * @param string $transactionDate
	 * @param string $transactionId
	 * @param string $transactionText
	 * @return array
	 */
	public function validateBillFromBo($bill, $transactionDate, $transactionId, $transactionText)
	{
		if ($bill->getStatus() == self::WAITING 
			&& f_util_StringUtils::isNotEmpty($transactionDate)
			&& f_util_StringUtils::isNotEmpty($transactionId)
			&& f_util_StringUtils::isNotEmpty($transactionText))
		{
			try 
			{
				$this->tm->beginTransaction();

				$bill->setStatus(self::SUCCESS);
				$bill->setTransactionId($transactionId);
				$bill->setTransactionDate(date_Converter::convertDateToGMT($transactionDate));
				$bill->setTransactionText($transactionText);
				$this->save($bill);
				$this->validatePayment($bill);

				$customer =  $bill->getOrder()->getCustomer();
				$customer->getDocumentService()->setLastOrderForCustomer($bill->getOrder(), $customer);

				$this->tm->commit();
			}
			catch (Exception $e)
			{
				$this->tm->rollBack($e);
				throw $e;
			}
			f_event_EventManager::dispatchEvent(self::BILL_STATUS_MODIFIED_EVENT, $this, array('document' => $bill, 'oldStatus' => self::WAITING));
		}
		else
		{
			throw new Exception('Error on bill validation');
		}
		return $this->buildBoRow($bill);
	}
	
	/**
	 * @param order_persistentdocument_bill $bill
	 * @return array
	 */
	public function cancelBillFromBo($bill)
	{
		$oldStatus = $bill->getStatus();
		try
		{
			$this->tm->beginTransaction();
			$bill->setTransactionDate(null);
			$bill->setStatus(self::FAILED);
			
			$backendUser = users_UserService::getInstance()->getCurrentBackEndUser();
			if (f_util_StringUtils::isEmpty($bill->getTransactionId()))
			{
				$bill->setTransactionId('CANCEL-BY-' . (($backendUser) ?  $backendUser->getId() : 'UNKNOWN'));
			}
			$bill->setTransactionText(LocaleService::getInstance()->transBO('m.order.bo.general.canceled-by', array('ucf', 'lab')) . ' ' . (($backendUser) ? $backendUser->getFullname() : 'UNKNOWN'));
			$this->save($bill);
			$order = $bill->getOrder();
			order_ModuleService::getInstance()->sendCustomerNotification('modules_order/bill_failed', $order, $bill);
			$order->getDocumentService()->cancelOrder($order, false);
			$this->tm->commit();
		}
		catch (Exception $e)
		{
			$this->tm->rollBack($e);
			throw $e;
		}
		if ($oldStatus != self::FAILED)
		{
			f_event_EventManager::dispatchEvent(self::BILL_STATUS_MODIFIED_EVENT, $this, array('document' => $bill, 'oldStatus' => $oldStatus));
		}
		return $this->buildBoRow($bill);
	}
	
	/**
	 * @see f_persistentdocument_DocumentService::getResume()
	 *
	 * @param order_persistentdocument_bill $document
	 * @param string $forModuleName
	 * @param array $allowedSections
	 * @return array
	 */
	public function getResume($document, $forModuleName, $allowedSections = null)
	{
		$data = parent::getResume($document, $forModuleName, $allowedSections);
		
		$data['properties']['status'] = $document->getBoStatusLabel();
		if ($document->getTransactionId())
		{
			$transactionId = $document->getTransactionId();
			$data['transaction']['transactionId'] = $transactionId;
			// This one is deprecated
			$data['properties']['transactionId'] = $transactionId; 
		}
		
		if ($document->getTransactionDate())
		{
			$dateTimeFormatted = date_Formatter::toDefaultDateTimeBO($document->getUITransactionDate());			
			$data['transaction']['transactionDate'] = $dateTimeFormatted;
			// This one is deprecated
			$data['properties']['transactionDate'] = $dateTimeFormatted;
		}
		$order = $document->getOrder();
		try 
		{
			$connector = $document->getPaymentConnector();
			if ($connector)
			{
				$infos = array();
				foreach ($connector->getDocumentService()->parsePaymentResponse($document) as $label => $value)
				{
					$infos[] = array("label" => $label, "value" => $value);
				}
				$data['transaction']['transactionInfo'] = $infos;
			}
		}
		catch (Exception $e)
		{
			Framework::warn(__METHOD__ . " Connector not found:" . $document->getConnectorId());
		}
		$data['properties']['amount'] = $document->getAmountFormated();
		
		$data['links']['customer'] = $order->getCustomer()->getLabel();
		$data['links']['order'] = $order->getOrderNumber();
		return $data;
	}
	
	// DEPRECATED.
	
	/**
	 * @deprecated
	 */
	public function udatePaymentStatus($bill, $newStatus)
	{
		$this->updatePaymentStatus($bill, $newStatus);
	}
}