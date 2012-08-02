<?php
/**
 * order_patch_0357
 * @package modules.order
 */
class order_patch_0357 extends patch_BasePatch
{

 
	/**
	 * Entry point of the patch execution.
	 */
	public function execute()
	{
		$scriptPath = 'modules/order/patch/0357/batchOrder.php';
		$documentId = 0;
		$chunkSize = 50;	
		while ($documentId >= 0)
		{		
			$output = f_util_System::execScript($scriptPath, array($documentId, $chunkSize));
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