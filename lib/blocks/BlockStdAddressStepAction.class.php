<?php
/**
 * order_BlockStdAddressStepAction
 * @package modules.order.lib.blocks
 */
class order_BlockStdAddressStepAction extends website_BlockAction
{
	
	/**
	 * @see website_BlockAction::getInputViewName()
	 */
	public function getInputViewName()
	{
		return website_BlockView::SUCCESS;
	}
		
	/**
	 * @see website_BlockAction::execute()
	 *
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
		$cart = order_CartService::getInstance()->getDocumentInstanceFromSession();
		if ($cart->isEmpty()) {$this->redirectToCart($cart);}
		
		$op = $cart->getOrderProcess();
		$op->setCurrentStep('Address');
		if ($cart->getAddressInfo() === null)
		{
			$addressInfo = new order_ShippingStepBean();
			$cart->setAddressInfo($addressInfo);
		}
		
		if ($cart->getUser() === null)
		{
			$this->setAuthenticateRequestParams($request, $cart);
			return 'Authenticate';
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
	public function executeAuthenticate($request, $response)
	{
		$validationRules = array('email{blank:false;email:true}');
		$valid = $this->processValidationRules($validationRules, $request, null);
		$cart = order_CartService::getInstance()->getDocumentInstanceFromSession();
		if ($valid)
		{
			$login = $request->getParameter('email');
			$password = $request->getParameter('password', '');
			$website = $this->getContext()->getWebsite();
			
			$cart->setUserId(null);
			$cart->setCustomerId(null);
			$wfus = users_UserService::getInstance();
			
			$user = $wfus->getIdentifiedUser($login, $password, $website->getGroup()->getId());
			if ($user === null)
			{
				$error = LocaleService::getInstance()->transFO('m.order.standardprocess.invalid-account', array('ucf'));
				$this->addError($error);
				$this->addErrorForProperty('email', $error);
				$valid = false;
			}
			
			if ($valid && $user)
			{
				$cart->setUserId($user->getId());
				$cart->setMergeWithUserCart(false);
				$cart->setAddressInfo(new order_ShippingStepBean());
				$wfus->authenticate($user);
			}
		}
		
		if (!$valid)
		{
			$this->setAuthenticateRequestParams($request, $cart);
			return 'Authenticate';
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
	public function executeUseRegistered($request, $response)
	{
		$cart = order_CartService::getInstance()->getDocumentInstanceFromSession();
		$addressId = intval($request->getParameter('billing-registered', 0));
		if ($addressId > 0)
		{
			$address = customer_persistentdocument_address::getInstanceById($addressId);
			$addressInfo = $cart->getAddressInfo();
			$addressInfo->billingAddress->import($address);
			$this->exportAddressToRequest($addressInfo->billingAddress, $request, 'billing');
			if (intval($request->getParameter('shipping-usesameaddress', 2)) == 1)
			{
				$addressInfo->shippingAddress->import($address);
				$this->exportAddressToRequest($addressInfo->shippingAddress, $request, 'shipping');
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
		$cart = order_CartService::getInstance()->getDocumentInstanceFromSession();
		$validationRules = array('billing-firstname{blank:false}', 'billing-lastname{blank:false}', 'billing-addressline1{blank:false}', 
			'billing-zipcode{blank:false}', 'billing-city{blank:false}', 'billing-country{blank:false}');
		
		$sameAddress = intval($request->getParameter('shipping-usesameaddress', 2)) == 1;
		if (!$sameAddress)
		{
			$validationRules = array_merge($validationRules, array('shipping-firstname{blank:false}', 'shipping-lastname{blank:false}', 'shipping-addressline1{blank:false}',
			'shipping-zipcode{blank:false}', 'shipping-city{blank:false}', 'shipping-country{blank:false}'));
		}
		if ($cart->getUserId() === null)
		{
			$email = $request->getParameter('email');
			$validationRules[] = 'email{blank:false;email:true}';
			$generatePassword = intval($request->getParameter('password-generate', 0)) == 1;
			if (!$generatePassword)
			{
				$securityLevel = ModuleService::getInstance()->getPreferenceValue('users', 'securitylevel');
				$validationRules[] = 'password{blank:false;password:' . $securityLevel . '}';
				$validationRules[] = 'password-validate{blank:false}';
			}
		}
		else
		{
			$email = $cart->getUser()->getEmail();
		}
		
		$valid = $this->processValidationRules($validationRules, $request, null);		
		if ($valid)
		{
			$this->importRequestInAddress($request, $cart->getAddressInfo()->billingAddress, 'billing');
			$cart->getAddressInfo()->billingAddress->Email = $email;
			if ($sameAddress)
			{
				$this->importRequestInAddress($request, $cart->getAddressInfo()->shippingAddress, 'billing');
				$cart->getAddressInfo()->shippingAddress->Email = $email;
				$cart->getAddressInfo()->useSameAddressForBilling = true;
			}
			else
			{
				$this->importRequestInAddress($request, $cart->getAddressInfo()->shippingAddress, 'shipping');
				$cart->getAddressInfo()->shippingAddress->Email = $email;
				$cart->getAddressInfo()->useSameAddressForBilling = false;
			}
			
			if ($cart->getUserId() === null)
			{
				$website = $this->getContext()->getWebsite();
				$user = users_UserService::getInstance()->getFrontendUserByLogin($email, $website->getId());
				if ($user !== null)
				{
					$error = LocaleService::getInstance()->transFO('m.order.standardprocess.invalid-account', array('ucf'));
					$this->addError($error);
					$this->addErrorForProperty('email', $error);
					$valid = false;
				}
				elseif (!$generatePassword)
				{
					$password = $request->getParameter('password');
					$passwordValidate = $request->getParameter('password-validate');
					if ($password !== $passwordValidate)	
					{
						$error = LocaleService::getInstance()->transFO('m.order.standardprocess.invalid-password-validate', array('ucf'));
						$this->addError($error);
						$this->addErrorForProperty('password-validate', $error);
						$valid = false;
					}
				}
				else
				{
					$password = null;
				}
				
				if ($valid)
				{
					$user = order_OrderProcessService::getInstance()->createNewUser($website, $email, $password, $cart->getAddressInfo()->billingAddress);
					if ($user === null)
					{
						$error = LocaleService::getInstance()->transFO('m.order.standardprocess.invalid-account', array('ucf'));
						$this->addError($error);
						$this->addErrorForProperty('email', $error);
						$valid = false;						
					}
					else
					{
						$cart->setUserId($user->getId());
						$cart->setMergeWithUserCart(false);
						users_UserService::getInstance()->authenticateFrontEndUser($user);
					}
				}
			}
			
			if ($valid && $cart->getCustomerId() === null)
			{
				$customer = customer_CustomerService::getInstance()->createNewCustomerFromUser($cart->getUser());
				if ($customer !== null)
				{
					$cart->setCustomer($customer);
				}
				else
				{
					$error = LocaleService::getInstance()->transFO('m.order.standardprocess.invalid-account', array('ucf'));
					$this->addError($error);
					$this->addErrorForProperty('email', $error);
					$valid = false;	
				}
			}
			
			$cas = customer_AddressService::getInstance();
			if ($valid && $cas->getDefaultByCustomer($cart->getCustomer()) === null)
			{
				$defaultAddress = $cas->getNewDocumentInstance();
				$cart->getAddressInfo()->billingAddress->export($defaultAddress);
				$defaultAddress->setLabel(LocaleService::getInstance()->transFO('m.order.standardprocess.default-address', array('ucf')));
				$cart->getCustomer()->addAddress($defaultAddress);
				$cart->getCustomer()->save();
			}	
		}
		
		if ($valid)
		{
			$op = $cart->getOrderProcess();
			$nextStep = $op->getNextStepForStep('Address');
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
	protected function setAuthenticateRequestParams($request, $cart)
	{
		$request->setAttribute('cart', $cart);	
	}	
	
	/**
	 * @param f_mvc_Request $request
	 * @param order_CartInfo $cart
	 */
	protected function setRequestParams($request, $cart)
	{
		$request->setAttribute('cart', $cart);	
		$user = $cart->getUser();
		$customer = $cart->getCustomer();
		$email = $request->getParameter('email', ($user	!== null) ? $user->getEmail() : null); 
		$addressInfo = $cart->getAddressInfo();
		if ($addressInfo->billingAddress->Email === null)
		{
			$addressInfo->billingAddress->Email = $email;
			$defaultAddress = customer_AddressService::getInstance()->getDefaultByCustomer($customer);
			if ($defaultAddress)
			{
				$request->setAttribute('billing-registered', $defaultAddress->getId());
				$addressInfo->billingAddress->import($defaultAddress);
			}
			else if ($user) 
			{
				$addressInfo->billingAddress->FirstName = $user->getFirstname();
				$addressInfo->billingAddress->LastName = $user->getLastname();
				$addressInfo->billingAddress->Title = $user->getTitleid();
			}
			$addressInfo->shippingAddress = clone($addressInfo->billingAddress);
		}
		
		if (!$request->hasParameter('submited'))	
		{
			if ($user === null)
			{	
				$request->setAttribute('password-generate', '1');
			}
			
			$request->setAttribute('shipping-usesameaddress', $addressInfo->useSameAddressForBilling ? '1' : '2');
			
			$this->exportAddressToRequest($addressInfo->billingAddress, $request, 'billing');
			$this->exportAddressToRequest($addressInfo->shippingAddress, $request, 'shipping');
		}
		
		//Refresh Cart
		order_CartService::getInstance()->refresh($cart, false);
		
		if ($customer && $customer->getAddressCount())
		{
			$registeredAddressList = array();
			foreach ($customer->getAddressArray() as $address) 
			{
				$registeredAddressList[$address->getId()] = $address->getLabel();
			}
			$request->setAttribute('registeredAddressList', $registeredAddressList);
		}
	}

