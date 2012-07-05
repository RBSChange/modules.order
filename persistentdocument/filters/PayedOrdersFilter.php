<?php
class order_PayedOrdersFilter extends f_persistentdocument_DocumentFilterImpl
{
	public function __construct()
	{
	}
	
	/**
	 * @return string
	 */
	public static function getDocumentModelName()
	{
		return 'modules_order/order';
	}
	
	/**
	 * @return f_persistentdocument_criteria_Query
	 */
	public function getQuery()
	{
		$orderQuery = order_OrderService::getInstance()->createQuery();
		$orderQuery->createCriteria("bill")
			->add(Restrictions::eq("status", order_BillService::SUCCESS));
		return $orderQuery;
	}
}