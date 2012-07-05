<?php
/**
 * Class where to put your custom methods for document order_persistentdocument_shippingaddress
 * @package modules.order.persistentdocument
 */
class order_persistentdocument_shippingaddress extends order_persistentdocument_shippingaddressbase 
{
	/**
	 * @param f_persistentdocument_PersistentDocument $val
	 */
	public function setTargetId($val)
	{
		$val = ($val instanceof f_persistentdocument_PersistentDocument) ? $val->getId() : intval($val);
		parent::setTargetId($val);
	}
	
	/**
	 * @return f_persistentdocument_PersistentDocument
	 */
	public function getTargetIdInstance()
	{
		$val = $this->getTargetId();
		if ($val !== null)
		{
			$m = f_persistentdocument_PersistentProvider::getInstance()->getDocumentModelName($val);
			if ($m !== null)
			{
				return f_persistentdocument_PersistentProvider::getInstance()->getDocumentInstance($val, $m);
			}
		}
		return null;
	}
	
	/**
	 * @param order_persistentdocument_order $val
	 */
	public function setOrderId($val)
	{
		$val = ($val instanceof f_persistentdocument_PersistentDocument) ? $val->getId() : intval($val);
		parent::setTargetId($val);
	}
		
	/**
	 * @return order_persistentdocument_order
	 */
	public function getOrderIdInstance()
	{
		$val = $this->getOrderId();
		if ($val !== null)
		{
			$m = f_persistentdocument_PersistentProvider::getInstance()->getDocumentModelName($val);
			if ($m !== null)
			{
				return f_persistentdocument_PersistentProvider::getInstance()->getDocumentInstance($val, $m);
			}
		}
		return null;
	}
}