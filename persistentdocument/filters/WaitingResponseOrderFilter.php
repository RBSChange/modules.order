<?php
class order_WaitingResponseOrderFilter extends f_persistentdocument_DocumentFilterImpl
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
		return order_OrderService::getInstance()->createQuery()->add(Restrictions::eq('needsAnswer', true));
	}
}