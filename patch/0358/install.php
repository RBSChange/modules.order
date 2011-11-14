<?php
/**
 * order_patch_0358
 * @package modules.order
 */
class order_patch_0358 extends patch_BasePatch
{
	/**
	 * Entry point of the patch execution.
	 */
	public function execute()
	{
		$this->log('Add property applicationPriority on cartmodifier document ...');
		$newPath = f_util_FileUtils::buildWebeditPath('modules/order/persistentdocument/cartmodifier.xml');
		$newModel = generator_PersistentModel::loadModelFromString(f_util_FileUtils::read($newPath), 'order', 'cartmodifier');
		$newProp = $newModel->getPropertyByName('applicationPriority');
		f_persistentdocument_PersistentProvider::getInstance()->addProperty('order', 'cartmodifier', $newProp);
		
		
		$this->log('Init default value of applicationPriority...');
		$query = "UPDATE `m_order_doc_cartmodifier` SET `applicationpriority` = 0 WHERE `applicationpriority` IS NULL";	
		$this->executeSQLQuery($query);
		
		$this->log('compile locales...');
		$this->execChangeCommand('compile-locales', array('order', 'marketing', 'customer'));
	}
}