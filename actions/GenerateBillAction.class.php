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
			$order = $this->getDocumentInstanceFromRequest($request);
			order_OrderService::getInstance()->genBill($order);
		}
		catch (Exception $e)
		{
			throw new BaseException('Unable To Generate Bill PDF', 'modules.order.bo.actions.Unable-to-generate-pdf-bill');
		}
		$this->sendJSON(array("url" => $order->getBillBoUrl()));
	}
	
	/**
	 * @see f_action_BaseAction::isSecure()
	 *
	 * @return boolean
	 */
	public function isSecure()
	{
		return true;
	}

}