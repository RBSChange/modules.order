<?php
class order_OrderProcess
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
	 * @var string
	 */
	protected $lastStep = 'Payment';

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

	public function __construct()
	{
		$steps = array_keys($this->config);
		$this->startStep = f_util_ArrayUtils::firstElement($steps);
		$this->lastStep = f_util_ArrayUtils::lastElement($steps);
	}
		
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
		return $this->currentStep ? $this->currentStep : $this->getFirstStep();
	}

	/**
	 * @return boolean
	 */
	public function inProcess()
	{
		return ($this->currentStep !== null && $this->currentStep !== $this->getLastStep());
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
	public function getFirstStep()
	{
		return $this->startStep;
	}

	/**
	 * @return string
	 */
	public function getLastStep()
	{
		return $this->lastStep;
	}

	/**
	 * @return array
	 */
	public function getSteps()
	{
		$result = array();
		$name = $this->startStep;
		$hasURL = true;
		$step = 1;
		$ls = LocaleService::getInstance();
		while ($name !== null)
		{
			$result[$name] = array('name' => $name, 'step' => $step, 'current'=> false, 'label' => $ls->transFO('m.order.process.' . $name, array('ucf')));
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

	// Deprecated.

	/**
	 * @deprecated (will be removed in 4.0) use order_OrderProcessService::getInstance()->loadFromSession()
	 */
	public static function getInstance()
	{
		return order_OrderProcessService::getInstance()->loadFromSession();
	}
}