<?php
/**
 * order_MarkAsAnsweredAction
 * @package modules.order.actions
 */
class order_MarkAsAnsweredAction extends f_action_BaseJSONAction
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
				$order->setNeedsAnswer(false);
				$order->save();
			}
		}
		return $this->sendJSON("OK");
	}
}