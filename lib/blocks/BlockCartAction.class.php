<?php
class order_BlockCartAction extends website_BlockAction
{
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return string
	 */
	public function execute($request, $response)
	{
		if ($this->isInBackoffice())
		{
			return website_BlockView::DUMMY;
		}
		
		$cs =  order_CartService::getInstance();
		$cart = $cs->getDocumentInstanceFromSession();


		
		$shop = $cart->getShop();
		if ($shop === null)
		{
			$shop = catalog_ShopService::getInstance()->getCurrentShop();
			if ($shop === null)
			{
				return null;
			}
		}
		
		// Any cart validation error.
		$cs->refresh($cart);		
		$pageId = $this->getContext()->getId();

		$request->setAttribute('shop', $shop);
		$hrefShop = LinkHelper::getDocumentUrl($shop->getTopic()->getIndexPage());
		$request->setAttribute('hrefShop', $hrefShop);

		// 1st case: Cart is empty
		// 		redirect to the 'cart-empty' tagged page, or, if this one does not exist
		// 		return the dummy view.
		if (($cart->getCartLineCount() == 0))
		{
			if ($this->getConfiguration()->getUseCartEmptyPage())
			{
				$emptyCartPage = TagService::getInstance()->getDocumentByContextualTag(
					'contextual_website_website_modules_order_cart-empty',
					website_WebsiteService::getInstance()->getCurrentWebsite(),
					false
				);
				if ($emptyCartPage !== null && $emptyCartPage->getId() != $pageId)
				{
					$url = LinkHelper::getDocumentUrl($emptyCartPage);
					change_Controller::getInstance()->redirectToUrl(str_replace('&amp;', '&', $url));
				}
			}
			$shop = catalog_ShopService::getInstance()->getCurrentShop();
			$request->setAttribute('shop', $shop);
			return website_BlockView::DUMMY;
		}

		// Cart is not empty.
		$request->setAttribute('updateActionUrl', LinkHelper::getActionUrl('order', 'UpdateCart', array('pageref' => $pageId)));
		$request->setAttribute('pageref', $pageId);
		$request->setAttribute('cart', $cart);

		$ms = order_ModuleService::getInstance();
		$request->setAttribute('orderProcess', array(
			'open' => $ms->isProcessOpen(),
			'message' => $ms->getProcessClosedMessage()
		));

		// Backlink.
		$cbLink = $this->getStorage()->readForUser('cartBackLink');
		if (!$cbLink)
		{
			if (isset($_SERVER['HTTP_REFERER']))
			{
				$href = $_SERVER['HTTP_REFERER'];
				$index = strpos($href, '?');
				if ($index > 0)
				{
					$href = substr($href, 0, $index);
				}
				$this->getStorage()->writeForUser('cartBackLink', $href);
			}
		}
		$request->setAttribute('addressInfo', $cart->getAddressInfo());
		return website_BlockView::SUCCESS;
	}
	
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return string
	 */
	public function executeOrder($request, $response)
	{
		$cart = order_CartService::getInstance()->getDocumentInstanceFromSession();
		if (!$this->getConfiguration()->getShowAcceptationCheckbox() || $request->getParameter('cgv'))
		{
			$cart->refresh();
			if ($cart->isValid())
			{
				$url = order_OrderProcessService::getInstance()->startOrderProcess($cart);
				if ($url)
				{
					change_Controller::getInstance()->redirectToUrl($url);
				}
			}
		}
		else
		{
			$this->addError(LocaleService::getInstance()->trans('m.order.frontoffice.must-agree-with-general-sales-conditions-error', array('ucf')), 'cgv');
		}
		return $this->execute($request, $response);
	}
	
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return string
	 */
	public function executeRefresh($request, $response)
	{
		return $this->execute($request, $response);
	}	
	
	/**
	 * @param f_mvc_Request $request
	 * @return array 
	 */
	public function getEvaluateshippingBeanInfo($request)
	{
		return array('className' => 'order_ShippingStepBean', 'beanName' => 'bean');
	}
	
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @param order_ShippingStepBean $bean
	 * @return string
	 */
	public function executeEvaluateshipping($request, $response, $bean)
	{
		$cs =  order_CartService::getInstance();
		$cart = $cs->getDocumentInstanceFromSession();
		$addressInfo = $cart->getAddressInfo();
		if ($addressInfo === null)
		{
			$cart->setAddressInfo($bean);
		}
		else
		{
			$addressInfo->shippingAddress->Zipcode = $bean->shippingAddress->Zipcode;
			$addressInfo->shippingAddress->City = $bean->shippingAddress->City;
			$addressInfo->shippingAddress->CountryId = $bean->shippingAddress->CountryId;
		}
		return $this->execute($request, $response);
	}
	
	/**
	 * @see website_BlockAction::execute()
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return string
	 */	
	public function executeUpdateCoupon($request, $response)
	{
		$cs = order_CartService::getInstance();
		$cart = $cs->getDocumentInstanceFromSession();
		if ($cart->hasCoupon())
		{
			$cs->setCoupon($cart, null);
		} 
		else
		{
			$couponCode = trim(strval($request->getParameter('coupon')));
			if (f_util_StringUtils::isNotEmpty($couponCode))
			{
				$coupon = customer_CouponService::getInstance()->getByCode($couponCode);
				$currentCoupon = $cs->setCoupon($cart, $coupon);
				if ($currentCoupon === null)
				{		
					$request->setAttribute('coupon', '');
					$this->addError(LocaleService::getInstance()->trans('m.order.standardprocess.invalid-coupon', array('ucf', 'html'), array('code' => $couponCode)), 
						'coupon');
				}
			}
		}
		return $this->execute($request, $response);
	}
	
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return string
	 */
	public function executeConfirmClear($request, $response)
	{
		return 'ConfirmClear';
	}
	
	/**
	 * @param f_mvc_Request $request
	 * @param f_mvc_Response $response
	 * @return string
	 */
	public function executeClear($request, $response)
	{
		$cs = order_CartService::getInstance();
		$cart = $cs->getDocumentInstanceFromSession();
		$cs->clearCart($cart);
		$params = $request->getParameters();
		unset($params['website_BlockAction_submit']);
		unset($params['message']);
		$url = LinkHelper::getActionUrl($params['addToCartModule'], $params['addToCartAction'], $params);
		change_Controller::getInstance()->redirectToUrl($url);
	}
}