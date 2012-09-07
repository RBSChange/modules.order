<?php
/**
 * order_BlockCartContextAction
 */
class order_BlockCartContextAction extends website_BlockAction
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
		$cs = order_CartService::getInstance();
		$cart = $cs->getDocumentInstanceFromSession();
		$ctxdoc = $cart->getContextDocument();
		if ($ctxdoc)
		{
			$ds = $ctxdoc->getDocumentService();
			$request->setAttribute('cart', $cart);
			$request->setAttribute('ctxdoc', $ctxdoc);
			if (method_exists($ds, 'getCartTemplate'))
			{
				return $ds->getCartTemplate($ctxdoc, $cart);
			}
			return website_BlockView::SUCCESS;
		}	
		return website_BlockView::NONE;
	}
}