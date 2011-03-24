<?php
/**
 * order_patch_0355
 * @package modules.order
 */
class order_patch_0355 extends patch_BasePatch
{
	/**
	 * Entry point of the patch execution.
	 */
	public function execute()
	{
		$this->executeLocalXmlScript('init.xml');
	}
}