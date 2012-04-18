<?php
/**
 * order_patch_0368
 * @package modules.order
 */
class order_patch_0368 extends patch_BasePatch
{ 
	/**
	 * Entry point of the patch execution.
	 */
	public function execute()
	{
		$newPath = f_util_FileUtils::buildWebeditPath('modules/order/persistentdocument/cartmodifier.xml');
		$newModel = generator_PersistentModel::loadModelFromString(f_util_FileUtils::read($newPath), 'order', 'cartmodifier');
		$newProp = $newModel->getPropertyByName('billingArea');
		f_persistentdocument_PersistentProvider::getInstance()->addProperty('order', 'cartmodifier', $newProp);
		$this->execChangeCommand('compile-db-schema');
		
		
		$array = order_CartmodifierService::getInstance()->createQuery()->find();
		foreach ($array as $cm)
		{
			/* @var $cm order_persistentdocument_cartmodifier */
			if ($cm->getBillingArea() === null)
			{
				if ($cm->getShop() === null)
				{
					$this->logWarning('Invalid Cartmodifier ' . $cm->getDocumentModelName() . ' ' . $cm->getId() . ' - ' . $cm->getLabel());
				}
				else
				{
					$cm->setBillingArea($cm->getShop()->getDefaultBillingArea());
					$cm->save();
				}
			}
		}
	}
}