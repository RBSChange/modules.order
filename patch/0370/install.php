<?php
/**
 * order_patch_0370
 * @package modules.order
 */
class order_patch_0370 extends patch_BasePatch
{
	/**
	 * Entry point of the patch execution.
	 */
	public function execute()
	{
		// Import the generation task.
		$ts = task_PlannedtaskService::getInstance();
		if (!$ts->getBySystemtaskclassname('order_GenerateNumbers'))
		{
			$task = task_PlannedtaskService::getInstance()->getNewDocumentInstance();
			$task->setSystemtaskclassname('order_GenerateNumbers');
			$task->setLabel('m.order.bo.general.task-generate-numbers');
			$task->save(ModuleService::getInstance()->getSystemFolderId('task', 'order'));
		}
	}
}