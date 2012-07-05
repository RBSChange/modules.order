<?php
class order_CartInfoScriptElement extends import_ScriptObjectElement
{
	/**
	 * @var order_CartInfo
	 */
	private $cart;
	
	private $coupon;
	
	public function process()
	{
		$cart = new order_CartInfo();
		$cart->setAddressInfo(new order_ShippingStepBean());
		foreach ($this->attributes as $name => $value) 
		{
			switch ($name)
			{
				case 'id':
					break;
				case 'shop-refid':
					$cart->setShop($this->script->getElementById($value)->getPersistentDocument());
					break;
				case 'customer-refid':
					$cart->setCustomer($this->script->getElementById($value)->getPersistentDocument());
					break;
				case 'address-refid':
					$addr = $this->script->getElementById($value)->getPersistentDocument();
					$shippingBean = $cart->getAddressInfo();
					$shippingBean->importShippingAddress($addr);
					$shippingBean->importBillingAddress($addr);
					$shippingBean->useSameAddressForBilling = true;
					break;
				case 'shippingFilter-refid':
					$shippingFilter = $this->script->getElementById($value)->getPersistentDocument();
					$cart->getAddressInfo()->shippingFilterId = $shippingFilter->getId();
					$cart->setRequiredShippingFilter(0, $shippingFilter);
					break;
				case 'coupon-refid':
					$this->coupon = $this->script->getElementById($value)->getPersistentDocument();
					break;
	 			case 'billingMode-refid':
					$billingMode = $this->script->getElementById($value)->getPersistentDocument();
					$cart->setBillingMode($billingMode);
					break;
				 case 'creationdate':
					$cart->setProperties('creationdate', $value);
					break; 
				default:
					throw new Exception("Invalid attribute : $name => $value");
			}
		}
  	
		$this->cart = $cart;
	}
   
	public function endProcess()
	{
		if ($this->coupon)
		{
			 order_CartService::getInstance()->setCoupon($this->cart, $this->coupon);	
		}
	}
	
	/**
	 * @param order_CartLineInfo $cartLine
	 */
	public function addLine($cartLine)
	{
		$price = catalog_PriceService::getInstance()->getPrice($cartLine->getProduct(), $this->cart->getShop(), $this->cart->getBillingArea(), $this->cart->getCustomer(), $cartLine->getQuantity());
		if (!$price)
		{
			throw new Exception('Invalid price for product: ' . $cartLine->getProductId() . ' Qtt: ' . $cartLine->getQuantity());
		}
		$cartLine->importPrice($price);
		$this->cart->addCartLine($cartLine);
	}
	
	/**
	 * @return order_CartInfo
	 */
	public function getObject()
	{
		return $this->cart;
	}
}