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
			if ($order instanceof order_persistentdocument_order)
			{
				$os->cancelOrder($order); // Save the order after it has been cancelled.
				$this->logAction($order);
			}
		}
		return self::getSuccessView();
	}
}