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
			->add(Restrictions::orExp(Restrictions::isNull('orderStatus'), Restrictions::notin('orderStatus', array(order_OrderService::COMPLETE, order_OrderService::CANCELED))))
			->setProjection(Projections::property('id', 'id'))->findColumn('id');
	}
}
