<?php
class order_SommeProductAttributeCartshippingFilter extends order_SommeProductAttributeFilter
{
	/**
	 * @return String
	 */
	public static function getDocumentModelName()
	{
		return 'order/cartshipping';
	}
}