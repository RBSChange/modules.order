<?php
/**
 * order_LoadOrderFinancialAction
 * @package modules.order.actions
 */
class order_LoadOrderFinancialAction extends generic_LoadJSONAction
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$order = $this->getDocumentInstanceFromRequest($request);
		$data = $this->exportFieldsData($order, array('financial'));
		return $this->sendJSON($data);
	}	
}