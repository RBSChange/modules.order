<?php
/**
 * order_BlockExpeditionDetailAction
 * @package modules.order.lib.blocks
 */
class order_BlockExpeditionDetailAction extends website_BlockAction
{
	/**
	 * @return array
	 */
	public function getRequestModuleNames()
	{
		$names = parent::getRequestModuleNames();
		if (!in_array('order', $names))
		{
			$names[] = 'order';
		}
		return $names;
	}
	
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return String
	 */
	public function execute($request, $response)
	{
		if ($this->isInBackofficeEdition())
		{
			return website_BlockView::NONE;
		}
		
		$expedition = $this->getDocumentParameter();
		if ((!$expedition instanceof order_persistentdocument_expedition) || !$expedition->isPublished())
		{
			return website_BlockView::NONE;
		}		
		$request->setAttribute('expedition', $expedition);
		$expeditionlines = $expedition->getDocumentService()->getLinesForDisplay($expedition);
		$request->setAttribute('expeditionlines', $expeditionlines);
		$request->setAttribute('order', $expedition->getOrder());
	
		return website_BlockView::SUCCESS;
	}
}