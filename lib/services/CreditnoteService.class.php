<?php
/**
 * order_CreditnoteService
 * @package modules.order
 */
class order_CreditnoteService extends f_persistentdocument_DocumentService
{
	/**
	 * @var order_CreditnoteService
	 */
	private static $instance;

	/**
	 * @return order_CreditnoteService
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
	 * @return order_persistentdocument_creditnote
	 */
	public function getNewDocumentInstance()
	{
		return $this->getNewDocumentInstanceByModelName('modules_order/creditnote');
	}

	/**
	 * Create a query based on 'modules_order/creditnote' model.
	 * Return document that are instance of modules_order/creditnote,
	 * including potential children.
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createQuery()
	{
		return $this->pp->createQuery('modules_order/creditnote');
	}
	
	/**
	 * Create a query based on 'modules_order/creditnote' model.
	 * Only documents that are strictly instance of modules_order/creditnote
	 * (not children) will be retrieved
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createStrictQuery()
	{
		return $this->pp->createQuery('modules_order/creditnote', false);
	}
	
	/**
	 * @param order_persistentdocument_creditnote $document
	 * @param Integer $parentNodeId Parent node ID where to save the document (optionnal => can be null !).
	 * @return void
	 */
//	protected function preSave($document, $parentNodeId)
//	{
//
//	}

