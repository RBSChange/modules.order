<?php
class order_CustomerCartFilter extends f_persistentdocument_DocumentFilterImpl
{
	public function __construct()
	{
		$info = new BeanPropertyInfoImpl('customer', BeanPropertyType::DOCUMENT, 'customer_persistentdocument_customer');
		$info->setLabelKey('m.order.bo.documentfilters.parameter.cart-customer');
		$parameter = new f_persistentdocument_DocumentFilterValueParameter($info);
		$this->setParameter('customer', $parameter);
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
			$param = $this->getParameter('customer');
			$customer = $value->getCustomer();
			if (!($customer instanceof customer_persistentdocument_customer))
			{
				return false;
			}
			$customerId = strval($customer->getId());
			return in_array($customerId, explode(',', $param->getValue()));
		}
		return false;
	}
}