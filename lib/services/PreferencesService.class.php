<?php
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
			self::$instance = new self();
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
		return $this->getPersistentProvider()->createQuery('modules_order/preferences');
	}

	/**
	 * @param order_persistentdocument_preferences $document
	 * @param Integer $parentNodeId Parent node ID where to save the document (optionnal => can be null !).
	 * @return void
	 */
	protected function preSave($document, $parentNodeId)
	{
		$document->setLabel('order');
	}
}