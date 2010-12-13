<?php
/**
 * @package modules.order
 */
class order_OrderBillListener
{
	/**
	 * @param Mixed $sender
	 * @param Array $params
	 * @return void
	 */
	public function onHourChange($sender, $params)
	{
		order_BillService::getInstance()->genBills();
	}
}