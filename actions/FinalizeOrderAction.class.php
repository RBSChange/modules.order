<?php
/**
 * order_FinalizeOrderAction
 * @package modules.order.actions
 */
class order_FinalizeOrderAction extends generic_LoadJSONAction
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$order = order_persistentdocument_order::getInstanceById($this->getDocumentIdFromRequest($request));
		order_ModuleService::getInstance()->finalizeOrder($order, true);
		$result = array('status' => $order->getOrderStatus());		
		$this->logAction($order, $result);
		$result = $this->exportFieldsData($order, array('shipping'));
		
		$creditNoteIds = order_CreditnoteService::getInstance()->createQuery()
			->add(Restrictions::eq('order', $order))
			->add(Restrictions::eq('publicationstatus', 'DRAFT'))
			->setProjection(Projections::property('id', 'id'))
			->findColumn('id');
		
		if (count($creditNoteIds))
		{
			$result['creditNoteIds'] = $creditNoteIds;
		}
		return $this->sendJSON($result);
	}
}