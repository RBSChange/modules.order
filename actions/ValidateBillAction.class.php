<?php
/**
 * order_ValidateBillAction
 * @package modules.order.actions
 */
class order_ValidateBillAction extends f_action_BaseJSONAction
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$bill = $this->getBillFromRequest($request);
		if ($request->getParameter('cancel') === 'true')
		{
			$result = order_BillService::getInstance()->cancelBillFromBo($bill);
		}
		else
		{
			$result = order_BillService::getInstance()->validateBillFromBo($bill, 
				$request->getParameter('transactionDate'),
				$request->getParameter('transactionId'),
				$request->getParameter('transactionText'));
		}
		return $this->sendJSON($result);
	}
	
	/**
	 * @param Request $request
	 * @return order_persistentdocument_bill
	 */
	private function getBillFromRequest($request)
	{
		$id = $this->getDocumentIdFromRequest($request);
		return DocumentHelper::getDocumentInstance($id, 'modules_order/bill');
	}
}