<?php
/**
 * @package modules.order
 */
interface order_ExpeditionNumberStrategy
{
	/**
	 * @param order_persistentdocument_expedition $expedition
	 * @return String
	 */
	public function generate($expedition);
}

class order_ExpeditionNumberGenerator
{
	/**
	 * @var order_ExpeditionNumberGenerator
	 */
	private static $instance;
	
	/**
	 * @var order_ExpeditionNumberStrategy
	 */
	private $strategy = null;


	private function __construct()
	{
		$className = Framework::getConfiguration('modules/order/expeditionNumberStrategyClass', false);
		if ($className !== false)
		{
			$this->strategy = new $className;
			if (!($this->strategy instanceof order_ExpeditionNumberStrategy))
			{
				$this->strategy = null;
			}
		} 
	}
	
	/**
	 * @return order_ExpeditionNumberGenerator
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
		Framework::info(__METHOD__);
		$order = $expedition->getOrder();	
		$expeditionCount = $expedition->getDocumentService()->createQuery()
			->setProjection(Projections::rowCount('count'))
			->add(Restrictions::ne('id', $expedition->getId()))
			->add(Restrictions::eq('order', $order))
			->findColumn("count");	
		return strval($expeditionCount[0]+1); 
	}
}

class order_ExpeditionOrderNumberStrategy implements order_ExpeditionNumberStrategy
{
	
	/**
 	 * @param order_persistentdocument_expedition $expedition
	 * @return String
	 */
	public function generate($expedition)
	{
		Framework::info(__METHOD__);
		$order = $expedition->getOrder();	
		$expeditionCount = $expedition->getDocumentService()->createQuery()
			->setProjection(Projections::rowCount('count'))
			->add(Restrictions::ne('id', $expedition->getId()))
			->add(Restrictions::eq('order', $order))
			->findColumn("count");	
		return 'E' . $order->getOrderNumber() . '-' . strval($expeditionCount[0]+1); 
	}
}

