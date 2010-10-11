<?php
class order_OrderProcess extends BaseService 
{
	/**
	 * @var string
	 */
	protected $currentStep;
	
	/**
	 * @var string
	 */
	protected $startStep = 'Identify';
	
	/**
	 * @var array
	 */
	protected $config = array(
		'Identify' => array('blocType' => 'order_IdentifyStep', 'nextStep' => 'Shipping'),
		'Shipping' => array('blocType' => 'order_ShippingStep', 'nextStep' => 'Billing'),
		'Billing' => array('blocType' => 'order_BillingStep', 'nextStep' => 'Confirm'),
		'Confirm' => array('blocType' => 'order_ConfirmStep', 'nextStep' => 'Payment'),
		'Payment' => array('blocType' => 'order_PaymentStep', 'nextStep' => null),
	);
			
	/**
	 * @param string $step
	 * @return string | null;
	 */
	public function getBlockTypeForStep($step)
	{
		if (isset($this->config[$step]))
		{
			return $this->config[$step]['blocType'];
		}
		return null;
	}	
	
	/**
	 * @param string $step
	 * @return string | null;
	 */
	public function getNextStepForStep($step)
	{
		if (isset($this->config[$step]))
		{
			return $this->config[$step]['nextStep'];
		}
		return null;
	}

	/**
	 * @return string
	 */
	public function getCurrentStep()
	{
		return $this->currentStep ? $this->currentStep : $this->startStep;
	}
	
	/**
	 * @return string
	 */
	public function getCurrentBlockType()
	{
		return $this->getBlockTypeForStep($this->getCurrentStep());
	}
	
	/**
	 * @param string $step
	 */
	public function setCurrentStep($step)
	{
		if (f_util_StringUtils::isNotEmpty($step) && isset($this->config[$step]))
		{
			$this->currentStep = $step;
		}
		else
		{
			$this->currentStep = null;
		}
	}
		
	/**
	 * @return string
	 */	
	public function getLastStep()
	{
		return f_util_ArrayUtils::lastElement(array_keys($this->config));
	}
		
	public function getSteps()
	{
		$result = array();
		$name = $this->startStep;
		$hasURL = true;
		$step = 1;
		while ($name !== null) 
		{
			$result[$name] = array('name' => $name, 'step' => $step, 'current'=> false, 'label' => f_Locale::translate('&modules.order.process.' . ucfirst($name) . ';'));
			if ($name == $this->getCurrentStep())
			{
				$result[$name]['current'] = true;
				$hasURL = false;
			}
			if ($hasURL)
			{
				$result[$name]['url'] = $this->getStepURL($name);
			}
			$step++;
			$name = $this->getNextStepForStep($name);
		}
		return $result;
	}

	/**
	 * @param string $step
	 * @return string || null
	 */
	public function getStepURL($step)
	{
		return order_OrderProcessService::getInstance()->getStepURL($step, $this);
	}
	
	/**
	 * @return string || null
	 */
	public function getOrderProcessURL()
	{
		return order_OrderProcessService::getInstance()->getOrderProcessURL($this);
	}
	
	/**
	 * @deprecated use rder_OrderProcessService::getInstance()->loadFromSession()
	 */
	public static function getInstance()
	{
		order_OrderProcessService::getInstance()->loadFromSession();
	}
}