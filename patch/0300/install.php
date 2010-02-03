<?php
/**
 * @author intportg
 * @package modules.order
 */
class order_patch_0300 extends patch_BasePatch
{
	/**
	 * Entry point of the patch execution.
	 */
	public function execute()
	{
		parent::execute();
		
		// Drop unused column.
		$this->executeSQLQuery("ALTER TABLE `m_order_doc_coupon` DROP COLUMN `numberofmailing`");
	}

	/**
	 * Returns the name of the module the patch belongs to.
	 *
	 * @return String
	 */
	protected final function getModuleName()
	{
		return 'order';
	}

	/**
	 * Returns the number of the current patch.
	 * @return String
	 */
	protected final function getNumber()
	{
		return '0300';
	}
}