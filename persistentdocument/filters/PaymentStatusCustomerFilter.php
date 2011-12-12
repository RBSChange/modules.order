<?php
class order_PaymentStatusCustomerFilter extends f_persistentdocument_DocumentFilterImpl
{
	/**
	 * @return string[]
	 */
	public static function getAliases()
	{
		return array('featurepacka_PaymentStatusCustomerFilter');
	}
	
	public function __construct()
	{
		$info = new BeanPropertyInfoImpl('count', 'Integer');
		$parameter = f_persistentdocument_DocumentFilterRestrictionParameter::getNewHavingInstance($info, 'ge');
		$this->setParameter('count', $parameter);
		
		$info = new BeanPropertyInfoImpl('restriction', 'String');
		$info->setListId('modules_filter/oneallnone');
		$parameter = new f_persistentdocument_DocumentFilterValueParameter($info);
		$this->setParameter('restriction', $parameter);
		
		$info = new BeanPropertyInfoImpl('status', 'String');
		$info->setListId('modules_order/billstatuses');
		$parameter = new f_persistentdocument_DocumentFilterValueParameter($info);
		$this->setParameter('status', $parameter);
	}
	
	/**
	 * @return String
	 */
	public static function getDocumentModelName()
	{
		return 'modules_customer/customer';
	}
	
	/**
	 * @return String
	 */
	private static function getDocumentModel()
	{
		return f_persistentdocument_PersistentDocumentModel::getInstanceFromDocumentModelName(self::getDocumentModelName());
	}
	
	/**
	 * @return f_persistentdocument_criteria_Query
	 */
	public function getQuery()
	{
		$status = $this->getParameter('status')->getValueForQuery();
		$restriction = $this->getParameter('restriction')->getValueForQuery();
		switch ($restriction)
		{
			case 'ONE':
				return $this->getOneForStatus($status);
				
			case 'ALL':
				return $this->getAllForStatus($status);
				
			case 'NONE':
				return $this->getNoneForStatus($status);
				
			default: 
				Framework::error(__METHOD__ . ' Unexpected restriction: ' . $restriction);
				return new filter_StaticQuery(self::getDocumentModel(), array());
		}
	}
	
	/**
	 * @param string $status
	 * @return f_persistentdocument_criteria_Query
	 */
	private function getOneForStatus($status)
	{
		$query = customer_CustomerService::getInstance()->createQuery()->having($this->getParameter('count')->getValueForQuery());
		$criteria1 = $query->createCriteria('order');
		$criteria1->setProjection(Projections::rowCount('count'));
		$criteria2 = $criteria1->createCriteria('bill');
		$criteria2->add(Restrictions::eq('status', $status));
		return $query;
	}
	
	/**
	 * @param string $status
	 * @return f_persistentdocument_criteria_Query
	 */
	private function getAllForStatus($status)
	{
		$os = order_OrderService::getInstance();
		
		// Find all orders having a bill with the required status.
		$query = $os->createQuery()->setProjection(Projections::property('id', 'oid'));
		$query->createCriteria('bill')->add(Restrictions::eq('status', $status));
		$query->createCriteria('customer')->setProjection(Projections::property('id', 'cid'));
		$rows = $query->find();
		
		// Ignore all orders having a bill without the required status.
		$query = $os->createQuery()->setProjection(Projections::property('id'));
		$criteria1 = $query->createCriteria('bill');
		$criteria1->add(Restrictions::ne('status', $status));
		$expludedOids = $query->findColumn('id');
			
		$ids = $this->extractCustomerIds($rows, $expludedOids);		
		return new filter_StaticQuery(self::getDocumentModel(), $ids);
	}
	
	/**
	 * @param string $status
	 * @return f_persistentdocument_criteria_Query
	 */
	private function getNoneForStatus($status)
	{
		$os = order_OrderService::getInstance();
		
		// Find all orders having a bill with the required status.
		$query = $os->createQuery()->setProjection(Projections::property('id', 'oid'));
		$query->createCriteria('bill')->add(Restrictions::ne('status', $status));
		$query->createCriteria('customer')->setProjection(Projections::property('id', 'cid'));
		$rows = $query->find();
		
		// Ignore all orders having a bill without the required status.
		$query = $os->createQuery()->setProjection(Projections::property('id'));
		$criteria1 = $query->createCriteria('bill');
		$criteria1->add(Restrictions::eq('status', $status));
		$expludedOids = $query->findColumn('id');
			
		$ids = $this->extractCustomerIds($rows, $expludedOids);		
		return new filter_StaticQuery(self::getDocumentModel(), $ids);
	}
	
