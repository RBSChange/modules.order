<?php
/**
 * order_SendMessageForOrderAction
 * @package modules.order.actions
 */
class order_SendMessageForOrderAction extends change_JSONAction
{
	/**
	 * @param change_Context $context
	 * @param change_Request $request
	 */
	public function _execute($context, $request)
	{
		$order = $this->getDocumentInstanceFromRequest($request);
		if ($request->hasParameter('content'))
		{
			$content = trim($request->getParameter('content'));
			if ($content != '')
			{
				$sender = users_UserService::getInstance()->getCurrentBackEndUser();
				if ($order->getDocumentService()->sendMessageToCustomer($order, $content, $sender))
				{
					return $this->sendJSON(order_MessageService::getInstance()->getInfosByOrder($order, true));
				}
			}
		}
		return $this->sendJSONError(LocaleService::getInstance()->trans('m.order.bo.doceditor.panel.messages.error-no-message-to-send', array('ucf'), true));
	}
}