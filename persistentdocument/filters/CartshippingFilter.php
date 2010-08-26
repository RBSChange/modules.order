<?php
class order_CartshippingFilter extends order_CartFilter
{
	/**
	 * @return String
	 */
	public static function getDocumentModelName()
	{
		return 'order/cartshipping';
	}
}