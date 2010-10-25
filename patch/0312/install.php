<?php
/**
 * order_patch_0312
 * @package modules.order
 */
class order_patch_0312 extends patch_BasePatch
{
	/**
	 * Entry point of the patch execution.
	 */
	public function execute()
	{
		$newPath = f_util_FileUtils::buildWebeditPath('modules/order/persistentdocument/order.xml');
		$newModel = generator_PersistentModel::loadModelFromString(f_util_FileUtils::read($newPath), 'order', 'order');
		$newProp = $newModel->getPropertyByName('needsAnswer');
		f_persistentdocument_PersistentProvider::getInstance()->addProperty('order', 'order', $newProp);
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
		return '0312';
	}
}