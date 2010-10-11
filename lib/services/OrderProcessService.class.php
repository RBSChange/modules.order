<?php
class order_OrderProcessService extends BaseService 
{
	/**
	 * @var order_OrderProcessService
	 */
	private static $instance;
		
	/**
	 * @return order_OrderProcessService
	 */
	public static function getInstance()
	{
		if (self::$instance === null)
		{
			self::$instance = self::getServiceClassInstance(get_class());
		}
		return self::$instance;
	}

	/**
	 * @return order_OrderProcess
	 */
	public function loadFromSession()
	{
		if (isset($_SESSION['order_OrderProcess']) && $_SESSION['order_OrderProcess'] instanceof order_OrderProcess)
		{
			$orderProcess = $_SESSION['order_OrderProcess'];
		}
		else
		{
			$orderProcess = $this->getNewOrderProcessInstance();
			$this->saveToSession($orderProcess);
		}		
		return $orderProcess;
	}
	
	
	/**
	 * @param order_OrderProcess $orderProcess
	 */
	protected function resetOrderProcess($orderProcess)
	{
		$orderProcess->setCurrentStep(null);
	}	
	
	public function resetSessionOrderProcess()
	{
		$orderProcess = $this->loadFromSession();
		$this->resetOrderProcess($orderProcess);
		return $orderProcess;
	}	
	
	/**
	 * @param order_OrderProcess $orderProcess
	 */
	protected function getNewOrderProcessInstance()
	{
		return new order_OrderProcess();
	}
	
	/**
	 * @param order_OrderProcess $orderProcess
	 */
	protected function saveToSession($orderProcess)
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
	 * @param order_OrderProcess $orderProcess
	 * @param array $urlParams
	 * @return string | null
	 */
	public function getOrderProcessURL($orderProcess, $urlParams = array())
	{
		$blocType = $orderProcess->getCurrentBlockType();
		if ($blocType)
		{
			$lang = RequestContext::getInstance()->getLang();
			return website_BlockController::getBlockUrl($blocType, $lang, $urlParams);
		}
		return null;
	}

	/**
	 * @param string $step
	 * @param order_OrderProcess $orderProcess
	 * @param array $urlParams
	 * @return string | null
	 */
	public function getStepURL($step, $orderProcess, $urlParams = array())
	{
		$blocType = $orderProcess->getBlockTypeForStep($step);
		if ($blocType)
		{
			$lang = RequestContext::getInstance()->getLang();
			return website_BlockController::getBlockUrl($blocType, $lang, $urlParams);
		}
		return null;
	}	
}