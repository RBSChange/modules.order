<?php
/**
 * order_patch_0353
 * @package modules.order
 */
class order_patch_0353 extends patch_BasePatch
{
//  by default, isCodePatch() returns false.
//  decomment the following if your patch modify code instead of the database structure or content.
    /**
     * Returns true if the patch modify code that is versionned.
     * If your patch modify code that is versionned AND database structure or content,
     * you must split it into two different patches.
     * @return Boolean true if the patch modify code that is versionned.
     */
//	public function isCodePatch()
//	{
//		return true;
//	}
 
	/**
	 * Entry point of the patch execution.
	 */
	public function execute()
	{
		$this->log('Update order orderstatus NULL -> initiated...');
		$sql = "UPDATE `m_order_doc_order` SET `orderstatus` = 'initiated' WHERE `orderstatus` IS NULL";
		$this->executeSQLQuery($sql);
		
		$this->log('Update bill status NULL -> initiated...');
		$sql = "UPDATE `m_order_doc_bill` SET `status` = 'initiated' WHERE `status` IS NULL";
		$this->executeSQLQuery($sql);
		
		$this->log('Update creditnote publicationstatus DRAFT -> PUBLICATED...');
		$sql = "UPDATE `m_order_doc_creditnote` SET `document_publicationstatus` = 'PUBLICATED' WHERE `document_publicationstatus` = 'DRAFT'";
		$this->executeSQLQuery($sql);
		
		$this->log('Compile locales order...');
		$this->execChangeCommand('compile-locales', array('order'));
	
		$this->log('Update list modules_order/orderstatuses, modules_order/billstatuses...');
		$this->executeLocalXmlScript('init-list.xml');
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
		return '0353';
	}
}