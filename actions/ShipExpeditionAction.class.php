<?php
/**
 * order_ShipExpeditionAction
 * @package modules.order.actions
 */
class order_ShipExpeditionAction extends generic_LoadJSONAction
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$expedition = $this->getExpeditionFromRequest($request);
		if ($request->hasParameter('documentproperties'))
		{
			
			$documentService = $expedition->getDocumentService();
			
			$propertiesNames = explode(',', $request->getParameter('documentproperties', ''));
			$propertiesNames[] = 'documentversion';
			
			$propertiesValue = array();
			foreach ($propertiesNames as $propertyName)
			{
				if ($request->hasParameter($propertyName))
				{
					$propertiesValue[$propertyName] = $request->getParameter($propertyName);
				}
			}
				
			uixul_DocumentEditorService::getInstance()->importFieldsData($expedition, $propertiesValue);
			
			$trackingNumber = f_util_ArrayUtils::firstElement($documentService->getTrackingNumbers($expedition));
			$packetNumber = f_util_ArrayUtils::firstElement($documentService->getPacketNumbers($expedition));		
			$documentService->shipExpedition($expedition, $expedition->getShippingDate(), $trackingNumber, $expedition->getTrackingText(), $packetNumber);
			
			$propertiesNames[] = 'id';
			$propertiesNames[] = 'lang';
			$result = $this->exportFieldsData($expedition, $propertiesNames);
		}
		elseif ($request->getParameter('cancel') === 'true')
		{
			$result = order_ExpeditionService::getInstance()->cancelExpeditionFromBo($expedition);
		}
		else
		{
			$result = order_ExpeditionService::getInstance()->shipExpeditionFromBo($expedition, 
				$request->getParameter('shippingDate'),
				$request->getParameter('trackingNumber'),
				$request->getParameter('packetNumber'));
		}
		return $this->sendJSON($result);
	}
	
	/**
	 * @param Request $request
	 * @return order_persistentdocument_expedition
	 */
	private function getExpeditionFromRequest($request)
	{
		$id = $this->getDocumentIdFromRequest($request);
		return DocumentHelper::getDocumentInstance($id, 'modules_order/expedition');
	}
}