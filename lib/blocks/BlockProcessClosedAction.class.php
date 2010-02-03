<?php
/**
 * @package modules.order.lib.blocks
 */
class order_BlockProcessClosedAction extends website_TaggerBlockAction
{
	/**
	 * @see website_BlockAction::execute()
	 *
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return String
	 */
	function execute($request, $response)
	{
		return website_BlockView::SUCCESS;	
	}
}