<?php
/**
 * order_patch_0366
 * @package modules.order
 */
class order_patch_0366 extends patch_BasePatch
{

	/**
	 * Entry point of the patch execution.
	 */
	public function execute()
	{
		//+orderpreparation.xml
		if (!is_readable(f_util_FileUtils::buildChangeBuildPath('modules', 'order', 'dataobject', 'm_order_doc_orderpreparation.mysql.sql')))
		{
			$this->log('Compile documents...');
			$this->execChangeCommand("compile-documents");
		
			$this->log('Generate database...');
			$this->execChangeCommand("generate-database");
		
		}
		
		//expedition.xml
		// +<add name="packetNumber" type="String" />
		
		$newPath = f_util_FileUtils::buildWebeditPath('modules/order/persistentdocument/expedition.xml');
		$newModel = generator_PersistentModel::loadModelFromString(f_util_FileUtils::read($newPath), 'order', 'expedition');
		
		$this->log('Add expedition/packetNumber...');
		$newProp = $newModel->getPropertyByName('packetNumber');
		$this->getPersistentProvider()->addProperty('order', 'expedition', $newProp);
		
		
		//expeditionline.xml
		// +<add name="orderId" type="Integer"></add>
		// +<add name="packetNumber" type="String"></add>
		// +<add name="trackingNumber" type="String" />
		// +<add name="shippingDate" type="DateTime" />
		// +<add name="status" type="String" />
		
		$newPath = f_util_FileUtils::buildWebeditPath('modules/order/persistentdocument/expeditionline.xml');
		$newModel = generator_PersistentModel::loadModelFromString(f_util_FileUtils::read($newPath), 'order', 'expeditionline');
		
		$this->log('Add expeditionline/orderId...');
		$newProp = $newModel->getPropertyByName('orderId');
		$this->getPersistentProvider()->addProperty('order', 'expeditionline', $newProp);
		
		$this->log('Add expeditionline/packetNumber...');
		$newProp = $newModel->getPropertyByName('packetNumber');
		$this->getPersistentProvider()->addProperty('order', 'expeditionline', $newProp);
		
		$this->log('Add expeditionline/trackingNumber...');
		$newProp = $newModel->getPropertyByName('trackingNumber');
		$this->getPersistentProvider()->addProperty('order', 'expeditionline', $newProp);
		
		$this->log('Add expeditionline/shippingDate...');
		$newProp = $newModel->getPropertyByName('shippingDate');
		$this->getPersistentProvider()->addProperty('order', 'expeditionline', $newProp);

		$this->log('Add expeditionline/status...');
		$newProp = $newModel->getPropertyByName('status');
		$this->getPersistentProvider()->addProperty('order', 'expeditionline', $newProp);

		//expedition.xml
		// -<add name="useOrderlines" type="Boolean" default-value="true" />
		$query = "SELECT `document_id` FROM `m_order_doc_expedition` WHERE `useorderlines` = 1";
		$stmt = $this->executeSQLSelect($query);
		$ids = $stmt->fetchAll(PDO::FETCH_COLUMN);		
		foreach (array_chunk($ids, 10) as $index =>  $chunk)
		{
			$this->log('Migrate Expeditions chunk: ' . $index);
			$batch = 'modules/order/patch/0366/migrateExpeditions.php';
			$result = f_util_System::execHTTPScript($batch, $chunk);
			$this->log($result);	
		}
		
		$query = "SELECT DISTINCT e.`document_id` FROM `m_order_doc_expedition` AS e INNER JOIN `f_relation` ON `relation_id1` = e.`document_id`
 INNER JOIN `m_order_doc_expeditionline` AS l ON `relation_id2` = l.`document_id` WHERE `orderid` IS NULL LIMIT 0 , 10";	
			
		while (true) 
		{
			$stmt = $this->executeSQLSelect($query);
			$chunk = $stmt->fetchAll(PDO::FETCH_COLUMN);
			if (count($chunk))
			{
				$batch = 'modules/order/patch/0366/migrateExpeditionsLines.php';
				$result = f_util_System::execHTTPScript($batch, $chunk);
				$this->log($result);
			}
			if (count($chunk) != 10) {break;}				
		}	
	}
}