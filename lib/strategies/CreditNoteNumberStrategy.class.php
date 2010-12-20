<?php
/**
 * @package modules.order
 */
interface order_CreditNoteNumberStrategy
{
	/**
	 * @param order_persistentdocument_creditnote $creditnote
	 * @return String
	 */
	public function generate($creditnote);
}

class order_CreditNoteNumberGenerator
{
	/**
	 * @var order_CreditNoteNumberGenerator
	 */
	private static $instance;
	
	/**
	 * @var order_CreditNoteNumberStrategy
	 */
	private $strategy = null;


	private function __construct()
	{
		$className = Framework::getConfiguration('modules/order/creditNoteNumberStrategyClass', false);
		if ($className !== false)
		{
			$this->strategy = new $className;
			if (!($this->strategy instanceof order_CreditNoteNumberStrategy))
			{
				$this->strategy = null;
			}
		} 
	}
	
	/**
	 * @return order_CreditNoteNumberGenerator
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
	 * @param order_persistentdocument_creditnote $creditnote
	 * @return String
	 */
	public function generate($creditnote)
	{
		if ($this->strategy !== null)
		{
			return $this->strategy->generate($creditnote);
		}
		return $this->generateDefault($creditnote);
	}	
	
	/**
	 * @return void
	 */
	public static final function clearInstance()
	{
		self::$instance = null;
	}
	
	/**
	 * @param order_persistentdocument_creditnote $creditnote
	 * @return string
	 */
	protected function generateDefault($creditnote)
	{
		Framework::info(__METHOD__);
		
		$order = $creditnote->getOrder();	
		$creditnoteCount = $creditnote->getDocumentService()->createQuery()
			->add(Restrictions::ne('publicationstatus', 'DRAFT'))
			->add(Restrictions::ne('id', $creditnote->getId()))
			->add(Restrictions::eq('order', $order))
			->setProjection(Projections::rowCount("creditnoteCount"))
			->findColumn("creditnoteCount");
	
		return 'A' . $order->getOrderNumber() . '-' . strval($creditnoteCount[0]+1); 
	}
}

class order_CreditNoteNumberYearSequenceStrategy implements order_CreditNoteNumberStrategy
{
	/**
 	 * @param order_persistentdocument_creditnote $creditnote
	 * @return String
	 */
	public function generate($creditnote)
	{
		Framework::info(__METHOD__);
		
		$year = date_Calendar::getInstance()->getYear();
		$beginDate = date_Converter::convertDateToGMT($year.'-01-01 00:00:00');
		$endDate = date_Converter::convertDateToGMT(($year+1).'-01-01 00:00:00');
		
		$creditnoteCount = $creditnote->getDocumentService()->createQuery()
						->setProjection(Projections::rowCount("creditnoteCount"))
						->add(Restrictions::ne('publicationstatus', 'DRAFT'))
						->add(Restrictions::ne('id', $creditnote->getId()))
						->add(Restrictions::ge('transactionDate', $beginDate))
						->add(Restrictions::lt('transactionDate', $endDate))
						->findColumn("creditnoteCount");
		$newCount = strval($creditnoteCount[0]+1);
		return $year . str_pad($newCount, 8, '0', STR_PAD_LEFT);
	}
}

