<?php
/**
 * order_GenerateBillAction
 * @package modules.order.actions
 */
class order_GenerateBillAction extends f_action_BaseJSONAction 
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		try 
		{
			$bill = $this->getDocumentInstanceFromRequest($request);
			order_BillService::getInstance()->genBill($bill);
			$this->sendJSON(array('url' => $bill->getArchiveBoURL()));
		}
		catch (Exception $e)
		{
			Framework::exception($e);
			throw new BaseException('Unable To Generate Bill PDF', 'modules.order.bo.actions.Unable-to-generate-pdf-bill');
		}
	}
	
	/**
	 * @return boolean
	 */
	public function isSecure()
	{
		return true;
	}
}