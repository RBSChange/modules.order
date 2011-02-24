<?php
/**
 * Class where to put your custom methods for document order_persistentdocument_fees
 * @package modules.order.persistentdocument
 */
class order_persistentdocument_fees extends order_persistentdocument_feesbase 
{
	
	/**
	 * @var order_FeesApplicationStrategy
	 */
	private $strategyInstance;
	
	/**
	 * @return order_FeesApplicationStrategy
	 */
	public function getStrategyInstance()
	{
		if ($this->strategyInstance === null)
		{
			$this->strategyInstance = $this->getDocumentService()->getNewApplicationStrategyInstance($this->getApplicationstrategy());
			$this->strategyInstance->setFees($this);
		}
		return $this->strategyInstance;
	}
	
	/**
	 * @param String $applicationstrategy
	 * @return void
	 */	
	public function setApplicationstrategy($applicationstrategy)
	{
		parent::setApplicationstrategy($applicationstrategy);
		$this->strategyInstance = null;
	}
	
		
	/**
	 * @param string $parameters
	 */
	public function setStrategyParametersJSON($parameters)
	{
		$this->getStrategyInstance()->setParameters($parameters);
	}	
	
	/**
	 * @param string $name
	 * @return mixed
	 */
	public function getStrategyParam($name)
	{
		return $this->getS18sProperty($name);
	}
	
	/**
	 * @param string $name
	 * @param mixed $value
	 */
	public function setStrategyParam($name, $value)
	{
		return $this->setS18sProperty($name, $value);
	}
	
	/**
	 * @param string $json
	 */
	public function setImportStrategyParameters($json)
	{
		if (f_util_StringUtils::isNotEmpty($json))
		{
			$datas = JsonService::getInstance()->decode($json);
			foreach ($datas as $key => $value) 
			{
				$this->setStrategyParam($key, $value);
			}
		}
	}
	
	/**
	 * @param string
	 */
	public function getImportStrategyParameters()
	{
		$params = $this->unserializeS18s();
		return JsonService::getInstance()->encode($params);
	}	
}