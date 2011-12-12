<?php
/**
 * order_PrintOrderAction
 * @package modules.order.actions
 */
class order_PrintOrderAction extends f_action_BaseAction
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$order = $this->getDocumentInstanceFromRequest($request);
		if ($order === null)
		{
			$context->getController()->forward('website', 'Error404');
			return View::NONE;
		}
		$request->setAttribute('order', $order);	
		return $request->getParameter('mode');
	}
}