<?php
/**
 * order_patch_0357
 * @package modules.order
 */
class order_patch_0357 extends patch_BasePatch
{
//  by default, isCodePatch() returns false.
//  decomment the following if your patch modify code instead of the database structure or content.
    /**
     * Returns true if the patch modify code that is versionned.
     * If your patch modify code that is versionned AND database structure or content,
     * you must split it into two different patches.
     * @return Boolean true if the patch modify code that is versionned.
     */
//	public function isCodePatch()
//	{
//		return true;
//	}
 
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