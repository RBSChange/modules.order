<?php
class order_BlockCartAction extends website_BlockAction
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
			return website_BlockView::DUMMY;
		}
		
		$cs =  order_CartService::getInstance();
		$cart = $cs->getDocumentInstanceFromSession();
		$shop = $cart->getShop();
		$pageId = $this->getContext()->getId();

		$request->setAttribute('shop', $shop);
		$hrefShop = LinkHelper::getUrl($shop->getTopic()->getIndexPage());
		$request->setAttribute('hrefShop', $hrefShop);

		// 1st case: Cart is empty
		// 		redirect to the 'cart-empty' tagged page, or, if this one does not exist
		// 		return the dummy view.
		if (($cart->getCartLineCount() == 0))
		{
			try
			{
				$emptyCartPage = TagService::getInstance()->getDocumentByContextualTag(
					'contextual_website_website_modules_order_cart-empty',
					website_WebsiteModuleService::getInstance()->getCurrentWebsite()
				);
				if ($emptyCartPage->getId() != $pageId)
				{
					$url = LinkHelper::getUrl($emptyCartPage);
					HttpController::getInstance()->redirectToUrl(str_replace('&amp;', '&', $url));
				}
			}
			catch (TagException $e)
			{
				if (Framework::isWarnEnabled())
				{
					Framework::warn($e->getMessage());
				}
			}
			$shop = catalog_ShopService::getInstance()->getCurrentShop();
			$request->setAttribute('shop', $shop);
			return website_BlockView::DUMMY;
		}

		// Cart is not empty.
		$request->setAttribute('updateActionUrl', LinkHelper::getUrl('order', 'UpdateCart', array('pageref' => $pageId)));
		$request->setAttribute('pageref', $pageId);
		$request->setAttribute('cart', $cart);

		$ms = order_ModuleService::getInstance();
		$request->setAttribute('orderProcess', array(
			'open' => $ms->isProcessOpen(),
			'message' => $ms->getProcessClosedMessage()
		));

		// Any cart validation error.
		$cs->refresh($cart);
		
		$request->setAttribute('warningMessages', $cart->getWarningMessageArray());
		$cart->clearWarningMessages();
		$request->setAttribute('errorMessages', $cart->getErrorMessageArray());
		
		// Backlink.
		$user = $this->getContext()->getUser();
		if (!$user->hasAttribute('cartBackLink'))
		{
			if (isset($_SERVER['HTTP_REFERER']))
			{
				$href = $_SERVER['HTTP_REFERER'];
				if ($index = strpos($href, '?'))
				{
					$href = substr($href, 0, $index);
				}
				$user->setAttribute('cartBackLink', $href);
			}
		}
	
		return website_BlockView::SUCCESS;
		
	}
	

	function executeOrder($request, $response)
	{
		$cgv = $request->getParameter("cgv");
		$cart = order_CartService::getInstance()->getDocumentInstanceFromSession();
		if ($cgv)
		{
		
			HttpController::getInstance()->redirectToUrl($cart->getOrderProcessURL());
		}
		$this->addError(f_Locale::translate("&modules.order.frontoffice.Must-agree-with-general-sales-conditions-error;"));
		return $this->execute($request, $response);
	}
	
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return String
	 */
	function executeRefresh($request, $response)
	{
		$cs =  order_CartService::getInstance();
		$cart = $cs->getDocumentInstanceFromSession();
		$cs->refresh($cart);
		return website_BlockView::SUCCESS;
	}	
}