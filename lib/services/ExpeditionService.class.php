<?php
/**
 * order_ExpeditionService
 * @package modules.order
 */
class order_ExpeditionService extends f_persistentdocument_DocumentService
{
	
	const PREPARE = "prepare";
	const SHIPPED = "shipped";	
	const CANCELED = "canceled";	
	
	/**
	 * @var order_ExpeditionService
	 */
	private static $instance;

	/**
	 * @return order_ExpeditionService
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
	 * @return order_persistentdocument_expedition
	 */
	public function getNewDocumentInstance()
	{
		return $this->getNewDocumentInstanceByModelName('modules_order/expedition');
	}

	/**
	 * Create a query based on 'modules_order/expedition' model.
	 * Return document that are instance of modules_order/expedition,
	 * including potential children.
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createQuery()
	{
		return $this->pp->createQuery('modules_order/expedition');
	}
	
	/**
	 * Create a query based on 'modules_order/expedition' model.
	 * Only documents that are strictly instance of modules_order/expedition
	 * (not children) will be retrieved
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createStrictQuery()
	{
		return $this->pp->createQuery('modules_order/expedition', false);
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @return order_persistentdocument_expedition[]
	 */
	public function getByOrder($order)
	{
		$query = $this->createQuery()->add(Restrictions::eq('order', $order))
				->addOrder(Order::asc('document_label'));
		return $query->find();
	}
	
