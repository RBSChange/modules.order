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
				$cartService = order_CartService::getInstance();
				$sessionCart = $cartService->getDocumentInstanceFromSession();
				if ($sessionCart->getMergeWithUserCart())
				{
					$cart = $customer->getCart();
					$addMessage = false;
					if ($cart === null)
					{
						$cart = new order_CartInfo();
					 	$cart->setShop($shop);
					 	$cart->setCustomer($customer);
					}
					else
					{
						$addMessage = true;
					}
					
							
					if ($sessionCart->getCartLineCount())
					{
						foreach ($sessionCart->getCartLineArray() as $line)
						{
							$cart->addCartLine($line);
						}
						if ($addMessage == true)
						{
							$cart->addWarningMessage(f_Locale::translate('&modules.order.frontoffice.Cart-fusion;'));
						}
					}
					else if ($addMessage == true)
					{
						$cart->addWarningMessage(f_Locale::translate('&modules.order.frontoffice.Cart-recup;'));
					}
					
					$cartService->refresh($cart);
				}
			}
		}
	}
}