<?php
class order_ContinueShoppingAction extends change_Action
{
	/**
	 * @param change_Context $context
	 * @param change_Request $request
	 */
	public function _execute($context, $request)
	{
		$user = $context->getUser();
		$backLink = $user->getAttribute('cartBackLink');		
		if ($backLink !== null)
		{
			$shop = $this->getDocumentInstanceFromRequest($request);
			$backLink = LinkHelper::getDocumentUrl($shop->getTopic()->getIndexPage());
		}
		$user->removeAttribute('cartBackLink');
		$context->getController()->redirectToUrl(str_replace('&amp;', '&', $backLink));

		return change_View::NONE;		
	}
	
	/**
	 * @return boolean
	 */
	public function isSecure()
	{
		return false;
	}		
}