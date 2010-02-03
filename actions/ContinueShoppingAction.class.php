<?php
class order_ContinueShoppingAction extends f_action_BaseAction
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$user = $context->getUser();
		$backLink = $user->getAttribute('cartBackLink');
		
		if (is_null($backLink))
		{
			$shop = $this->getDocumentInstanceFromRequest($request);
			$backLink = LinkHelper::getUrl($shop->getTopic()->getIndexPage());
		}
		$user->removeAttribute('cartBackLink');
		$context->getController()->redirectToUrl(str_replace('&amp;', '&', $backLink));

		return View::NONE;		
	}
	
    /**
	 * @return boolean
	 */
	function isSecure ()
    {
		return false;
    }		
	
}