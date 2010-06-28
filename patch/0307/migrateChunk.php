<?php
include_once dirname(__FILE__) . '/MigrateCommandService.class.php';

$sql = "SELECT * FROM `m_order_bak_order` WHERE document_id IN (" . implode(', ', $_POST['argv']) . ")";
$orders = f_persistentdocument_PersistentProvider::getInstance()->executeSQLSelect($sql)->fetchAll();
foreach ($orders as $orderRawInfo) 
{
	try 
	{
		echo "Migrate order : " . $orderRawInfo['document_id'] . "..\n";
		$order = order_MigrateCommandService::getInstance()->updateOrder($orderRawInfo);
		if ($order->isModified())
		{
			f_persistentdocument_PersistentProvider::getInstance()->updateDocument($order);
			echo "Save order : " . $order->getId() . "\n";
		}
	} 
	catch (Exception $e)
	{
		echo "Error on order " . $orderRawInfo['document_id'] . " : " . $e->getMessage() . "\n";
		Framework::exception($e);
	}
}