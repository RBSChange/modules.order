<?php
class order_BackgroundOrderCheck extends task_SimpleSystemTask  
{
	/**
	 * @see task_SimpleSystemTask::execute()
	 *
	 */
	protected function execute()
	{		
		$documentsArray = array_chunk($this->getDocumentIdsToProcess(), 10);
		f_persistentdocument_PersistentProvider::getInstance()->closeConnection();
        f_persistentdocument_PersistentProvider::clearInstance();
		$script = 'modules/order/lib/task/orderCheckChunk.php';
		foreach ($documentsArray as $chunk)
		{
			$result = f_util_System::execHTTPScript($script, $chunk);
			// Log fatal errors...
			if ($result != 'OK')
			{
				Framework::error(__METHOD__ . ' ' . $script . ' unexpected result: "' . $result . '"');
			}
		}
		
		$this->plannedTask->reSchedule(date_Calendar::getInstance()->add(date_Calendar::MINUTE, +30));
	}
	
	/**
	 * @return integer[]
	 */
	private function getDocumentIdsToProcess()
	{
		return order_OrderService::getInstance()
			->createQuery()
			->add(Restrictions::notin('orderStatus', array(order_OrderService::COMPLETE, order_OrderService::CANCELED)))
			->add(Order::desc('id'))
				->setProjection(Projections::property('id', 'id'))->findColumn('id');
	}
}
