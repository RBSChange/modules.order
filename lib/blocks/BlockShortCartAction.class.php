<?php
/**
 * @package modules.order.lib.blocks
 */
class order_BlockShortCartAction extends website_BlockAction
{
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return String
	 */
	function execute($request, $response)
	{
		if (catalog_ShopService::getInstance()->getCurrentShop() === null)
		{
			return website_BlockView::DUMMY;
		}
		$ms = order_ModuleService::getInstance();
		
		// Get the current cart from session.
		$cart = order_CartService::getInstance()->getDocumentInstanceFromSession();
		$request->setAttribute('cart', $cart);
		
		// Check if order process is started or not.
		$currentPage = website_WebsiteModuleService::getInstance()->getCurrentPage();
		if ($ms->isProcessStarted())
		{
			// If we are on order process page, do not set link.			
			$page = $ms->getPage();
			if ($page !== null && $page->getId() !== $currentPage->getId())
			{
				$request->setAttribute('processUrl', $ms->getPageLink());
			}
			return 'Process';
		}
		else
		{
			// If we are on the cart page, do not set link.
			$ts = TagService::getInstance();
			$request->setAttribute('setLinks', !$ts->hasTag($currentPage, 'contextual_website_website_modules_order_cart'));
			
			if (is_null($cart) || ($cart->getCartLineCount() == 0))
			{
				return 'EmptyCart';
			}
			return 'Cart';
		}		
	}
}