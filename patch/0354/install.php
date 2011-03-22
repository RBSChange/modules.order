<?php
/**
 * order_patch_0354
 * @package modules.order
 */
class order_patch_0354 extends patch_BasePatch
{
	/**
	 * Entry point of the patch execution.
	 */
	public function execute()
	{
		$this->execChangeCommand('update-autoload', array('modules/order'));
		$this->execChangeCommand('compile-locales', array('order'));
	}
}