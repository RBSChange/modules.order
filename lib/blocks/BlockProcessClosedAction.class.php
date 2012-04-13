<?php
/**
 * @package modules.order.lib.blocks
 */
class order_BlockProcessClosedAction extends website_TaggerBlockAction
{
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return String
	 */
	public function execute($request, $response)
	{
		return website_BlockView::SUCCESS;	
	}
}