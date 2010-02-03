<?php
class order_CartFilter extends f_persistentdocument_DocumentFilterImpl
{
	public function __construct()
	{
		$parameters = array();
		$beanprop = new BeanPropertyInfoImpl('totalAmountWithTax', 'Double');
		$beanprop->setLabelKey('&modules.order.bo.documentfilters.Cart-totalAmountWithTax;');
		$beanprop2 = new BeanPropertyInfoImpl('totalAmountWithoutTax', 'Double');
		$beanprop2->setLabelKey('&modules.order.bo.documentfilters.Cart-totalAmountWithoutTax;');
		$beanprop3 = new BeanPropertyInfoImpl('lineCount', 'Integer');
		$beanprop3->setLabelKey('&modules.order.bo.documentfilters.Cart-lineCount;');
		$beanprop4 = new BeanPropertyInfoImpl('productCount', 'Integer');
		$beanprop4->setLabelKey('&modules.order.bo.documentfilters.Cart-productCount;');
		$parameter = f_persistentdocument_DocumentFilterRestrictionParameter::getNewInstance();
		$parameter->addAllowedProperty('totalAmountWithTax', $beanprop);
		$parameter->addAllowedProperty('totalAmountWithoutTax', $beanprop2);
		$parameter->addAllowedProperty('lineCount', $beanprop3);
		$parameter->addAllowedProperty('productCount', $beanprop4);
		$parameters['cart'] = $parameter;
		$this->setParameters($parameters);
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
			case 'lineCount':
				return $value->getCartLineCount();
			case 'productCount':
				$count = 0;
				foreach ($value->getCartLineArray() as $cartLineInfo)
				{
					$count += $cartLineInfo->getQuantity();
				}
				return $count;
		}
		return null;
	}
}