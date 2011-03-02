<?php
/**
 * @package modules.order
 */
class order_CancelOrderJSONAction extends f_action_BaseJSONAction
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$order = $this->getDocumentInstanceFromRequest($request);
		if ($order instanceof order_persistentdocument_order)
		{
			$order->getDocumentService()->cancelOrder($order);
			$this->logAction($order);
			$propertiesNames = explode(',', $request->getParameter('documentproperties', ''));
			$propertiesNames[] = 'documentversion';
			$propertiesNames[] = 'id';
			$propertiesNames[] = 'lang';
			$data = $this->exportFieldsData($order, $propertiesNames);
			return $this->sendJSON($data); 
		}		
		else
		{
			return $this->sendJSONError('Invalid order parameter');
		}
	}
	
	/**
	 * @param f_persistentdocument_PersistentDocument $document
	 * @param String[]
	 * @return Array
	 */
	protected function exportFieldsData($document, $allowedProperties)
	{
		return uixul_DocumentEditorService::getInstance()->exportFieldsData($document, $allowedProperties);
	}
}