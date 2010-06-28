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
			f_util_System::execHTTPScript($script, $chunk);
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
			->add(Restrictions::orExp(Restrictions::eq('orderStatus', order_OrderService::IN_PROGRESS), Restrictions::isNull('orderStatus')))
			->setProjection(Projections::property('id', 'id'))->findColumn('id');
	}
}
