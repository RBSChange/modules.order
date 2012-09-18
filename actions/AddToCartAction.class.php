<?php
/**
 * order_AddToCartAction
 * @package modules.order.actions
 */
class order_AddToCartAction extends change_Action
{
	/**
	 * @param change_Context $context
	 * @param change_Request $request
	 */
	public function _execute($context, $request)
	{
		// Get parameters.
		$cs = order_CartService::getInstance();
		$cart = $cs->getDocumentInstanceFromSession();
		$shop = $this->getShopFromRequest($request);
		$shopService = $shop->getDocumentService();
			
		$contextDocument = DocumentHelper::getDocumentInstanceIfExists($request->getParameter('contextId'));

		$product = $this->getProductFromRequest($request);
		$quantity = $this->getQuantityFromRequest($request);
		$backUrl = $request->getParameter('backurl');
		$cartUrl = LinkHelper::getTagUrl('contextual_website_website_modules_order_cart');
		
		// Configurate the product.
		$product->getDocumentService()->updateProductFromRequestParameters($product, $request->getParameters());
		
		// Check.
		$paramsToRedirect = $request->getParameters();
		list($module, $action) = explode('_', get_class($this)); // $this->getModuleName() does not always return the good value.
		$paramsToRedirect['addToCartModule'] = $module;
		$paramsToRedirect['addToCartAction'] = substr($action, 0, -6);
		unset($paramsToRedirect['module']);
		unset($paramsToRedirect['action']);
		unset($paramsToRedirect['lang']);
		$products = array($product);
		$quantities = array($product->getId() => $quantity);
		
		if ($cs->checkAddToCart($cart, $shop, $products, $quantities, true, $paramsToRedirect, $contextDocument))
		{
			// Add.
			if ($cs->addProductToCart($cart, $product, $quantity, $shop))
			{
				$replacements = array('label' => $product->getLabelAsHtml(), 'cartUrl' => $cartUrl);
				$cart->addSuccessMessage(LocaleService::getInstance()->trans('m.order.fo.product-label-added-link', array('ucf', 'html'), $replacements));
				$cart->refresh();
			}
		}
		
		// Redirect.
		change_Controller::getInstance()->redirectToUrl($backUrl ? $backUrl : $cartUrl);
	}
	
	/**
	 * @param change_Request $request
	 * @return catalog_persistentdocument_shop
	 */
	protected function getShopFromRequest($request)
	{
		return catalog_persistentdocument_shop::getInstanceById($request->getParameter('shopId'));
	}
	
	/**
	 * @param change_Request $request
	 * @return catalog_persistentdocument_product
	 */
	protected function getProductFromRequest($request)
	{
		return catalog_persistentdocument_product::getInstanceById($request->getParameter('productId'));
	}
	
	/**
	 * @param change_Request $request
	 * @return catalog_persistentdocument_product
	 */
	protected function getQuantityFromRequest($request)
	{
		$quantity = null;
		if ($request->hasParameter('quantity'))
		{
			$quantity = $request->getParameter('quantity');
		}
		return max(1, intval($quantity));
	}
	
	/**
	 * @return boolean
	 */
	public function isSecure()
	{
		return false;
	}
}