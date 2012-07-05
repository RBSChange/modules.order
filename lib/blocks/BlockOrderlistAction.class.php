<?php
/**
 * @package modules.order.lib.blocks
 */
class order_BlockOrderlistAction extends website_TaggerBlockAction
{

	/**
	 * @return string
	 */
	protected function getTag()
	{
		return "contextual_website_website_modules_order_my-account-orders";
	}
	
	/**
	 * @see website_BlockAction::execute()
	 *
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return string
	 */
	function execute($request, $response)
	{
		$currentCustomer = customer_CustomerService::getInstance()->getCurrentCustomer();
		if ($currentCustomer !== null)
		{
			$orders = order_OrderService::getInstance()->getByCustomer($currentCustomer);
		}
		else
		{
			$orders = null;
		}
		
		$request->setAttribute('orders', $orders);
		return website_BlockView::SUCCESS;
	}
}