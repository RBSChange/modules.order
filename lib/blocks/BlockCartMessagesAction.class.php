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
		
		// Transient error messages.
		$errorMessages = $cart->getTransientErrorMessages();
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
		$cart->clearTransientErrorMessages();
		
		// Persistent error messages.
		foreach ($cart->getPersistentErrorMessages() as $msg)
		{
			$this->addError($msg);
		}
		
		// Success messages.
		foreach ($cart->getSuccessMessages() as $msg)
		{
			$this->addMessage($msg);
		}
		$cart->clearSuccessMessages();
		
		return website_BlockView::SUCCESS;
	}
}