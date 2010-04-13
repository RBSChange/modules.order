<?php
class order_ShopOrderFilter extends f_persistentdocument_DocumentFilterImpl
{
	public function __construct()
	{
		$parameters = array();
		
		$info = new BeanPropertyInfoImpl('shop', 'modules_catalog/shop');
		$shopParameter = new f_persistentdocument_DocumentFilterValueParameter($info);
		$parameters['shop'] = $shopParameter;
		$this->setParameters($parameters);
	}
	
	/**
	 * @return String
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
		$shopIds =  DocumentHelper::getIdArrayFromDocumentArray($this->getParameter('shop')->getValueForQuery());
		return order_OrderService::getInstance()->createQuery()->add(Restrictions::in('shopId', $shopIds));
	}
	
	/**
	 * @param customer_persistentdocument_customer $value
	 */
	public function checkValue($value)
	{
		if ($value instanceof order_persistentdocument_order)
		{
			$shopIds =  DocumentHelper::getIdArrayFromDocumentArray($this->getParameter('shop')->getValueForQuery());
			return in_array($value->getShopId(), $shopIds);
		}
		return false;
	}
}