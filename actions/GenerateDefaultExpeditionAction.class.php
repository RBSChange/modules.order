<?php
/**
 * order_GenerateDefaultExpeditionAction
 * @package modules.order.actions
 */
class order_GenerateDefaultExpeditionAction extends generic_LoadJSONAction
{
	/**
	 * @param change_Context $context
	 * @param change_Request $request
	 */
	public function _execute($context, $request)
	{
		$order = $this->getDocumentInstanceFromRequest($request);
		order_ModuleService::getInstance()->checkOrderProcessing($order);
		$result = $this->exportFieldsData($order, array('shipping'));
		return $this->sendJSON($result);
	}
}