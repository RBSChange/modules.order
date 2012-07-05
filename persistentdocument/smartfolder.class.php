<?php
/**
 * Class where to put your custom methods for document order_persistentdocument_smartfolder
 * @package order.persistentdocument
 */
class order_persistentdocument_smartfolder extends order_persistentdocument_smartfolderbase 
{
	/**
	 * @deprecated (will be removed in 4.0) use getVirtualChildrenAt on order_SmartfolderService.
	 */
	public function getOrders()
	{
		return $this->getDocumentService()->getOrders($this);
	}
}