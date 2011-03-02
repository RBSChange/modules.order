<?php
class order_CancelOrderAction extends f_action_BaseJSONAction
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$labels = array("");
		foreach ($this->getDocumentInstanceArrayFromRequest($request) as $order)
		{
			if ($order instanceof order_persistentdocument_order)
			{
				$order->getDocumentService()->cancelOrder($order);
				$this->logAction($order);
				$labels[] = $order->getOrderNumber();
			}
		}
		return $this->sendJSON(array('message' => 
        	LocaleService::getInstance()->transBO('m.order.bo.actions.cancel-order-success', 
        		array(), array('OrderNumbers' => implode("\n ", $labels)))));
	}
}