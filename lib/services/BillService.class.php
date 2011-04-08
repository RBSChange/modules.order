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
		
		$query = $this->createQuery()->add(Restrictions::published())
			->add(Restrictions::isNull("archive"));

		foreach ($query->find() as $bill)
		{
			$this->genBill($bill);
		}
	}
	
	/**
	 * @param order_persistentdocument_bill $bill
	 */
	public function genBill($bill)
	{	
		if (!$this->generateBillIsActive() || !$bill->isPublished() || $bill->getArchive() !== null)
		{
			return;
		}
		
		$billContent = $this->createBill($bill);
		$tmpPath = f_util_FileUtils::getTmpFile();
		f_util_FileUtils::write($tmpPath, $billContent, f_util_FileUtils::OVERRIDE);
		
		try
		{
			$this->tm->beginTransaction();
			$media = media_SecuremediaService::getInstance()->getNewDocumentInstance();
			$label = $bill->getLabel();
			$media->setLabel($label);
			$media->setTitle($label);
			$media->setNewFileName($tmpPath, "bill-" . $bill->getId() .".pdf");
			$media->save();
			$bill->setArchive($media);
			$bill->save();
			$this->tm->commit();
		}
		catch (Exception $e)
		{
			$this->tm->rollBack($e);
		}
	}
	
	/**
	 * @param order_persistentdocument_bill $bill
	 * @return String the pdf content
	 */
	public function createBill($bill)
	{
		$order = $bill->getOrder();
		$shop = $order->getShop();
		$customer = $order->getCustomer();
		
		// OK, ... I will code a dedicated getInfo method
		$data = order_OrderService::getInstance()->getInfo($order);
		foreach ($data['informations'] as $key => $value)
		{
			$data[$key] = $value;
		}
		unset($data['informations']);
		
		//
		$data["number"] = $bill->getLabel();
		$data["amountWithoutTax"] = $shop->formatPrice($order->getLinesAmountWithoutTax());
		
		$lines = $data['billingAddressLine1'];
		if ($data['billingAddressLine2'] != "")
		{
			$lines .= "\n".$data['billingAddressLine2'];
		}
		if ($data['billingAddressLine3'] != "")
		{
			$lines .= "\n".$data['billingAddressLine3'];
		}
		$data['billingAddressLines'] = $lines;
	
		$taxes = array();
		foreach ($order->getTotalTaxInfoArray() as $subTotal)
		{
			$taxes[] = array("rate" => $subTotal['formattedTaxRate'],
				"amount" => $order->formatPrice($subTotal['taxAmount']));
		}
		$data['taxes'] = $taxes;		
		// Discounts
		$discounts = array();
		$cartModificators = $order->getDiscountDataArray();
		if (count($cartModificators) > 0)
		{
			foreach ($cartModificators as $discount) 
			{
				if ($discount["valueWithTax"] > 0)
				{
					$discounts[] = array("name" => $discount["label"], 
						"value" => $order->formatPrice($discount["valueWithTax"]));
				}
			}			
		}
		$data["discounts"] = $discounts;
		$lang = RequestContext::getInstance()->getLang();
		$data['creationdate'] = date_DateFormat::format($bill->getUICreationdate(), date_DateFormat::getDateFormatForLang($lang));
		$data['creationdatetime'] = date_DateFormat::format($bill->getUICreationdate(), date_DateFormat::getDateTimeFormatForLang($lang));
		$data['customerCode'] = $customer->getCode(); 
		
		$odt2pdf = new Odtphp2PDFClient(Framework::getConfigurationValue("modules/order/odtphp2pdfURL"));
		$billTemplate = FileResolver::getInstance()->setPackageName("modules_order")->setDirectory("templates")->getPath("billTemplate.odt");
		
		return $odt2pdf->getPdf($billTemplate, $data);
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
	protected function cancelBill($bill)
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
			$bill->setTransactionText(LocaleService::getInstance()->transBO('m.order.bo.general.canceled-by', array('ucf', 'labl')) . ' ' . (($backendUser) ? $backendUser->getFullname() : 'UNKNOWN'));
			$this->save($bill);
			$this->cancelBill($bill);
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
		$bill->setLabel(order_BillNumberGenerator::getInstance()->generate($bill));
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
	 * @param order_persistentdocument_bill $bill
	 */		
	protected function validatePayment($bill)
	{
		$order = $bill->getOrder();
		if ($order->getOrderStatus() == order_OrderService::INITIATED)
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
	 * @return Array<String=>String>
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
				
				$this->tm->commit();
			}
			catch (Exception $e)
			{
				$this->tm->rollBack($e);
				throw $e;
			}
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
			$bill->setTransactionText(LocaleService::getInstance()->transBO('m.order.bo.general.canceled-by', array('ucf', 'labl')) . ' ' . (($backendUser) ? $backendUser->getFullname() : 'UNKNOWN'));
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
			$dateTimeFormat = customer_ModuleService::getInstance()->getUIDateTimeFormat();
			$dateTimeFormatted = date_DateFormat::format($document->getUITransactionDate(), $dateTimeFormat);
			
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