<?php
/**
 * order_patch_0352
 * @package modules.order
 */
class order_patch_0352 extends patch_BasePatch
{
 
	/**
	 * Entry point of the patch execution.
	 */
	public function execute()
	{
		$this->log('compile-documents ...');
		$this->execChangeCommand('compile-documents');
		$shippingFilterModel = f_persistentdocument_PersistentDocumentModel::getInstance('catalog', 'shippingfilter');
		if (!$shippingFilterModel->hasProperty('feesId'))
		{
			$this->logError('Update catalog module before apply this patch');
			die();
		}
		$this->log('update-autoload modules/order ...');
		$this->execChangeCommand('update-autoload', array('modules/order'));
		
		$this->log('compile-locales ...');
		$this->execChangeCommand('compile-locales', array('order'));

		$this->log('add feesId porperty in shippingfilter ...');
		$newPath = f_util_FileUtils::buildWebeditPath('modules/catalog/persistentdocument/shippingfilter.xml');
		$newModel = generator_PersistentModel::loadModelFromString(f_util_FileUtils::read($newPath), 'catalog', 'shippingfilter');
		$newProp = $newModel->getPropertyByName('feesId');
		f_persistentdocument_PersistentProvider::getInstance()->addProperty('catalog', 'shippingfilter', $newProp);
		
		foreach (catalog_ShippingfilterService::getInstance()->createQuery()->find() as $shippingfilter) 
		{
			$fees = order_FeesService::getInstance()->generateDefaultForShippingFilter($shippingfilter);
			$this->log('Add Fees : ' . $fees->getLabel() . "(". $fees->getId().")");
		}
		
		$this->log('add BO fees editor ...');
		$mbs = uixul_ModuleBindingService::getInstance();
		$mbs->addImportInPerspective('catalog', 'order', 'catalog.perspective');
		$mbs->addImportInActions('catalog', 'order', 'catalog.actions');
		$result = $mbs->addImportform('catalog', 'modules_order/fees');
		if ($result['action'] == 'create')
		{
			uixul_DocumentEditorService::getInstance()->compileEditorsConfig();
		}
		f_permission_PermissionService::getInstance()->addImportInRight('catalog', 'order', 'catalog.rights');
		
		$this->executeModuleScript('listfeesstrategy.xml', 'order');
		
		$this->executeModuleScript('useractionlogger.xml', 'order');
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
		return '0352';
	}
}