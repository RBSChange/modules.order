<?php
/**
 * order_ExpeditionlineService
 * @package modules.order
 */
class order_ExpeditionlineService extends f_persistentdocument_DocumentService
{
	/**
	 * @var order_ExpeditionlineService
	 */
	private static $instance;

	/**
	 * @return order_ExpeditionlineService
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
	 * @return order_persistentdocument_expeditionline
	 */
	public function getNewDocumentInstance()
	{
		return $this->getNewDocumentInstanceByModelName('modules_order/expeditionline');
	}

	/**
	 * Create a query based on 'modules_order/expeditionline' model.
	 * Return document that are instance of modules_order/expeditionline,
	 * including potential children.
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createQuery()
	{
		return $this->pp->createQuery('modules_order/expeditionline');
	}
	
	/**
	 * Create a query based on 'modules_order/expeditionline' model.
	 * Only documents that are strictly instance of modules_order/expeditionline
	 * (not children) will be retrieved
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createStrictQuery()
	{
		return $this->pp->createQuery('modules_order/expeditionline', false);
	}
}