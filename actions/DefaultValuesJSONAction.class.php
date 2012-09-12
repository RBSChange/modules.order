<?php
class order_DefaultValuesJSONAction extends generic_DefaultValuesJSONAction 
{	
	/**
	 * @param f_persistentdocument_PersistentDocument $document
	 * @param String[] $allowedProperties
	 * @param integer $parentId
	 * @return Array
	 */
	protected function exportFieldsData($document, $allowedProperties, $parentId = null)
	{		
		if ($document instanceof order_persistentdocument_creditnote)
		{
			if (intval($parentId))
			{
				$order = DocumentHelper::getDocumentInstance($parentId);
				if ($order instanceof order_persistentdocument_order) 
				{
					order_CreditnoteService::getInstance()->updateInitFormPropertyForOrder($document, $order);
				}
			}
		}
		return parent::exportFieldsData($document, $allowedProperties, $parentId);
	}
}