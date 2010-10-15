<?php
/**
 * order_patch_0311
 * @package modules.order
 */
class order_patch_0311 extends patch_BasePatch
{
	/**
	 * Entry point of the patch execution.
	 */
	public function execute()
	{
		$this->executeModuleScript('init-lists-for-filters.xml', 'order');
		
		$newPath = f_util_FileUtils::buildWebeditPath('modules/order/persistentdocument/bill.xml');
		$newModel = generator_PersistentModel::loadModelFromString(f_util_FileUtils::read($newPath), 'order', 'bill');
		$newProp = $newModel->getPropertyByName('paidByCustomerId');
		f_persistentdocument_PersistentProvider::getInstance()->addProperty('order', 'bill', $newProp);
		
		$this->executeSQLQuery("UPDATE m_order_doc_bill SET paidbycustomerid = (SELECT customer from m_order_doc_order WHERE document_id = m_order_doc_bill.order) WHERE status = 'success'");
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
		return '0311';
	}
}