<?php
/**
 * Class where to put your custom methods for document order_persistentdocument_creditnote
 * @package modules.order.persistentdocument
 */
class order_persistentdocument_creditnote extends order_persistentdocument_creditnotebase 
{	
	/**
	 * @var double
	 */
	private $orderAmount;

	/**
	 * @var double
	 */
	private $otherCreditNoteAmount;
	
	/**
	 * @return double
	 */
	public function getOtherCreditNoteAmount()
	{
		return $this->otherCreditNoteAmount;
	}

	/**
	 * @param double $otherCreditNoteAmount
	 */
	public function setOtherCreditNoteAmount($otherCreditNoteAmount)
	{
		$this->otherCreditNoteAmount = $otherCreditNoteAmount;
	}

	/**
	 * @return string
	 */
	public function getOrderNumber()
	{
		if ($this->getOrder() == null) {return "";}
		return $this->getOrder()->getOrderNumber();	
	}
	
	/**
	 * @return double
	 */
	public function getOrderAmount()
	{
		if ($this->getOrder() == null) {return 0;}
		return $this->getOrder()->getTotalAmountWithTax();
	}

	/**
	 * @return string
	 */
	public function getCurrencySymbol()
	{
		return catalog_PriceFormatter::getInstance()->getSymbol($this->getCurrency());
	}
	
	/**
	 * @return string
	 */
	public function getAmountFormated()
	{
		return $this->getOrder()->formatPrice($this->getAmount());
	}

	/**
	 * @return string
	 */
	public function getAmountNotAppliedFormated()
	{
		return $this->getOrder()->formatPrice($this->getAmountNotApplied());
	}
	
	/**
	 * @param string $activate
	 */
	public function setAutoActivate($activate)
	{
		if ($activate == "1")
		{
			$this->setPublicationstatus(f_persistentdocument_PersistentDocument::STATUS_ACTIVE);
		}
	}
}