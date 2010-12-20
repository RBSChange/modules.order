<?php
class order_DefaultValuesJSONAction extends generic_DefaultValuesJSONAction 
{	
	/**
	 * @param f_persistentdocument_PersistentDocument $document
	 * @param String[]
	 * @return Array
	 */
	protected function exportFieldsData($document, $allowedProperties)
	{		
		if ($document instanceof order_persistentdocument_creditnote)
		{
			$parentRefId = $this->getContext()->getRequest()->getParameter('parentref');
			if (intval($parentRefId))
			{
				$order = DocumentHelper::getDocumentInstance($parentRefId);
				if ($order instanceof order_persistentdocument_order) 
				{
					order_CreditnoteService::getInstance()->updateInitFormPropertyForOrder($document, $order);
				}
			}
		}
		return parent::exportFieldsData($document, $allowedProperties);
	}
}