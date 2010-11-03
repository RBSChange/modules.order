<?php
class order_RemoveCartLineAction extends f_action_BaseAction
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$cartService = order_CartService::getInstance();
		$cart = $cartService->getDocumentInstanceFromSession();
		$cartLineIndex = $request->getParameter('cartLineIndex');

		if (!f_util_StringUtils::isEmpty($cartLineIndex) && !is_null($cart))
		{
			$cartService->removeLine($cart, $cartLineIndex);
			$cartService->refresh($cart);
		}

		$pageId = $request->getParameter('pageref', null);
		$url = null;
		if (is_numeric($pageId))
		{
			$url = LinkHelper::getDocumentUrl(DocumentHelper::getDocumentInstance($pageId, 'modules_website/page'));
		}		
		$context->getController()->redirectToUrl(str_replace('&amp;', '&', $url));

		return View::NONE;
	}

	/**
	 * @return boolean
	 */
	public function isSecure()
	{
		return false;
	}
}