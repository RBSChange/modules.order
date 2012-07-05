<?php
/**
 * order_BlockStdShippingStepAction
 * @package modules.order.lib.blocks
 */
class order_BlockStdShippingStepAction extends website_BlockAction
{
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
	 * @return string
	 */
	public function execute($request, $response)
	{
		if ($this->isInBackoffice())
		{
			return website_BlockView::NONE;
		}
		$cart = order_CartService::getInstance()->getDocumentInstanceFromSession();
		if ($cart->isEmpty() || $cart->getCustomer() === null) {$this->redirectToCart($cart);}
		$op = $cart->getOrderProcess();
		$op->setCurrentStep('Shipping');

		$this->setRequestParams($request, $cart);
		return $this->getInputViewName();
	}

	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return string
	 */
	public function executeUpdateMode($request, $response)
	{
		$cart = order_CartService::getInstance()->getDocumentInstanceFromSession();
		$shippingFilterId = intval($request->getParameter('shippingFilterId', 0));
		if ($shippingFilterId > 0)
		{
			$shippingFilter = catalog_persistentdocument_shippingfilter::getInstanceById($shippingFilterId);
			$cart->setCurrentTestFilter($shippingFilter);
			if ($shippingFilter->getDocumentService()->isValidShippingFilter($shippingFilter, $cart))
			{
				$cart->setRequiredShippingFilter(0, $shippingFilter);
			}
			else
			{
				$this->addError(LocaleService::getInstance()->trans('m.order.standardprocess.invalid-shipping-mode'), "shippingFilters");
			}
			$cart->setCurrentTestFilter(null);
		}

		$this->setRequestParams($request, $cart);
		return $this->getInputViewName();
	}

	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return string
	 */
	public function executeNextStep($request, $response)
	{
		$cart = order_CartService::getInstance()->getDocumentInstanceFromSession();
		if ($cart->canSelectShippingModeId())
		{
			if ($request->hasParameter('shippingFilterId') || !$cart->getShippingFilterId())
			{
				$shippingFilterId = intval($request->getParameter('shippingFilterId', 0));
				if ($shippingFilterId == 0)
				{
					$this->addError(LocaleService::getInstance()->trans('m.order.standardprocess.shipping-mode-not-selected'), "shippingFilters");
				}
				else
				{
					$shippingFilter = catalog_persistentdocument_shippingfilter::getInstanceById($shippingFilterId);
					$cart->setCurrentTestFilter($shippingFilter);
					if ($shippingFilter->getDocumentService()->isValidShippingFilter($shippingFilter, $cart))
					{
						$cart->setRequiredShippingFilter(0, $shippingFilter);
					}
					else
					{
						$this->addError(LocaleService::getInstance()->trans('m.order.standardprocess.invalid-shipping-mode'), "shippingFilters");
					}
					$cart->setCurrentTestFilter(null);
				}
			}
		}

		if (!$this->hasErrors())
		{
			if (!order_ShippingModeConfigurationService::getInstance()->checkModesConfiguration($this, $request, 'nextStep'))
			{
				return null;
			}
				
			$op = $cart->getOrderProcess();
			$nextStep = $op->getNextStepForStep('Shipping');
			$op->setCurrentStep($nextStep);
			$cart->save();
				
			$url = $op->getOrderProcessURL();
			$this->redirectToUrl($url);
			exit(0);
		}

		$this->setRequestParams($request, $cart);
		return $this->getInputViewName();
	}

	/**
	 * @param f_mvc_Request $request
	 * @param order_CartInfo $cart
	 */
	protected function setRequestParams($request, $cart)
	{
		$request->setAttribute('cart', $cart);
		$requiredShippingModeIds = $cart->getRequiredShippingModeIds();
		if (count($requiredShippingModeIds))
		{
			$request->setAttribute('hasPredefinedShippingMode', true);
			$requiredShippingFilters = $cart->getRequiredShippingModes();
			$request->setAttribute('requiredShippingFilters', $requiredShippingFilters);
			if (count($requiredShippingModeIds) !== count($requiredShippingFilters))
			{
				$requiredShippingModeErrors = array();
				foreach ($requiredShippingModeIds as $id)
				{
					$error = true;
					foreach ($requiredShippingFilters as $requiredShippingFilter)
					{
						if ($requiredShippingFilter->getMode()->getId() == $id)
						{
							$error = false;
							break;
						}
					}
					if ($error)
					{
						$requiredShippingModeErrors[] = DocumentHelper::getDocumentInstance($id);
					}
				}
				$request->setAttribute('requiredShippingModeErrors', $requiredShippingModeErrors);
				$this->addError(LocaleService::getInstance()->trans('m.order.standardprocess.required-shippingmode-not-found'), "requiredShippingMode");
			}
		}

		if ($cart->canSelectShippingModeId())
		{
			$request->setAttribute('canSelectShippingMode', true);
			$shippingFilters = $this->getShippingFilters($cart);
			if (count($shippingFilters))
			{
				$request->setAttribute('shippingFilters', $shippingFilters);
			}
			else
			{
				$this->addError(LocaleService::getInstance()->trans('m.order.standardprocess.shippingmode-not-found'), "shippingFilters");
			}
			$request->setAttribute('shippingFilterId', $cart->getShippingFilterId());
		}

		if (!$this->hasErrors())
		{
			order_CartService::getInstance()->refresh($cart, false);
			$request->setAttribute('canContinue', true);
		}

		$this->setResumeInfoFromCart($request, $cart);
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

	protected $shippingFilters;

	/**
	 * @param order_CartInfo $cart
	 * @return catalog_persistentdocument_shippingfilter[]
	 */
	protected function getShippingFilters($cart)
	{
		if ($this->shippingFilters === null)
		{
			$shippingModeId = $cart->getShippingModeId();
			$inList = false;
			$results = catalog_ShippingfilterService::getInstance()->getCurrentShippingModes($cart);
			foreach ($results as $shippingFilter)
			{
				if ($shippingFilter instanceof catalog_persistentdocument_shippingfilter)
				{
					$shippingFilter->evaluateValue($cart);
					if ($shippingFilter->getMode()->getId() == $shippingModeId)
					{
						$inList = true;
					}
				}
			}
			usort($results, array($this, 'comprareShippingFilterValue'));
			if (!$inList)
			{
				$shippingFilter = (count($results)) ? $results[0] : null;
				$cart->setRequiredShippingFilter(0, $shippingFilter);
			}
			$this->shippingFilters = $results;
		}
		return $this->shippingFilters;
	}

	/**
	 * @param catalog_persistentdocument_shippingfilter $a
	 * @param catalog_persistentdocument_shippingfilter $b
	 * @return integer
	 */
	protected function comprareShippingFilterValue($a, $b)
	{
		if ($a === $b || intval($a->getValueWithoutTax()) === intval($b->getValueWithoutTax()))
		{
			return 0;
		}
		return (intval($a->getValueWithoutTax()) < intval($b->getValueWithoutTax())) ? -1 : 1;
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
			if ($address->Province) {$result[] = $address->Province;};
			if ($address->CountryId) {$result[] = DocumentHelper::getDocumentInstance($address->CountryId)->getLabel();};
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