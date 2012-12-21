<?php
/**
 * order_patch_0371
 * @package modules.order
 */
class order_patch_0371 extends patch_BasePatch
{
	/**
	 * Entry point of the patch execution.
	 */
	public function execute()
	{
		// Add the configuration to deactivate delayed generation.
		$this->addProjectConfigurationEntry('modules/order/delayNumberGeneration', 'false');
	}
}