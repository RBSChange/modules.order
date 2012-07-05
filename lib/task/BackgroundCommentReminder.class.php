<?php
class order_BackgroundCommentReminder extends task_SimpleSystemTask
{
	/**
	 * @see task_SimpleSystemTask::execute()
	 *
	 */
	protected function execute()
	{
		if (order_ModuleService::getInstance()->areCommentRemindersEnabled())
		{
			order_OrderService::getInstance()->sendCommentReminders();
		}
	}
}
