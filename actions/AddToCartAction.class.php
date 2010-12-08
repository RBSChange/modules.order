<?php
/**
 * order_AddToCartAction
 * @package modules.order.actions
 */
class order_AddToCartAction extends f_action_BaseAction
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		// Get parameters.
		$cs = order_CartService::getInstance();
		$cart = $cs->getDocumentInstanceFromSession();
		$shop = DocumentHelper::getDocumentInstance($request->getParameter('shopId'), 'modules_catalog/shop');
		$shopService = $shop->getDocumentService();
		$product = $this->getProductFromRequest($request);
		$quantity = $this->getQuantityFromRequest($request);
		$backUrl = $request->getParameter('backurl');
		
		// Configurate the product.
		$product->getDocumentService()->updateProductFromRequestParameters($product, $request->getParameters());
		
		// Check.
		$paramsToRedirect = $request->getParameters();
		$paramsToRedirect['addToCartModule'] = $this->getModuleName();
		$paramsToRedirect['addToCartAction'] = $this->getActionName();
		unset($paramsToRedirect['module']);
		unset($paramsToRedirect['action']);
		unset($paramsToRedirect['lang']);
		$cs->checkAddToCart($cart, $shop, array($product), array($product->getId() => $quantity), $paramsToRedirect);

		// Add.
		if ($cs->addProductToCart($cart, $product, $quantity, $shop))
		{
			$cart->addSuccessMessage(LocaleService::getInstance()->transFO('m.order.fo.product-added', array('ucf')));
		}
		
		// Redirect.
		if (!$backUrl)
		{
			$backUrl = LinkHelper::getTagUrl('contextual_website_website_modules_order_cart');
		}
		HttpController::getInstance()->redirectToUrl($backUrl);
	}
	
	/**
	 * @param f_mvc_Request $request
	 * @return catalog_persistentdocument_product
	 */
	protected function getProductFromRequest($request)
	{
		$productId = null;
		if ($request->hasParameter('productId'))
		{
			$productId = $request->getParameter('productId');
		}
		return DocumentHelper::getDocumentInstance($productId, 'modules_catalog/product');
	}
	
	/**
	 * @param f_mvc_Request $request
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
}