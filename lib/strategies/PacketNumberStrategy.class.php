<?php
/**
 * @package modules.order
 */
interface order_PacketNumberStrategy
{
	/**
	 * @param order_persistentdocument_expedition $expedition
	 * @return String
	 */
	public function generate($expedition);
}


class order_PacketNumberGenerator
{
	/**
	 * @var order_PacketNumberGenerator
	 */
	private static $instance;
	
	/**
	 * @var order_PacketNumberStrategy
	 */
	private $strategy = null;


	private function __construct()
	{
		$className = Framework::getConfiguration('modules/order/packetNumberStrategyClass', false);
		if ($className !== false)
		{
			$this->strategy = new $className;
			if (!($this->strategy instanceof order_PacketNumberStrategy))
			{
				$this->strategy = null;
			}
		} 
	}
	
	/**
	 * @return order_PacketNumberGenerator
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
	 * @param order_persistentdocument_expedition $expedition
	 * @return String
	 */
	public function generate($expedition)
	{
		if ($this->strategy !== null)
		{
			return $this->strategy->generate($expedition);
		}
		return $this->generateDefault($expedition);
	}	
	
	/**
	 * @return void
	 */
	public static final function clearInstance()
	{
		self::$instance = null;
	}
	
	/**
	 * @param order_persistentdocument_expedition $expedition
	 * @return string
	 */
	protected function generateDefault($expedition)
	{
		$order = $expedition->getOrder();		
		$baseNumber = 'P-' .  $order->getOrderNumber() . '-';
		
		$result = order_ExpeditionlineService::getInstance()->createQuery()->setProjection(Projections::property('packetNumber', 'packetNumber'))
			->add(Restrictions::like('packetNumber', $baseNumber, MatchMode::START()))
			->add(Restrictions::eq('orderId', $order->getId()))
			->addOrder(Order::desc('packetNumber'))->setMaxResults(1)
			->findColumn('packetNumber');
		
		$lastNumber = 1;
		if (is_array($result) && count($result))
		{
			if (preg_match('/([0-9]+)$/', $result[0], $matches))
			{
				$lastNumber = intval(end($matches)) + 1;
			}
		}
		return $baseNumber . str_pad(strval($lastNumber), 4, '0', STR_PAD_LEFT);
	}
}