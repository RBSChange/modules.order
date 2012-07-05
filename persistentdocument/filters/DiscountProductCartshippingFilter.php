<?php
class order_DiscountProductCartshippingFilter extends order_DiscountProductCartFilter
{
	/**
	 * @return string
	 */
	public static function getDocumentModelName()
	{
		return 'order/cartshipping';
	}
}