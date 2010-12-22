<?php
/**
 * order_patch_0350
 * @package modules.order
 */
class order_patch_0350 extends patch_BasePatch
{
 
	/**
	 * Entry point of the patch execution.
	 */
	public function execute()
	{

		$this->log('update modules/order autoload...');
		$this->execChangeCommand('update-autoload', array('modules/order'));
		
		$this->log('compile documents...');
		$this->execChangeCommand('compile-documents');
		
		$this->log('add usecreditnote property on order document...');
		$newPath = f_util_FileUtils::buildWebeditPath('modules/order/persistentdocument/order.xml');
		$newModel = generator_PersistentModel::loadModelFromString(f_util_FileUtils::read($newPath), 'order', 'order');
		$newProp = $newModel->getPropertyByName('usecreditnote');
		f_persistentdocument_PersistentProvider::getInstance()->addProperty('order', 'order', $newProp);
		
		$this->log('generate database...');
		$this->execChangeCommand('generate-database');

		$this->log('compile document filters...');
		$this->execChangeCommand('compile-document-filters');
		
		$this->log('import lists...');
		$this->executeModuleScript('init-creditnote-lists.xml', 'order');
		
		$this->log('compile order locales...');
		$this->execChangeCommand('compile-locales', array('order'));
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
		return '0350';
	}
}