<?php
class order_OrderFilter extends f_persistentdocument_DocumentFilterImpl
{
	public function __construct()
	{
		$fieldParameter = f_persistentdocument_DocumentFilterRestrictionParameter::getNewInstance();
		$fieldParameter->setAllowedPropertyNames(array(
			'modules_order/order.creationdate',
			'modules_order/order.currencyCode',
			'modules_order/order.totalAmountWithTax',
			'modules_order/order.totalAmountWithoutTax',
			'modules_order/order.orderStatus'
		));
		$this->setParameters(array('field' => $fieldParameter));
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
		return order_OrderService::getInstance()->createQuery()
			->add($this->getParameter('field')->getValueForQuery());
	}
}