	/**
	 * @param order_persistentdocument_expedition $expedition
	 * @return Array<String=>String>
	 */
	public function getNotificationParameters($expedition)
	{
		$trackingNumber = $expedition->getTrackingNumber();
		if (!is_null($trackingUrl = $expedition->getTrackingURL()))
		{
			$trackingNumber = '<a href="' . $trackingUrl . '">' . $trackingNumber . '</a>';
		}
		return array('packageNumber' => $trackingNumber, 'trackingNumber' => $expedition->getTrackingNumber());
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @return order_persistentdocument_expedition
	 */
	public function createForOrder($order)
	{
		$previousExpeditions = $this->getByOrder($order);
		$expedition = $this->getNewDocumentInstance();
		$expedition->setLabel(strval(count($previousExpeditions) + 1));
		$expedition->setOrder($order);
		$expedition->setStatus(self::PREPARE);
		$expedition->setAddress($order->getShippingAddress());
		$expedition->setShippingModeId($order->getShippingModeId());
		
		$shippingMode = $expedition->getShippingMode();
		$expedition->setTransporteur($shippingMode->getCodeReference());
		$expedition->setTrackingURL($shippingMode->getTrackingUrl());
		
		$expedition->setAmount($order->getShippingFeesWithTax());
		$expedition->setTax($order->getShippingFeesWithTax() - $order->getShippingFeesWithoutTax());
		$expedition->setTaxCode($order->getShippingModeTaxCode());
		if (count($previousExpeditions) == 0)
		{
			$expedition->setUseOrderlines(true);	
			return $expedition;
		}
		$previouslines = array();
		foreach ($previousExpeditions as $previousExpedition) 
		{
			$this->getOrderLineIds($previousExpedition, $previouslines);
		}
		if (count($previouslines) == 0)
		{
			$expedition->setUseOrderlines(true);	
			return $expedition;			
		}
		
		$expedition->setUseOrderlines(false);
		foreach ($order->getLineArray() as $line) 
		{
			$qtt = $line->getQuantity();
			if (isset($previouslines[$line->getId()]))
			{
				$qtt -= $previouslines[$line->getId()];
			}
			if ($qtt <= 0)
			{
				continue;
			}
			$expLine = order_ExpeditionlineService::getInstance()->getNewDocumentInstance();
			$expLine->setLabel($line->getLabel());
			$expLine->setQuantity($qtt);
			$expLine->setOrderlineid($line->getId());
			$expedition->addLine($expLine);
		}
		
		if ($expedition->getLineCount() > 0)
		{
			return $expedition;
		}
		return null;
	}
	
	/**
	 * @param order_persistentdocument_expedition $expedition
	 * @param array $array
	 */
	private function getOrderLineIds($expedition, &$array)
	{
		if ($expedition->getStatus() != self::CANCELED)
		{
			if ($expedition->getUseOrderlines())
			{
				foreach ($expedition->getOrder()->getLineArray() as $line) 
				{
					$array[$line->getId()] = $line->getQuantity();
				}
			}
			foreach ($expedition->getLineArray() as $line) 
			{
				if (isset($array[$line->getOrderlineid()]))
				{
					$array[$line->getOrderlineid()] += $line->getQuantity();
				}
				else
				{
					$array[$line->getOrderlineid()] = $line->getQuantity();
				}
			}
		}
		return $array;
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
	 * @param order_persistentdocument_expedition $expedition
	 * @return array
	 */
	private function buildBoRow($expedition)
	{
		$result = array(
			'id' => $expedition->getId(),
			'lang' => $expedition->getLang(),
			'type' => str_replace('/', '_', $expedition->getDocumentModelName()),
			'st' => $expedition->getStatus(),
			'status' => $expedition->getBoStatusLabel(),
			'label' => $expedition->getLabel(),
			'traking' => $expedition->getTrackingNumber(),
			'transporteur' => $expedition->getTransporteur()
		);		
		return $result;
	}
	
	/**
	 * @param order_persistentdocument_expedition $expedition
	 * @return array
	 */
	public function cancelExpeditionFromBo($expedition)
	{
		try
		{
			$this->tm->beginTransaction();
			$expedition->setShippingDate(null);
			$expedition->setStatus(self::CANCELED);
			
			$backendUser = users_UserService::getInstance()->getCurrentBackEndUser();
			$expedition->setTrackingText('Cancel by :' . (($backendUser) ? $backendUser->getFullname() : 'UNKNOWN'));
			$this->save($expedition);
			$order = $expedition->getOrder();
			order_ModuleService::getInstance()->sendCustomerNotification('modules_order/expedition_canceled', $order, $expedition->getBill(), $expedition);
			$this->tm->commit();
		}
		catch (Exception $e)
		{
			$this->tm->rollBack($e);
			throw $e;
		}

		return $this->buildBoRow($expedition);
	}
	
	/**
	 * @param order_persistentdocument_expedition $expedition
	 * @param string $shippingDate
	 * @param string $trackingNumber
	 * @return array
	 */
	public function shipExpeditionFromBo($expedition, $shippingDate, $trackingNumber)
	{
		
		if ($expedition->getStatus() == self::PREPARE	&& f_util_StringUtils::isNotEmpty($shippingDate))
		{
			try 
			{
				$this->tm->beginTransaction();				
				$expedition->setStatus(self::SHIPPED);
				$expedition->setTrackingNumber($trackingNumber);
				$expedition->setShippingDate(date_Converter::convertDateToGMT($shippingDate));
				$this->save($expedition);	
				$order = $expedition->getOrder();
				order_ModuleService::getInstance()->sendCustomerNotification('modules_order/expedition_shipped', $order, $expedition->getBill(), $expedition);
				
				$nextExpedition = $this->createForOrder($order);
				if ($nextExpedition === null)
				{
					order_OrderService::getInstance()->completeOrder($order);	
				}
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
			throw new Exception('Error on expedition shipping');
		}
		return $this->buildBoRow($expedition);
	}
	
	
	/**
	 * @param order_persistentdocument_expedition $document
	 * @param Integer $parentNodeId Parent node ID where to save the document (optionnal => can be null !).
	 * @return void
	 */
//	protected function preSave($document, $parentNodeId = null)
//	{
//
//	}

	/**
	 * @param order_persistentdocument_expedition $document
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
//	protected function preInsert($document, $parentNodeId = null)
//	{
//	}

	/**
	 * @param order_persistentdocument_expedition $document
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
//	protected function postInsert($document, $parentNodeId = null)
//	{
//	}

	/**
	 * @param order_persistentdocument_expedition $document
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
//	protected function preUpdate($document, $parentNodeId = null)
//	{
//	}

	/**
	 * @param order_persistentdocument_expedition $document
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
//	protected function postUpdate($document, $parentNodeId = null)
//	{
//	}

	/**
	 * @param order_persistentdocument_expedition $document
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
//	protected function postSave($document, $parentNodeId = null)
//	{
//	}

	/**
	 * @param order_persistentdocument_expedition $document
	 * @return void
	 */
//	protected function preDelete($document)
//	{
//	}

	/**
	 * @param order_persistentdocument_expedition $document
	 * @return void
	 */
//	protected function preDeleteLocalized($document)
//	{
//	}

	/**
	 * @param order_persistentdocument_expedition $document
	 * @return void
	 */
//	protected function postDelete($document)
//	{
//	}

	/**
	 * @param order_persistentdocument_expedition $document
	 * @return void
	 */
//	protected function postDeleteLocalized($document)
//	{
//	}

	/**
	 * @param order_persistentdocument_expedition $document
	 * @return boolean true if the document is publishable, false if it is not.
	 */
//	public function isPublishable($document)
//	{
//		$result = parent::isPublishable($document);
//		return $result;
//	}


	/**
	 * Methode à surcharger pour effectuer des post traitement apres le changement de status du document
	 * utiliser $document->getPublicationstatus() pour retrouver le nouveau status du document.
	 * @param order_persistentdocument_expedition $document
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
	 * @param order_persistentdocument_expedition $document
	 * @param String $tag
	 * @return void
	 */
//	public function tagAdded($document, $tag)
//	{
//	}

	/**
	 * @param order_persistentdocument_expedition $document
	 * @param String $tag
	 * @return void
	 */
//	public function tagRemoved($document, $tag)
//	{
//	}

	/**
	 * @param order_persistentdocument_expedition $fromDocument
	 * @param f_persistentdocument_PersistentDocument $toDocument
	 * @param String $tag
	 * @return void
	 */
//	public function tagMovedFrom($fromDocument, $toDocument, $tag)
//	{
//	}

	/**
	 * @param f_persistentdocument_PersistentDocument $fromDocument
	 * @param order_persistentdocument_expedition $toDocument
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
	 * @param order_persistentdocument_expedition $document
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
	 * @param order_persistentdocument_expedition $newDocument
	 * @param order_persistentdocument_expedition $originalDocument
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
	 * @param order_persistentdocument_expedition $newDocument
	 * @param order_persistentdocument_expedition $originalDocument
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
	 * @param order_persistentdocument_expedition $document
	 * @param string $lang
	 * @param array $parameters
	 * @return string
	 */
//	public function generateUrl($document, $lang, $parameters)
//	{
//	}

	/**
	 * @param order_persistentdocument_expedition $document
	 * @return integer | null
	 */
//	public function getWebsiteId($document)
//	{
//	}

	/**
	 * @param order_persistentdocument_expedition $document
	 * @return website_persistentdocument_page | null
	 */
//	public function getDisplayPage($document)
//	{
//	}

	/**
	 * @param order_persistentdocument_expedition $document
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
	 * @param order_persistentdocument_expedition $document
	 * @param string $bockName
	 * @return array with entries 'module' and 'template'. 
	 */
//	public function getSolrserachResultItemTemplate($document, $bockName)
//	{
//		return array('module' => 'order', 'template' => 'Order-Inc-ExpeditionResultDetail');
//	}
}