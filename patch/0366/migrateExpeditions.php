<?php
$ids = $_POST['argv'];
Framework::info(__FILE__ . ' -> ' . implode(', ', $ids));
$tm = f_persistentdocument_TransactionManager::getInstance();
foreach ($ids as $id)
{
	echo $id, '.';
	try
	{
		$tm->beginTransaction();		
		$expedition = order_persistentdocument_expedition::getInstanceById($id);
		$order = $expedition->getOrder();
		foreach ($order->getLineArray() as $line) 
		{
			$expLine = order_ExpeditionlineService::getInstance()->getNewDocumentInstance();
			$expLine->setLabel($line->getLabel());
			$expLine->setQuantity($line->getQuantity());
			$expLine->setOrderlineid($line->getId());
			
			$expLine->setOrderId($order->getId());			
			$expLine->setPacketNumber($expedition->getPacketNumber());
			$expLine->setTrackingNumber($expedition->getTrackingNumber());
			$expLine->setShippingDate($expedition->getShippingDate());
			
			$expLine->save();
			$expedition->addLine($expLine);
		}
		$expedition->save();
		
		$script = "UPDATE `m_order_doc_expedition` SET `useorderlines` = 0  WHERE `document_id` = " .$id;
		$tm->getPersistentProvider()->executeSQLScript($script);
		
		$tm->commit();
	}
	catch (Exception $e)
	{
		$tm->rollBack($e);
	}
echo '.';
}
echo 'OK';