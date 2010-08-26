<?php
class order_DiscountProductCartshippingFilter extends order_DiscountProductCartFilter
{
	/**
	 * @return String
	 */
	public static function getDocumentModelName()
	{
		return 'order/cartshipping';
	}
}