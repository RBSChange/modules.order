<?php
/**
 * order_BlockStdBillingStepAction
 * @package modules.order.lib.blocks
 */
class order_BlockStdBillingStepAction extends website_BlockAction
{
	/**
	 * @var integer
	 */
	protected $trivialErrorCount = 0;
	
	/**
	 * @see website_BlockAction::getInputViewName()
	 */
	public function getInputViewName()
	{
		return website_BlockView::SUCCESS;
	}
	
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return String
	 */
	public function execute($request, $response)
	{
		if ($this->isInBackoffice())
		{
			return website_BlockView::NONE;
		}	
		$cs = order_CartService::getInstance();	
		$cart =$cs->getDocumentInstanceFromSession();
		$cs->refresh($cart, false);
		
		if ($cart->isEmpty() || $cart->getCustomer() === null) {$this->redirectToCart($cart);}
		$op = $cart->getOrderProcess();
		$op->setCurrentStep('Billing');
		
		$this->setRequestParams($request, $cart);
		return $this->getInputViewName();
	}

	/**
	 * @see website_BlockAction::execute()
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return String
	 */	
	public function executeUpdateCoupon($request, $response)
	{
		$couponCode = trim(strval($request->getParameter('coupon')));
		$cs = order_CartService::getInstance();
		$cart = $cs->getDocumentInstanceFromSession();
		if ($cart->hasCoupon())
		{
			if (f_util_StringUtils::isEmpty($couponCode))
			{
				$cs->setCoupon($cart, null);
			}
		} 
		else
		{
			if (f_util_StringUtils::isNotEmpty($couponCode))
			{
				$coupon = customer_CouponService::getInstance()->getByCode($couponCode);
				$currentCoupon = $cs->setCoupon($cart, $coupon);
				if ($currentCoupon === null)
				{		
					$request->setAttribute('coupon', '');
					$this->trivialErrorCount++;
					$this->addError(LocaleService::getInstance()->transFO('m.order.standardprocess.invalid-coupon', array('ucf'), array('code' => $couponCode)), 
						'coupon');
				}
			}
		}
		
		$this->setRequestParams($request, $cart);
		return $this->getInputViewName();
	}
	
	/**
	 * @see website_BlockAction::execute()
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return String
	 */	
	public function executeNextStep($request, $response)
	{
		$cs = order_CartService::getInstance();
		$cart = order_CartService::getInstance()->getDocumentInstanceFromSession();
		$paymentFilterId = intval($request->getParameter('paymentFilterId', 0));
		if ($paymentFilterId == 0)
		{
			$this->addError(LocaleService::getInstance()->transFO('m.order.standardprocess.payment-connector-not-selected'), 'paymentFilter');
		}
		else
		{
			$paymentFilter = catalog_persistentdocument_paymentfilter::getInstanceById($paymentFilterId);
			if ($paymentFilter->getDocumentService()->isValidPaymentFilter($paymentFilter, $cart))
			{
				$cart->setBillingMode($paymentFilter->getConnector());
			}
			else
			{
				$cart->setBillingMode(null);
				$this->addError(LocaleService::getInstance()->transFO('m.order.standardprocess.invalid-payment-connector'), "paymentFilter");
			}
		}
		
		if (!$this->hasErrors())
		{
			$order = $this->generateOrderForCart($cart);
			if ($order !== null)
			{	
				$this->setRequestOrderParams($request, $cart, $order);
				return 'Payment';
			}
		}
		
		$this->setRequestParams($request, $cart);
		return $this->getInputViewName();
	}
	
	/**
	 * @param order_CartInfo $cart
	 * @return order_persistentdocument_order
	 */
	protected function generateOrderForCart($cart)
	{
		$cs = order_CartService::getInstance();
		$cs->refresh($cart, false);
		if ($cs->canOrder($cart))
		{
			return $cs->createOrder($cart);
		}
		return null;		
	}
	
