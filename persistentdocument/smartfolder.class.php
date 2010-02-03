<?php
/**
 * Class where to put your custom methods for document order_persistentdocument_smartfolder
 * @package order.persistentdocument
 */
class order_persistentdocument_smartfolder extends order_persistentdocument_smartfolderbase 
{
	/**
	 * @return order_persistentdocument_order
	 */
	public function getOrders()
	{
		return $this->getDocumentService()->getOrders($this);
	}
}