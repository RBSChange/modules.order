<?php
/**
 * order_AddToCartMultipleAction
 * @package modules.order.actions
 */
class order_AddToCartMultipleAction extends change_Action
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
		$products = $this->getProductsFromRequest($request);
		$quantities = $this->getQuantitiesFromRequest($request, $products);
		$backUrl = $request->getParameter('backurl');
		$contextDocument = DocumentHelper::getDocumentInstanceIfExists($request->getParameter('contextId'));
		
		
		// No product configuration : products are added with their default values.
		// TODO: handle product configuration here?
		
		// Check.
		$paramsToRedirect = $request->getParameters();
		$paramsToRedirect['addToCartModule'] = $this->getModuleName();
		$paramsToRedirect['addToCartAction'] = $this->getActionName();
		unset($paramsToRedirect['module']);
		unset($paramsToRedirect['action']);
		unset($paramsToRedirect['lang']);
		if ($cs->checkAddToCart($cart, $shop, $products, $quantities, true, $paramsToRedirect, $contextDocument))
		{
			// Add products.
			$productAdded = false;
			foreach ($products as $product)
			{
				if ($cs->addProductToCart($cart, $product, $quantities[$product->getId()]))
				{
					$this->addedProductLabels[] = $product->getLabelAsHtml();
					$productAdded = true;
				}
				else
				{
					$this->notAddedProductLabels[] = $product->getLabelAsHtml();
				}
			}
			
			// Refresh the cart.
			if ($productAdded)
			{
				$cart->refresh();
				$this->addMessagesToCart($cart);
			}
		}
		
		// Add messages to cart.
		$this->addMessagesToCart($cart);
		
		// Redirect.
		change_Controller::getInstance()->redirectToUrl($backUrl ? $backUrl : $this->getCartUrl());
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
	protected function getProductsFromRequest($request)
	{
		$products = array();
		if ($request->hasParameter('productIds'))
		{
			foreach ($request->getParameter('productIds') as $id)
			{
				$products[] = DocumentHelper::getDocumentInstance($id, 'modules_catalog/product');
			}
		}
		return $products;
	}
	
	/**
	 * @param change_Request $request
	 * @return catalog_persistentdocument_product
	 */
	protected function getQuantitiesFromRequest($request, $products)
	{
		$quantities = array();
		$quantitiesFormParam = $request->getParameter('quantities', array());
		foreach ($products as $product)
		{
			$productId = $product->getId();
			$quantities[$product->getId()] = 1;
			if (array_key_exists($product->getId(), $quantitiesFormParam))
			{
				$quantities[$product->getId()] = max(intval($quantitiesFormParam[$productId]), 1);
			}
		}
		return $quantities;
	}
	
	/**
	 * @var string[]
	 */
	protected $addedProductLabels = array();
	
	/**
	 * @var string[]
	 */
	protected $notAddedProductLabels = array();
	
	/**
	 * @param order_CartInfo $cart
	 */
	protected function addMessagesToCart($cart)
	{
		$message = $this->getMessage($this->addedProductLabels, 'added');
		if ($message !== null)
		{
			$cart->addSuccessMessage($message);
		}
		$this->addedProductLabels = null;
		$message = $this->getMessage($this->notAddedProductLabels, 'not-added');
		if ($message !== null)
		{
			$cart->addTransientErrorMessage($message);
		}
		$this->notAddedProductLabels = null;
	}
	
	/**
	 * @param Array<String> $labels
	 * @param string $mode
	 * @return string
	 */
	private function getMessage($labels, $eventname)
	{
		$ls = LocaleService::getInstance();
		switch (count($labels))
		{
			case 0 :
				$message = null;
				break;
			case 1 :
				$replacements = array('label' => f_util_ArrayUtils::firstElement($labels), 'cartUrl' => $this->getCartUrl());
				$message = $ls->trans('m.order.fo.product-label-'.$eventname.'-link', array('ucf'), $replacements);
				break;
			default :
				$lastLabel = array_pop($labels);
				$firstLabels = implode(', ', $labels);
				$replacements = array('firstLabels' => $firstLabels, 'lastLabel' => $lastLabel, 'cartUrl' => $this->getCartUrl());
				$message = $ls->trans('m.order.fo.product-labels-'.$eventname.'-link', array('ucf'), $replacements);
				break;
		}
		return $message;
	}
	
	/**
	 * @var string
	 */
	private $cartUrl;
	
	/**
	 * @return string
	 */
	protected function getCartUrl()
	{
		if ($this->cartUrl === null)
		{
			$this->cartUrl = LinkHelper::getTagUrl('contextual_website_website_modules_order_cart');
		}
		return $this->cartUrl;
	}
	
	/**
	 * @return boolean
	 */
	public function isSecure()
	{
		return false;
	}
}