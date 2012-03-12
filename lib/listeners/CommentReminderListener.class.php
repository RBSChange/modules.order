<?php
/**
 * @package modules.order.lib.listeners
 */
class order_CommentRemiderListener
{
	public function onHourChange($sender, $params)
	{
		if (order_ModuleService::getInstance()->areCommentRemindersEnabled())
		{
			Framework::debug(__METHOD__ . ' : comment reminder enabled');
			order_OrderService::getInstance()->sendCommentReminders();
		}
	}
}