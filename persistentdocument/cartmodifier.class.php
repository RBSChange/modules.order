<?php
/**
 * Class where to put your custom methods for document order_persistentdocument_cartmodifier
 * @package modules.order.persistentdocument
 */
class order_persistentdocument_cartmodifier extends order_persistentdocument_cartmodifierbase 
{
	/**
	 * @param order_CartInfo $cart
	 * @return boolean
	 */
	public function applyToCart($cart)
	{
		return $this->getDocumentService()->applyToCart($this, $cart);
	}
	
	/**
	 * @param order_CartInfo $cart
	 * @return boolean
	 */
	public function removeFromCart($cart)
	{
		return $this->getDocumentService()->removeFromCart($this, $cart);
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @param order_DiscountInfo $discount
	 * @return array
	 */
	public function updateOrder($order, $discountInfo)
	{
		return $this->getDocumentService()->updateOrder($this, $order, $discountInfo);
	}
	
	
	//Back office editor
	/**
	 * @return string
	 */	
	public function getBillingAreaColumnLabel()
	{
		$ba = $this->getBillingArea();
		return $ba ? $ba->getTreeNodeLabel() : '-';
	}
}