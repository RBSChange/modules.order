<?php
/**
 * order_BlockRepaymentsAction
 * @package modules.order.lib.blocks
 */
class order_BlockRepaymentsAction extends website_BlockAction
{
	/**
	 * @see website_BlockAction::execute()
	 *
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return String
	 */
	public function execute($request, $response)
	{
		if ($this->isInBackoffice())
		{
			return website_BlockView::NONE;
		}
	
		$customer = customer_CustomerService::getInstance()->getCurrentCustomer();
		$repayments = order_CreditnoteService::getInstance()->getRepaymentsByCustomer($customer);
				
		$request->setAttribute('repayments', $repayments);
		return website_BlockView::SUCCESS;
	}
}