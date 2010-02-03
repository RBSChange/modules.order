<?php
class order_ShopBasicStatisticsProducer implements f_chart_DataTableProducer
{
	/**
	 * @param array<String, mixed> $params
	 * @return f_chart_DataTable
	 */
	function getDataTable($params = null)
	{
		$data = new f_chart_DataTable();
				
		$data->addColumn(null, f_chart_DataTable::STRING_TYPE);
		switch ($params['mode'])
		{
			case 'orders' : 
				$data->addColumn(null, f_chart_DataTable::NUMBER_TYPE, '555599');
				break;
			
			case 'amount' :
				$data->addColumn(null, f_chart_DataTable::NUMBER_TYPE, '559955');
				break;
			
			case 'toDeliver' :
				$data->addColumn(null, f_chart_DataTable::NUMBER_TYPE, '995555');
				break;
		}
		$data->addRows(4);
				
		$os = order_OrderService::getInstance();
		$shop = DocumentHelper::getDocumentInstance($params['shopId']);
		$fromDate = $toDate = null;
		for ($m = 0 ; $m < 12 ; $m++)
		{
			$this->initPreviousMonthDates($fromDate, $toDate, 11-$m);
			$statistics = $os->getStatisticsByShop($shop, $fromDate, $toDate);
			$data->setValue($m, 0, $statistics['monthShortLabel']);
			switch ($params['mode'])
			{
				case 'orders' : 
					$data->setValue($m, 1, $statistics['totalCount']);
					break;
				
				case 'amount' :
					$data->setValue($m, 1, $statistics['totalAmount']);
					break;
				
				case 'toDeliver' :
					$data->setValue($m, 1, $statistics['toDeliver']);
					break;
			}
		}
		
		return $data;
	}
	
	/**
	 * @param date_Calendar $fromDate
	 * @param date_Calendar $toDate
	 */
	private function initPreviousMonthDates(&$fromDate, &$toDate, $monthCount = 1)
	{
		$fromDate = date_Calendar::now()->sub(date_Calendar::MONTH, $monthCount);
		$fromDate->setDay(1);
		$toDate = date_Calendar::now()->sub(date_Calendar::MONTH, $monthCount);
		$toDate->setDay($toDate->getDaysInMonth());
	}
}