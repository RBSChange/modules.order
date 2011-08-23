<?php
/**
 * order_SmartfolderService
 * @package order
 */
class order_SmartfolderService extends filter_QueryfolderService
{
	/**
	 * @var order_SmartfolderService
	 */
	private static $instance;

	/**
	 * @return order_SmartfolderService
	 */
	public static function getInstance()
	{
		if (self::$instance === null)
		{
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @return order_persistentdocument_smartfolder
	 */
	public function getNewDocumentInstance()
	{
		return $this->getNewDocumentInstanceByModelName('modules_order/smartfolder');
	}

	/**
	 * Create a query based on 'modules_order/smartfolder' model.
	 * Return document that are instance of modules_order/smartfolder,
	 * including potential children.
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createQuery()
	{
		return $this->pp->createQuery('modules_order/smartfolder');
	}
	
	/**
	 * Create a query based on 'modules_order/smartfolder' model.
	 * Only documents that are strictly instance of modules_order/smartfolder
	 * (not children) will be retrieved
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createStrictQuery()
	{
		return $this->pp->createQuery('modules_order/smartfolder', false);
	}
	
	/**
	 * @param filter_persistentdocument_queryfolder $document
	 * @param string[] $subModelNames
	 * @param integer $locateDocumentId null if use startindex
	 * @param integer $pageSize
	 * @param integer $startIndex
	 * @param integer $totalCount
	 * @return f_persistentdocument_PersistentDocument[]
	 */
	public function getVirtualChildrenAt($document, $subModelNames, $locateDocumentId, $pageSize, &$startIndex, &$totalCount)
	{
		$queryIntersection = f_persistentdocument_DocumentFilterService::getInstance()->getQueryIntersectionFromJson($document->getQuery());
		$result = $queryIntersection->findAtOffset($startIndex, $pageSize, $totalCount, 'DESC');
		return $result;
	}
}