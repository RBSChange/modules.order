<?php
/**
 * @package modules.order.lib.blocks
 */
class order_BlockPaymentStepAction extends order_BlockAbstractProcessStepAction
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
		$this->setCurrentStep('Payment');
		$cartInfo = $this->getCurrentCart();
		$order = $cartInfo->getOrder();
		if ($order === null)
		{
			$this->redirectToFirstStep();
		}
		$bills = order_BillService::getInstance()->getByOrderForPayment($order);	
		$request->setAttribute('order', $order);
		$request->setAttribute('shop', $cartInfo->getShop());
		$request->setAttribute('bills', $bills);
		return $this->getInputViewName();
	}
}