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
			$batchPath = 'modules/order/lib/bin/backgroundCommentReminderChunk.php';
			$errors = array();	
			$chunkSize = Framework::getConfigurationValue('modules/order/backgroundCommentReminderChunkSize', 100);
			$lastId = 0;
			$errors = array();
			while ($lastId >= 0)
			{
				$this->plannedTask->ping();
				$result = f_util_System::execScript($batchPath, array($lastId, $chunkSize));	
				if (!$result)
				{
					$errors[] = 'No result';
					break;
				}
					
				$lines = array_reverse(explode(PHP_EOL, $result));
				$newId = $lastId;
				foreach ($lines as $line)
				{
					if (preg_match('/^id:([0-9-]+)$/', $line, $matches))
					{
						$newId = $matches[1];
						break;
					}
					else
					{
						$errors[] = $line;
					}
				}
				
				if ($newId != $lastId)
				{
					$lastId = $newId;
				}
				else
				{
					break;
				}
			}
			
			if (count($errors))
			{
				throw new Exception(implode(PHP_EOL, array_reverse($errors)));
			}			
		}
	}
}
