<?php
/**
 * order_ReCreditNoteAction
 * @package modules.order.actions
 */
class order_ReCreditNoteAction extends f_action_BaseJSONAction
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$creditNote = order_persistentdocument_creditnote::getInstanceById($this->getDocumentIdFromRequest($request));
		$transactiontext = $request->getParameter('transactiontext');
		if (f_util_StringUtils::isEmpty($transactiontext)) {$transactiontext = null;}
		$transactiondate = $request->getParameter('transactiondate');
		$transactiondate = f_util_StringUtils::isEmpty($transactiondate) ? null : date_Converter::convertDateToGMT($transactiondate);
		$creditNote->getDocumentService()->reCreditNote($creditNote, $transactiondate, $transactiontext);
				
		return $this->sendJSON(array(
			'id' =>  $creditNote->getId(), 
			'amountNotApplied' => $creditNote->getAmountNotAppliedFormated(),
			'canReCreditNote' => ($creditNote->getAmountNotApplied() > 0.1)
		));
	}
}