<?php
/**
 * order_BlockStdResultStepAction
 * @package modules.order.lib.blocks
 */
class order_BlockStdResultStepAction extends website_BlockAction
{
	
	/**
	 * @see website_BlockAction::getInputViewName()
	 */
	public function getInputViewName()
	{
		return website_BlockView::SUCCESS;
	}
	
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
		
		$order = $this->getCurrentOrder();
		if ($order !== null)
		{
			$request->setAttribute('order', $order);
			$bills = order_BillService::getInstance()->getByOrderForPayment($order);	
			$request->setAttribute('bills', $bills);
			$lastBill = null;
			if (count($bills > 0))
			{
				foreach ($bills as $bill) 
				{
					if ($bill instanceof order_persistentdocument_bill) 
					{
						if (in_array($bill->getStatus(), array(order_BillService::SUCCESS, order_BillService::WAITING)))
						{
							$request->setAttribute('lastBillSuccess', true);
							$lastBill = $bill;
							break;
						}
					}
				}
				if ($lastBill === null)
				{
					$lastBill = $bill;
				}
				$request->setAttribute('lastBill', $lastBill);
			}
		}
		
		$cs = order_CartService::getInstance();	
		$cart  = $cs->getDocumentInstanceFromSession();
		$op = $cart->getOrderProcess();
		$op->setCurrentStep('Result');
		if ($cart->isEmpty())
		{	
			$request->setAttribute('orderProcess', $op);
		}
		else
		{
			$request->setAttribute('cart', $cart);
		}
		return $this->getInputViewName();
	}
	
	/**
	 * @return order_persistentdocument_order
	 */
	public function getCurrentOrder()
	{			
		$orderId = $this->findParameterValue('orderId');
		if (intval($orderId))
		{
			$order =  order_persistentdocument_order::getInstanceById($orderId);
			$customer = customer_CustomerService::getInstance()->getCurrentCustomer();
			if (DocumentHelper::equals($customer, $order->getCustomer()))
			{
				return $order;
			}
		}
		return null;
	}	
	
}