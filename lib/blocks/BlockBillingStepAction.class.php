<?php
/**
 * @package modules.order.lib.blocks
 */
class order_BlockBillingStepAction extends order_BlockAbstractProcessStepAction
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
		if ($this->isInBackoffice())
		{
			return website_BlockView::NONE;
		}
		if (!$this->checkCurrentCustomer())
		{
			$this->redirectToFirstStep();
		}
		$cartInfo = $this->getCurrentCart();
		if ($cartInfo->isEmpty())
		{
			$this->redirectToEmptyCart();
		}
		$this->setCurrentStep('Billing');
		$billingStep = $this->generateBillingStepBean($cartInfo);
		$request->setAttribute('billingStep', $billingStep);		
		return $this->getInputViewName();
	}
	
	/**
	 * @param order_CartInfo $cartInfo
	 * @return order_BillingStepBean
	 */
	private function generateBillingStepBean($cartInfo)
	{
		$billingStep = new order_BillingStepBean();
		if ($cartInfo->getBillingModeId())
		{
			$connector = $cartInfo->getBillingMode();
			foreach ($cartInfo->getPaymentConnectors() as $filter)
			{
				if ($connector === $filter->getConnector())
				{
					$billingStep->paymentFilterId = $filter->getId();
				}
			}
			
		}
		if ($cartInfo->hasCoupon())
		{
			$billingStep->coupon = $cartInfo->getCoupon()->getLabel();
			$billingStep->couponValue = $cartInfo->getCoupon()->getValueWithTax();
		}
		return $billingStep;
	}
	
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @param order_BillingStepBean $billingStep
	 * @return String
	 */
	function executeRefresh($request, $response, order_BillingStepBean $billingStep)
	{
		if ($this->applyCoupon($billingStep))
		{
			$this->getCurrentCart()->save();
		}
		$request->setAttribute('billingStep', $billingStep);
		return $this->getInputViewName();	
	}
	
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return String
	 */
	function executeRemoveCoupon($request, $response)
	{
		$cart = $this->getCurrentCart();
		if ($cart->hasCoupon())
		{
			$cart->setCoupon(null);
			$cart->save();
			$this->redirectToStep('Billing');
		}
	}
	
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @param order_BillingStepBean $billingStep
	 * @return String
	 */
	function executeSelect($request, $response, order_BillingStepBean $billingStep)
	{
		$save = $this->applyCoupon($billingStep);
		if (!$this->hasErrors())
		{
			$cart = $this->getCurrentCart();
			$cart->setBillingMode($billingStep->getPaymentFilter()->getConnector());
			$order = order_OrderService::getInstance()->createFromCartInfo($cart);
			$cart->save();
			$save = false;
			if ($order !== null)
			{	
				$this->redirectToNextStep();
			}
		}
		if ($save)
		{
				$cart->save();
		}
		$request->setAttribute('billingStep', $billingStep);
		return $this->getInputViewName();	
	}	
	
	/**
	 * @param order_BillingStepBean $billingStep
	 * @return boolean
	 */
	private function applyCoupon($billingStep)
	{
		$cartInfo = $this->getCurrentCart();
		$currentCoupon = $cartInfo->getCoupon();
		$couponCode = trim($billingStep->coupon);
		$save = false;
		if (f_util_StringUtils::isNotEmpty($couponCode)) 
		{		
			$ocs = marketing_CouponService::getInstance();
			$coupon = $ocs->getByCode($couponCode);
			if ($ocs->validateForCart($coupon, $cartInfo))
			{
				$currentCoupon = order_CartService::getInstance()->setCoupon($cartInfo, $coupon);		
				$save = true;
			}
			else
			{
				$this->addError(f_Locale::translate('&modules.order.document.billingstepbean.Invalid-coupon-code;', array('coupon' => $couponCode)));							
			}
		}
		
		//Mise à jour de l'affichage
		if ($currentCoupon)
		{
			$billingStep->coupon = $currentCoupon->getLabel();
			$billingStep->couponValue = $currentCoupon->getValueWithTax();;
		}
		else
		{
			$billingStep->coupon = null;
			$billingStep->couponValue = null;				
		}
		return $save;
	}	
}
