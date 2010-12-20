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
		
		if ($document->getAmountnotapplied() == null)
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
	 * @param order_persistentdocument_creditnote $document
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
//	protected function postInsert($document, $parentNodeId)
//	{
//	}

	/**
	 * @param order_persistentdocument_creditnote $document
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
//	protected function preUpdate($document, $parentNodeId)
//	{
//	}

	/**
	 * @param order_persistentdocument_creditnote $document
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
//	protected function postUpdate($document, $parentNodeId)
//	{
//	}

	/**
	 * @param order_persistentdocument_creditnote $document
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
//	protected function postSave($document, $parentNodeId)
//	{
//	}

	/**
	 * @param order_persistentdocument_creditnote $document
	 * @return void
	 */
//	protected function preDelete($document)
//	{
//	}

	/**
	 * @param order_persistentdocument_creditnote $document
	 * @return void
	 */
//	protected function preDeleteLocalized($document)
//	{
//	}

	/**
	 * @param order_persistentdocument_creditnote $document
	 * @return void
	 */
//	protected function postDelete($document)
//	{
//	}

	/**
	 * @param order_persistentdocument_creditnote $document
	 * @return void
	 */
//	protected function postDeleteLocalized($document)
//	{
//	}

	/**
	 * @param order_persistentdocument_creditnote $document
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
	 * @param order_persistentdocument_creditnote $document
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
	 * @param order_persistentdocument_creditnote $document
	 * @param String $tag
	 * @return void
	 */
//	public function tagAdded($document, $tag)
//	{
//	}

	/**
	 * @param order_persistentdocument_creditnote $document
	 * @param String $tag
	 * @return void
	 */
//	public function tagRemoved($document, $tag)
//	{
//	}

	/**
	 * @param order_persistentdocument_creditnote $fromDocument
	 * @param f_persistentdocument_PersistentDocument $toDocument
	 * @param String $tag
	 * @return void
	 */
//	public function tagMovedFrom($fromDocument, $toDocument, $tag)
//	{
//	}

	/**
	 * @param f_persistentdocument_PersistentDocument $fromDocument
	 * @param order_persistentdocument_creditnote $toDocument
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
	 * @param order_persistentdocument_creditnote $document
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
	 * @param order_persistentdocument_creditnote $newDocument
	 * @param order_persistentdocument_creditnote $originalDocument
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
	 * @param order_persistentdocument_creditnote $newDocument
	 * @param order_persistentdocument_creditnote $originalDocument
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
	 * @param order_persistentdocument_creditnote $document
	 * @param string $lang
	 * @param array $parameters
	 * @return string
	 */
//	public function generateUrl($document, $lang, $parameters)
//	{
//	}

	/**
	 * Filter the parameters used to generate the document url.
	 *
	 * @param f_persistentdocument_PersistentDocument $document
	 * @param string $lang
	 * @param array $parameters may be an empty array
	 */
//	public function filterDocumentUrlParams($document, $lang, $parameters)
//	{
//		return $parameters;
//	}

	/**
	 * @param order_persistentdocument_creditnote $document
	 * @return integer | null
	 */
//	public function getWebsiteId($document)
//	{
//	}

	/**
	 * @param order_persistentdocument_creditnote $document
	 * @return website_persistentdocument_page | null
	 */
//	public function getDisplayPage($document)
//	{
//	}

	/**
	 * @param order_persistentdocument_creditnote $document
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
	 * @param order_persistentdocument_creditnote $document
	 * @param string $bockName
	 * @return array with entries 'module' and 'template'. 
	 */
//	public function getSolrserachResultItemTemplate($document, $bockName)
//	{
//		return array('module' => 'order', 'template' => 'Order-Inc-CreditnoteResultDetail');
//	}
}