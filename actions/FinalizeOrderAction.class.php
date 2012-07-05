<?php
/**
 * order_FinalizeOrderAction
 * @package modules.order.actions
 */
class order_FinalizeOrderAction extends generic_LoadJSONAction
{
	/**
	 * @param change_Context $context
	 * @param change_Request $request
	 */
	public function _execute($context, $request)
	{
		$order = order_persistentdocument_order::getInstanceById($this->getDocumentIdFromRequest($request));
		order_ModuleService::getInstance()->finalizeOrder($order, true);
		$result = array('status' => $order->getOrderStatus());		
		$this->logAction($order, $result);
		
		$result = $this->exportFieldsData($order, array('shipping'));
		return $this->sendJSON($result);
	}
}