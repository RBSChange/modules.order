<?php
class order_ContainedProductCartshippingFilter extends order_ContainedProductCartFilter
{
	/**
	 * @return string
	 */
	public static function getDocumentModelName()
	{
		return 'order/cartshipping';
	}
}