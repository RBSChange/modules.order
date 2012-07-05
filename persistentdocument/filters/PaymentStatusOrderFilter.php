<?php
class order_PaymentStatusOrderFilter extends f_persistentdocument_DocumentFilterImpl
{
	/**
	 * @return string[]
	 */
	public static function getAliases()
	{
		return array('featurepacka_PaymentStatusOrderFilter');
	}
		
	public function __construct()
	{
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
	 * @return string
	 */
	public static function getDocumentModelName()
	{
		return 'modules_order/order';
	}
	
	/**
	 * @return string
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
		$query = order_OrderService::getInstance()->createQuery();
		$criteria1 = $query->createCriteria('bill');
		$criteria1->add(Restrictions::eq('status', $status));
		return $query;
	}
	
	/**
	 * @param string $status
	 * @return f_persistentdocument_criteria_Query
	 */
	private function getAllForStatus($status)
	{
		// Find all orders having a bill with the required status.
		$query = order_OrderService::getInstance()->createQuery()->setProjection(Projections::property('id'));
		$criteria1 = $query->createCriteria('bill');
		$criteria1->add(Restrictions::eq('status', $status));
		$ids = $query->findColumn('id');
		
		// Ignore all orders having a bill without the required status.
		$query = order_OrderService::getInstance()->createQuery()->setProjection(Projections::property('id'));
		$criteria1 = $query->createCriteria('bill');
		$criteria1->add(Restrictions::ne('status', $status));
		$ids = array_diff($ids, $query->findColumn('id'));
		
		return new filter_StaticQuery(self::getDocumentModel(), $ids);
	}
	
	/**
	 * @param string $status
	 * @return f_persistentdocument_criteria_Query
	 */
	private function getNoneForStatus($status)
	{
		// Find all orders having a bill without the rejected status.
		$query = order_OrderService::getInstance()->createQuery()->setProjection(Projections::property('id'));
		$criteria1 = $query->createCriteria('bill');
		$criteria1->add(Restrictions::ne('status', $status));
		$ids = $query->findColumn('id');
		
		// Ignore all orders having a bill with the rejected status.
		$query = order_OrderService::getInstance()->createQuery()->setProjection(Projections::property('id'));
		$criteria1 = $query->createCriteria('bill');
		$criteria1->add(Restrictions::eq('status', $status));
		$ids = array_diff($ids, $query->findColumn('id'));		
		
		return new filter_StaticQuery(self::getDocumentModel(), $ids);
	}
}