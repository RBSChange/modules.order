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
		return catalog_PriceHelper::getCurrencySymbol($this->getCurrency());
	}
	
	/**
	 * @return string
	 */
	public function getAmountFormated()
	{
		$priceFormat = ($this->getOrder() == null) ? null : $this->getOrder()->getPriceFormat();
		return catalog_PriceHelper::applyFormat($this->getAmount(), $priceFormat ? $priceFormat : "%s €");
	}

	/**
	 * @return string
	 */
	public function getAmountNotAppliedFormated()
	{
		$priceFormat = ($this->getOrder() == null) ? null : $this->getOrder()->getPriceFormat();
		return catalog_PriceHelper::applyFormat($this->getAmountNotApplied(), $priceFormat ? $priceFormat : "%s €");
	}
	
	public function setAutoActivate($activate)
	{
		if ($activate == "1")
		{
			$this->setPublicationstatus(f_persistentdocument_PersistentDocument::STATUS_ACTIVE);
		}
	}
}