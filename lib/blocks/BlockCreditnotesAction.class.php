<?php
/**
 * order_BlockCreditnotesAction
 * @package modules.order.lib.blocks
 */
class order_BlockCreditnotesAction extends website_BlockAction
{
	/**
	 * @see website_BlockAction::execute()
	 *
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return string
	 */
	public function execute($request, $response)
	{
		if ($this->isInBackoffice())
		{
			return website_BlockView::NONE;
		}
		$customer = customer_CustomerService::getInstance()->getCurrentCustomer();
		$includeRepayments = $this->getConfiguration()->getIncludeRepayments();
		$includeUsedCreditnotes = $this->getConfiguration()->getIncludeUsedCreditNotes();
		$creditnotes = order_CreditnoteService::getInstance()->getByCustomer($customer, $includeRepayments, $includeUsedCreditnotes);
				
		$request->setAttribute('creditnotes', $creditnotes);
		return website_BlockView::SUCCESS;
	}
}