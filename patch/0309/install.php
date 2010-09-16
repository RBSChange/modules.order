<?php
/**
 * order_patch_0309
 * @package modules.order
 */
class order_patch_0309 extends patch_BasePatch
{
	/**
	 * Entry point of the patch execution.
	 */
	public function execute()
	{
		$this->executeModuleScript('init-expeditions.xml', 'order');
	}

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
		return '0309';
	}
}