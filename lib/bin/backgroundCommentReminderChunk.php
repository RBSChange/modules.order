<?php
echo "id:-1";
$lastId = $argv[0];
$chunkSize = $argv[1];
$tm = f_persistentdocument_TransactionManager::getInstance();
try
{
	$tm->beginTransaction();
	$oos = order_OrderService::getInstance();
	$ids = $oos->getOrderIdsToRemind($lastId, $chunkSize);
	foreach ($ids as $id)
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