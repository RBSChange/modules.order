<?php
class order_RemoveCartLineAction extends change_Action
{
	/**
	 * @param change_Context $context
	 * @param change_Request $request
	 */
	public function _execute($context, $request)
	{
		$cartService = order_CartService::getInstance();
		$cart = $cartService->getDocumentInstanceFromSession();
		$cartLineIndex = $request->getParameter('cartLineIndex');

		if (!f_util_StringUtils::isEmpty($cartLineIndex))
		{
			$cartService->removeLine($cart, $cartLineIndex);
		}
		$cart->save();
		
		$pageId = $request->getParameter('pageref', null);
		if (is_numeric($pageId))
		{
			$url = LinkHelper::getDocumentUrl(DocumentHelper::getDocumentInstance($pageId, 'modules_website/page'));
			$context->getController()->redirectToUrl(str_replace('&amp;', '&', $url));
			return change_View::NONE;	
		}
			
		$backUrl = $request->getParameter('backurl', null);
		if ($backUrl !== null)
		{
			$context->getController()->redirectToUrl($backUrl);
			return change_View::NONE;	
		}
		
		$context->getController()->forward('website', 'Error404');
		return change_View::NONE;		
	}

	/**
	 * @return boolean
	 */
	public function isSecure()
	{
		return false;
	}
}