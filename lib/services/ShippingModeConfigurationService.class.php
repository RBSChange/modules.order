<?php
/**
 * @package modules.order
 * @method order_ShippingModeConfigurationService getInstance()
 */
class order_ShippingModeConfigurationService extends change_BaseService
{
	/**
	 * @param website_BlockAction $block
	 * @param f_mvc_Request $request
	 * @param string $executeName
	 * @return boolean
	 */
	public function checkModesConfiguration($block, $request, $executeName)
	{
		$cart = order_CartService::getInstance()->getDocumentInstanceFromSession();
		foreach ($cart->getShippingArray() as $shippingInfos)
		{
			$modeId = $shippingInfos['filter']['modeId'];
			if ($this->isModeIdChecked($cart, $modeId)) { continue; }
			$mode = shipping_persistentdocument_mode::getInstanceById($modeId);
			$modeService = $mode->getDocumentService();
			if (f_util_ClassUtils::methodExists($modeService, 'getConfigurationBlockForProcess'))
			{
				$request->setAttribute('cart', $cart);
				$request->setAttribute('mode', $mode);
				$request->setAttribute('modeId', $modeId);
				$request->setAttribute('hiddenFieldName', $block->getModuleName() . 'Param[website_BlockAction_submit][' . $block->getBlockId() . '][' . $executeName . ']');
				$block->forward('order', 'ShippingModeConfigurationContainer');
				// When the configuration is OK, the configuration block adds the id in the list.
				if (!$this->isModeIdChecked($cart, $modeId)) { return false; }
			}
			else
			{
				$this->addCheckedModeId($cart, $modeId);
			}
		}
		$this->clearCheckedModeIds($cart);
		return true;
	}
	
	/**
	 * @param order_CartInfo $cart
	 * @param integer $modeId
	 * @return boolean
	 */
	public function isModeIdChecked($cart, $modeId)
	{
		$modeIds = $this->getCheckedModeIds($cart);
		return (in_array($modeId, $modeIds));
	}
	
	/**
	 * @param order_CartInfo $cart
	 * @param integer $modeId
	 * @return boolean
	 */
	public function addCheckedModeId($cart, $modeId)
	{
		$modeIds = $this->getCheckedModeIds($cart);
		$modeIds[] = $modeId;
		$cart->setProperties('__configurationCheckedIds', $modeIds);
	}

	/**
	 * @param order_CartInfo $cart
	 */
	public function clearCheckedModeIds($cart)
	{
		$cart->setProperties('__configurationCheckedIds', null);
	}
	
	/**
	 * @param order_CartInfo $cart
	 * @return array
	 */
	protected function getCheckedModeIds($cart)
	{
		$modeIds = ($cart->hasProperties('__configurationCheckedIds')) ? $cart->getProperties('__configurationCheckedIds') : null;
		if (!is_array($modeIds)) { $modeIds = array(); }
		return $modeIds;
	}
	
	/**
	 * @param order_CartInfo $cart
	 * @param integer $modeId
	 * @param string $key
	 * @param mixed $value
	 */
	public function setConfiguration($cart, $modeId, $key, $value)
	{
		$modeConfig = $this->getConfigurations($cart, $modeId);
		$modeConfig[$key] = $value;
		$cart->setProperties($this->getModeKey($modeId), $modeConfig);
	}
	
	/**
	 * @param order_CartInfo $cart
	 * @param integer $modeId
	 * @param string $key
	 * @return mixed
	 */
	public function getConfiguration($cart, $modeId, $key)
	{
		$modeConfig = $this->getConfigurations($cart, $modeId);
		return (isset($modeConfig[$key])) ? $modeConfig[$key] : null;
	}
	
	/**
	 * @param order_CartInfo $cart
	 * @param string $modeId
	 * @return array
	 */
	protected function getConfigurations($cart, $modeId)
	{
		$modekey = $this->getModeKey($modeId);
		return ($cart->hasProperties($modekey)) ? $cart->getProperties($modekey) : array();
	}
	
	/**
	 * @param order_CartInfo $cart
	 * @param string $modeId
	 * @param array $modeConfig
	 */
	protected function setConfigurations($cart, $modeId, $modeConfig)
	{
		$modekey = $this->getModeKey($modeId);
		$cart->setProperties($modekey, count($modeConfig) ? $modeConfig : null);
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @param integer $modeId
	 * @param string $key
	 * @return mixed
	 */
	public function getConfigurationOnOrder($order, $modeId, $key)
	{
		$modeConfig = $this->getConfigurationsOnOrder($order, $modeId);
		return (isset($modeConfig[$key])) ? $modeConfig[$key] : null;
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @param string $modeId
	 * @return array
	 */
	protected function getConfigurationsOnOrder($order, $modeId)
	{
		$modekey = $this->getModeKey($modeId);
		$cartProperties = $order->getGlobalProperty(order_OrderService::PROPERTIES_CART_PROPERTIES);
		if (!is_array($cartProperties) || !isset($cartProperties[$modekey])) { return array(); }
		return $cartProperties[$modekey];
	}
	
	/**
	 * @param string $modeId
	 */
	protected function getModeKey($modeId)
	{
		return 'shipping-configuration-' . $modeId;
	}
	
	/**
	 * @param order_CartInfo $cart
	 * @param integer $modeId
	 */
	public function getCartLinesForMode($cart, $mode)
	{
		$lines = $cart->getCartLineArrayByShippingMode($mode);
		if ($cart->canSelectShippingModeId() && $cart->getShippingModeId() == $mode->getId())
		{
			$shippingArray = $cart->getShippingArray();
			foreach ($shippingArray[0]['lines'] as $lineNumber)
			{
				$lines[] = $cart->getCartLine($lineNumber);
			}
		}
		return $lines;
	}
}