<?php
/**
 * order_LoadOrderMessagesAction
 * @package modules.order.actions
 */
class order_LoadOrderMessagesAction extends f_action_BaseJSONAction
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$order = $this->getDocumentInstanceFromRequest($request);
		
		$result = order_MessageService::getInstance()->getInfosByOrder($order, true);
				
		return $this->sendJSON($result);
	}
}