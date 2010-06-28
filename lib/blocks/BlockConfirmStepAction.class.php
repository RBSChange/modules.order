<?php
/**
 * @package modules.order.lib.blocks
 */
class order_BlockConfirmStepAction extends order_BlockAbstractProcessStepAction
{
	/**
	 * @see website_BlockAction::execute()
	 *
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return String
	 */
	function execute($request, $response)
	{		
		if ($this->isInBackoffice())
		{
			return website_BlockView::NONE;
		}
		if (!$this->checkCurrentCustomer())
		{
			$this->redirectToFirstStep();
		}
		$this->setCurrentStep('Confirm');
		$cartInfo = $this->getCurrentCart();
		$order = $cartInfo->getOrder();
		$bill = order_BillService::getInstance()->initializeByOrderForPayment($order);		
		$request->setAttribute('order', $order);
		$request->setAttribute('shop', $cartInfo->getShop());
		$request->setAttribute('bill', $bill);
		return $this->getInputViewName();
	}
}