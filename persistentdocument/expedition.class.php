<?php
/**
 * Class where to put your custom methods for document order_persistentdocument_expedition
 * @package modules.order.persistentdocument
 */
class order_persistentdocument_expedition extends order_persistentdocument_expeditionbase 
{
	/**
	 * @param string $moduleName
	 * @param string $treeType
	 * @param array<string, string> $nodeAttributes
	 */
//	protected function addTreeAttributes($moduleName, $treeType, &$nodeAttributes)
//	{
//	}
	
	/**
	 * @param string $actionType
	 * @param array $formProperties
	 */
//	public function addFormProperties($propertiesNames, &$formProperties)
//	{	
//	}

	/**
	 * @return shipping_persistentdocument_mode
	 */
	public function getShippingMode()
	{
		$shippingId = intval($this->getShippingModeId());
		if ($shippingId > 0)
		{
			try 
			{
				return DocumentHelper::getDocumentInstance($shippingId, 'modules_shipping/mode');
			}
			catch (Exception $e)
			{
				Framework::exception($e);
			}
		}
		return null;
	}
	
	
	/**
	 * @return String
	 */
	public function getStatusLabel()
	{
		$key = '&modules.order.frontoffice.status.expedition.' . ucfirst($this->getStatus()) . ';';
		return f_Locale::translate($key);
	}
	
	/**
	 * @return String
	 */
	public function getBoStatusLabel()
	{
		$key = '&modules.order.frontoffice.status.expedition.' . ucfirst($this->getStatus()) . ';';
		return f_Locale::translateUI($key);
	}
}