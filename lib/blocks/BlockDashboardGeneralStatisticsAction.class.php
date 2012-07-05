<?php
/**
 * order_BlockDashboardGeneralStatisticsAction
 * @package modules.order.lib.blocks
 */
class order_BlockDashboardGeneralStatisticsAction extends dashboard_BlockDashboardAction
{
	/**
	 * @see dashboard_BlockDashboardAction::setRequestContent()
	 *
	 * @param f_mvc_Request $request
	 * @param boolean $forEdition
	 */
	protected function setRequestContent($request, $forEdition)
	{
		$shop = null;
		$shops = catalog_ShopService::getInstance()->createQuery()->find();
		if ($request->hasParameter('shopId'))
		{
			$shop = DocumentHelper::getDocumentInstance($request->getParameter('shopId'));
		}
		else
		{
			$shop = $this->getConfiguration()->getShop();
		}
		if ($shop == null)
		{
			$shop = f_util_ArrayUtils::firstElement($shops);
		}
		if ($forEdition || !$shop) {return;}

		$shopId = $shop->getId();
		if (!$this->getConfiguration()->getUsecharts())
		{
			$widget = array();
			$os = order_OrderService::getInstance();
			$fromDate = $toDate = null;
			for ($m = 0 ; $m < 6 ; $m++)
			{
				$this->initPreviousMonthDates($fromDate, $toDate, $m);
				$widget['lines'][] = $os->getStatisticsByShop($shop, $fromDate, $toDate);
			}
			$columns = array();
			foreach (explode(',', $this->getConfiguration()->getColumns()) as $columnName)
			{
				$columns[$columnName] = true;
			}
			$request->setAttribute('columnsArray', $columns);
			$request->setAttribute('widget', $widget);
		}
		else
		{
			$charts = array();
			foreach (explode(',', $this->getConfiguration()->getColumns()) as $columnName)
			{
				$producer = new order_ShopBasicStatisticsProducer();
				$chart = new f_chart_BarChart($producer->getDataTable(array('shopId' => $shopId, 'mode' => $columnName)));
				$chart->setGrid(new f_chart_Grid(0, 20));
				$charts[] = array('chart' => $chart, 'title' => LocaleService::getInstance()->trans('m.order.bo.blocks.dashboardgeneralstatistics.column-'.$columnName, array('ucf')));
			}
			$request->setAttribute('charts', $charts);
		}
		$request->setAttribute('columns', $this->getConfiguration()->getColumns());
		$request->setAttribute('shops', $shops);
		$request->setAttribute('shopId', $shopId);
	}

	/**
	 * @param date_Calendar $fromDate
	 * @param date_Calendar $toDate
	 */
	private function initPreviousMonthDates(&$fromDate, &$toDate, $monthCount = 1)
	{
		$fromDate = date_Converter::convertDateToLocal(date_Calendar::now());
		$fromDate->sub(date_Calendar::MONTH, $monthCount);
		$fromDate->setDay(1);
		$fromDate->setSecond(0);
		$fromDate->setMinute(0);
		$fromDate->setHour(0);
		$fromDate = date_Converter::convertDateToGMT($fromDate);
		$toDate = date_Converter::convertDateToLocal(date_Calendar::now());
		$toDate->sub(date_Calendar::MONTH, $monthCount);
		$toDate->setDay($toDate->getDaysInMonth());
		$toDate->setSecond(59);
		$toDate->setMinute(59);
		$toDate->setHour(23);
		$toDate = date_Converter::convertDateToGMT($toDate);
	}
}