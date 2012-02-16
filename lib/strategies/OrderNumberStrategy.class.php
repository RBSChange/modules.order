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
		$baseNumber = '' . date_Calendar::getInstance()->getYear();
		
		$os = $order->getDocumentService();
		$result = $os->createQuery()->setProjection(Projections::property('orderNumber', 'orderNumber'))
			->add(Restrictions::like('orderNumber', $baseNumber, MatchMode::START()))
			->setMaxResults(1)
			->addOrder(Order::desc('orderNumber'))
			->findColumn('orderNumber');
	
		$nextNumber = 1;
		if (is_array($result) && count($result))
		{
			if (preg_match('/^[0-9]{4}([0-9]+)$/', $result[0], $matches))
			{
				$nextNumber = intval(end($matches)) + 1;
			}
		}
		$number = $baseNumber . str_pad($nextNumber, 8, '0', STR_PAD_LEFT);
		if (Framework::isInfoEnabled())
		{		
			Framework::info(__METHOD__ . ' ' . $number);
		}
		return $number;
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
		$number = str_pad($newCount, 10, '0', STR_PAD_LEFT);
		
		if (Framework::isInfoEnabled())
		{
			Framework::info(__METHOD__ . ' ' . $number);
		}
		return $number;
	}
}