<?php
/**
 * @package modules.order
 */
interface order_BillNumberStrategy
{
	/**
	 * @param order_persistentdocument_bill $bill
	 * @return String
	 */
	public function generate($bill);
}

class order_BillNumberGenerator
{
	/**
	 * @var order_BillNumberGenerator
	 */
	private static $instance;
	
	/**
	 * @var order_BillNumberStrategy
	 */
	private $strategy = null;


	private function __construct()
	{
		$className = Framework::getConfiguration('modules/order/billNumberStrategyClass', false);
		if ($className !== false)
		{
			$this->strategy = new $className;
			if (!($this->strategy instanceof order_BillNumberStrategy))
			{
				$this->strategy = null;
			}
		} 
	}
	
	/**
	 * @return order_BillNumberGenerator
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
	 * @param order_persistentdocument_bill $bill
	 * @return String
	 */
	public function generate($bill)
	{
		if ($this->strategy !== null)
		{
			return $this->strategy->generate($bill);
		}
		return $this->generateDefault($bill);
	}	
	
	/**
	 * @return void
	 */
	public static final function clearInstance()
	{
		self::$instance = null;
	}
	
	/**
	 * @param order_persistentdocument_bill $bill
	 * @return string
	 */
	protected function generateDefault($bill)
	{
		$billCount = $bill->getDocumentService()->createQuery()
			->add(Restrictions::ne('publicationstatus', 'DRAFT'))
			->add(Restrictions::like('label', '_____', MatchMode::EXACT()))
			->setProjection(Projections::max('label', 'max'))
			->findColumn('max');
		return str_pad(strval($billCount[0]+1), 5, '0', STR_PAD_LEFT);
	}
}

/**
 * /!\ This strategy is incompatible with immediate order numbering (same numbering format than orders).
 */
class order_BillNumberYearSequenceStrategy implements order_BillNumberStrategy
{
	/**
 	 * @param order_persistentdocument_bill $bill
	 * @return String
	 */
	public function generate($bill)
	{
		$year = ($bill->getCreationdate()) ? substr($bill->getCreationdate(), 0, 4) : date("Y");
		$row = $bill->getDocumentService()->createQuery()
			->add(Restrictions::ne('publicationstatus', 'DRAFT'))
			->add(Restrictions::ne('id', $bill->getId()))
			->add(Restrictions::like('label', $year.'________', MatchMode::EXACT()))
			->addOrder(Order::desc('label'))
			->setProjection(Projections::property('label'))
			->findUnique();
		if (is_array($row))
		{
			$newCount = strval(intval(substr($row['label'], 4))+1);
		}
		else
		{
			$newCount = '1';
		}
		return $year . str_pad($newCount, 8, '0', STR_PAD_LEFT);
	}
}