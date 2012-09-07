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
		
		$newPath = f_util_FileUtils::buildWebeditPath('modules/order/persistentdocument/order.xml');
		$newModel = generator_PersistentModel::loadModelFromString(f_util_FileUtils::read($newPath), 'order', 'order');
		$newProp = $newModel->getPropertyByName('contextId');
		f_persistentdocument_PersistentProvider::getInstance()->addProperty('order', 'order', $newProp);
		
	}
}