<?php
$arguments = $_POST['argv'];
if (count($arguments) != 2)
{
	Framework::error(__FILE__ . " invalid arguments " . var_export($arguments, true));
	echo 'ERROR';
}
else
{
	list($documentId, $chunkSize) = $arguments;	
	$orders = order_OrderService::getInstance()->createQuery()
		->setMaxResults($chunkSize)
		->add(Restrictions::gt('id', $documentId))
		->addOrder(Order::asc('id'))
		->find();
	$ts = TreeService::getInstance();
	$ts->setTreeNodeCache(false);
	foreach ($orders as $order) 
	{
		/* @var $order order_persistentdocument_order */
		$documentId = $order->getId();
		if ($order->getTreeId())
		{
			$tn = $ts->getInstanceByDocument($order);
			if ($tn !== null)
			{
				$ts->deleteNode($tn);
			}
			
		}
	}	
		
	if (count($orders) == $chunkSize)
	{
		echo $documentId;
	}
	else
	{
		echo -1;
	}
}