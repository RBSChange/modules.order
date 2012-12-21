<?php
class order_GenerateNumbers extends task_SimpleSystemTask
{
	/**
	 * @see task_SimpleSystemTask::execute()
	 */
	protected function execute()
	{
		$errors = array();
		$this->generateForShortName('Order', $errors);
		$this->generateForShortName('Bill', $errors);
		$this->generateForShortName('Orderpreparation', $errors);
		$this->generateForShortName('Expedition', $errors);
		$this->generateForShortName('Creditnote', $errors);
		
		if (count($errors))
		{
			throw new Exception(implode(PHP_EOL, array_reverse($errors)));
		}
	}
	
	/**
	 * @param string $shortName
	 * @param string[] $errors
	 */
	protected function generateForShortName($shortName, $errors)
	{
		$batchPath = 'modules/order/lib/bin/generate' . $shortName . 'NumbersChunk.php';
		$chunkSize = Framework::getConfigurationValue('modules/order/generate' . $shortName . 'NumbersChunkSize', 100);
		$lastId = 0;
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
	}
}