<?php
/**
 * @package modules.order.lib.bean
 */
class order_BillingStepBean
{
	/**
	 * @var String
	 */
	public $coupon;
		
	/**
	 * @var Integer
	 * @required
	 */
	public $paymentFilterId;	

	/**
	 * @return catalog_persistentdocument_paymentfilter
	 */
	public function getPaymentFilter()
	{
		if ($this->paymentFilterId)
		{
			return DocumentHelper::getDocumentInstance($this->paymentFilterId, 'modules_catalog/paymentfilter');
		}
		return null;
	}
	
	public function selected($id, $first)
	{
		return (($id == $this->paymentFilterId) || (!$this->paymentFilterId && $first));
	}
}