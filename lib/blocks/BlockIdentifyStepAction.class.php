<?php
/**
 * @package modules.order.lib.blocks
 */
class order_BlockIdentifyStepAction extends order_BlockAbstractProcessStepAction
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
		$cartInfo = $this->getCurrentCart();
		if ($cartInfo->isEmpty())
		{
			$this->redirectToEmptyCart();
		}
		$this->setCurrentStep('Identify');
		$identifyStep = new order_IdentifyStepBean();
		$user = users_UserService::getInstance()->getCurrentFrontEndUser();
		if ($user !== null)
		{
			$identifyStep->email = $user->getLogin();
			if ($this->getConfiguration()->getAlwaysaskforpassword() == false)
			{
				$this->authentifyUser($user, $identifyStep);	
			}
		}
		$request->setAttribute('identifyStep', $identifyStep);
		return $this->getInputViewName();
	}
	
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return String
	 */
	function executeChangeAccount($request, $response)
	{
		$cartInfo = $this->getCurrentCart();
		
		users_UserService::getInstance()->authenticateFrontEndUser(null);
		$cartInfo->setCustomerId(null);
		$cartInfo->setUserId(null);
		$cartInfo->save();
		
		$this->setCurrentStep('Identify');
		$orderProcess = $this->getCurrentOrderProcess();
				
		$url = $orderProcess->getOrderProcessURL();
		HttpController::getInstance()->redirectToUrl($url);	
	}
	
	/**
	 * @param f_mvc_Request $request
	 * @param order_IdentifyStepBean $identifyStep
	 * @return array
	 */
	function getLoginInputValidationRules($request, $identifyStep)
	{
		return array(
			'email{blank:false;}',
			'password{blank:false;}',
		);
	}
	
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @param order_IdentifyStepBean $identifyStep
	 * @return String
	 */
	function executeLogin($request, $response, order_IdentifyStepBean $identifyStep)
	{
		$website = website_WebsiteModuleService::getInstance()->getCurrentWebsite();
		$user = users_UserService::getInstance()->getIdentifiedFrontendUser($identifyStep->email, $identifyStep->password, $website->getId());
		if ($user !== null)
		{
			$this->authentifyUser($user, $identifyStep);			
		}
		else
		{
			$errMsg = f_Locale::translate('&modules.order.document.identifystepbean.Login-error;');
			$this->addError($errMsg);			
		}
		return $this->execute($request, $response);
	}
	
	/**
	 * @param users_persistentdocument_websitefrontenduser $user
	 * @param order_IdentifyStepBean $identifyStep
	 */
	private function authentifyUser($user, $identifyStep)
	{
		$cartInfo = $this->getCurrentCart();
		$cartInfo->setMergeWithUserCart(false);
		users_UserService::getInstance()->authenticateFrontEndUser($user);
		$cartInfo->setUserId($user->getId());
		$customer = customer_CustomerService::getInstance()->getByUser($user);
		if ($customer === null)
		{
			$customer = customer_CustomerService::getInstance()->createNewCustomerFromUser($user);
		}
		
		if ($customer !== null)
		{
			$cartInfo->setCustomerId($customer->getId());
			$cartInfo->setUserId($user->getId());
			order_CartService::getInstance()->refresh($cartInfo, false);
			$this->redirectToNextStep();
		}
		$errMsg = f_Locale::translate('&modules.order.document.identifystepbean.CreateAccount-error;');
		$this->addError($errMsg);
	}
	
	/**
	 * @param f_mvc_Request $request
	 * @param order_IdentifyStepBean $identifyStep
	 * @return array
	 */
	function getNewAccountInputValidationRules($request, $identifyStep)
	{
		return array();
	}
	
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @param order_IdentifyStepBean $identifyStep
	 * @return String
	 */
	function executeNewAccount($request, $response, order_IdentifyStepBean $identifyStep)
	{
		$identifyStep->newemail = $identifyStep->email;	
		$request->setAttribute('identifyStep', $identifyStep);
		return $this->getCreateAccountInputViewName();
	}
	
	/**
	 * @param string $login
	 * @return boolean
	 */
	private function checkValidNewUserAccount($login)
	{
		$website = website_WebsiteModuleService::getInstance()->getCurrentWebsite();
		$user = users_UserService::getInstance()->getFrontendUserByLogin($login, $website->getId());
		if ($user !== null)
		{
			$errMsg = f_Locale::translate('&modules.order.document.identifystepbean.Useraccount-already-exist;');	
			$this->addError($errMsg);
			return false;			
		}
		return true;
	}
	
	/**
	 * @return string
	 */
	function getCreateAccountInputViewName()
	{
		return 'New';
	}
	
	/**
	 * @param f_mvc_Request $request
	 * @param order_IdentifyStepBean $identifyStep
	 * @return boolean
	 */
	function validateCreateAccountInput($request, $identifyStep)
	{
		$securityLevel = ModuleService::getInstance()->getPreferenceValue('users', 'securitylevel');
		
		$validationRules = array(
			'newemail{blank:false;email:true}',
			'firstname{blank:false}',
			'lastname{blank:false}',
			'newpassword{blank:false}',
			'newpassword{password:' . $securityLevel . '}',
			'newpasswordconfirm{blank:false}',
		);

		$ok = $this->processValidationRules($validationRules, $request, $identifyStep);
		$accountExists = users_WebsitefrontenduserService::getInstance()->getFrontendUserByLogin($identifyStep->newemail, website_WebsiteModuleService::getInstance()->getCurrentWebsite()->getId()) !== null;
		if ($accountExists)
		{
			$errMsg = f_Locale::translate('&modules.order.document.identifystepbean.Login-already-exists-error;');
			$this->addError($errMsg);
			$this->addErrorForProperty('newemail', $errMsg);	
			$ok = false;
		}
		if ($ok && $identifyStep->newpassword != $identifyStep->newpasswordconfirm)
		{
			$ok = false;
			$errMsg = f_Locale::translate('&modules.order.document.identifystepbean.Passwordconfirm-error;');
			$this->addError($errMsg);
			$this->addErrorForProperty('passwordconfirm', $errMsg);			
		}
	
		if (!$ok) 
		{
			$identifyStep->newpassword = $identifyStep->newpasswordconfirm = '';
		}
		return $ok;
	}
	
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @param order_IdentifyStepBean $identifyStep
	 * @return String
	 */
	function executeCreateAccount($request, $response, order_IdentifyStepBean $identifyStep)
	{
		$website = website_WebsiteModuleService::getInstance()->getCurrentWebsite();
		$customer = customer_CustomerService::getInstance()->createNewCustomer($website->getId(), $identifyStep->newemail, $identifyStep->firstname, $identifyStep->lastname, $identifyStep->newpassword);
		if ($customer !== null)
		{
			$user = $customer->getUser();
			$cartInfo = $this->getCurrentCart();
			$cartInfo->setMergeWithUserCart(false);
			users_UserService::getInstance()->authenticateFrontEndUser($user);			
			$cartInfo->setCustomerId($customer->getId());
			$cartInfo->setUserId($user->getId());
			$cartInfo->save();
			$this->redirectToNextStep();				
		}
		$errMsg = f_Locale::translate('&modules.order.document.identifystepbean.CreateAccount-error;');
		$this->addError($errMsg);
		$request->setAttribute('identifyStep', $identifyStep);
		return $this->getCreateAccountInputViewName();
	}
}