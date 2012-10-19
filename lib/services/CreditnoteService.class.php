<?php
/**
 * @package modules.order
 * @method order_CreditnoteService getInstance()
 */
class order_CreditnoteService extends f_persistentdocument_DocumentService
{
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
		return $this->getPersistentProvider()->createQuery('modules_order/creditnote');
	}
	
	/**
	 * Create a query based on 'modules_order/creditnote' model.
	 * Only documents that are strictly instance of modules_order/creditnote
	 * (not children) will be retrieved
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createStrictQuery()
	{
		return $this->getPersistentProvider()->createQuery('modules_order/creditnote', false);
	}
	
	/**
	 * @param order_persistentdocument_creditnote $document
	 * @param integer $parentNodeId Parent node ID where to save the document.
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
			'status' => $creditNote->getPublicationstatus(),
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
		$oldCreditNoteDataArray = $order->getCreditNoteDataArray();
		$oldCreditnotes = $order->getUsecreditnoteArray();
		foreach ($oldCreditnotes as $creditNote) 
		{
			/* @var $creditNote order_persistentdocument_creditnote */
			$amount = $oldCreditNoteDataArray[$creditNote->getId()];
			$nv = min($creditNote->getAmount(), $creditNote->getAmountNotApplied() + $amount);
			$creditNote->setAmountNotApplied($nv);
		}
		$order->setCreditNoteDataArray(null);
		$order->removeAllUsecreditnote();
		
		$pf = catalog_PriceFormatter::getInstance();
	
		$totalAmountWithTax = $cart->getTotalWithTax();
		$order->setTotalAmountWithoutTax($pf->round($cart->getTotalWithoutTax(), $order->getCurrencyCode()));

		$newCreditNoteDataArray = array();
		foreach ($cart->getCreditNoteArray() as $creditNoteId => $creditNoteAmount) 
		{
			$creditNote =  order_persistentdocument_creditnote::getInstanceById($creditNoteId);
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
		
		$order->setTotalAmountWithTax($pf->round($totalAmountWithTax,$order->getCurrencyCode()));
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @param double $amount
	 * @return double the remaining amount
	 */
	public function removeFromOrder($order, $amount)
	{
		$creditNoteDataArray = $order->getCreditNoteDataArray();
		$creditNotes = $order->getUsecreditnoteArray();
		foreach ($creditNotes as $creditNote) 
		{
			/* @var $creditNote order_persistentdocument_creditnote */
			$anAmount = $creditNoteDataArray[$creditNote->getId()];
			$removeAmount = (abs($anAmount - $amount) < 0.00001);
			if ($removeAmount || $anAmount <= $amount)
			{
				if ($removeAmount) {$amount = 0;} else {$amount -= $anAmount;}
				$nv = min($creditNote->getAmount(), $creditNote->getAmountNotApplied() + $anAmount);
				$creditNote->setAmountNotApplied($nv);
				unset($creditNoteDataArray[$creditNote->getId()]);
				$order->removeUsecreditnote($creditNote);
			}
			else
			{
				$creditNoteDataArray[$creditNote->getId()] -= $amount;
				$nv = min($creditNote->getAmount(), $creditNote->getAmountNotApplied() + $amount);
				$creditNote->setAmountNotApplied($nv);
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
	 * @param boolean $published
	 * @return order_persistentdocument_creditnote
	 */
	public function createForOrder($order, $amount, $published = true)
	{
		$creditNote = $this->getNewDocumentInstance();
		$creditNote->setOrder($order);
		$creditNote->setAmount($amount);
		if (!$published)
		{
			$creditNote->setPublicationstatus('DRAFT');
		}
		$creditNote->save();
		return $creditNote;
	}
	
	/**
	 * @param order_persistentdocument_creditnote $document
	 * @param string $actionType
	 * @param array $formProperties
	 */
	public function addFormProperties($document, $propertiesNames, &$formProperties, $parentId = null)
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
		$notification = notification_NotificationService::getInstance()->getConfiguredByCodeName('modules_order/reCreditNote', $order->getWebsiteId(), $order->getLang());
		if ($notification)
		{
			$notification->setSendingModuleName('order');
			$notification->addGlobalParam('repaymentAmount', $order->formatPrice($reCreditedAmount));
			$notification->registerCallback($this, 'getNotificationParameter', $document);		
			$user = $order->getCustomer()->getUser();
			$notification->sendToUser($user);
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
		if ($creditNote->getAmountNotApplied() > 0.1)
		{
			$params['creditNoteEndDate'] = date_Formatter::toDefaultDateTime($creditNote->getUIEndpublicationdate());
		}
		if ($creditNote->getTransactionDate())
		{
			$params['creditNoteTransactionDate'] = date_Formatter::toDefaultDateTime($creditNote->getUITransactionDate());
			$params['creditNoteTransactionText'] = $creditNote->getTransactionTextAsHtml();
		}
		return $params;
	}
	
	/**
	 * @param customer_persistentdocument_customer $customer
	 * @param boolean $includeRepayments
	 * @return order_persistentdocument_creditnote[]
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
	 * @return order_persistentdocument_creditnote[]
	 */
	public function getRepaymentsByCustomer($customer)
	{
		$query = $this->createQuery()->add(Restrictions::published())
			->add(Restrictions::eq('customer', $customer))
			->add(Restrictions::isNotNull('transactionDate'));
		return $query->find();
	}
	
	/**
	 * @param customer_persistentdocument_customer $customer
	 * @return float
	 */
	public function getTotalAvailableAmountByCustomer($customer)
	{
		$query = $this->createQuery()->add(Restrictions::published())->add(Restrictions::eq('customer', $customer));
		$query->add(Restrictions::gt('amountNotApplied', 0.000001));
		$query->setProjection(Projections::sum('amountNotApplied', 'availableAmount'));
		return f_util_ArrayUtils::firstElement($query->findColumn('availableAmount'));
	}
}