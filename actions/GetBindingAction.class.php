<?php
/**
 * @package modules.order
 */
class order_GetBindingAction extends change_Action
{
	/**
	 * @param change_Context $context
	 * @param change_Request $request
	 */
	public function _execute($context, $request)
	{
		header("Expires: " . gmdate("D, d M Y H:i:s", time()+28800) . " GMT");
		header('Content-type: text/xml');
	    $rq = RequestContext::getInstance();
	    $rq->setUILangFromParameter($request->getParameter('uilang'));		
	    try 
	    {
        	$rq->beginI18nWork($rq->getUILang());
			$fees = order_persistentdocument_fees::getInstanceById($request->getParameter('cmpref'));
			$xblDom = $fees->getDocumentService()->getEditParametersBinding($fees);
			echo $xblDom->saveXML();
			$rq->endI18nWork();
	    } 
	    catch (Exception  $e)
	    {
	    	$rq->endI18nWork($e);
	    	f_web_http_Header::setStatus(404);
	    	echo $e->getMessage();
	    }  
		return change_View::NONE;
	}
}
