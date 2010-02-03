<?php
/**
 * @package modules.customer
 */
class order_BlockMessagesAction extends website_BlockAction
{
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return String
	 */
	function execute($request, $response)
	{
		$order = $this->getDocumentParameter();
		if ($order === null)
		{
			return website_BlockView::NONE;
		}
		else
		{
			if ($request->hasParameter('comment'))
			{
				$content = $request->getParameter('comment');
				if ($content != '')
				{
					$sender = users_UserService::getInstance()->getCurrentFrontEndUser();
					if ($order->getDocumentService()->sendMessageFromCustomer($order, $content, $sender))
					{
						$this->addMessage(f_Locale::translate('&modules.order.frontoffice.Success-sending-message;'));
						$request->setAttribute('comment', '');
					}
					else
					{
						$this->addError(f_Locale::translate('&modules.order.frontoffice.Error-sending-message;'));
						$request->setAttribute('comment', $content);
					}
				}
			}
			
			$request->setAttribute('companyName', Framework::getCompanyName());
			$request->setAttribute('order', $order);
			$request->setAttribute('messages', order_MessageService::getInstance()->getByOrder($order));
			return website_BlockView::SUCCESS;
		}
	}
}