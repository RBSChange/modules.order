<?php
/**
 * order_LoadOrderFinancialAction
 * @package modules.order.actions
 */
class order_LoadOrderFinancialAction extends generic_LoadJSONAction
{
	/**
	 * @param change_Context $context
	 * @param change_Request $request
	 */
	public function _execute($context, $request)
	{
		$order = $this->getDocumentInstanceFromRequest($request);
		$data = $this->exportFieldsData($order, array('financial'));
		return $this->sendJSON($data);
	}	
}