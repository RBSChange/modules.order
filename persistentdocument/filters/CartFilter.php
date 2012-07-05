<?php
class order_CartFilter extends order_LinesCartFilterBase
{
	public function __construct()
	{
		parent::__construct();
		
		$parameter = f_persistentdocument_DocumentFilterRestrictionParameter::getNewInstance();
		
		if ($this->getDocumentModelName() == 'order/cart')
		{
			$beanprop = new BeanPropertyInfoImpl('totalAmount', 'Double');
			$beanprop->setLabelKey('m.order.bo.documentfilters.cart-totalamount');
			$parameter->addAllowedProperty('totalAmount', $beanprop);
			
			$beanprop = new BeanPropertyInfoImpl('totalAmountWithTax', 'Double');
			$beanprop->setLabelKey('m.order.bo.documentfilters.cart-totalamountwithtax');
			$parameter->addAllowedProperty('totalAmountWithTax', $beanprop);
			
			$beanprop = new BeanPropertyInfoImpl('totalAmountWithoutTax', 'Double');
			$beanprop->setLabelKey('m.order.bo.documentfilters.cart-totalamountwithouttax');
			$parameter->addAllowedProperty('totalAmountWithoutTax', $beanprop);
			
			if (ModuleService::getInstance()->isInstalled('marketing'))
			{
				$beanprop = new BeanPropertyInfoImpl('coupon', 'modules_marketing/coupon');
				$beanprop->setLabelKey('m.order.bo.documentfilters.cart-coupon');
				$parameter->addAllowedProperty('coupon', $beanprop);
			}
		}
		
		$beanprop = new BeanPropertyInfoImpl('linesAmountWithTax', 'Double');
		$beanprop->setLabelKey('m.order.bo.documentfilters.cart-linesamountwithtax');
		$parameter->addAllowedProperty('linesAmountWithTax', $beanprop);
		
		$beanprop = new BeanPropertyInfoImpl('linesAmountWithoutTax', 'Double');
		$beanprop->setLabelKey('m.order.bo.documentfilters.cart-linesamountwithouttax');
		$parameter->addAllowedProperty('linesAmountWithoutTax', $beanprop);
		
		$beanprop = new BeanPropertyInfoImpl('lineCount', 'Integer');
		$beanprop->setLabelKey('m.order.bo.documentfilters.cart-linecount');
		$parameter->addAllowedProperty('lineCount', $beanprop);
		
		$beanprop = new BeanPropertyInfoImpl('productCount', 'Integer');
		$beanprop->setLabelKey('m.order.bo.documentfilters.cart-productcount');
		$parameter->addAllowedProperty('productCount', $beanprop);

		$this->setParameter('cart', $parameter);
	}
	
	/**
	 * @return string
	 */
	public static function getDocumentModelName()
	{
		return 'order/cart';
	}
	
	/**
	 * @param order_CartInfo $value
	 */
	public function checkValue($value)
	{
		if ($value instanceof order_CartInfo)
		{
			$param = $this->getParameter('cart');
			$testVal = $this->getTestVal($param, $value);
			$restriction = $param->getRestriction();
			$val = $param->getParameter()->getValue();
			return $this->evalRestriction($testVal, $restriction, $val);
		}
		return false;
	}

	/**
	 * @param f_persistentdocument_DocumentFilterRestrictionParameter $paremeter
	 * @param order_CartInfo $value
	 * @return mixed
	 */
	private function getTestVal($paremeter, $value)
	{
		switch ($paremeter->getPropertyName())
		{
			case 'coupon':
				return ($value->hasCoupon()) ? array($value->getCoupon()->getId()) : array();
			case 'totalAmount':
				return catalog_PriceFormatter::getInstance()->round($value->getTotalAmount());
			case 'totalAmountWithTax':
				return catalog_PriceFormatter::getInstance()->round($value->getTotalWithTax());
			case 'totalAmountWithoutTax':
				return catalog_PriceFormatter::getInstance()->round($value->getTotalWithoutTax());
			case 'linesAmountWithTax':
				$count = 0.0;
				foreach ($this->getLines($value) as $cartLineInfo) 
				{
					$count += $cartLineInfo->getTotalValueWithTax();
				}
				return catalog_PriceFormatter::getInstance()->round($count);
			case 'linesAmountWithoutTax':
				$count = 0.0;
				foreach ($this->getLines($value) as $cartLineInfo) 
				{
					$count += $cartLineInfo->getTotalValueWithoutTax();
				}
				return catalog_PriceFormatter::getInstance()->round($count);
			case 'lineCount':
				return count($this->getLines($value));
			case 'productCount':
				$count = 0;
				foreach ($this->getLines($value) as $cartLineInfo)
				{
					$count += $cartLineInfo->getQuantity();
				}
				return $count;
		}
		return null;
	}
}