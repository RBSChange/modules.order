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
	 * @return order_OrderProcess
	 */
	public static function getInstance()
	{
		$orderProcess = self::loadFromSession();
		if ($orderProcess instanceof order_OrderProcess) 
		{
			return $orderProcess;
		}
		return self::getServiceClassInstance(get_class());
	}
	
	/**
	 * @return order_OrderProcess | null;
	 */
	private static function loadFromSession()
	{
		if (isset($_SESSION['order_OrderProcess']))
		{
			return $_SESSION['order_OrderProcess'];
		}		
		return null;
	}
	
	/**
	 * @param order_OrderProcess $orderProcess
	 */
	private static function saveToSession($orderProcess)
	{
		if ($orderProcess instanceof order_OrderProcess) 
		{
			$_SESSION['order_OrderProcess'] = $orderProcess;
		}		
		else
		{
			$_SESSION['order_OrderProcess'] = null;
		}
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
		self::saveToSession($this);
	}
	
	/**
	 * @return string | null
	 */
	public function getOrderProcessURL()
	{
		$blocType = $this->getCurrentBlockType();
		if ($blocType)
		{
			$lang = RequestContext::getInstance()->getLang();
			return website_BlockController::getBlockUrl($blocType, $lang, array());
		}
		return null;
	}
	
	/**
	 * @return string
	 */	
	public function getLastStep()
	{
		return f_util_ArrayUtils::lastElement(array_keys($this->config));
	}
	
	
	/**
	 * @return string | null
	 */
	public function getStepURL($step)
	{
		$blocType = $this->getBlockTypeForStep($step);
		if ($blocType)
		{
			$lang = RequestContext::getInstance()->getLang();
			return website_BlockController::getBlockUrl($blocType, $lang, array());
		}
		return null;
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
}