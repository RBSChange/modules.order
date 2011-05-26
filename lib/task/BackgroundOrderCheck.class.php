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
		$errors = array();
		
		foreach ($documentsArray as $chunk)
		{
			$this->plannedTask->ping();
			$result = f_util_System::execHTTPScript($script, $chunk);
			if ($result != 'OK')
			{
				$errors[] = $result;
			}
		}
		
		if (count($errors))
		{
			throw new Exception(implode("\n", $errors));
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
