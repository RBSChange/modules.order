<?php
/**
 * order_PrintOrderAction
 * @package modules.order.actions
 */
class order_PrintOrderAction extends change_Action
{
	/**
	 * @param change_Context $context
	 * @param change_Request $request
	 */
	public function _execute($context, $request)
	{
		$order = $this->getDocumentInstanceFromRequest($request);
		if ($order === null)
		{
			$context->getController()->forward('website', 'Error404');
			return change_View::NONE;
		}
		$request->setAttribute('order', $order);	
		return $request->getParameter('mode');
	}
}