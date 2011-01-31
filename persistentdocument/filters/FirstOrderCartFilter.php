<?php
class order_FirstOrderCartFilter extends order_LinesCartFilterBase
{	
	/**
	 * @return String
	 */
	public static function getDocumentModelName()
	{
		return 'order/cart';
	}
	
	/**
	 * @param order_CartInfo $value
	 */
	public function checkValue($value)
	{
		$firstOrder = false;
		if ($value instanceof order_CartInfo) 
		{
			$currentCustomer = $value->getCustomer();
			if ($currentCustomer)
			{
				$firstOrder = (order_OrderService::getInstance()->getOrderCountByCustomer($currentCustomer) == 0);				
			}
		}
		return $firstOrder;
	}
}