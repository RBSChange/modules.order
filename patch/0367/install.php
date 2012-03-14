<?php
/**
 * order_patch_0367
 * @package modules.order
 */
class order_patch_0367 extends patch_BasePatch
{
 
	/**
	 * Entry point of the patch execution.
	 */
	public function execute()
	{
		$this->execChangeCommand('compile-listeners');
		$this->execChangeCommand('update-autoload', array('modules/order'));
		$tpts = task_PlannedtaskService::getInstance();
		
		$tasks = $tpts->getBySystemtaskclassname('order_BackgroundPDFBillGenerator');
		
		if (count($tasks) === 0)
		{
			$task = task_PlannedtaskService::getInstance()->getNewDocumentInstance();
			$task->setSystemtaskclassname('order_BackgroundPDFBillGenerator');
			$task->setMinute(-1);
			$task->setLabel('order_BackgroundPDFBillGenerator');
			$task->save(ModuleService::getInstance()->getSystemFolderId('task', 'order'));
		}
		
		$tasks = $tpts->getBySystemtaskclassname('order_BackgroundCommentReminder');
		
		if (count($tasks) === 0)
		{
			$task = task_PlannedtaskService::getInstance()->getNewDocumentInstance();
			$task->setSystemtaskclassname('order_BackgroundCommentReminder');
			$task->setMinute(-1);
			$task->setLabel('order_BackgroundCommentReminder');
			$task->save(ModuleService::getInstance()->getSystemFolderId('task', 'order'));
		}
	}
}