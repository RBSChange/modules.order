<?php
interface order_FeesApplicationStrategy {
	
	/**
	 * @param order_persistentdocument_fees $fees
	 */
	function setFees($fees);
	
	/**
	 * @return array
	 */
	function getParameters();
	
	/**
	 * @param array $parameters
	 */
	function setParameters($parameters);
	
	
	/**
	 * @return string
	 */
	function getEditorModuleName();
	
	/**
	 * @return string
	 */
	function getEditorDefinitionPanelName();
	
	/**
	 * @param order_CartInfo $cart
	 * @return boolean
	 */
	function applyToCart($cart);
	
	/**
	 * @param order_CartInfo $cart
	 * @return boolean
	 */
	function removeFromCart($cart);	
	
	/**
	 * @param order_persistentdocument_order $order
	 * @param order_FeesInfo $feesInfo
	 * @return array
	 */
	function updateOrder($order, $feesInfo);
	
	/**
	 * @param catalog_persistentdocument_shippingfilter $shippingFilter
	 * @param order_CartInfo $cart
	 */
	public function updateShippingFilter($shippingFilter, $cart);
}



abstract class order_BaseFeesApplicationStrategy implements order_FeesApplicationStrategy 
{
	/**
	 * @var order_persistentdocument_fees
	 */
	protected $fees;
	
	/**
	 * @var array
	 */
	protected $parameters = array();
	
	/**
	 * @param order_persistentdocument_fees $fees
	 */
	public function setFees($fees)
	{
		$this->fees = $fees;
	}
	
	
	/**
	 * @return array
	 */
	function getParameters()
	{
		return $this->parameters;
	}
	
	/**
	 * @param array $parameters
	 */
	function setParameters($parameters)
	{
		$this->parameters = is_array($parameters) ? $parameters : array();
	}
	
	/**
	 * @return string
	 */
	function getEditorModuleName()
	{
		 list($moduleName, ) = explode('_', get_class($this));
		 return $moduleName;
	}
	
	/**
	 * @return string
	 */
	function getEditorDefinitionPanelName()
	{
		 list(, $panelName) = explode('_', get_class($this));
		 return $panelName;		
	}
		
	/**
	 * @param order_persistentdocument_order $order
	 * @param order_FeesInfo $feesInfo
	 */
	function updateOrder($order, $feesInfo)
	{
		return array();
	}
	
	/**
	 * @param catalog_persistentdocument_shippingfilter $shippingFilter
	 * @param order_CartInfo $cart
	 */
	public function updateShippingFilter($shippingFilter, $cart)
	{
		//Nothing
	}

}

class order_NotFoundFeesApplicationStrategy extends order_BaseFeesApplicationStrategy
{
	/**
	 * @param order_CartInfo $cart
	 * @return boolean
	 */
	function applyToCart($cart)
	{
		return false;
	}
	
	/**
	 * @param order_CartInfo $cart
	 * @return boolean
	 */
	function removeFromCart($cart)
	{
		return false;
	}
}