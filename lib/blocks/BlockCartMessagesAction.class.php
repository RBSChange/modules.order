<?php
/**
 * order_BlockCartMessagesAction
 * @package modules.order.lib.blocks
 */
class order_BlockCartMessagesAction extends website_BlockAction
{
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return String
	 */
	public function execute($request, $response)
	{
		if ($this->isInBackoffice())
		{
			return website_BlockView::NONE;
		}
	
		$cs = order_CartService::getInstance();
		$cart = $cs->getDocumentInstanceFromSession();
		
		// Errors.
		$errorMessages = $cart->getErrorMessageArray();
		if (count($errorMessages) > 0)
		{
			foreach ($errorMessages as $msg)
			{
				$this->addError($msg);
			}
			if ($request->getParameter('handleCantOrderMessage') == 'true')
			{
				$request->setAttribute('showCantOrderMessage', true);
			}
		}
		
		// Warnings.
		foreach ($cart->getWarningMessageArray() as $msg)
		{
			$this->addError($msg);
		}
		$cart->clearErrorMessages();
		
		// Successes.
		foreach ($cart->getSuccessMessageArray() as $msg)
		{
			$this->addMessage($msg);
		}
		$cart->clearSuccessMessages();
		
		return website_BlockView::SUCCESS;
	}
}