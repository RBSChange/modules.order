<?php
class order_ContainedProductCartshippingFilter extends order_ContainedProductCartFilter
{
	/**
	 * @return String
	 */
	public static function getDocumentModelName()
	{
		return 'order/cartshipping';
	}
}