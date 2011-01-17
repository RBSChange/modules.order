<?php
/**
 * @date Fri Jan 18 09:51:39 CET 2008
 * @author intbonjf
 * @package modules.order
 */
interface order_OrderNumberStrategy
{
	/**
	 * @param order_persistentdocument_order $order
	 * @return String
	 */
	public function generate($order);
}

class order_OrderNumberGenerator
{
	/**
	 * @var order_OrderNumberGenerator
	 */
	private static $instance;

	/**
	 * Constructor of order_OrderNumberGenerator
	 */
	private function __construct()
	{
	}

	/**
	 * @return order_OrderNumberGenerator
	 */
	public static function getInstance()
	{
		if (is_null(self::$instance))
		{
			self::$instance = new order_OrderNumberGenerator();
		}
		return self::$instance;
	}

	/**
	 * @return void
	 */
	public static final function clearInstance()
	{
		if (PROFILE != 'test')
		{
			throw new Exception(__METHOD__." is only available in test mode.");
		}
		self::$instance = null;
	}

	/**
	 * @var order_OrderNumberStrategy
	 */
	private $strategy = null;

	/**
	 * @param order_OrderNumberStrategy $strategy
	 * @return order_OrderNumberGenerator $this
	 */
	public final function setStrategy($strategy)
	{
		$this->strategy = $strategy;
		return $this;
	}

	/**
	 * @return order_OrderNumberStrategy
	 */
	public final function getStrategy()
	{
		if ( is_null($this->strategy) )
		{
			try
			{
				$className = Framework::getConfiguration('modules/order/orderNumberStrategyClass');
			}
			catch (ConfigurationException $e)
			{
				// No strategy defined in the project's config file: use default one.
				$className = 'order_OrderNumberDefaultStrategy';
				if (Framework::isInfoEnabled())
				{
					Framework::info("No strategy defined to build order numbers for this projet: using default one (".$className.").");
					Framework::debug($e->getMessage());
				}
			}
			$this->strategy = new $className;
		}
		return $this->strategy;
	}

	/**
	 * @param order_persistentdocument_order $order
	 * @return String
	 */
	public final function generate($order)
	{
		return $this->getStrategy()->generate($order);
	}
}

class order_OrderNumberDefaultStrategy implements order_OrderNumberStrategy
{
	/**
	 * @param order_persistentdocument_order $order
	 * @return String
	 */
	public function generate($order)
	{
		Framework::info(__METHOD__);
		$year = ($order->getCreationdate()) ? substr($order->getCreationdate(), 0, 4) : date("Y");
		$beginDate = date_Converter::convertDateToGMT($year.'-01-01 00:00:00');
		$endDate = date_Converter::convertDateToGMT(($year+1).'-01-01 00:00:00');

		$orderCount = $order->getDocumentService()->createQuery()
			->add(Restrictions::ge("creationdate", $beginDate))
			->add(Restrictions::lt("creationdate", $endDate))
			->setProjection(Projections::rowCount("orderCount"))->findColumn("orderCount");
		$newCount = strval($orderCount[0]+1);
		$newCount = str_pad($newCount, 8, '0', STR_PAD_LEFT);
		return $year.$newCount;
	}
}

class order_OrderNumberSequenceStrategy implements order_OrderNumberStrategy
{
	/**
	 * @param order_persistentdocument_order $order
	 * @return String
	 */
	public function generate($order)
	{
		$orderCount = $order->getDocumentService()->createQuery()
			->setProjection(Projections::rowCount("orderCount"))->findColumn("orderCount");
		$newCount = strval($orderCount[0]+1);
		while (strlen($newCount) < 10)
		{
			$newCount = "0".$newCount;
		}
		return $newCount;
	}
}