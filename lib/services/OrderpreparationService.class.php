<?php
/**
 * order_OrderpreparationService
 * @package modules.order
 */
class order_OrderpreparationService extends f_persistentdocument_DocumentService
{
	/**
	 * @var order_OrderpreparationService
	 */
	private static $instance;

	/**
	 * @return order_OrderpreparationService
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
	 * @return order_persistentdocument_orderpreparation
	 */
	public function getNewDocumentInstance()
	{
		return $this->getNewDocumentInstanceByModelName('modules_order/orderpreparation');
	}

	/**
	 * Create a query based on 'modules_order/orderpreparation' model.
	 * Return document that are instance of modules_order/orderpreparation,
	 * including potential children.
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createQuery()
	{
		return $this->pp->createQuery('modules_order/orderpreparation');
	}
	
	/**
	 * Create a query based on 'modules_order/orderpreparation' model.
	 * Only documents that are strictly instance of modules_order/orderpreparation
	 * (not children) will be retrieved
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createStrictQuery()
	{
		return $this->pp->createQuery('modules_order/orderpreparation', false);
	}
	
	/**
	 * @param integer $orderId
	 * @return boolean
	 */	
	public function existForOrderId($orderId)
	{
		$result = $this->createQuery()->setProjection(Projections::count('id', 'countId'))
			->add(Restrictions::eq('orderId', $orderId))->findColumn('countId');
		return is_array($result) && $result[0] > 0;
	}
	
	/**
	 * @param array $lineInfo
	 * @return array<'id' => orderlineId, 'quantity' => quantity> OR null
	 */
	public function cleanUpLineInfo($lineInfo)
	{
		if (is_array($lineInfo) && isset($lineInfo['id']) && isset($lineInfo['quantity']) && ($lineInfo['quantity'] > 0))
		{
			$dm = $this->getPersistentProvider()->getDocumentModelName($lineInfo['id']);
			if ($dm )
			{
				$srv = f_persistentdocument_DocumentService::getInstanceByDocumentModelName($dm);
				if ($srv instanceof order_OrderlineService)
				{
					return array('id' => $lineInfo['id'], 'quantity' => $lineInfo['quantity']);
				}
			}
		}
		return null;
	}
	
	/**
	 * 
	 * @param order_persistentdocument_order $order
	 * @return array<array<'id' => orderlineId, 'quantity' => quantity>>
	 */
	public function getLinesInfosForOrder($order)
	{
		$array = array();
		foreach ($order->getLineArray() as $line) 
		{
			/* @var $line order_persistentdocument_orderline */
			$array[$line->getId()]  = array('id' => $line->getId(), 'quantity' => $line->getQuantity());
		}
		
		$opArray = $this->createQuery()->add(Restrictions::eq('orderId', $order->getId()))->find();
		foreach ($opArray as $op) 
		{
			/* @var $op order_persistentdocument_orderpreparation */
			foreach ($op->getLinesArray() as $lineInfo) 
			{
				$lid = $lineInfo['id'];
				if (isset($array[$lid]))
				{
					$array[$lid]['quantity'] -= $lineInfo['quantity'];
					if ($array[$lid]['quantity'] <= 0)
					{
						unset($array[$lid]);
					}
				}
			}			
		}
		return $array;
	}
	
	
	/**
	 * @param order_persistentdocument_orderpreparation $document
	 * @param String[] $propertiesName
	 * @param Array $datas
	 * @param integer $parentId
	 */
	public function addFormProperties($document, $propertiesName, &$datas, $parentId = null)
	{
		if ($document->isNew() && intval($parentId))
		{
			$dm = $this->getPersistentProvider()->getDocumentModelName(intval($parentId));
			if ($dm)
			{
				$order = DocumentHelper::getDocumentInstance(intval($parentId), $dm);
				if ($order instanceof order_persistentdocument_order)
				{
					$document->setOrderId($order->getId());
					$document->setLinesArray($this->getLinesInfosForOrder($order));
					$datas['oplinesJSON'] = $document->getOplinesJSON();
				}
			}
		}
		elseif (in_array('oplinesJSON', $propertiesName))
		{
			$datas['updatelines'] =  ($document->getPublicationstatus() == 'DRAFT');
		}
	}
	
	/**
	 * @param order_persistentdocument_orderpreparation $document
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
	protected function preInsert($document, $parentNodeId)
	{
		if ($document->getOrderId() == null)
		{
			$o = order_persistentdocument_order::getInstanceById($parentNodeId);
			$document->setOrderId($o->getId());
		}
		
		if ($document->getGenerateNumber() && $document->getLabel() == null)
		{
			$document->setLabel($this->getNextNumber($document));
		}
	}
	
	
	/**
	 * @param order_persistentdocument_orderpreparation $document
	 * @return boolean true if the document is publishable, false if it is not.
	 */
	public function isPublishable($document)
	{
		$result = parent::isPublishable($document);
		return $result && count($document->getLinesArray());
	}
	
