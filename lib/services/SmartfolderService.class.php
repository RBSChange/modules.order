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
			self::$instance = self::getServiceClassInstance(get_class());
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
	 * @param string $orderBy
	 * @return order_persistentdocument_order[]
	 */
	public function getVirtualChildrenAt($document, $subModelNames, $locateDocumentId, $pageSize, &$startIndex, &$totalCount, $orderBy = null)
	{
		$queryIntersection = f_persistentdocument_DocumentFilterService::getInstance()->getQueryIntersectionFromJson($document->getQuery());
		if ($orderBy == null)
		{
			$result = $queryIntersection->findAtOffset($startIndex, $pageSize, $totalCount, 'DESC');
			return $result;
		}
		$ids = $queryIntersection->findIds();
		if (count($ids) > 1000)
		{
			Framework::warn(__METHOD__ . ' total order > 1000');
			$ids = array_slice($ids, 0, 1000);	
		}
		$query = order_OrderService::getInstance()->createQuery()
					->add(Restrictions::in('id', $ids))
					->setFirstResult($startIndex)->setMaxResults($pageSize);

		list($cn, $dir) = explode(':', $orderBy);
		if($cn == 'label')
		{
			$fn = 'orderNumber';
		}
		elseif($cn == 'formattedTotalAmountWithTax')
		{
			$fn = 'totalAmountWithTax';
		}
		elseif($cn == 'date')
		{
			$fn = 'id';
		}
		elseif($cn == 'customer')
		{
			if ($dir === 'asc')
			{
				$query->createCriteria('customer')
				->createCriteria('user')->addOrder(Order::asc('customer.user.firstname'))->addOrder(Order::asc('customer.user.lastname'));
			}
			else
			{
				$query->createCriteria('customer')
				->createCriteria('user')->addOrder(Order::desc('customer.user.firstname'))->addOrder(Order::desc('customer.user.lastname'));
			}
			$fn = null;
		}
		else
		{
			$fn = null;
		}
	
		if ($fn)
		{
			if ($dir === 'asc')
			{
				$query->addOrder(Order::asc($fn));
			}
			else
			{
				$query->addOrder(Order::desc($fn));
			}
		}
			
		return $query->find();		
	}
	
	// DEPRECATED
	
	/**
	 * @deprecated (will be removed in 4.0) use getVirtualChildrenAt
	 */
	public function getOrders($folder)
	{
		return f_persistentdocument_DocumentFilterService::getInstance()->getQueryIntersectionFromJson($folder->getQuery())->find();
	}
}