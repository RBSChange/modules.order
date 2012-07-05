<?php
class order_CartshippingFilter extends order_CartFilter
{
	/**
	 * @return string
	 */
	public static function getDocumentModelName()
	{
		return 'order/cartshipping';
	}
}