<?php
class order_StatisticsService extends BaseService
{
	/**
	 * Singleton
	 * @var order_StatisticsService
	 */
	private static $instance = null;

	/**
	 * @return order_StatisticsService
	 */
	public static function getInstance()
	{
		if (is_null(self::$instance))
		{
			self::$instance = self::getServiceClassInstance(get_class());
		}
		return self::$instance;
	}

	/**
	 * Returns the orders created from $fromDate to $toDate.
	 * If $fromDate is null, this method returns all the orders created until $toDate.
	 * If $toDate is null, this method returns all the orders created from $fromDate.
	 * If $fromDate and $toDate are both null, all the orders are returned.
	 *
	 * @param date_Calendar $fromDate
	 * @param date_Calendar $toDate
	 * @param Boolean $restrictToSuccessfulOrders
	 * @return Array<order_persistentdocument_order>
	 */
	public function getOrders($fromDate, $toDate, $restrictToSuccessfulOrders = true)
	{
		$query = $this->buildQueryFromDateToDate($fromDate, $toDate);
		if ($restrictToSuccessfulOrders)
		{
			$query->add(Restrictions::orExp(
				Restrictions::eq('orderStatus', order_OrderService::PAYMENT_SUCCESS),
				Restrictions::eq('orderStatus', order_OrderService::SHIPPED)
				));
		}
		return $query->find();
	}

	/**
	 * @param date_Calendar $fromDate
	 * @param date_Calendar $toDate
	 * @param Boolean $restrictToSuccessfulOrders
	 * @return Integer
	 */
	public function getOrdersCount($fromDate, $toDate, $restrictToSuccessfulOrders = true)
	{
		$query = $this->buildQueryFromDateToDate($fromDate, $toDate);
		if ($restrictToSuccessfulOrders)
		{
			$query->add(Restrictions::orExp(
				Restrictions::eq('orderStatus', order_OrderService::PAYMENT_SUCCESS),
				Restrictions::eq('orderStatus', order_OrderService::SHIPPED)
				));
		}
		$result = $query->setProjection(Projections::rowCount('count'))->findUnique();
		return intval($result['count']);
	}

	/**
	 * @param date_Calendar $fromDate
	 * @param date_Calendar $toDate
	 * @return Array<order_persistentdocument_order>
	 */
	public function getPaymentWaitingOrders($fromDate, $toDate)
	{
		return $this->getPaymentWaitingOrdersQuery($fromDate, $toDate)->find();
	}

	/**
	 * @param date_Calendar $fromDate
	 * @param date_Calendar $toDate
	 * @return Integer
	 */
	public function getPaymentWaitingOrdersCount($fromDate, $toDate)
	{
		$result = $this->getPaymentWaitingOrdersQuery($fromDate, $toDate)
			->setProjection(Projections::rowCount('count'))
			->findUnique();
		return intval($result['count']);
	}

	/**
	 * @param date_Calendar $fromDate
	 * @param date_Calendar $toDate
	 * @return Array<order_persistentdocument_order>
	 */
	public function getPaymentFailedOrders($fromDate, $toDate)
	{
		return $this->getPaymentFailedOrdersQuery($fromDate, $toDate)->find();
	}

	/**
	 * @param date_Calendar $fromDate
	 * @param date_Calendar $toDate
	 * @return Integer
	 */
	public function getPaymentFailedOrdersCount($fromDate, $toDate)
	{
		$result = $this->getPaymentFailedOrdersQuery($fromDate, $toDate)
			->setProjection(Projections::rowCount('count'))
			->findUnique();
		return intval($result['count']);
	}

	/**
	 * @param date_Calendar $fromDate
	 * @param date_Calendar $toDate
	 * @return Array<order_persistentdocument_order>
	 */
	public function getPaymentSuccessOrders($fromDate, $toDate)
	{
		return $this->getPaymentSuccessOrdersQuery($fromDate, $toDate)->find();
	}

	/**
	 * @param date_Calendar $fromDate
	 * @param date_Calendar $toDate
	 * @return Integer
	 */
	public function getPaymentSuccessOrdersCount($fromDate, $toDate)
	{
		$result = $this->getPaymentSuccessOrdersQuery($fromDate, $toDate)
			->setProjection(Projections::rowCount('count'))
			->findUnique();
		return intval($result['count']);
	}

	/**
	 * @param date_Calendar $fromDate
	 * @param date_Calendar $toDate
	 * @return Array<order_persistentdocument_order>
	 */
	public function getShippedOrders($fromDate, $toDate)
	{
		return $this->getShippedOrdersQuery($fromDate, $toDate)->find();
	}

	/**
	 * @param date_Calendar $fromDate
	 * @param date_Calendar $toDate
	 * @return Integer
	 */
	public function getShippedOrdersCount($fromDate, $toDate)
	{
		$result = $this->getShippedOrdersQuery($fromDate, $toDate)
			->setProjection(Projections::rowCount('count'))
			->findUnique();
		return intval($result['count']);
	}

	/**
	 * @param date_Calendar $fromDate
	 * @param date_Calendar $toDate
	 * @return Array<order_persistentdocument_order>
	 */
	public function getCancelledOrders($fromDate, $toDate)
	{
		return $this->getCancelledOrdersQuery($fromDate, $toDate)->find();
	}

