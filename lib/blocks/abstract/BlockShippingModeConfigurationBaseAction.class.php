<?php
/**
 * order_BlockShippingModeConfigurationBaseAction
 * @package modules.order.lib.blocks
 */
abstract class order_BlockShippingModeConfigurationBaseAction extends order_BlockStdShippingStepAction
{
	/**
	 * @param f_mvc_Request $request
	 * @param order_CartInfo $cart
	 */
	protected function setRequestParams($request, $cart)
	{
		parent::setRequestParams($request, $cart);
		
		if ($request->getAttribute('hasPredefinedShippingMode'))
		{
			$mode = $request->getParameter('mode');		
			$lines = order_ShippingModeConfigurationService::getInstance()->getCartLinesForMode($cart, $mode);
			$request->setAttribute('lines', $lines);
		}
		else
		{
			$request->setAttribute('lines', null);
		}
	}
	
	/**
	 * @param order_CartInfo $cart
	 * @param integer $modeId
	 * @return boolean
	 */
	protected function isModeIdChecked($cart, $modeId)
	{
		return order_ShippingModeConfigurationService::getInstance()->isModeIdChecked($cart, $modeId);
	}
	
	/**
	 * @param order_CartInfo $cart
	 * @param integer $modeId
	 * @return boolean
	 */
	protected function addCheckedModeId($cart, $modeId)
	{
		order_ShippingModeConfigurationService::getInstance()->addCheckedModeId($cart, $modeId);
	}
	
	/**
	 * @param order_CartInfo $cart
	 * @param integer $modeId
	 * @param string $key
	 * @param mixed $value
	 */
	protected function setModeConfiguration($cart, $modeId, $key, $value)
	{
		order_ShippingModeConfigurationService::getInstance()->setConfiguration($cart, $modeId, $key, $value);
	}
	
	/**
	 * @param order_CartInfo $cart
	 * @param integer $modeId
	 * @param string $key
	 * @return mixed
	 */
	protected function getModeConfiguration($cart, $modeId, $key)
	{
		return order_ShippingModeConfigurationService::getInstance()->getConfiguration($cart, $modeId, $key);
	}
	
	/**
	 * @param f_mvc_Request $request
	 */
	protected function checkSubmissionForCurrentMode($request)
	{
		$modeId = $request->getParameter('modeId');
		$forModeId = $request->getParameter('forModeId');
		return ($modeId == $forModeId);
	}
}