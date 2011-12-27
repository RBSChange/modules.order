<?php
/**
 * order_GenerateExpeditionAction
 * @package modules.order.actions
 */
class order_GenerateExpeditionAction extends generic_LoadJSONAction
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$orderPreparation = $this->getDocumentInstanceFromRequest($request);
		if ($orderPreparation instanceof order_persistentdocument_orderpreparation)
		{
			if ($orderPreparation->getPublicationstatus() === 'DRAFT')
			{
				$orderPreparation->getDocumentService()->activate($orderPreparation->getId());
			}
			$order = $orderPreparation->getOrderInstance();
			order_ExpeditionService::getInstance()->createForOrderPreparation($orderPreparation);
			$this->logAction($orderPreparation);
			$result = $this->exportFieldsData($order, array('shipping'));
		}
		else
		{
			throw new Exception('Invalid document type: ' . get_class($orderPreparation));
		} 
		return $this->sendJSON($result);
	}
}