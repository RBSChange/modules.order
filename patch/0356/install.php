<?php
/**
 * order_patch_0356
 * @package modules.order
 */
class order_patch_0356 extends patch_BasePatch
{
	/**
	 * Entry point of the patch execution.
	 */
	public function execute()
	{
		$this->log('update-autoload...');
		$this->execChangeCommand('update-autoload', array('modules/order'));
		
		$this->log('compile locales, blocks, tags...');
		$this->execChangeCommand('compile-locales', array('order'));
		$this->execChangeCommand('compile-blocks');
		$this->execChangeCommand('compile-tags');
		
		$this->log('Pour ajouter les pages d\'exemple du nouveau process, executer :');
		$this->log('change.php import-data order standardProccess.xml');
	}
}