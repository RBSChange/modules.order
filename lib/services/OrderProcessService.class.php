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
		$orderProcess = $this->getFromSession();
		if ($orderProcess === null)
		{
			$orderProcess = $this->getNewOrderProcessInstance();
			$this->saveToSession($orderProcess);
		}		
		return $orderProcess;
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

	
	/**
	 * @param order_CartInfo $cart
	 * @param boolean $reset
	 * @return string URL
	 */
	public function startOrderProcess($cart, $reset = false)
	{
		$default = $this->getNewOrderProcessInstance($cart);
		$orderProcess  = $this->getFromSession();
		if ($reset || $orderProcess === null || get_class($orderProcess) !== get_class($default))
		{
			$orderProcess = $default;
			$this->saveToSession($orderProcess);
		}
		return $this->getOrderProcessURL($orderProcess, null);
	}
	
	
	/**
	 * @return order_OrderProcess || null
	 */
	protected function getFromSession()
	{
		if (isset($_SESSION['order_OrderProcess']) && $_SESSION['order_OrderProcess'] instanceof order_OrderProcess)
		{
			return $_SESSION['order_OrderProcess'];
		}
		return null;
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
	 * @param order_CartInfo $cart
	 * @return order_OrderProcess
	 */
	protected function getNewOrderProcessInstance($cart = null)
	{
		$orderProcessClassName = ($cart !== null && $cart->getShop() !== null) ? $cart->getShop()->getOrderProcessClassName() : null;
		if ($orderProcessClassName == null)
		{
			$orderProcessClassName = Framework::getConfigurationValue('modules/order/orderProcess/default', 'order_OrderProcess');
		}
		return new $orderProcessClassName();
	}

}