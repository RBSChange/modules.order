<?php
/**
 * order_LoadOrderShippingAction
 * @package modules.order.actions
 */
class order_LoadOrderShippingAction extends generic_LoadJSONAction
{
	/**
	 * @param change_Context $context
	 * @param change_Request $request
	 */
	public function _execute($context, $request)
	{
		$order = $this->getDocumentInstanceFromRequest($request);
		$data = $this->exportFieldsData($order, array('shipping'));
		return $this->sendJSON($data);
	}
}