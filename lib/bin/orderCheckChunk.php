<?php
/* @var $arguments array */
$arguments = isset($arguments) ? $arguments : array();
$tm = f_persistentdocument_TransactionManager::getInstance();
$rc = RequestContext::getInstance();
foreach ($arguments as $id) 
{
	try
	{
		$tm->beginTransaction();
		$order = order_persistentdocument_order::getInstanceById($id);
		order_ModuleService::getInstance()->checkOrderProcessing($order);
		$tm->commit();
	}
	catch (Exception $e)
	{
		$tm->rollBack($e);
	}
}
echo 'OK';