	/**
	 * @param order_persistentdocument_orderpreparation $document
	 */
	public function getNextNumber($document)
	{
		return order_OrderPreparationNumberGenerator::getInstance()->generate($document);
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @return order_persistentdocument_orderpreparation[]
	 */
	public function getByOrder($order)
	{
		if ($order instanceof order_persistentdocument_order)
		{
			$query = $this->createQuery()->add(Restrictions::eq('orderId', $order->getId()))
			->addOrder(Order::asc('label'));
			return $query->find();
		}
		return array();
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @return array
	 */
	public function getBoList($order)
	{
		$result = array();
		foreach ($this->getByOrder($order) as $orderpreparation)
		{
			/* @var orderpreparation order_persistentdocument_orderpreparation */
			$result[] = $this->buildBoRow($orderpreparation);
		}
		return $result;
	}
	
	/**
	 * @param order_persistentdocument_orderpreparation $orderpreparation
	 * @return array
	 */
	protected function buildBoRow($orderpreparation)
	{
		$st = $orderpreparation->getPublicationstatus();
		$result = array(
				'id' => $orderpreparation->getId(),
				'status' => $st,
				'lang' => $orderpreparation->getLang(),
				'type' => str_replace('/', '_', $orderpreparation->getDocumentModelName()),
				'label' => $orderpreparation->getLabel(),
				'nextaction' => ($st != 'FILED' ) ? 'generateExpedition' : '',
		);
		return $result;
	}	
	
	/**
	 * @param order_persistentdocument_orderpreparation $document
	 * @param Integer $parentNodeId Parent node ID where to save the document (optionnal => can be null !).
	 * @return void
	 */
//	protected function preSave($document, $parentNodeId)
//	{
//
//	}



	/**
	 * @param order_persistentdocument_orderpreparation $document
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
//	protected function postInsert($document, $parentNodeId)
//	{
//	}

	/**
	 * @param order_persistentdocument_orderpreparation $document
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
//	protected function preUpdate($document, $parentNodeId)
//	{
//	}

	/**
	 * @param order_persistentdocument_orderpreparation $document
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
//	protected function postUpdate($document, $parentNodeId)
//	{
//	}

	/**
	 * @param order_persistentdocument_orderpreparation $document
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
//	protected function postSave($document, $parentNodeId)
//	{
//	}

	/**
	 * @param order_persistentdocument_orderpreparation $document
	 * @return void
	 */
//	protected function preDelete($document)
//	{
//	}

	/**
	 * @param order_persistentdocument_orderpreparation $document
	 * @return void
	 */
//	protected function preDeleteLocalized($document)
//	{
//	}

	/**
	 * @param order_persistentdocument_orderpreparation $document
	 * @return void
	 */
//	protected function postDelete($document)
//	{
//	}

	/**
	 * @param order_persistentdocument_orderpreparation $document
	 * @return void
	 */
//	protected function postDeleteLocalized($document)
//	{
//	}


	/**
	 * Methode à surcharger pour effectuer des post traitement apres le changement de status du document
	 * utiliser $document->getPublicationstatus() pour retrouver le nouveau status du document.
	 * @param order_persistentdocument_orderpreparation $document
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
	 * @param order_persistentdocument_orderpreparation $document
	 * @param String $tag
	 * @return void
	 */
//	public function tagAdded($document, $tag)
//	{
//	}

	/**
	 * @param order_persistentdocument_orderpreparation $document
	 * @param String $tag
	 * @return void
	 */
//	public function tagRemoved($document, $tag)
//	{
//	}

	/**
	 * @param order_persistentdocument_orderpreparation $fromDocument
	 * @param f_persistentdocument_PersistentDocument $toDocument
	 * @param String $tag
	 * @return void
	 */
//	public function tagMovedFrom($fromDocument, $toDocument, $tag)
//	{
//	}

	/**
	 * @param f_persistentdocument_PersistentDocument $fromDocument
	 * @param order_persistentdocument_orderpreparation $toDocument
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
	 * @param order_persistentdocument_orderpreparation $document
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
	 * @param order_persistentdocument_orderpreparation $newDocument
	 * @param order_persistentdocument_orderpreparation $originalDocument
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
	 * @param order_persistentdocument_orderpreparation $newDocument
	 * @param order_persistentdocument_orderpreparation $originalDocument
	 * @param Integer $parentNodeId
	 *
	 * @throws IllegalOperationException
	 */
//	protected function postDuplicate($newDocument, $originalDocument, $parentNodeId)
//	{
//	}

	/**
	 * @param website_UrlRewritingService $urlRewritingService
	 * @param order_persistentdocument_orderpreparation $document
	 * @param website_persistentdocument_website $website
	 * @param string $lang
	 * @param array $parameters
	 * @return f_web_Link | null
	 */
//	public function getWebLink($urlRewritingService, $document, $website, $lang, $parameters)
//	{
//		return null;
//	}

	/**
	 * @param order_persistentdocument_orderpreparation $document
	 * @return integer | null
	 */
//	public function getWebsiteId($document)
//	{
//		return parent::getWebsiteId($document);
//	}

	/**
	 * @param order_persistentdocument_orderpreparation $document
	 * @return integer[] | null
	 */
//	public function getWebsiteIds($document)
//	{
//		return parent::getWebsiteIds($document);
//	}

	/**
	 * @param order_persistentdocument_orderpreparation $document
	 * @return website_persistentdocument_page | null
	 */
//	public function getDisplayPage($document)
//	{
//		return parent::getDisplayPage($document);
//	}

	/**
	 * @param order_persistentdocument_orderpreparation $document
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
	 * @param order_persistentdocument_orderpreparation $document
	 * @param string $bockName
	 * @return array with entries 'module' and 'template'. 
	 */
//	public function getSolrsearchResultItemTemplate($document, $bockName)
//	{
//		return array('module' => 'order', 'template' => 'Order-Inc-OrderpreparationResultDetail');
//	}

	/**
	 * @param order_persistentdocument_orderpreparation $document
	 * @param string $moduleName
	 * @param string $treeType
	 * @param array<string, string> $nodeAttributes
	 */
//	public function addTreeAttributes($document, $moduleName, $treeType, &$nodeAttributes)
//	{
//	}
	

		
}