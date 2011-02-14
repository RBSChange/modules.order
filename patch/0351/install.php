<?php
/**
 * order_patch_0351
 * @package modules.order
 */
class order_patch_0351 extends patch_BasePatch
{
	/**
	 * Entry point of the patch execution.
	 */
	public function execute()
	{
		$this->executeSQLQuery('DROP TABLE IF EXISTS m_order_doc_cartmodifier;');
		$this->executeSQLQuery('RENAME TABLE m_marketing_doc_discount TO m_order_doc_cartmodifier;');
		
		$archivePath = f_util_FileUtils::buildWebeditPath('modules/order/patch/0351/cartmodifier-1.xml');
		$newPath = f_util_FileUtils::buildWebeditPath('modules/order/persistentdocument/cartmodifier.xml');
		$oldModel = generator_PersistentModel::loadModelFromString(f_util_FileUtils::read($archivePath), 'order', 'cartmodifier');
		$oldProp = $oldModel->getPropertyByName('excludeDiscount');
		$newModel = generator_PersistentModel::loadModelFromString(f_util_FileUtils::read($newPath), 'order', 'cartmodifier');
		$newProp = $newModel->getPropertyByName('excludeModifier');
		f_persistentdocument_PersistentProvider::getInstance()->renameProperty('order', 'cartmodifier', $oldProp, $newProp);
		$this->execChangeCommand('compile-db-schema');
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
		return '0351';
	}
}