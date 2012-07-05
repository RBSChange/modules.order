<?php
/**
 * order_ShipExpeditionAction
 * @package modules.order.actions
 */
class order_ShipExpeditionAction extends change_JSONAction
{
	/**
	 * @param change_Context $context
	 * @param change_Request $request
	 */
	public function _execute($context, $request)
	{
		$expedition = $this->getExpeditionFromRequest($request);
		if ($request->getParameter('cancel') === 'true')
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
	 * @param change_Request $request
	 * @return order_persistentdocument_expedition
	 */
	private function getExpeditionFromRequest($request)
	{
		$id = $this->getDocumentIdFromRequest($request);
		return DocumentHelper::getDocumentInstance($id, 'modules_order/expedition');
	}
}