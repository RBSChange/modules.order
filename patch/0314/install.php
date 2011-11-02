<?php
/**
 * order_patch_0314
 * @package modules.order
 */
class order_patch_0314 extends patch_BasePatch
{
	/**
	 * @return String
	 */
	protected final function getModuleName()
	{
		return 'order';
	}

	/**
	 * @return String
	 */
	protected final function getNumber()
	{
		return '0314';
	}
 
	/**
	 * Entry point of the patch execution.
	 */
	public function execute()
	{
		$scriptPath = 'modules/order/patch/0314/batchOrder.php';
		$documentId = 0;
		$chunkSize = 50;	
		while ($documentId >= 0)
		{		
			$output = f_util_System::execHTTPScript($scriptPath, array($documentId, $chunkSize));
			if (!is_numeric($output))
			{
				$chunkInfo = __METHOD__ . " Error on processsing chunck start at $documentId size $chunkSize. ($output)";
				$documentId = -1;
				Framework::error($chunkInfo);
			}
			else
			{
				$documentId = intval($output);
			}
		}
		CacheService::getInstance()->boShouldBeReloaded();
	}
}