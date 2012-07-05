<?php
$controller = change_Controller::newInstance("controller_ChangeController");
$tm = f_persistentdocument_TransactionManager::getInstance();
$rc = RequestContext::getInstance();
foreach ($_POST['argv'] as $id) 
{
	try
	{
		$tm->beginTransaction();
		$order = DocumentHelper::getDocumentInstance($id, 'modules_order/order');
		order_ModuleService::getInstance()->checkOrderProcessing($order);
		$tm->commit();
	}
	catch (Exception $e)
	{
		$tm->rollBack($e);
	}
}
echo 'OK';