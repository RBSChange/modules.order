<?php
class order_StandardOrderProcess extends order_OrderProcess
{
	public function __construct()
	{
		$this->name = 'standardprocess';
		
		$this->config = array(
		'Address' => array('blocType' => 'order_StdAddressStep', 'nextStep' => 'Shipping'),
		'Shipping' => array('blocType' => 'order_StdShippingStep', 'nextStep' => 'Billing'),
		'Billing' => array('blocType' => 'order_StdBillingStep', 'nextStep' => 'Result'),
		'Result' => array('blocType' => 'order_StdResultStep', 'nextStep' => null));
		parent::__construct();
	}
	
	/**
	 * @return string
	 */
	public function getAddressStepUrl()
	{
		return $this->getStepURL('Address');
	}
	
	/**
	 * @return string
	 */
	public function getShippingStepUrl()
	{
		return $this->getStepURL('Shipping');
	}
	
	/**
	 * @return string
	 */
	public function getBillingStepUrl()
	{
		return $this->getStepURL('Billing');
	}
	
	/**
	 * @return string
	 */
	public function getResultStepUrl()
	{
		return $this->getStepURL('Result');
	}	
}