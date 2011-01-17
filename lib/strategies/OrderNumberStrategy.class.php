<?php
/**
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
	 * @var order_OrderNumberStrategy
	 */
	private $strategy = null;


	private function __construct()
	{
		$className = Framework::getConfiguration('modules/order/orderNumberStrategyClass', false);
		if ($className !== false)
		{
			$this->strategy = new $className;
			if (!($this->strategy instanceof order_OrderNumberStrategy))
			{
				$this->strategy = null;
			}
		} 
	}
	
	/**
	 * @return order_OrderNumberGenerator
	 */
	public static function getInstance()
	{
		if (is_null(self::$instance))
		{
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @return String
	 */
	public function generate($order)
	{
		if ($this->strategy !== null)
		{
			return $this->strategy->generate($order);
		}
		return $this->generateDefault($order);
	}	
	
	/**
	 * @return void
	 */
	public static final function clearInstance()
	{
		self::$instance = null;
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @return string
	 */
	protected function generateDefault($order)
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
		Framework::info(__METHOD__);
		$orderCount = $order->getDocumentService()->createQuery()
			->setProjection(Projections::rowCount("orderCount"))->findColumn("orderCount");
		$newCount = strval($orderCount[0]+1);
		return str_pad($newCount, 10, '0', STR_PAD_LEFT);
	}
}