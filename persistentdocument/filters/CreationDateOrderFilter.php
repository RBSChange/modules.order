<?php
class order_CreationDateOrderFilter extends f_persistentdocument_DocumentFilterImpl
{
	public function __construct()
	{
		$parameters = array();
		
		$info = new BeanPropertyInfoImpl('count', 'Integer');
		$countParameter = new f_persistentdocument_DocumentFilterValueParameter($info);
		$parameters['count'] = $countParameter;
		
		$info = new BeanPropertyInfoImpl('unit', 'String');
		$info->setListId('modules_filter/dateunits');
		$countParameter = new f_persistentdocument_DocumentFilterValueParameter($info);
		$parameters['unit'] = $countParameter;
		
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
		$count = $this->getParameter('count')->getValueForQuery();
		$unit = $this->getParameter('unit')->getValueForQuery();
		$date = filter_DateFilterHelper::getReferenceDate($unit, $count);
		return order_OrderService::getInstance()->createQuery()->add(Restrictions::gt('creationdate', $date));
	}
	
	/**
	 * @param customer_persistentdocument_customer $value
	 */
	public function checkValue($value)
	{
		if ($value instanceof order_persistentdocument_order)
		{
			$count = $this->getParameter('count')->getValueForQuery();
			$unit = $this->getParameter('unit')->getValueForQuery();
			$date = filter_DateFilterHelper::getReferenceDate($unit, $count);
			return $date < $value->getCreationdate();
		}
		return false;
	}
}
?>