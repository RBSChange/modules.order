<?php
/**
 * @package modules.customer
 */
class order_BlockMessagesAction extends website_BlockAction
{
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return string
	 */
	function execute($request, $response)
	{
		/* @var $order order_persistentdocument_order */
		$order = $this->getDocumentParameter();
		if ($order === null)
		{
			return website_BlockView::NONE;
		}
		
		if ($request->hasParameter('comment'))
		{
			$content = $request->getParameter('comment');
			if ($content != '')
			{
				$sender = users_UserService::getInstance()->getCurrentFrontEndUser();
				if ($order->getDocumentService()->sendMessageFromCustomer($order, $content, $sender))
				{
					$this->addMessage(LocaleService::getInstance()->trans('m.order.frontoffice.success-sending-message', array('ucf')));
					$request->setAttribute('comment', '');
				}
				else
				{
					$this->addError(LocaleService::getInstance()->trans('m.order.frontoffice.error-sending-message', array('ucf')));
					$request->setAttribute('comment', $content);
				}
			}
		}
		
		$request->setAttribute('order', $order);
		$request->setAttribute('messages', order_MessageService::getInstance()->getByOrder($order));
		return website_BlockView::SUCCESS;
	}
}