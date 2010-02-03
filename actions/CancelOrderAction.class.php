<?php
/**
 * @date Tue Dec 11 14:43:05 CET 2007
 * @author intbonjf
 * @package modules.order
 */
class order_CancelOrderAction extends f_action_BaseAction
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$os = order_OrderService::getInstance();
		foreach ($this->getDocumentInstanceArrayFromRequest($request) as $order)
		{
			$os->cancelOrder($order, true); // Save the order after it has been cancelled.
		}
		return self::getSuccessView();
	}
}