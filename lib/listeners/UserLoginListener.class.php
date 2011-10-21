<?php
/**
 * @package modules.order.lib.listeners
 */
class order_UserLoginListener
{
	public function onUserLogin($sender, $params)
	{	
		$user = $params['user'];
		if ($user instanceof users_persistentdocument_user)
		{
			// Cart merge has to be done only if the current user is a customer.
			$customer = customer_CustomerService::getInstance()->getByUser($user);
			if ($customer === null) {return;}
			
			$cs = order_CartService::getInstance();
			if ($cs->hasCartInSession())
			{
				$sessionCart = $cs->getDocumentInstanceFromSession();
				$sessionCart->setCustomer($customer);
			
				if (!$sessionCart->getMergeWithUserCart()) {return;}

				Framework::info(__METHOD__ . ' MergeWithUserCart');
				
				$cart = $customer->getCart();
				if ($cart !== null && !$cart->isEmpty())
				{
					$cs->mergeCustomerCart($sessionCart, $cart, $customer);
				}
			}
			else
			{
				$cart = $customer->getCart();
				if ($cart !== null && !$cart->isEmpty())
				{
					$sessionCart = $cs->getDocumentInstanceFromSession();
					$cs->mergeCustomerCart($sessionCart, $cart, $customer);
				}
			}
		}
	}
}