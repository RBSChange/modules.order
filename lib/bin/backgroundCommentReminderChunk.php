<?php
/* @var $arguments array */
$arguments = isset($arguments) ? $arguments : array();
echo "id:-1";
$lastId = $arguments[0];
$chunkSize = $arguments[1];
$tm = f_persistentdocument_TransactionManager::getInstance();
$oos = order_OrderService::getInstance();
$ids = $oos->getOrderIdsToRemind($lastId, $chunkSize);
foreach (array_chunk($ids, 10) as $chunk)
{
	try
	{
		$tm->beginTransaction();	
		foreach ($chunk as $id)
		{
			echo PHP_EOL, 'id:', $id;
			$oos->sendCommentReminder(order_persistentdocument_order::getInstanceById($id));
		}
		$tm->commit();
	}
	catch (Exception $e)
	{
		echo PHP_EOL, 'EXCEPTION: ', $e->getMessage();
		$tm->rollBack($e);
	}
}