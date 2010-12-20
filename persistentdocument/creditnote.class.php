<?php
/**
 * Class where to put your custom methods for document order_persistentdocument_creditnote
 * @package modules.order.persistentdocument
 */
class order_persistentdocument_creditnote extends order_persistentdocument_creditnotebase 
{
	/**
	 * @param string $moduleName
	 * @param string $treeType
	 * @param array<string, string> $nodeAttributes
	 */
//	protected function addTreeAttributes($moduleName, $treeType, &$nodeAttributes)
//	{
//	}
	
	/**
	 * @param string $actionType
	 * @param array $formProperties
	 */
	public function addFormProperties($propertiesNames, &$formProperties)
	{	
		if (in_array('maxAmount', $propertiesNames))
		{
			$tamount = $this->getDocumentService()->getTotalAmountForOrder($this->getOrder(), $this);
			$this->setOtherCreditNoteAmount($tamount);
			$formProperties['maxAmount'] = $this->getOrderAmount() - $this->getOtherCreditNoteAmount();
		}	
	}

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
		return $this->getOrder()->getOrderNumber();	
	}
	
	/**
	 * @return double
	 */
	public function getOrderAmount()
	{
		return $this->getOrder()->getTotalAmountWithTax();
	}

	/**
	 * @return string
	 */
	public function getCurrencySymbol()
	{
		return catalog_PriceHelper::getCurrencySymbol($this->getCurrency());
	}
}