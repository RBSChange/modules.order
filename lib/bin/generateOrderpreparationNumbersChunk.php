<?php
echo "id:-1";
$lastId = $argv[0];
$chunkSize = $argv[1];
$tm = f_persistentdocument_TransactionManager::getInstance();
$oms = order_ModuleService::getInstance();
$query = order_OrderpreparationService::getInstance()->createQuery();
$query->add(Restrictions::eq('label', order_ModuleService::TEMPORARY_NUMBER));
foreach ($query->addOrder(Order::asc('id'))->setFirstResult($lastId)->setMaxResults($chunkSize)->find() as $doc)
{
	/* @var $doc order_persistentdocument_orderpreparation */
	try
	{
		$tm->beginTransaction();
		$doc->getDocumentService()->applyNumber($doc, true);
		$doc->save();
		$oms->sendDelayedNotifications($doc);
		$tm->commit();
	}
	catch (Exception $e)
	{
		echo PHP_EOL, 'EXCEPTION: ', $e->getMessage();
		$tm->rollBack($e);
	}
}