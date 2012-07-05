<?php
class order_UpdateCartAction extends change_Action
{
	/**
	 * @param change_Context $context
	 * @param change_Request $request
	 */
	public function _execute($context, $request)
	{
		$moduleParams =	$request->getParameter('orderParam');
		$cartService = order_CartService::getInstance();
		$cart = $cartService->getDocumentInstanceFromSession();

		// -- Lines management.
		if (array_key_exists('lines', $moduleParams))
		{
			foreach ($moduleParams['lines'] as $index => $cartLine)
			{
				// If there is no line for this index skip it.
				$cartLineInfo = $cart->getCartLine($index);
				if ($cartLineInfo === null)
				{
					continue;
				}
				
				// TODO: add a configuration to allow floating quantities.
				$quantity = array_key_exists('quantity', $cartLine) ? $cartLine['quantity'] : null;
				if (!is_numeric($quantity) || $quantity != intval($quantity))
				{
					$cart->addTransientErrorMessage(LocaleService::getInstance()->trans('m.order.frontoffice.invalid-quantity', array('ucf'), array('quantity' => $quantity)));
					continue;
				}
				$quantity = intval($quantity);
				
				$productId = array_key_exists('productId', $cartLine) ? $cartLine['productId'] : null;
				if ($productId !== null)
				{
					$product = catalog_persistentdocument_product::getInstanceById($productId);
					$cartService->updateLine($cart, $index, $product, $quantity);
				}
			}
		}
		$cart->save();
		
		$pageId = $request->getParameter('pageref', null);
		if (is_numeric($pageId))
		{
			$url = LinkHelper::getDocumentUrl(DocumentHelper::getDocumentInstance($pageId, 'modules_website/page'));
			$context->getController()->redirectToUrl(str_replace('&amp;', '&', $url));
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