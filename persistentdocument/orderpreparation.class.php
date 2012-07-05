<?php
/**
 * Class where to put your custom methods for document order_persistentdocument_orderpreparation
 * @package modules.order.persistentdocument
 */
class order_persistentdocument_orderpreparation extends order_persistentdocument_orderpreparationbase 
{
	
	/**
	 * @return boolean
	 */
	public function isValid()
	{
		return parent::isValid() && $this->isLinesDataValid();
	}
	
	/**
	 * @return boolean
	 */
	protected function isLinesDataValid()
	{
		if (count($this->getLinesArray()) == 0)
		{
			$this->getValidationErrors()->rejectValue('LinesData',  
					LocaleService::getInstance()->trans('m.order.document.orderpreparation.invalid-lines-count', array('ucf')));
			return false;
		}
		return true;
	}
	
	/**
	 * @return order_persistentdocument_order
	 */
	public function getOrderInstance()
	{
		if ($this->getOrderId())
		{
			return order_persistentdocument_order::getInstanceById($this->getOrderId());
		}
		return null;
	}
	
	/**
	 * @return boolean
	 */
	public function getGenerateNumber()
	{
		return ($this->getLabel() == null);
	}

	/**
	 * @param boolean $generateNumber
	 */
	public function setGenerateNumber($generateNumber)
	{
		if ($generateNumber)
		{
			$this->setLabel(null);
		}
	}
	
	
	public function getLinesArray()
	{
		$string = $this->getLinesData();
		if ($string !== null)
		{
			return unserialize($string);
		}
		return array();		
	}
	
	public function setLinesArray($lineInfoArray)
	{
		$ds = $this->getDocumentService();
		if (is_array($lineInfoArray) && count($lineInfoArray))
		{
			$array = array();
			foreach ($lineInfoArray as $lineInfo) 
			{
				$vli = $ds->cleanUpLineInfo($lineInfo);
				if ($vli)
				{
					$array[$vli['id']] = $vli;
				}
			}
			if (count($array))
			{
				$this->setLinesData(serialize($array));
				return;
			}
		}
		$this->setLinesData(null);
	}	
	
	public function setLine($lineInfo)
	{
		$ds = $this->getDocumentService();
		$array = $this->getLinesArray();
		$vli = $ds->cleanUpLineInfo($lineInfo);
		if ($vli)
		{
			$array[$vli['id']] = $vli;
			$this->setLinesData(serialize($array));
		}
	}
	
	//USED BY BO
	
	/**
	 * @return string
	 */
	public function getAutoNumber()
	{
		return $this->getGenerateNumber() ? 'true' : 'false';
	}
	
	/**
	 * @package string $string
	 */
	public function setAutoNumber($string)
	{
		$this->setGenerateNumber(($string == 'true'));
	}
	
	public function getOplinesJSON()
	{		
		$json = array();
		$maxQuantity = array();
		$defaultMode = '????';
		if ($this->getOrderId())
		{
			$order = order_persistentdocument_order::getInstanceById($this->getOrderId());
			$maxQuantity = $this->getDocumentService()->getLinesInfosForOrder($order);
			$modeId = $order->getShippingModeId();
			if ($modeId)
			{
				try {
					$defaultMode = shipping_persistentdocument_mode::getInstanceById($modeId)->getLabel();
				} catch (Exception $e) {
					Framework::warn('Invalid Order Shipping mode id: ' . $modeId);
				}
			}
		}
		
		foreach ($this->getLinesArray() as $lineInfo) 
		{
		
			$id = $lineInfo['id'];
			$orderLine = order_persistentdocument_orderline::getInstanceById($id);
			$product = $orderLine->getProduct();
			$lineInfo['codereference'] = $product->getCodeReference();
			$lineInfo['label'] = $orderLine->getLabel();
			$lineInfo['shippingmode'] = $defaultMode;	
			$modeId = $orderLine->getShippingModeId();
			if ($modeId)
			{
				try {
					$lineInfo['shippingmode'] = shipping_persistentdocument_mode::getInstanceById($modeId)->getLabel();
				} catch (Exception $e) {
					Framework::warn('Invalid Shipping mode id: ' . $modeId);
				}
			}
			
			if (isset($maxQuantity[$id]) && !$this->isNew())
			{
				$lineInfo['maxquantity'] = $lineInfo['quantity'] + $maxQuantity[$id]['quantity'];
			}
			else
			{
				$lineInfo['maxquantity'] = $lineInfo['quantity'];
			}
			$json[$id] = $lineInfo;
		}
		
		foreach ($maxQuantity as $lineInfo)
		{
			$id = $lineInfo['id'];
			if (!isset($json[$id]))
			{
				$lineInfo['maxquantity'] = $lineInfo['quantity'];
				$lineInfo['quantity'] = 0;
				$lineInfo['shippingmode'] = $defaultMode;
				$orderLine = order_persistentdocument_orderline::getInstanceById($id);
				$modeId = $orderLine->getShippingModeId();
				if ($modeId)
				{
					try {
						$lineInfo['shippingmode'] = shipping_persistentdocument_mode::getInstanceById($modeId)->getLabel();
					} catch (Exception $e) {
						Framework::warn('Invalid Shipping mode id: ' . $modeId);
					}
				}
				$product = $orderLine->getProduct();
				$lineInfo['codereference'] = $product->getCodeReference();
				$lineInfo['label'] = $orderLine->getLabel();
				$json[$id] = $lineInfo;
			}
		}
		return JsonService::getInstance()->encode(array_values($json));
	}
	
	public function setOplinesJSON($string)
	{
		if (!empty($string))
		{
			$array = JsonService::getInstance()->decode($string);
			$this->setLinesArray($array);
		}
		else
		{
			$this->setLinesData(null);
		}
	}
}