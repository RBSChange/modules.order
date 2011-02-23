<?php
/**
 * @package modules.order.lib.listeners
 */
class order_UserLoginListener
{
	public function onUserLogin($sender, $params)
	{	
		$user = $params['user'];
		if ($user instanceof users_persistentdocument_frontenduser)
		{
			// Cart merge has to be done only if the current user is a customer.
			$customer = customer_CustomerService::getInstance()->getByUser($user);
			if ($customer === null)
			{
				return;
			}
			$cart = $customer->getCart();
			
			$cs = order_CartService::getInstance();
			$sessionCart = $cs->getDocumentInstanceFromSession();
			if (!$sessionCart->getMergeWithUserCart())
			{
				return;
			}
			
			Framework::info(__METHOD__ . ' MergeWithUserCart');
			$sessionCart->setCustomer($customer);
			$sessionCart->setMergeWithUserCart(false);
	
			if ($cart !== null && !$cart->isEmpty())
			{
				$recup = $sessionCart->isEmpty();
				
				$products = array();
				$quantities = array();
				foreach ($cart->getCartLineArray() as $line)
				{
					try 
					{
						$product = $line->getProduct();
						if ($product !== null)
						{
							$products[] = $product;
							$quantities[] = $line->getQuantity();
						}
					}
					catch (Exception $e)
					{
						Framework::warn(__METHOD__ . ' ' . $e->getMessage());
					}
				}
				
				// Add products.
				$shop = $cart->getShop();
				if ($cs->checkAddToCart($sessionCart, $shop, $products, $quantities, false))
				{
					$added = false;
					foreach ($products as $key => $product)
					{
						if ($cs->addProductToCart($sessionCart, $product, $quantities[$key]))
						{
							$added = true;
						}
					}
					
					if ($added)
					{
						$key = ($recup) ? 'm.order.frontoffice.cart-recup' : 'm.order.frontoffice.cart-fusion';
						$sessionCart->addSuccessMessage(LocaleService::getInstance()->transBO($key, array('ucf')));
					}
				}
			}
			$cs->refresh($sessionCart);
		}
	}
}