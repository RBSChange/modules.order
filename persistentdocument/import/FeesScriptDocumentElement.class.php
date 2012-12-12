<?php
/**
 * order_FeesScriptDocumentElement
 * @package modules.order.persistentdocument.import
 */
class order_FeesScriptDocumentElement extends import_ScriptDocumentElement
{
	/**
	 * @return order_persistentdocument_fees
	 */
	protected function initPersistentDocument()
	{
		return order_FeesService::getInstance()->getNewDocumentInstance();
	}
	
	/**
	 * @return f_persistentdocument_PersistentDocumentModel
	 */
	protected function getDocumentModel()
	{
		return f_persistentdocument_PersistentDocumentModel::getInstanceFromDocumentModelName('modules_order/fees');
	}
	
	/**
	 * @param import_ScriptExecuteElement $scriptExecute
	 * @param array<string, string> $attr
	 * @throws Exception
	 */
	public function setStrategyParam($scriptExecute, $attr = null)
	{
	
		if (!is_array($attr) || !array_key_exists('name', $attr) || !array_key_exists('value', $attr))
		{
			throw new Exception(__METHOD__ . ' need params name and value');
		}
	
		$this->getPersistentDocument()->setStrategyParam($attr['name'], $attr['value']);
	
		$this->saveDocument();
	}
}