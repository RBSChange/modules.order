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
	
	
	public function getAddressStepUrl()
	{
		return $this->getStepURL('Address');
	}
	
	public function getShippingStepUrl()
	{
		return $this->getStepURL('Shipping');
	}
	
	public function getBillingStepUrl()
	{
		return $this->getStepURL('Billing');
	}
	
	public function getResultStepUrl()
	{
		return $this->getStepURL('Result');
	}	
}