	/**
	 * @param order_persistentdocument_creditnote $document
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
	protected function preInsert($document, $parentNodeId)
	{
		$document->setInsertInTree(false);
		if ($document->getOrder() === null)
		{
			$document->setOrder(DocumentHelper::getDocumentInstance($parentNodeId, 'modules_order/order'));
		}
		
		if ($document->getLabel() == null)
		{
			$document->setLabel(order_CreditNoteNumberGenerator::getInstance()->generate($document));
		}
		$order = $document->getOrder();
		
		if ($document->getCustomer() == null)
		{
			$document->setCustomer($order->getCustomer()); 
		}
		if ($document->getCurrency() == null)
		{
			$document->setCurrency($order->getCurrencyCode()); 
		}
		
		if ($document->getAmountnotapplied() === null)
		{
			$document->setAmountnotapplied($document->getAmount());
		}
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @param order_persistentdocument_creditnote $creditnoteToIgnore
	 */
	public function getTotalAmountForOrder($order, $creditnoteToIgnore = null) 
	{
		$id = ($creditnoteToIgnore === null) ? -1 : $creditnoteToIgnore->getId();
		$result = $this->createQuery()->add(Restrictions::ne('publicationstatus', 'DRAFT'))
			->add(Restrictions::eq('order', $order))
			->add(Restrictions::ne('id', $id))
			->setProjection(Projections::sum("amount", "TotalAmount"))
			->findColumn("TotalAmount");

		if (count($result))
		{
			return $result[0];
		}
		return 0;
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @return order_persistentdocument_creditnote[]
	 */
	public function getByOrder($order)
	{
		return $this->createQuery()
			->addOrder(Order::asc('creationdate'))
			->add(Restrictions::eq('order', $order))
			->find();
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 */
	public function getBoList($order)
	{
		$result = array();
		foreach ($this->getByOrder($order) as $creditNote) 
		{
			$result[] = $this->buildBoRow($creditNote);
		}
		return $result;			
	}
	
	/**
	 * @param order_persistentdocument_creditnote $creditNote
	 * @return array
	 */
	protected function buildBoRow($creditNote)
	{
		$result = array(
			'id' => $creditNote->getId(),
			'lang' => $creditNote->getLang(),
			'type' => str_replace('/', '_', $creditNote->getDocumentModelName()),
			'label' => $creditNote->getLabel(),
			'amount' => $creditNote->getAmountFormated(),
			'amountNotApplied' => $creditNote->getAmountNotAppliedFormated(),
			'canReCreditNote' => ($creditNote->getAmountNotApplied() > 0.1),
			'actionrow' => true
		);		
		return $result;
	}

	/**
	 * @param order_persistentdocument_creditnote $document
	 * @param order_persistentdocument_order $order
	 */
	public function updateInitFormPropertyForOrder($document, $order)
	{
		$document->setOrder($order);
		$document->setCustomer($order->getCustomer()); 
		$document->setCurrency($order->getCurrencyCode());
		$document->setLabel(order_CreditNoteNumberGenerator::getInstance()->generate($document));
	}
	
	/**
	 * @param order_CartInfo $cart
	 */
	public function refreshCreditnoteArrayForCart($cart)
	{
		if (Framework::isInfoEnabled())
		{
			Framework::info(__METHOD__);
		}
		$cart->removeAllCreditNote();
		$customer = $cart->getCustomer();
		if ($customer === null) {return;}
		$maxAmount = $cart->getTotalAmount();
		
		$creditNotes = $this->createQuery()
			->add(Restrictions::published())
			->add(Restrictions::eq('customer', $customer))
			->add(Restrictions::gt('amountNotApplied', 0))
			->setProjection(Projections::property('id', 'id'), Projections::property('amountNotApplied', 'amountNotApplied'))
			->addOrder(Order::asc('creationdate'))
			->find();

		foreach ($creditNotes as $row) 
		{
			$creditNoteId = $row['id'];
			$creditNoteAmount = doubleval($row['amountNotApplied']);
			$amount = $maxAmount - $creditNoteAmount;
			if ($amount <= 0)
			{
				$cart->setCreditNoteAmount($creditNoteId, $maxAmount);
				break;
			}
			$cart->setCreditNoteAmount($creditNoteId, $creditNoteAmount);
			$maxAmount = $amount;
		}
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @param order_CartInfo $cart
	 */
	public function setOrderInfoFromCart($order, $cart)
	{
		if (Framework::isInfoEnabled())
		{
			Framework::info(__METHOD__);
		}
		
		$oldCreditNoteDataArray = $order->getCreditNoteDataArray();
		$oldCreditnotes = $order->getUsecreditnoteArray();
		foreach ($oldCreditnotes as $creditNote) 
		{
			$amount = $oldCreditNoteDataArray[$creditNote->getId()];
			$creditNote->setAmountNotApplied($creditNote->getAmountNotApplied() + $amount);
		}
		$order->setCreditNoteDataArray(null);
		$order->removeAllUsecreditnote();
		
	
		$totalAmountWithTax = $cart->getTotalWithTax();
		$order->setTotalAmountWithoutTax(catalog_PriceHelper::roundPrice($cart->getTotalWithoutTax()));
		

		$newCreditNoteDataArray = array();
		foreach ($cart->getCreditNoteArray() as $creditNoteId => $creditNoteAmount) 
		{

			$creditNote = DocumentHelper::getDocumentInstance($creditNoteId, 'modules_order/creditnote');
			$newamount = $creditNote->getAmountNotApplied() - $creditNoteAmount;
			if ($newamount >= 0)
			{
				$creditNote->setAmountNotApplied($newamount);
				$newCreditNoteDataArray[$creditNote->getId()] = $creditNoteAmount;
				$order->addUsecreditnote($creditNote);
				$creditNote->save();
				$totalAmountWithTax -= $creditNoteAmount;
			}
			else
			{
				$creditNote->setAmountNotApplied(0);
				$newCreditNoteDataArray[$creditNote->getId()] = $creditNoteAmount;
				$order->addUsecreditnote($creditNote);
				$creditNote->save();
				$totalAmountWithTax -= $creditNoteAmount;
			}
		}

		$order->setCreditNoteDataArray($newCreditNoteDataArray);
		
		//Save removed credit note
		foreach ($oldCreditnotes as $creditNote) 
		{
			if (!isset($newCreditNoteDataArray[$creditNote->getId()]))
			{
				$creditNote->save();
			}
		}
		
		$order->setTotalAmountWithTax(catalog_PriceHelper::roundPrice($totalAmountWithTax));
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @param double $amount
	 * @return double the remaining amount
	 */
	public function removeFromOrder($order, $amount)
	{
		Framework::info(__METHOD__);
		$creditNoteDataArray = $order->getCreditNoteDataArray();
		$creditNotes = $order->getUsecreditnoteArray();
		foreach ($creditNotes as $creditNote) 
		{
			$anAmount = $creditNoteDataArray[$creditNote->getId()];
			if ($anAmount < $amount)
			{
				$amount -= $anAmount;
				$creditNote->setAmountNotApplied($creditNote->getAmountNotApplied() + $anAmount);
				unset($creditNoteDataArray[$creditNote->getId()]);
				$order->removeUsecreditnote($creditNote);
			}
			else
			{
				$creditNoteDataArray[$creditNote->getId()] -= $amount;
				$creditNote->setAmountNotApplied($creditNote->getAmountNotApplied() + $amount);
				$amount = 0;
			}
			$creditNote->save();
			if ($amount == 0)
			{
				break;
			}
		}
		$order->setCreditNoteDataArray($creditNoteDataArray);
		return $amount;
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @param double $amount
	 * @return order_persistentdocument_creditnote
	 */
	public function createForOrder($order, $amount)
	{
		$creditNote = $this->getNewDocumentInstance();
		$creditNote->setOrder($order);
		$creditNote->setAmount($amount);
		$creditNote->save();
		return $creditNote;
	}
	
	/**
	 * @param order_persistentdocument_creditnote $document
	 * @param string $actionType
	 * @param array $formProperties
	 */
	public function addFormProperties($document, $propertiesNames, &$formProperties)
	{	
		$tamount = $this->getTotalAmountForOrder($document->getOrder(), $document);
		$document->setOtherCreditNoteAmount($tamount);
		$formProperties['maxAmount'] = $document->getOrderAmount() - $document->getOtherCreditNoteAmount();
		if (!isset($formProperties['currencySymbol']))
		{
			$formProperties['currencySymbol'] = $document->getCurrencySymbol();
		}
	}

	/**
	 * @param order_persistentdocument_creditnote $document
	 * @param string $forModuleName
	 * @param array $allowedSections
	 * @return array
	 */
	public function getResume($document, $forModuleName, $allowedSections = null)
	{
		$resume = parent::getResume($document, $forModuleName, $allowedSections);
		
		$order = $document->getOrder();
		$resume['properties']['amount'] = $order->formatPrice($document->getAmount());
		$resume['properties']['amountNotApplied'] = $order->formatPrice($document->getAmountNotApplied());
		if ($document->getTransactionDate())
		{
			$resume['properties']['transactionDate'] = $document->getUITransactionDate();
		}
		if ($document->getTransactionId())
		{
			$resume['properties']['transactionId'] = $document->getTransactionId();
		}
		if ($document->getTransactionText())
		{
			$resume['properties']['transactionText'] = $document->getTransactionTextAsHtml();
		}
		return $resume;
	}
	
	/**
	 * @param order_persistentdocument_creditnote $document
	 * @param string $transactionDate
	 * @param string $transactionText
	 */
	public function reCreditNote($document, $transactionDate = null, $transactionText = null)
	{
		$reCreditedAmount = $document->getAmountNotApplied();
		$document->setTransactionDate(date_Calendar::getInstance($transactionDate)->toString());
		$document->setTransactionText($transactionText);
		$document->setAmountNotApplied(0);
		if ($document->getPublicationstatus() === f_persistentdocument_PersistentDocument::STATUS_DRAFT)
		{
			$document->setPublicationstatus(f_persistentdocument_PersistentDocument::STATUS_ACTIVE);
		}
		$this->save($document);
		
		// Send the notification.
		$order = $document->getOrder();
		$wms = website_WebsiteModuleService::getInstance();
		$currentWebsite = $wms->getCurrentWebsite();
		$wms->setCurrentWebsite($order->getWebsite());
		$rc = RequestContext::getInstance();
		try 
		{
			$rc->beginI18nWork($order->getLang());
			
			$user = $order->getCustomer()->getUser();
			$replacements = $this->getNotificationParameter($document);
			$replacements['repaymentAmount'] = $order->formatPrice($reCreditedAmount);
			users_UserService::getInstance()->sendNotificationToUser($user, 'modules_order/reCreditNote', $replacements, 'order');
			
			$wms->setCurrentWebsite($currentWebsite);
			$rc->endI18nWork();
		}
		catch (Exception $e)
		{
			$wms->setCurrentWebsite($currentWebsite);
			$rc->endI18nWork($e);
		}
	}
	
	/**
	 * @param order_persistentdocument_creditnote $creditNote
	 */
	public function getNotificationParameter($creditNote)
	{
		$params = array(
			'creditNoteLabel' => $creditNote->getLabelAsHtml(),
			'creditNoteAmountNotApplied' => $creditNote->getAmountNotAppliedFormated()
		);
		$format = date_DateFormat::getDateTimeFormat();
		if ($creditNote->getAmountNotApplied() > 0.1)
		{
			$params['creditNoteEndDate'] = date_DateFormat::format($creditNote->getUIEndpublicationdate(), $format);
		}
		if ($creditNote->getTransactionDate())
		{
			$params['creditNoteTransactionDate'] = date_DateFormat::format($creditNote->getUITransactionDate(), $format);
			$params['creditNoteTransactionText'] = $creditNote->getTransactionTextAsHtml();
		}
		return $params;
	}
	
	/**
	 * 
	 * @param customer_persistentdocument_customer $customer
	 * @param Boolean $includeRepayments
	 */
	public function getByCustomer($customer, $includeRepayments = false, $includeUsedCreditNotes = true)
	{
		$query = $this->createQuery()->add(Restrictions::published())->add(Restrictions::eq('customer', $customer));
		if(!$includeUsedCreditNotes)
		{
			$query->add(Restrictions::gt('amountNotApplied', 0.000001));
		}
		if(!$includeRepayments)
		{
			$query->add(Restrictions::isNull('transactionDate'));
		}
		return $query->find();
	}
	
	/**
	 * @param customer_persistentdocument_customer $customer
	 */
	public function getRepaymentsByCustomer($customer)
	{
		$query = $this->createQuery()->add(Restrictions::published())
					->add(Restrictions::eq('customer', $customer))
					->add(Restrictions::isNotNull('transactionDate'));
		return $query->find();
	}

	/**
	 * @param order_persistentdocument_creditnote $document
	 * @param string $bockName
	 * @return array with entries 'module' and 'template'. 
	 */
//	public function getSolrserachResultItemTemplate($document, $bockName)
//	{
//		return array('module' => 'order', 'template' => 'Order-Inc-CreditnoteResultDetail');
//	}
}