	/**
	 * @param array $rows
	 * @param integer[] $expludedOids
	 */
	private function extractCustomerIds($rows, $expludedOids)
	{
		$ocids = array();
		foreach ($rows as $row)
		{
			$ocids[$row['oid']] = $row['cid'];
		}
		$ocids = array_diff_key($ocids, array_flip($expludedOids));
		
		// Count valid orders by customer.
		$counts = array();
		foreach ($ocids as $oid => $cid)
		{
			if (!isset($counts[$cid]))
			{
				$counts[$cid] = 1;
			}
			else
			{
				$counts[$cid]++;
			}
		}
		
		$ids = array();
		$param = $this->getParameter('count');
		$reference = $param->getParameter()->getValueForQuery(); 
		foreach ($counts as $id => $count)
		{
			if ($count >= $reference)
			{
				$ids[] = $id;
			}
		}
		return $ids;
	}
	
	/**
	 * @param customer_persistentdocument_customer $value
	 */
	public function checkValue($value)
	{
		if ($value instanceof customer_persistentdocument_customer)
		{
			$status = $this->getParameter('status')->getValueForQuery();
			$restriction = $this->getParameter('restriction')->getValueForQuery();
			switch ($restriction)
			{
				case 'ONE':
					$count = $this->getCountOneForStatus($value, $status);
					break;
					
				case 'ALL':
					$count = $this->getCountAllForStatus($value, $status);
					break;
					
				case 'NONE':
					$count = $this->getCountNoneForStatus($value, $status);
					break;
					
				default: 
					Framework::error(__METHOD__ . ' Unexpected restriction: ' . $restriction);
					return false;
			}			
			return $count >= $this->getParameter('count')->getParameter()->getValue();
		}
		return false;
	}
	
	/**
	 * @param customer_persistentdocument_customer $value
	 * @param string $status
	 * @return integer
	 */
	private function getCountOneForStatus($value, $status)
	{
		$query = order_OrderService::getInstance()->createQuery()->add(Restrictions::eq('customer', $value));
		$criteria1 = $query->createCriteria('bill');
		$criteria1->add(Restrictions::eq('status', $status));
		$query->setProjection(Projections::count('id', 'count'));
		return f_util_ArrayUtils::firstElement($query->findColumn('count'));
	}
	
	/**
	 * @param customer_persistentdocument_customer $value
	 * @param string $status
	 * @return integer
	 */
	private function getCountAllForStatus($value, $status)
	{
		$os = order_OrderService::getInstance();
		
		// Find all orders having a bill with the required status.
		$query = $os->createQuery()->setProjection(Projections::property('id'))->add(Restrictions::eq('customer', $value));
		$criteria1 = $query->createCriteria('bill');
		$criteria1->add(Restrictions::eq('status', $status));
		$ids = $query->findColumn('id');
		
		// Ignore all orders having a bill without the required status.
		$query = $os->createQuery()->setProjection(Projections::property('id'))->add(Restrictions::eq('customer', $value));
		$criteria1 = $query->createCriteria('bill');
		$criteria1->add(Restrictions::ne('status', $status));
		$ids = array_diff($ids, $query->findColumn('id'));
		
		return count($ids);
	}
	
	/**
	 * @param customer_persistentdocument_customer $value
	 * @param string $status
	 * @return integer
	 */
	private function getCountNoneForStatus($value, $status)
	{
		$os = order_OrderService::getInstance();
		
		// Find all orders having a bill with the required status.
		$query = $os->createQuery()->setProjection(Projections::property('id'))->add(Restrictions::eq('customer', $value));
		$criteria1 = $query->createCriteria('bill');
		$criteria1->add(Restrictions::ne('status', $status));
		$ids = $query->findColumn('id');
		
		// Ignore all orders having a bill without the required status.
		$query = $os->createQuery()->setProjection(Projections::property('id'))->add(Restrictions::eq('customer', $value));
		$criteria1 = $query->createCriteria('bill');
		$criteria1->add(Restrictions::eq('status', $status));
		$ids = array_diff($ids, $query->findColumn('id'));
		
		return count($ids);
	}
}