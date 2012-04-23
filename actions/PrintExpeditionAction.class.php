<?php
/**
 * order_PrintExpeditionAction
 * @package modules.order.actions
 */
class order_PrintExpeditionAction extends f_action_BaseAction
{
	/**
	 * @return boolean
	 */
	public function isSecure()
	{
		return false;
	}
	
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$expedition = $this->getDocumentInstanceFromRequest($request);
		if ($expedition instanceof order_persistentdocument_expedition)
		{
			$fpdf = new order_FPDFExpeditionGenerator($expedition);
			$fpdf->generatePDF();
		}
		return View::NONE;
	}
}