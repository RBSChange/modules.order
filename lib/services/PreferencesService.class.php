<?php
/**
 * @package modules.order
 * @method order_PreferencesService getInstance()
 */
class order_PreferencesService extends f_persistentdocument_DocumentService
{
	/**
	 * @return order_persistentdocument_preferences
	 */
	public function getNewDocumentInstance()
	{
		return $this->getNewDocumentInstanceByModelName('modules_order/preferences');
	}

	/**
	 * Create a query based on 'modules_order/preferences' model
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createQuery()
	{
		return $this->getPersistentProvider()->createQuery('modules_order/preferences');
	}
}