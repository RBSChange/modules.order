<?php
/**
 * order_patch_0307
 * @package modules.order
 */
class order_patch_0307 extends patch_BasePatch
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
		$this->dropPatchedTables();
		$this->createPatchedTables();
		
		$this->backupOrders();
		
		$ids = $this->getOrderIds();
		$count = count($ids);
		$this->log("Orders to migrate: $count");
		$scriptPath = 'modules/order/patch/0307/migrateChunk.php';
		foreach (array_chunk($ids, 5) as $chunk)
		{
			$this->log(f_util_System::execHTTPScript($scriptPath, $chunk));
		}
		
		
		$this->fixOrderColumns();
		
		$this->updateNotifCodeName();
		
		$this->initNewDocument();
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
		return '0307';
	}
	
	private function dropPatchedTables()
	{
		$deleted = $this->getPersistentProvider()->createQuery('modules_order/expedition')->delete();
		$this->log("Removed expedition: $deleted");
		
		$deleted = $this->getPersistentProvider()->createQuery('modules_order/bill')->delete();
		$this->log("Removed bill: $deleted");
		$this->executeSQLQuery("DROP TABLE `m_order_doc_expedition`, `m_order_doc_expeditionline`, `m_order_doc_bill`");
	}
	
	private function createPatchedTables()
	{
		$this->log("Generate new tables...");
		$sqlPath = f_util_FileUtils::buildChangeBuildPath('modules', 'order', 'dataobject');
		foreach (f_util_FileUtils::getDirFiles($sqlPath) as $script)
		{
			if (f_util_StringUtils::endsWith($script, '.mysql.sql'))
			{
				$sql = file_get_contents($script);
				try
				{
					$this->executeSQLQuery($sql);
				}
				catch (BaseException $e)
				{
					if ($e->getAttribute('sqlstate') != '42S21' || $e->getAttribute('errorcode') != '1060')
					{
						throw $e;
					}
				}
			}
		}
		
		try
		{
			$sql = "ALTER TABLE `m_order_doc_order` ADD `document_s18s` mediumtext";
			$this->executeSQLQuery($sql);
		}
		catch (BaseException $e)
		{
			if ($e->getAttribute('sqlstate') != '42S21' || $e->getAttribute('errorcode') != '1060')
			{
				throw $e;
			}
		}
	}
	
	private function backupOrders()
	{
		$this->log("Backup order table...");
		$sql = "DROP TABLE IF EXISTS `m_order_bak_order`";
		$this->executeSQLQuery($sql);
		
		$sql = "CREATE TABLE `m_order_bak_order` SELECT * FROM `m_order_doc_order`";
		$this->executeSQLQuery($sql);
	}
	
	/**
	 * @return integer[]
	 */
	private function getOrderIds()
	{
		return $this->getPersistentProvider()->createQuery('modules_order/order')->setProjection(Projections::property('id', 'id'))->findColumn('id');
	}
	
	private function fixOrderColumns()
	{
		$this->log("Delete columns ...	: paymentdate bill billingproperties shipmentDate shippingproperties");
		$sql = "ALTER TABLE `m_order_doc_order` DROP COLUMN `paymentdate`, DROP COLUMN `bill`, DROP COLUMN `billingproperties`, DROP COLUMN `shipmentDate`, DROP COLUMN `shippingproperties`";
		$this->executeSQLQuery($sql);
	}
	
	private function updateNotifCodeName()
	{
		$this->log("Update notification codename...");
		$sql = "UPDATE m_notification_doc_notification SET codename='modules_order/bill_admin_success' WHERE codename ='modules_order/orderconfirmed'";
		$this->executeSQLQuery($sql);
		
		$sql = "UPDATE m_notification_doc_notification SET codename='modules_order/bill_failed' WHERE codename ='modules_order/status_PAYMENT_FAILED'";
		$this->executeSQLQuery($sql);	

		$sql = "UPDATE m_notification_doc_notification SET codename='modules_order/bill_success' WHERE codename ='modules_order/status_PAYMENT_SUCCESS'";
		$this->executeSQLQuery($sql);

		$sql = "UPDATE m_notification_doc_notification SET codename='modules_order/bill_waiting' WHERE codename ='modules_order/status_PAYMENT_WAITING'";
		$this->executeSQLQuery($sql);
		
		$sql = "UPDATE m_notification_doc_notification SET codename='modules_order/order_canceled' WHERE codename ='modules_order/status_CANCELED'";
		$this->executeSQLQuery($sql);
		
		$sql = "UPDATE m_notification_doc_notification SET codename='modules_order/order_complete' WHERE codename ='modules_order/status_SHIPPED'";
		$this->executeSQLQuery($sql);
	}
	
	private function initNewDocument()
	{
		$this->log("Mise à jour list...");
		$this->executeLocalXmlScript('pacthlist.xml');
		
		$this->log("Tache plannifier creation expedition...");
		$task = task_PlannedtaskService::getInstance()->getNewDocumentInstance();
		$task->setSystemtaskclassname('order_BackgroundOrderCheck');
		$task->setUniqueExecutiondate(date_Calendar::getInstance());
		$task->setLabel('order_BackgroundOrderCheck');
		$task->save(ModuleService::getInstance()->getSystemFolderId('task', 'order'));
	}
}