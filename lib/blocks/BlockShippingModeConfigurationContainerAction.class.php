<?php
/**
 * order_BlockShippingModeConfigurationContainerAction
 * @package modules.order.lib.blocks
 */
class order_BlockShippingModeConfigurationContainerAction extends website_BlockAction
{
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return string
	 */
	public function execute($request, $response)
	{
		if ($this->isInBackofficeEdition())
		{
			return website_BlockView::NONE;
		}

		/* @var $modeService shipping_ModeService */
		$mode = $request->getAttribute('mode');
		$cart = order_CartService::getInstance()->getDocumentInstanceFromSession();
		$modeService = $mode->getDocumentService();
		list($cfgModule, $cfgBlock) = $modeService->getConfigurationBlockForCart($mode, $cart);
		$request->setAttribute('configurationBlockModule', $cfgModule);
		$request->setAttribute('configurationBlockName', $cfgBlock);
		
		return website_BlockView::SUCCESS;
	}
}