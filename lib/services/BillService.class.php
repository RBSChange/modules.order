<?php
/**
 * order_BillService
 * @package modules.order
 */
class order_BillService extends f_persistentdocument_DocumentService
{
	
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
				"amount" => $shop->formatPrice($subTotal['taxAmount']));
		}
		$data['taxes'] = $taxes;		
		// Discounts
		$discounts = array();
		$cartModificators = $order->getGlobalProperty('__cartModificators');
		if (count($cartModificators) > 0)
		{
			$coupon = f_util_ArrayUtils::firstElement($cartModificators);
			$discounts[] = array("name" => $coupon["label"], "value" => $coupon['formattedValue']);
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
	 * Generate the next Bill number
	 * @return string
	 */
	public function getNextBillNumber()
	{
		$orderCount = $this->createQuery()->add(Restrictions::ne('publicationstatus', 'DRAFT'))
			->setProjection(Projections::rowCount("orderCount"))->findColumn("orderCount");
		return str_pad(strval($orderCount[0]+1), 5, '0', STR_PAD_LEFT);
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
	
	/**
	 * @param order_persistentdocument_order $order
	 * @return order_persistentdocument_bill[]
	 */
	public function getByOrder($order)
	{
		$query = $this->createQuery()->add(Restrictions::eq('order', $order))
				->add(Restrictions::ne('publicationstatus', 'DRAFT'))
				->addOrder(Order::asc('document_label'));
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
			->add(Restrictions::in('status', array(self::WAITING, self::SUCCESS)))
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
	 * @param order_persistentdocument_bill $bill
	 * @param string $newStatus in waiting, success, failed
	 */
	public function udatePaymentStatus($bill, $newStatus)
	{
		try 
		{
			$this->tm->beginTransaction();

			$bill->setStatus($newStatus);
			switch ($newStatus) 
			{
				case 'failed':
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
		$order->getDocumentService()->cancelOrder($order, false);
		$this->delete($bill);
	}
	
	/**
	 * @param order_persistentdocument_bill $bill
	 */	
	protected function confirmPayment($bill)
	{
		$order = $bill->getOrder();	
		$bill->setLabel($this->getNextBillNumber());	
		$this->activate($bill->getId());
		if ($bill->getStatus() == self::SUCCESS)
		{
			$this->validatePayment($bill);
		}
		else
		{
			$order->getDocumentService()->processOrder($order);
			order_ModuleService::getInstance()->sendCustomerNotification('modules_order/bill_' . $bill->getStatus(), $order, $bill);
		}
	}
	
	/**
	 * @param order_persistentdocument_bill $bill
	 */		
	protected function validatePayment($bill)
	{
		$order = $bill->getOrder();
		$order->getDocumentService()->updateStock($order);
		$order->getDocumentService()->processOrder($order);
		order_ModuleService::getInstance()->sendCustomerNotification('modules_order/bill_success', $order, $bill);
		order_ModuleService::getInstance()->sendAdminNotification('modules_order/bill_admin_success', $order, $bill);
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
			if ($document->getStatus() === 'success')
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
		return array('billnumber' => $bill->getLabel(), 
					 'billdate' => $bill->getUITransactionDate(),
					 'billtransaction' => $bill->getTransactionTextAsHtml()
		);
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
		
		//Generate the default expedition
		$expedition = order_ExpeditionService::getInstance()->createForOrder($bill->getOrder());
		if ($expedition)
		{
			$expedition->setBill($bill);
			$expedition->save();
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
			$bill->setTransactionId('CANCEL-BY-' . (($backendUser) ?  $backendUser->getId() : 'UNKNOWN'));
			$bill->setTransactionText('Cancel by :' . (($backendUser) ? $backendUser->getFullname() : 'UNKNOWN'));
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
			$data['properties']['transactionId'] = $document->getTransactionId();
		}
		
		if ($document->getTransactionDate())
		{
			$dateTimeFormat = customer_ModuleService::getInstance()->getUIDateTimeFormat();
			$data['properties']['transactionDate'] = date_DateFormat::format($document->getUITransactionDate(), $dateTimeFormat);
		}
		$order = $document->getOrder();
		
		$cmd = "openActionUri('customer,openDocument,modules_customer_customer,". $order->getCustomer()->getId() ."')";
		$data['links']['customer'] = array('moduleaction' => "", 'jsaction' => $cmd, 
			"actionlabel" => f_Locale::translateUI('&modules.order.bo.doceditor.panel.properties.View-customer;'), 
			"label" => $order->getCustomer()->getLabel());
		
		$cmd = "openActionUri('order,openDocument,modules_order_order,". $order->getId() ."')";
		$data['links']['order'] = array('moduleaction' => "", 'jsaction' => $cmd, 
			"actionlabel" => f_Locale::translateUI('&modules.order.bo.doceditor.panel.properties.View-order;'), 
			"label" => $order->getOrderNumber());
		return $data;
	}

	
	
	/**
	 * @param order_persistentdocument_bill $document
	 * @param Integer $parentNodeId Parent node ID where to save the document (optionnal => can be null !).
	 * @return void
	 */
//	protected function preSave($document, $parentNodeId = null)
//	{
//
//	}

	/**
	 * @param order_persistentdocument_bill $document
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
//	protected function preInsert($document, $parentNodeId = null)
//	{
//	}

	/**
	 * @param order_persistentdocument_bill $document
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
//	protected function postInsert($document, $parentNodeId = null)
//	{
//	}

	/**
	 * @param order_persistentdocument_bill $document
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
//	protected function preUpdate($document, $parentNodeId = null)
//	{
//	}

	/**
	 * @param order_persistentdocument_bill $document
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
//	protected function postUpdate($document, $parentNodeId = null)
//	{
//	}

	/**
	 * @param order_persistentdocument_bill $document
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
//	protected function postSave($document, $parentNodeId = null)
//	{
//	}

	/**
	 * @param order_persistentdocument_bill $document
	 * @return void
	 */
//	protected function preDelete($document)
//	{
//	}

	/**
	 * @param order_persistentdocument_bill $document
	 * @return void
	 */
//	protected function preDeleteLocalized($document)
//	{
//	}

	/**
	 * @param order_persistentdocument_bill $document
	 * @return void
	 */
//	protected function postDelete($document)
//	{
//	}

	/**
	 * @param order_persistentdocument_bill $document
	 * @return void
	 */
//	protected function postDeleteLocalized($document)
//	{
//	}




	/**
	 * Methode à surcharger pour effectuer des post traitement apres le changement de status du document
	 * utiliser $document->getPublicationstatus() pour retrouver le nouveau status du document.
	 * @param order_persistentdocument_bill $document
	 * @param String $oldPublicationStatus
	 * @param array<"cause" => String, "modifiedPropertyNames" => array, "oldPropertyValues" => array> $params
	 * @return void
	 */
//	protected function publicationStatusChanged($document, $oldPublicationStatus, $params)
//	{
//	}

	/**
	 * Correction document is available via $args['correction'].
	 * @param f_persistentdocument_PersistentDocument $document
	 * @param Array<String=>mixed> $args
	 */
//	protected function onCorrectionActivated($document, $args)
//	{
//	}

	/**
	 * @param order_persistentdocument_bill $document
	 * @param String $tag
	 * @return void
	 */
//	public function tagAdded($document, $tag)
//	{
//	}

	/**
	 * @param order_persistentdocument_bill $document
	 * @param String $tag
	 * @return void
	 */
//	public function tagRemoved($document, $tag)
//	{
//	}

	/**
	 * @param order_persistentdocument_bill $fromDocument
	 * @param f_persistentdocument_PersistentDocument $toDocument
	 * @param String $tag
	 * @return void
	 */
//	public function tagMovedFrom($fromDocument, $toDocument, $tag)
//	{
//	}

	/**
	 * @param f_persistentdocument_PersistentDocument $fromDocument
	 * @param order_persistentdocument_bill $toDocument
	 * @param String $tag
	 * @return void
	 */
//	public function tagMovedTo($fromDocument, $toDocument, $tag)
//	{
//	}

	/**
	 * Called before the moveToOperation starts. The method is executed INSIDE a
	 * transaction.
	 *
	 * @param f_persistentdocument_PersistentDocument $document
	 * @param Integer $destId
	 */
//	protected function onMoveToStart($document, $destId)
//	{
//	}

	/**
	 * @param order_persistentdocument_bill $document
	 * @param Integer $destId
	 * @return void
	 */
//	protected function onDocumentMoved($document, $destId)
//	{
//	}

	/**
	 * this method is call before saving the duplicate document.
	 * If this method not override in the document service, the document isn't duplicable.
	 * An IllegalOperationException is so launched.
	 *
	 * @param order_persistentdocument_bill $newDocument
	 * @param order_persistentdocument_bill $originalDocument
	 * @param Integer $parentNodeId
	 *
	 * @throws IllegalOperationException
	 */
//	protected function preDuplicate($newDocument, $originalDocument, $parentNodeId)
//	{
//		throw new IllegalOperationException('This document cannot be duplicated.');
//	}

	/**
	 * this method is call after saving the duplicate document.
	 * $newDocument has an id affected.
	 * Traitment of the children of $originalDocument.
	 *
	 * @param order_persistentdocument_bill $newDocument
	 * @param order_persistentdocument_bill $originalDocument
	 * @param Integer $parentNodeId
	 *
	 * @throws IllegalOperationException
	 */
//	protected function postDuplicate($newDocument, $originalDocument, $parentNodeId)
//	{
//	}

	/**
	 * Returns the URL of the document if has no URL Rewriting rule.
	 *
	 * @param order_persistentdocument_bill $document
	 * @param string $lang
	 * @param array $parameters
	 * @return string
	 */
//	public function generateUrl($document, $lang, $parameters)
//	{
//	}

	/**
	 * @param order_persistentdocument_bill $document
	 * @return integer | null
	 */
//	public function getWebsiteId($document)
//	{
//	}

	/**
	 * @param order_persistentdocument_bill $document
	 * @return website_persistentdocument_page | null
	 */
//	public function getDisplayPage($document)
//	{
//	}

	/**
	 * @param order_persistentdocument_bill $document
	 * @param string $forModuleName
	 * @param array $allowedSections
	 * @return array
	 */
//	public function getResume($document, $forModuleName, $allowedSections = null)
//	{
//		$resume = parent::getResume($document, $forModuleName, $allowedSections);
//		return $resume;
//	}

	/**
	 * @param order_persistentdocument_bill $document
	 * @param string $bockName
	 * @return array with entries 'module' and 'template'. 
	 */
//	public function getSolrserachResultItemTemplate($document, $bockName)
//	{
//		return array('module' => 'order', 'template' => 'Order-Inc-BillResultDetail');
//	}
}