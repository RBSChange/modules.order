<?php
/**
 * order_LoadOrderShippingAction
 * @package modules.order.actions
 */
class order_LoadOrderShippingAction extends generic_LoadJSONAction
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$order = $this->getDocumentInstanceFromRequest($request);
		order_ModuleService::getInstance()->checkOrderProcessing($order);
		$data = $this->exportFieldsData($order, array('shipping'));
		return $this->sendJSON($data);
	}
}
