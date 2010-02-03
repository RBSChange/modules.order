<?php
/**
 * @package modules.order.lib.blocks
 */
class order_BlockConfirmStepAction extends order_BlockAbstractProcessStepAction
{
	/**
	 * @see website_BlockAction::execute()
	 *
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return String
	 */
	function execute($request, $response)
	{		
		if ($this->isInBackoffice())
		{
			return website_BlockView::NONE;
		}
		if (!$this->checkCurrentCustomer())
		{
			$this->redirectToFirstStep();
		}
		$this->setCurrentStep('Confirm');
		$cartInfo = $this->getCurrentCart();
		$request->setAttribute('order', $cartInfo->getOrder());
		$request->setAttribute('shop', $cartInfo->getShop());
		return $this->getInputViewName();
	}
}