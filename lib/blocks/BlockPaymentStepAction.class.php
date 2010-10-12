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
		
		$order = $this->getCurrentOrder();
		if ($order === null)
		{
			if (Framework::isInfoEnabled())
			{
				Framework::info(__METHOD__ . ' No current order found');	
				$this->redirectToFirstStep();
			}
		}
		
		$this->setCurrentStep('Payment');
		$bills = order_BillService::getInstance()->getByOrderForPayment($order);	
		$request->setAttribute('order', $order);
		$request->setAttribute('shop', $order->getShop());
		$request->setAttribute('bills', $bills);
		return $this->getInputViewName();
	}
	
	/**
	 * @return order_persistentdocument_order
	 */
	public function getCurrentOrder()
	{
		//get the current cart (empty if à valid payment transaction in progress)
		$cartInfo = $this->getCurrentCart();			
		$orderId = $this->findParameterValue('orderId');
		if (intval($orderId))
		{
			$order = DocumentHelper::getDocumentInstance($orderId, 'modules_order/order');
			$customer = customer_CustomerService::getInstance()->getCurrentCustomer();
			if (DocumentHelper::equals($customer, $order->getCustomer()))
			{
				return $order;
			}
		}
		
		return null;
	}
}