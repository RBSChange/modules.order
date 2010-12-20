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
		Framework::info(__METHOD__);
		$billCount = $bill->getDocumentService()->createQuery()
			->add(Restrictions::ne('publicationstatus', 'DRAFT'))
			->setProjection(Projections::rowCount("billCount"))
			->findColumn("billCount");
		return str_pad(strval($billCount[0]+1), 5, '0', STR_PAD_LEFT);
	}
}

class order_BillNumberYearSequenceStrategy implements order_BillNumberStrategy
{
	/**
 	 * @param order_persistentdocument_bill $bill
	 * @return String
	 */
	public function generate($bill)
	{
		Framework::info(__METHOD__);
		$year = date_Calendar::getInstance()->getYear();
		$beginDate = date_Converter::convertDateToGMT($year.'-01-01 00:00:00');
		$endDate = date_Converter::convertDateToGMT(($year+1).'-01-01 00:00:00');
		
		$billCount = $bill->getDocumentService()->createQuery()
						->setProjection(Projections::rowCount("billCount"))
						->add(Restrictions::ne('publicationstatus', 'DRAFT'))
						->add(Restrictions::ne('id', $bill->getId()))
						->add(Restrictions::ge('transactionDate', $beginDate))
						->add(Restrictions::lt('transactionDate', $endDate))
						->findColumn("billCount");
		$newCount = strval($billCount[0]+1);
		return $year . str_pad($newCount, 8, '0', STR_PAD_LEFT);
	}
}

