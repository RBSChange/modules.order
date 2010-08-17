<?php
/**
 * @package modules.order.lib.blocks
 */
class order_BlockAbstractProcessStepAction extends website_TaggerBlockAction
{
	/**
	 * @var order_CartInfo
	 */
	private $cart = null;
	
	/**
	 * @return order_CartInfo
	 */
	protected function getCurrentCart()
	{
		if ($this->cart === null)
		{
			$this->cart = order_CartService::getInstance()->getDocumentInstanceFromSession();
		}
		return $this->cart;
	}
	
	/**
	 * @see website_BlockAction::execute()
	 *
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 */
	function initialize($request, $response)
	{
		$request->setAttribute('cart', $this->getCurrentCart());	
	}
	
	protected function setCurrentStep($currentStep)
	{
		$orderProcess = order_OrderProcess::getInstance();
		$orderProcess->setCurrentStep($currentStep);
	}
	
	protected function redirectToNextStep($currentStep = null)
	{
		$orderProcess = order_OrderProcess::getInstance();
		if ($currentStep === null)
		{
			$currentStep = $orderProcess->getCurrentStep();
		}
		$nextStep = $orderProcess->getNextStepForStep($currentStep);
		if (Framework::isInfoEnabled())
		{
			Framework::info(__METHOD__ . " from : $currentStep, to : $nextStep");
		}
		$orderProcess->setCurrentStep($nextStep);
		$url = $orderProcess->getOrderProcessURL();
		HttpController::getInstance()->redirectToUrl($url);
	}
	
	protected function redirectToStep($step)
	{
		$orderProcess = order_OrderProcess::getInstance();
		$orderProcess->setCurrentStep($step);
		$url = $orderProcess->getOrderProcessURL();
		HttpController::getInstance()->redirectToUrl($url);
	}
	
	protected function redirectToFirstStep()
	{
		$orderProcess = order_OrderProcess::getInstance();
		$orderProcess->setCurrentStep(null);
		$url = $orderProcess->getOrderProcessURL();
		HttpController::getInstance()->redirectToUrl($url);
	}
	
	/**
	 * Redirect to proper page when there's nothing to buy
	 */
	protected function redirectToEmptyCart()
	{
		try
		{
			$emptyCartPage = TagService::getInstance()->getDocumentByContextualTag('contextual_website_website_modules_order_cart-empty', website_WebsiteModuleService::getInstance()->getCurrentWebsite());
			$url = LinkHelper::getUrl($emptyCartPage);
			HttpController::getInstance()->redirectToUrl(str_replace('&amp;', '&', $url));
		}
		catch ( TagException $e )
		{
			if (Framework::isWarnEnabled())
			{
				Framework::warn($e->getMessage());
			}
		}
		$url = LinkHelper::getDocumentUrl($emptyCartPage);
		HttpController::getInstance()->redirectToUrl(str_replace('&amp;', '&', $url));
	}
	
	protected function checkCurrentCustomer()
	{
		$cartInfo = $this->getCurrentCart();
		if ($cartInfo->getCustomerId())
		{
			$customer = customer_CustomerService::getInstance()->getCurrentCustomer();
			return ($customer !== null && $customer->getId() == $cartInfo->getCustomerId());
		}
		return false;
	}
}