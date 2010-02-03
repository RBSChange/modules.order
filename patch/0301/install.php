<?php
/**
 * order_patch_0301
 * @package modules.order
 */
class order_patch_0301 extends patch_BasePatch
{
	/**
	 * Entry point of the patch execution.
	 */
	public function execute()
	{
		parent::execute();

		// Add new fields in prefrences.
		$this->executeSQLQuery("ALTER TABLE `m_order_doc_preferences` ADD `enablecommentreminder` tinyint(1) NOT NULL default '0'");
		$this->executeSQLQuery("ALTER TABLE `m_order_doc_preferences` ADD `commentreminderperiod` int(11)");
		$this->executeSQLQuery("ALTER TABLE `m_order_doc_preferences` ADD `commentreminderreference` varchar(255)");
		$this->executeSQLQuery("ALTER TABLE `m_order_doc_preferences` ADD `commentreminderselection` varchar(255)");
		$this->executeSQLQuery("ALTER TABLE `m_order_doc_preferences` ADD `commentremindercount` int(11)");

		// Add new fields in orders.
		$this->executeSQLQuery("ALTER TABLE `m_order_doc_order` ADD `paymentdate` datetime");
		$this->executeSQLQuery("ALTER TABLE `m_order_doc_order` ADD `shipmentdate` datetime");
		$this->executeSQLQuery("ALTER TABLE `m_order_doc_order` ADD `lastcommentreminder` datetime");
		$this->executeSQLQuery("ALTER TABLE `m_order_doc_order` ADD `couponid` int(11)");
		$this->executeSQLQuery("ALTER TABLE `m_order_doc_order` ADD `couponvaluewithtax` float");

		// Add lists.
		ChangeProject::getInstance()->executeTask('import-data', array('order', 'init-comment.xml'));

		// Init fields in prefrences.
		$prefs = ModuleService::getPreferencesDocument('order');
		if ($prefs !== null)
		{
			$prefs->setEnableCommentReminder(true);
			$prefs->setCommentReminderPeriod(7);
			$prefs->setCommentReminderReference('shipment');
			$prefs->setCommentReminderSelection('random');
			$prefs->setCommentReminderCount(3);
			$prefs->save();
		}
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
		return '0301';
	}
}