	/**
	 * @return array
	 */
	protected function getAddressParams()
	{
		return array('civility' => 'Title', 'firstname' => 'FirstName', 'lastname' => 'LastName', 
			 'addressline1' => 'Addressline1', 'addressline2' => 'Addressline2', 'addressline3' => 'Addressline3',
			 'zipcode' => 'Zipcode', 'city' => 'City', 'province' => 'Province', 'country' => 'CountryId',
			 'phone' => 'Phone'); 
			 //'company' => 'Company', 'email' => 'Email','fax' => 'Fax');
	}
	
	/**
	 * @param order_AddressBean $address
	 * @param f_mvc_Request $request
	 * @param string $type
	 */
	protected function exportAddressToRequest($address, $request, $type)
	{
		$addressParams = $this->getAddressParams();
		foreach ($addressParams as $requestName => $propertyName) 
		{
			$request->setAttribute($type . '-' . $requestName, $address->{$propertyName});
		}
	}
	
	/**
	 * @param f_mvc_Request $request
	 * @param order_AddressBean $address
	 * @param string $type
	 */
	protected function importRequestInAddress($request, $address, $type)
	{
		$addressParams = $this->getAddressParams();
		foreach ($addressParams as $requestName => $propertyName) 
		{
			$value = $request->getParameter($type . '-'. $requestName, '');
			if ($value !== null && is_string($value))
			{
				$address->{$propertyName} = f_util_StringUtils::isEmpty($value) ? null : $value;
			}
		}
	}
	
	
	/**
	 * @param order_CartInfo $cart
	 */
	protected function redirectToCart($cart)
	{
		$website = website_WebsiteService::getInstance()->getCurrentWebsite();
		$page = null;
		if ($cart->isEmpty())
		{
			$page = TagService::getInstance()->getDocumentByContextualTag('contextual_website_website_modules_order_cart-empty', $website, false);
		}
		if ($page === null)
		{
			$page = TagService::getInstance()->getDocumentByContextualTag('contextual_website_website_modules_order_cart', $website, true);
		}
		$url = LinkHelper::getDocumentUrl($page === null ? $website : $page);
		$this->redirectToUrl($url);
		exit(0);
	}
}