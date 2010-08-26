<?php
class order_HasProductAttributeCartshippingFilter extends order_HasProductAttributeFilter
{
	/**
	 * @return String
	 */
	public static function getDocumentModelName()
	{
		return 'order/cartshipping';
	}
}