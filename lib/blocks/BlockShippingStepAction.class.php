<?php
/**
 * @package modules.order.lib.blocks
 */
class order_BlockShippingStepAction extends order_BlockAbstractProcessStepAction
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
		$this->setCurrentStep('Shipping');
		$cartInfo = $this->getCurrentCart();
		$shippingStep = $cartInfo->getAddressInfo();
		if (!$shippingStep instanceof order_ShippingStepBean)
		{
			$shippingStep = new order_ShippingStepBean();
			$customer = $cartInfo->getCustomer();
			if ($customer->getDefaultAddress())
			{
				$shippingStep->importShippingAddress($customer->getDefaultAddress());
			}
			else
			{
				$user = $customer->getUser();
				$shippingStep->shippingAddress->Email = $user->getEmail();
				$shippingStep->shippingAddress->FirstName = $user->getFirstname();
				$shippingStep->shippingAddress->LastName = $user->getLastname();
				$shippingStep->shippingAddress->Title = $user->getTitle();
			}
			$cartInfo->setAddressInfo($shippingStep);
			$cartInfo->save();
		}
		$request->setAttribute('shippingStep', $shippingStep);		
		return $this->getInputViewName();
	}
	
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @param order_ShippingStepBean $shippingStep
	 * @return String
	 */
	function executeUseRegisteredShippingAddress($request, $response, order_ShippingStepBean $shippingStep)
	{
		$addressId = intval($request->getParameter('registeredShippingAddress'));
		if ($addressId > 0)
		{
			$address = DocumentHelper::getDocumentInstance($addressId, 'modules_customer/address');
			$shippingStep->importShippingAddress($address);	
		}
	
		$addressId = intval($request->getParameter('registeredBillingAddress'));
		if ($addressId > 0)
		{
			$address = DocumentHelper::getDocumentInstance($addressId, 'modules_customer/address');
			$shippingStep->importBillingAddress($address);	
		}
		$request->setAttribute('shippingStep', $shippingStep);
		return $this->getInputViewName();
	}
	
	
	/**
	 * @param f_mvc_Request $request
	 * @param order_ShippingStepBean $shippingStep
	 */
	function getSelectInputValidationRules($request, $shippingStep)
	{
		$rules = array_merge(BeanUtils::getBeanValidationRules($shippingStep),
		BeanUtils::getSubBeanValidationRules($shippingStep, "shippingAddress"));
		if (!$shippingStep->useSameAddressForBilling)
		{
			$rules = array_merge($rules, BeanUtils::getSubBeanValidationRules($shippingStep, "billingAddress"));
		}
		return $rules;
	}
	
	/**
	 * @param f_mvc_Request $request
	 * @param order_ShippingStepBean $shippingStep
	 * @return boolean
	 */
	function validateSelectInput($request, $shippingStep)
	{
		$cartInfo = $this->getCurrentCart();
		$validationRules = $this->getSelectInputValidationRules($request, $shippingStep);
		$ok = $this->processValidationRules($validationRules, $request, $shippingStep);
		if ($shippingStep->shippingFilterId)
		{
			$shippingFilter = DocumentHelper::getDocumentInstance($shippingStep->shippingFilterId);
			if (!catalog_ShippingfilterService::getInstance()->isValidShippingFilter($shippingFilter, $cartInfo))
			{
				$shippingStep->shippingFilterId = null;
			}
		}
		
		$cartInfo->setAddressInfo($shippingStep);
		$cartInfo->save();
		
		if (!$shippingStep->shippingFilterId)
		{
			$ok = false;
			$errMsg = f_Locale::translate('&modules.order.document.shippingstepbean.ShippingMode-Error;');
			$this->addError($errMsg);
			$this->addErrorForProperty('shippingFilterId', $errMsg);		
		}
		return $ok;
	}
	
	
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @param order_ShippingStepBean $shippingStep
	 * @return String
	 */
	function executeSelect($request, $response, order_ShippingStepBean $shippingStep)
	{
		$cartInfo = $this->getCurrentCart();
		$shippingFilter = DocumentHelper::getDocumentInstance($shippingStep->shippingFilterId);
		$shippingStep->shippingModeId = $shippingFilter->getMode()->getId();
		$shippingStep->shippingTaxCode = $shippingFilter->getTaxCode();
		$shippingStep->shippingValueWithoutTax = $shippingFilter->getValueWithoutTax();
		$shippingStep->shippingvalueWithTax = $shippingFilter->getValueWithTax();
		
		$cartInfo->setAddressInfo($shippingStep);
		$cartInfo->save();
		
		$this->redirectToNextStep();
		return $this->getInputViewName();	
	}
}