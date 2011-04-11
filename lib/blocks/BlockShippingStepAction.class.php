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
		if ($cartInfo->isEmpty())
		{
			$this->redirectToEmptyCart();
		}
		else if (!$cartInfo->isValid())
		{
			$this->redirectToCart();
		}

		$tm = f_persistentdocument_TransactionManager::getInstance();
		try 
		{
			$tm->beginTransaction();
			// The order process is started, so init lastAbandonedOrderDate.
			$customer = $cartInfo->getCustomer();
			$lastAbandonedOrderDate = date_Calendar::getInstance()->toString();
			$customer->setLastAbandonedOrderDate($lastAbandonedOrderDate);
	
			$shippingStep = $cartInfo->getAddressInfo();
			if (!$shippingStep instanceof order_ShippingStepBean || !$shippingStep->shippingAddress->Email)
			{
				$Zipcode = null;
				$City = null;
				$CountryId = null;
				if ($shippingStep instanceof order_ShippingStepBean)
				{
					$Zipcode = $shippingStep->shippingAddress->Zipcode;
					$City = $shippingStep->shippingAddress->City;
					$CountryId = $shippingStep->shippingAddress->CountryId;
				}
				
				$shippingStep = new order_ShippingStepBean();
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
					$shippingStep->shippingAddress->Zipcode = $Zipcode;
					$shippingStep->shippingAddress->City = $City;
					$shippingStep->shippingAddress->CountryId = $CountryId;
				}
				$shippingStep->billingAddress->Email = $customer->getUser()->getEmail();
				$cartInfo->setAddressInfo($shippingStep);			
				$cartInfo->save();
			}
			
			if ($customer->isModified())
			{
				$customer->save();
			}
						
			$tm->commit();
		} 
		catch (Exception $e)
		{
			$tm->rollBack($e);
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
		$cartInfo->setAddressInfo($shippingStep);
		if ($shippingStep->shippingFilterId)
		{
			$shippingFilter = DocumentHelper::getDocumentInstance($shippingStep->shippingFilterId);
			if (!catalog_ShippingfilterService::getInstance()->isValidShippingFilter($shippingFilter, $cartInfo))
			{
				$shippingStep->shippingFilterId = null;
			}
		}
		$cartInfo->save();
		
		if ($cartInfo->canSelectShippingModeId() && !$shippingStep->shippingFilterId)
		{
			$ok = false;
			$errMsg = LocaleService::getInstance()->transFO('m.order.document.shippingstepbean.shippingmode-error', array('ucf', 'html'));
			$this->addError($errMsg);
		}
		
		if ($ok)
		{	
			if (!order_CartService::getInstance()->validateShippingAddress($cartInfo))
			{
				$ok = false;
				$errMsg = LocaleService::getInstance()->transFO('m.order.document.shippingstepbean.shipping-invalid-address', array('ucf', 'html'));
				$this->addError($errMsg);
			}
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
		
		if ($cartInfo->canSelectShippingModeId())
		{
			$shippingFilter = DocumentHelper::getDocumentInstance($shippingStep->shippingFilterId);
			$cartInfo->setRequiredShippingFilter(0, $shippingFilter);
		}
		else
		{
			$cartInfo->setRequiredShippingFilter(0, null);
		}
		
		$cartInfo->setAddressInfo($shippingStep);
		order_CartService::getInstance()->refresh($cartInfo, false);
		$this->redirectToNextStep();
		return $this->getInputViewName();	
	}
}