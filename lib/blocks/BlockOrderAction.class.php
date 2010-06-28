<?php
/**
 * @package modules.order.lib.blocks
 */
class order_BlockOrderAction extends website_TaggerBlockAction
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
		$currentCustomer = customer_CustomerService::getInstance()->getCurrentCustomer();
		if (! DocumentHelper::equals($currentCustomer, $order->getCustomer()))
		{
			throw new Exception('Invalid logged customer account');
		}
		$request->setAttribute('order', $order);
		$request->setAttribute('shop', $order->getShop());
		$bills = order_BillService::getInstance()->getByOrder($order);	
		$request->setAttribute('bills', $bills);
		return website_BlockView::SUCCESS;
	}
	
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return String
	 */
	function executeAddToCart($request, $response)
	{

		$order = $this->getCurrentOrder();
		order_OrderService::getInstance()->appendOrderToCart($order);
		$website = website_WebsiteModuleService::getInstance()->getCurrentWebsite();
		$tagService = TagService::getInstance();
		$page = $tagService->getDocumentByContextualTag('contextual_website_website_modules_order_cart', $website);
		$url = LinkHelper::getUrl($page, RequestContext::getInstance()->getLang());
		HttpController::getInstance()->redirectToUrl(str_replace('&amp;', '&', $url));
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