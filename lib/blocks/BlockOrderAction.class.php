<?php
/**
 * @package modules.order.lib.blocks
 */
class order_BlockOrderAction extends website_TaggerBlockAction
{
	/**
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
		$currentCustomer = customer_CustomerService::getInstance()->getCurrentCustomer();
		if (! DocumentHelper::equals($currentCustomer, $order->getCustomer()))
		{
			throw new Exception('Invalid logged customer account');
		}
		
		if ($order->getOrderStatus() == order_OrderService::IN_PROGRESS)
		{
			$tm = f_persistentdocument_TransactionManager::getInstance();
			try
			{
				$tm->beginTransaction();
				order_ModuleService::getInstance()->checkOrderProcessing($order);
				$tm->commit();
			}
			catch (Exception $e)
			{
				$tm->rollBack($e);
			}
		}
		
		$request->setAttribute('order', $order);
		$request->setAttribute('shop', $order->getShop());
		$bills = order_BillService::getInstance()->getByOrder($order);	
		$request->setAttribute('bills', $bills);
		
		$expeditions = order_ExpeditionService::getInstance()->getByOrderForDisplay($order);		
		$request->setAttribute('expeditions', $expeditions);
		
		$cs =  order_CartService::getInstance();
		if ($cs->hasCartInSession())
		{
			$theme = Framework::getConfigurationValue("modules/website/jquery-ui-theme", "south-street");
			$this->getContext()->addStyle("modules.website.jquery-ui.$theme");
			$this->getContext()->addScript('modules.website.lib.js.jquery-ui-dialog');
			
			$cart = $cs->getDocumentInstanceFromSession();
			$request->setAttribute('cart', $cart);
		}
		return website_BlockView::SUCCESS;
	}
	
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return String
	 */
	public function executeAddToCart($request, $response)
	{
		if ($request->getParameter('clearCart') == '1')
		{
			$cs =  order_CartService::getInstance();
			$cart = $cs->getDocumentInstanceFromSession();
			$cs->clearCart($cart);
		}
		$order = $this->getCurrentOrder();
		order_OrderService::getInstance()->appendOrderToCart($order);
		return website_BlockView::NONE;
	}
	
	/**
	 * @return order_persistentdocument_order
	 */
	private function getCurrentOrder()
	{
		return $this->getRequiredDocumentParameter('cmpref', 'order_persistentdocument_order');
	}
}