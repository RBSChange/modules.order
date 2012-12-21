<?php
/**
 * @package modules.order
 */
class order_BlockExpeditionAction extends website_BlockAction
{
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return String
	 */
	public function execute($request, $response)
	{
		$expedition = $this->getExpeditionDocument();
		if ($expedition === null)
		{
			return website_BlockView::NONE;
		}
		$request->setAttribute('expedition', $expedition);
		$expeditionlines = $expedition->getDocumentService()->getLinesForDisplay($expedition);
		$request->setAttribute('expeditionlines', $expeditionlines);
		return website_BlockView::SUCCESS;
	}
	
	/**
	 * @return order_persistentdocument_expedition
	 */
	private function getExpeditionDocument()
	{
		$expedition = null;
		$expeditionId = $this->findLocalParameterValue('expeditionid');
		Framework::info(__METHOD__ . ' ' . $expeditionId);
		if (intval($expeditionId) > 0)
		{
			$expedition = DocumentHelper::getDocumentInstance($expeditionId, 'modules_order/expedition');
		}
		return $expedition;
	}
}