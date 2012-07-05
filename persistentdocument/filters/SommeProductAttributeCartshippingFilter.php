<?php
class order_SommeProductAttributeCartshippingFilter extends order_SommeProductAttributeFilter
{
	/**
	 * @return string
	 */
	public static function getDocumentModelName()
	{
		return 'order/cartshipping';
	}
}