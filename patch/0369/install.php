<?php
/**
 * order_patch_0369
 * @package modules.order
 */
class order_patch_0369 extends patch_BasePatch
{
 
	/**
	 * Entry point of the patch execution.
	 */
	public function execute()
	{
		$this->executeLocalXmlScript('listfeesstrategy.xml');
	}
}