<?php
class order_patch_0302 extends patch_BasePatch
{

 
	/**
	 * Entry point of the patch execution.
	 */
	public function execute()
	{
		// backported in 2.0.5
		
		//$this->executeSQLQuery("ALTER TABLE `m_order_doc_order` ADD `bill` INT( 11 ) NULL");
		//exec("change compile-documents");
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
		return '0302';
	}

}