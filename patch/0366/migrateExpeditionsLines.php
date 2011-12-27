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
		foreach ($expedition->getLineArray() as $expLine) 
		{			
			/* @var $expLine order_persistentdocument_expeditionline */
			$expLine->setOrderId($order->getId());			
			$expLine->setPacketNumber($expedition->getPacketNumber());
			$expLine->setTrackingNumber($expedition->getTrackingNumber());
			$expLine->setShippingDate($expedition->getShippingDate());	
			$expLine->save();
		}
		$tm->commit();
	}
	catch (Exception $e)
	{
		$tm->rollBack($e);
	}
echo '.';
}
echo 'OK';