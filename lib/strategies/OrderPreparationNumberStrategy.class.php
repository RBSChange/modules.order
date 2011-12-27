<?php
/**
 * @package modules.order
 */
interface order_OrderPreparationNumberStrategy
{
	/**
	 * @param order_persistentdocument_orderpreparation $orderpreparation
	 * @return String
	 */
	public function generate($orderpreparation);
}

class order_OrderPreparationNumberGenerator
{
	/**
	 * @var order_OrderPreparationNumberGenerator
	 */
	private static $instance;
	
	/**
	 * @var order_OrderPreparationNumberStrategy
	 */
	private $strategy = null;


	private function __construct()
	{
		$className = Framework::getConfiguration('modules/order/orderPreparationNumberStrategyClass', false);
		if ($className !== false)
		{
			$this->strategy = new $className;
			if (!($this->strategy instanceof order_OrderPreparationNumberStrategy))
			{
				$this->strategy = null;
			}
		} 
	}
	
	/**
	 * @return order_OrderPreparationNumberGenerator
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
	 * @param order_persistentdocument_orderpreparation $orderpreparation
	 * @return String
	 */
	public function generate($orderpreparation)
	{
		if ($this->strategy !== null)
		{
			return $this->strategy->generate($orderpreparation);
		}
		return $this->generateDefault($orderpreparation);
	}	
	
	/**
	 * @return void
	 */
	public static final function clearInstance()
	{
		self::$instance = null;
	}
	
	/**
	 * @param order_persistentdocument_orderpreparation $orderpreparation
	 * @return string
	 */
	protected function generateDefault($orderpreparation)
	{
		$ops = order_OrderpreparationService::getInstance();
		$baseNumber = 'BP-' . date_Calendar::getInstance()->getYear() . '-';
		$result = $ops->createQuery()->setProjection(Projections::property('label', 'label'))
			->add(Restrictions::like('label', $baseNumber, MatchMode::START()))
			->setMaxResults(1)
			->addOrder(Order::desc('label'))->findColumn('label');
		
		$lastNumber = 1;
		if (is_array($result) && count($result))
		{
			if (preg_match('/([0-9]+)$/', $result[0], $matches))
			{
				$lastNumber = intval(end($matches)) + 1;
			}
		}
		return $baseNumber . str_pad(strval($lastNumber), 9, '0', STR_PAD_LEFT);
	}
}