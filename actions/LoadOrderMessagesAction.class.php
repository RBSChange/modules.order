<?php
/**
 * order_LoadOrderMessagesAction
 * @package modules.order.actions
 */
class order_LoadOrderMessagesAction extends change_JSONAction
{
	/**
	 * @param change_Context $context
	 * @param change_Request $request
	 */
	public function _execute($context, $request)
	{
		$order = $this->getDocumentInstanceFromRequest($request);
		
		$result = order_MessageService::getInstance()->getInfosByOrder($order, true);
				
		return $this->sendJSON($result);
	}
}