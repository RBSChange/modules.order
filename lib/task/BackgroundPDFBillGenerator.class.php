<?php
class order_BackgroundPDFBillGenerator extends task_SimpleSystemTask
{
	/**
	 * @see task_SimpleSystemTask::execute()
	 *
	 */
	protected function execute()
	{
		$obs = order_BillService::getInstance();
		if (!$obs->generateBillIsActive())
		{
			return;
		}
		
		$query = $obs->createQuery()->add(Restrictions::published())
		->add(Restrictions::isNull("archive"));
		
		foreach ($query->find() as $bill)
		{
			$obs->genBill($bill);
		}
	}
}
