<?php
class order_CartFilter extends order_LinesCartFilterBase
{
	public function __construct()
	{
		parent::__construct();
		
		$parameter = f_persistentdocument_DocumentFilterRestrictionParameter::getNewInstance();
		
		if ($this->getDocumentModelName() == 'order/cart')
		{
			$beanprop = new BeanPropertyInfoImpl('totalAmountWithTax', 'Double');
			$beanprop->setLabelKey('&modules.order.bo.documentfilters.Cart-totalAmountWithTax;');
			$parameter->addAllowedProperty('totalAmountWithTax', $beanprop);
			
			$beanprop = new BeanPropertyInfoImpl('totalAmountWithoutTax', 'Double');
			$beanprop->setLabelKey('&modules.order.bo.documentfilters.Cart-totalAmountWithoutTax;');
			$parameter->addAllowedProperty('totalAmountWithoutTax', $beanprop);
		}
		
		$beanprop = new BeanPropertyInfoImpl('linesAmountWithTax', 'Double');
		$beanprop->setLabelKey('&modules.order.bo.documentfilters.Cart-linesAmountWithTax;');
		$parameter->addAllowedProperty('linesAmountWithTax', $beanprop);
		
		$beanprop = new BeanPropertyInfoImpl('linesAmountWithoutTax', 'Double');
		$beanprop->setLabelKey('&modules.order.bo.documentfilters.Cart-linesAmountWithoutTax;');
		$parameter->addAllowedProperty('linesAmountWithoutTax', $beanprop);
		
		$beanprop = new BeanPropertyInfoImpl('lineCount', 'Integer');
		$beanprop->setLabelKey('&modules.order.bo.documentfilters.Cart-lineCount;');
		$parameter->addAllowedProperty('lineCount', $beanprop);
		
		$beanprop = new BeanPropertyInfoImpl('productCount', 'Integer');
		$beanprop->setLabelKey('&modules.order.bo.documentfilters.Cart-productCount;');
		$parameter->addAllowedProperty('productCount', $beanprop);

		$this->setParameter('cart', $parameter);
	}
	
	/**
	 * @return String
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
			case 'totalAmountWithTax':
				return $value->getTotalWithTax();
			case 'totalAmountWithoutTax':
				return $value->getTotalWithoutTax();
			case 'linesAmountWithTax':
				$count = 0.0;
				foreach ($this->getLines($value) as $cartLineInfo) 
				{
					$count += $cartLineInfo->getTotalValueWithTax();
				}
				return $count;
			case 'linesAmountWithoutTax':
				$count = 0.0;
				foreach ($this->getLines($value) as $cartLineInfo) 
				{
					$count += $cartLineInfo->getTotalValueWithoutTax();
				}
				return $count;
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