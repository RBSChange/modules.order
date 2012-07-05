<?php
/**
 * @package modules.order
 * @method order_WaitingresponseorderfolderService getInstance()
 */
class order_WaitingresponseorderfolderService extends generic_FolderService
{
	/**
	 * @return order_persistentdocument_waitingresponseorderfolder
	 */
	public function getNewDocumentInstance()
	{
		return $this->getNewDocumentInstanceByModelName('modules_order/waitingresponseorderfolder');
	}

	/**
	 * Create a query based on 'modules_order/waitingresponseorderfolder' model.
	 * Return document that are instance of modules_order/waitingresponseorderfolder,
	 * including potential children.
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createQuery()
	{
		return $this->getPersistentProvider()->createQuery('modules_order/waitingresponseorderfolder');
	}
	
	/**
	 * Create a query based on 'modules_order/waitingresponseorderfolder' model.
	 * Only documents that are strictly instance of modules_order/waitingresponseorderfolder
	 * (not children) will be retrieved
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createStrictQuery()
	{
		return $this->getPersistentProvider()->createQuery('modules_order/waitingresponseorderfolder', false);
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
		$query = order_MessageService::getInstance()->createQuery();
		$criteria = $query->createCriteria('order');
		$criteria->add(Restrictions::eq('needsAnswer', true))->setProjection(Projections::groupProperty('id', 'id'));
		$query->setProjection(Projections::max('creationdate', 'date'));
		
		$rows = $query->find();
		$totalCount = count($rows);
		if ($totalCount == 0 || $startIndex >= $totalCount)
		{
			return array();
		}
		
		$datesById = array();
		foreach ($rows as $row)
		{
			$datesById[$row['id']] = $row['date'];
		}
		arsort($datesById);
		$ids = array_keys($datesById);
		
		$sortedOrders = array();
		$query = order_OrderService::getInstance()->createQuery()->add(Restrictions::in('id', array_slice($ids, $startIndex, $pageSize)));
		foreach ($query->find() as $order)
		{
			$sortedOrders[array_search($order->getId(), $ids)] = $order;
		}
		ksort($sortedOrders);		
		return array_values($sortedOrders);
	}
}