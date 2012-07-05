<?php
/**
 * order_PrintExpeditionAction
 * @package modules.order.actions
 */
class order_PrintExpeditionAction extends change_Action
{
	/**
	 * @return boolean
	 */
	public function isSecure()
	{
		return false;
	}
	
	/**
	 * @param change_Context $context
	 * @param change_Request $request
	 */
	public function _execute($context, $request)
	{
		$expedition = $this->getDocumentInstanceFromRequest($request);
		if ($expedition instanceof order_persistentdocument_expedition)
		{
			$fpdf = new order_FPDFExpeditionGenerator($expedition);
			$fpdf->generatePDF();
		}
		return change_View::NONE;
	}
}