<?php
/**
 * order_Setup
 * @package modules.order
 */
class order_Setup extends object_InitDataSetup
{
	public function install()
	{
		$task = task_PlannedtaskService::getInstance()->getNewDocumentInstance();
		$task->setSystemtaskclassname('order_BackgroundOrderCheck');
		$task->setUniqueExecutiondate(date_Calendar::getInstance());
		$task->setLabel('order_BackgroundOrderCheck');
		$task->save(ModuleService::getInstance()->getSystemFolderId('task', 'order'));
		
		try
		{
			$scriptReader = import_ScriptReader::getInstance();
			$scriptReader->executeModuleScript('order', 'init.xml');
			$scriptReader->executeModuleScript('order', 'init-lists-for-filters.xml');
			$scriptReader->executeModuleScript('order', 'init-comment.xml');
		}
		catch (Exception $e)
		{
			echo "ERROR: " . $e->getMessage() . "\n";
			Framework::exception($e);
		}
	}
	
	private $resourceDir = null;
	
	/**
	 * @param String $relativePath
	 * @return String absolute path
	 */
	protected final function getResourcePath($relativePath)
	{
		if (is_null($this->resourceDir))
		{
			$class = new ReflectionClass($this);
			$base = realpath(dirname($class->getFileName()));
			while (!is_dir(realpath($base . DIRECTORY_SEPARATOR . 'resources')))
			{
				$base = realpath(dirname($base));
				if ($base == '/')
				{
					throw new Exception("Could not find resources folder");
				}
			}
			$this->resourceDir = $base . DIRECTORY_SEPARATOR . 'resources';
		}
		return realpath($this->resourceDir . DIRECTORY_SEPARATOR . $relativePath);
	}
}
