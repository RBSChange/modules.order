<?php
/**
 * @date Fri, 07 Dec 2007 14:03:30 +0100
 * @author intbonjf
 */
class order_PreferencesService extends f_persistentdocument_DocumentService
{
	/**
	 * @var order_PreferencesService
	 */
	private static $instance;

	/**
	 * @return order_PreferencesService
	 */
	public static function getInstance()
	{
		if (self::$instance === null)
		{
			self::$instance = self::getServiceClassInstance(get_class());
		}
		return self::$instance;
	}

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
		return $this->pp->createQuery('modules_order/preferences');
	}

	/**
	 * @param order_persistentdocument_preferences $document
	 * @param Integer $parentNodeId Parent node ID where to save the document (optionnal => can be null !).
	 * @return void
	 */
	protected function preSave($document, $parentNodeId)
	{
		$document->setLabel('&modules.order.bo.general.Module-name;');
	}
}