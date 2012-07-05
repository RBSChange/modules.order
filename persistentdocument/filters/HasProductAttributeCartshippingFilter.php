<?php
class order_HasProductAttributeCartshippingFilter extends order_HasProductAttributeFilter
{
	/**
	 * @return string
	 */
	public static function getDocumentModelName()
	{
		return 'order/cartshipping';
	}
}