	/**
	 * @param f_mvc_Request $request
	 * @param order_CartInfo $cart
	 */
	protected function setRequestParams($request, $cart)
	{
		$request->setAttribute('cart', $cart);
		$paymentFilters = catalog_PaymentfilterService::getInstance()->getCurrentPaymentConnectors($cart);
		if (count($paymentFilters))
		{
			$request->setAttribute('paymentFilters', $paymentFilters);
			$paymentFilterId = null;
			foreach ($paymentFilters as $paymentFilter) 
			{
				if ($paymentFilter instanceof catalog_persistentdocument_paymentfilter) 
				{
					if ($paymentFilter->getConnector()->getId() == $cart->getBillingModeId())
					{
						$paymentFilterId = $paymentFilter->getId();
						break;
					}
				}
			}
			
			if ($paymentFilterId === null)
			{
				$paymentFilterId = $paymentFilters[0]->getId();
				$cart->setBillingModeId($paymentFilters[0]->getConnector()->getId());
			}
			$request->setAttribute('paymentFilterId', $paymentFilterId);	
		}
		else
		{
			$cart->setBillingModeId(null);
			$this->addError(LocaleService::getInstance()->transFO('m.order.standardprocess.connector-not-found'), 'paymentFilter');
		}
		$request->setAttribute('showPriceWithTax', $cart->getShop()->getDisplayPriceWithTax());	
		$request->setAttribute('showPriceWithoutTax',  $cart->getShop()->getDisplayPriceWithoutTax());	
		$request->setAttribute('showPrice', $request->getAttribute('showPriceWithTax') || $request->getAttribute('showPriceWithoutTax')); 
		
		$this->setResumeInfoFromCart($request, $cart);
		$request->setAttribute('canContinue',  $this->canContinue());	
	}
	
	/**
	 * @return boolean
	 */
	protected function canContinue()
	{
		return (count($this->getErrors()) - $this->trivialErrorCount) == 0;
	}
	
	/**
	 * @param f_mvc_Request $request
	 * @param order_CartInfo $cart
	 */	
	protected function setResumeInfoFromCart($request, $cart)
	{
		$addressInfo = $cart->getAddressInfo();
		$request->setAttribute('billingAddress', $this->getResumeAddressInfo($addressInfo->billingAddress));
		$request->setAttribute('shippingAddress', $this->getResumeAddressInfo($addressInfo->shippingAddress));
	}
	
	/**
	 * @param order_AddressBean $address
	 * @return string
	 */
	protected function getResumeAddressInfo($address)
	{
		if ($address instanceof order_AddressBean)
		{
			$result = array();
			if ($address->Title) 
			{
				$result[] = DocumentHelper::getDocumentInstance($address->Title)->getLabel() . ' ' . $address->FirstName . ' ' .  $address->LastName;
			}
			else
			{
				$result[] = $address->FirstName . ' ' .  $address->LastName;
			}
			$result[] = $address->Addressline1;
			if ($address->Addressline2) {$result[] = $address->Addressline2;}
			if ($address->Addressline3) {$result[] = $address->Addressline3;}
			$result[] = $address->Zipcode . ' ' . $address->City;
			if ($address->Province) {$result[] = $address->Province;}
			if ($address->CountryId) {$result[] = DocumentHelper::getDocumentInstance($address->CountryId)->getLabel();}
			return $result;
		}
		return null;
	}
	
	/**
	 * @param f_mvc_Request $request
	 * @param order_CartInfo $cart
	 * @param order_persistentdocument_order $order
	 */
	protected function setRequestOrderParams($request, $cart, $order)
	{
		$request->setAttribute('orderProcess', $cart->getOrderProcess());
		$request->setAttribute('order', $order);
		$bill = order_BillService::getInstance()->initializeByOrderForPayment($order);		
		
		$request->setAttribute('showPriceWithTax', $order->getShop()->getDisplayPriceWithTax());	
		$request->setAttribute('showPriceWithoutTax',  $order->getShop()->getDisplayPriceWithoutTax());	
		$request->setAttribute('showPrice', $request->getAttribute('showPriceWithTax') || $request->getAttribute('showPriceWithoutTax')); 
		
		$this->setResumeInfoFromOrder($request, $order);
		$request->setAttribute('bill', $bill);
	}
	
	/**
	 * @param f_mvc_Request $request
	 * @param order_persistentdocument_order $order
	 */	
	protected function setResumeInfoFromOrder($request, $order)
	{
		$request->setAttribute('billingAddress', $this->getResumeOrderAddressInfo($order->getBillingAddress()));
		$request->setAttribute('shippingAddress', $this->getResumeOrderAddressInfo($order->getShippingAddress()));
	}
	
	/**
	 * @param customer_persistentdocument_address $address
	 */
	protected function getResumeOrderAddressInfo($address)
	{
		if ($address instanceof customer_persistentdocument_address)
		{
			$result = array();
			$result[] = $address->getFullName(true);
			$result[] = $address->getAddressLine1();
			if ($address->getAddressLine2()) {$result[] = $address->getAddressLine2();}
			if ($address->getAddressLine3()) {$result[] = $address->getAddressLine3();}
			$result[] = $address->getZipCode() . ' ' . $address->getCity();
			if ($address->getProvince()) {$result[] = $address->getProvince();};
			if ($address->getCountryid()) {$result[] = $address->getCountryName();}
			return $result;
		}
		return null;		
	}
	
	/**
	 * @param order_CartInfo $cart
	 */
	protected function redirectToCart($cart)
	{
		$this->redirectToUrl($cart->getCartUrl());
		exit(0);
	}
}