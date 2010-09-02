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
			$customer = customer_CustomerService::getInstance()->getByUser($user);
			$shop = catalog_ShopService::getInstance()->getCurrentShop();
			
			// Cart merge has to be done only if the current user is a customer and if
			// there is a current catalog (because a cart is always related to a catalog).
			if ($customer !== null && $shop !== null)
			{
				$cart = $customer->getCart();
				$cartService = order_CartService::getInstance();
				$sessionCart = $cartService->getDocumentInstanceFromSession();
				if ($sessionCart->getMergeWithUserCart())
				{
					if (Framework::isInfoEnabled())
					{
						Framework::info(__METHOD__ . ' MergeWithUserCart');
					}
					$sessionCart->setCustomer($customer);
					$sessionCart->setShop($shop);
					$sessionCart->setMergeWithUserCart(false);
			
					if ($cart !== null && !$cart->isEmpty())
					{
						$recup = $sessionCart->isEmpty();
						$added = false;
						
						foreach ($cart->getCartLineArray() as $line)
						{
							try 
							{
								$product = $line->getProduct();
								if ($product !== null)
								{
									if ($cartService->addProductToCart($sessionCart, $product, $line->getQuantity()))
									{
										$added = true;
									}
								}
							}
							catch (Exception $e)
							{
								Framework::warn(__METHOD__ . ' ' . $e->getMessage());
							}
						}
						
						if ($added)
						{
							if ($recup)
							{
								$sessionCart->addWarningMessage(f_Locale::translate('&modules.order.frontoffice.Cart-recup;'));
							}
							else
							{
								$sessionCart->addWarningMessage(f_Locale::translate('&modules.order.frontoffice.Cart-fusion;'));
							}
						}
					}
					$cartService->refresh($sessionCart);
				}
			}
		}
	}
}