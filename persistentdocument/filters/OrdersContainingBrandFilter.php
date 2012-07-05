<?php
class order_OrdersContainingBrandFilter extends f_persistentdocument_DocumentFilterImpl
{
	public function __construct()
	{
		$info = new BeanPropertyInfoImpl('brand', 'modules_brand/brand');
		$info->setMaxOccurs(-1);
		$info->setMinOccurs(1);
		$parameters['brand'] = new f_persistentdocument_DocumentFilterValueParameter($info);
		$this->setParameters($parameters);
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
		$query = order_OrderService::getInstance()->createQuery();
		$criteria1 = $query->createCriteria('line');
		$criteria2 = $criteria1->createPropertyCriteria('productId', 'modules_catalog/product');
		$criteria2->add(Restrictions::in('brand', $this->getParameter('brand')->getValueForQuery()));
		return $query;
	}
}