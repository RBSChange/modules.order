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
		$shop = DocumentHelper::getDocumentInstance($request->getParameter('shopId'), 'modules_catalog/shop');
		$shopService = $shop->getDocumentService();
		$product = $this->getProductFromRequest($request);
		$quantity = $this->getQuantityFromRequest($request);
		$backUrl = $request->getParameter('backurl');
		
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
		if ($cs->checkAddToCart($cart, $shop, $products, $quantities, true, $paramsToRedirect))
		{
			// Add.
			if ($cs->addProductToCart($cart, $product, $quantity, $shop))
			{
				$cart->addSuccessMessage(LocaleService::getInstance()->transFO('m.order.fo.product-added', array('ucf')));
				$cart->refresh();
			}
		}
		
		// Redirect.
		if (!$backUrl)
		{
			$backUrl = LinkHelper::getTagUrl('contextual_website_website_modules_order_cart');
		}
		change_Controller::getInstance()->redirectToUrl($backUrl);
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
	
	/**
	 * @return boolean
	 */
	public function isSecure()
	{
		return false;
	}
}