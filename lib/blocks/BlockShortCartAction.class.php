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
		$ops = order_OrderProcessService::getInstance();
		$op = $ops->loadFromSession();
		// Get the current cart from session.
		$cs = order_CartService::getInstance();
		$cart = $cs->getDocumentInstanceFromSession();
		$request->setAttribute('cart', $cart);
		
		// Check if order process is started or not.
		$currentPage = website_WebsiteModuleService::getInstance()->getCurrentPage();
		if ($op->inProcess())
		{
			// Set the url only if it is not the current page.
			$opURL = $op->getOrderProcessURL();
			if (strpos($opURL, '?') !== false)
			{
				list($urlToCheck, ) = explode('?', $opURL);
			}
			else
			{
				$urlToCheck = $opURL;
			}
			
			if ($urlToCheck != $_SERVER['SCRIPT_URI'])
			{
				$request->setAttribute('processUrl', $opURL);
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