	/**
	 * @param date_Calendar $fromDate
	 * @param date_Calendar $toDate
	 * @return Integer
	 */
	public function getCancelledOrdersCount($fromDate, $toDate)
	{
		$result = $this->getCancelledOrdersQuery($fromDate, $toDate)
			->setProjection(Projections::rowCount('count'))
			->findUnique();
		return intval($result['count']);
	}

	/**
	 * @param date_Calendar $fromDate
	 * @param date_Calendar $toDate
	 */
	public function initPreviousMonthDates(&$fromDate, &$toDate, $monthCount = 1)
	{
		$fromDate = date_Calendar::now()->sub(date_Calendar::MONTH, $monthCount);
		$fromDate->setDay(1);
		$toDate = date_Calendar::now()->sub(date_Calendar::MONTH, $monthCount);
		$toDate->setDay($toDate->getDaysInMonth());
	}

	/**
	 * @param date_Calendar $fromDate
	 * @param date_Calendar $toDate
	 * @return Double
	 */
	public function getTotalAmount($fromDate, $toDate)
	{
		$amount = 0;
		$orders = $this->getOrders($fromDate, $toDate);
		foreach ($orders as $order)
		{
			$amount += $order->getTotalAmountWithTax();
		}
		return $amount;
	}

	/**
	 * @param date_Calendar $fromDate
	 * @param date_Calendar $toDate
	 * @return String
	 */
	public function getTotalAmountFormatted($fromDate, $toDate)
	{
		return catalog_PriceHelper::formatPrice($this->getTotalAmount($fromDate, $toDate));
	}

	/**
	 * @param date_Calendar $fromDate
	 * @param date_Calendar $toDate
	 * @return Double
	 */
	public function getSoldArticlesQuantity($fromDate, $toDate)
	{
		// Order lines are created at the same as their parent order.
		// TODO intbonjf 2007-12-13 : get only the SHIPPED and PAYED orders.
		$amount = 0;
		$result = $this->addDateRestrictionsToQuery($fromDate, $toDate, order_OrderlineService::getInstance()->createQuery())
			->setProjection(Projections::property('quantity'))
			->find();
		foreach ($result as $q)
		{
			$amount += doubleval($q['quantity']);
		}
		return $amount;
	}

	/**
	 * @param date_Calendar $fromDate
	 * @param date_Calendar $toDate
	 * @return f_persistentdocument_criteria_Query
	 */
	private function getCancelledOrdersQuery($fromDate, $toDate)
	{
		return $this->buildQueryFromDateToDate($fromDate, $toDate)
			->add(Restrictions::eq('orderStatus', order_OrderService::CANCELED));
	}

	/**
	 * @param date_Calendar $fromDate
	 * @param date_Calendar $toDate
	 * @return f_persistentdocument_criteria_Query
	 */
	private function getShippedOrdersQuery($fromDate, $toDate)
	{
		return $this->buildQueryFromDateToDate($fromDate, $toDate)
			->add(Restrictions::eq('orderStatus', order_OrderService::SHIPPED));
	}

	/**
	 * @param date_Calendar $fromDate
	 * @param date_Calendar $toDate
	 * @return f_persistentdocument_criteria_Query
	 */
	private function getPaymentWaitingOrdersQuery($fromDate, $toDate)
	{
		return $this->buildQueryFromDateToDate($fromDate, $toDate)
			->add(Restrictions::eq('orderStatus', order_OrderService::PAYMENT_WAITING));
	}

	/**
	 * @param date_Calendar $fromDate
	 * @param date_Calendar $toDate
	 * @return f_persistentdocument_criteria_Query
	 */
	private function getPaymentFailedOrdersQuery($fromDate, $toDate)
	{
		return $this->buildQueryFromDateToDate($fromDate, $toDate)
			->add(Restrictions::eq('orderStatus', order_OrderService::PAYMENT_FAILED));
	}

	/**
	 * @param date_Calendar $fromDate
	 * @param date_Calendar $toDate
	 * @return f_persistentdocument_criteria_Query
	 */
	private function getPaymentSuccessOrdersQuery($fromDate, $toDate)
	{
		return $this->buildQueryFromDateToDate($fromDate, $toDate)
			->add(Restrictions::eq('orderStatus', order_OrderService::PAYMENT_SUCCESS));
	}

	/**
	 * @param date_Calendar $fromDate
	 * @param date_Calendar $toDate
	 * @return f_persistentdocument_criteria_Query
	 */
	private function buildQueryFromDateToDate($fromDate, $toDate)
	{
		return $this->addDateRestrictionsToQuery($fromDate, $toDate, order_OrderService::getInstance()->createQuery());
	}

	/**
	 * @param date_Calendar $fromDate
	 * @param date_Calendar $toDate
	 * @param f_persistentdocument_criteria_Query $query
	 * @return f_persistentdocument_criteria_Query
	 */
	private function addDateRestrictionsToQuery($fromDate, $toDate, $query)
	{
		$dbFormat = 'Y-m-d H:i:s';
		if (!is_null($fromDate) && !is_null($toDate))
		{
			$query->add(Restrictions::between(
				'creationdate',
				date_DateFormat::format($fromDate, $dbFormat),
				date_DateFormat::format($toDate, $dbFormat)
				));
		}
		else if (!is_null($fromDate))
		{
			$query->add(Restrictions::ge(
				'creationdate',
				date_DateFormat::format($fromDate, $dbFormat)
				));
		}
		else if (!is_null($toDate))
		{
			$query->add(Restrictions::le(
				'creationdate',
				date_DateFormat::format($toDate, $dbFormat)
				));
		}
		return $query;
	}
}