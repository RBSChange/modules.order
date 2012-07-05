<?php
/**
 * order_OrderScriptDocumentElement
 * @package modules.order.persistentdocument.import
 */
class order_OrderScriptDocumentElement extends import_ScriptDocumentElement
{
	/**
	 * @return order_persistentdocument_order
	 */
	protected function initPersistentDocument()
	{
		$cart = $this->getComputedAttribute("cart");
		if ($cart === null)
		{
			throw new Exception("Can not create an order without a cart");
		}
		return order_OrderService::getInstance()->createFromCartInfo($cart);
	}
	
	/**
	 * @return void
	 */
	protected function saveDocument()
	{
		$document = $this->getPersistentDocument();
		$document->save();
	}
}