<?php
abstract class order_LinesCartFilterBase extends f_persistentdocument_DocumentFilterImpl
{
	public function __construct()
	{
		if ($this->getDocumentModelName() == 'order/cartshipping')
		{
			$info = new BeanPropertyInfoImpl('mode', BeanPropertyType::DOCUMENT, 'shipping_persistentdocument_mode');
			$parameter = new f_persistentdocument_DocumentFilterValueParameter($info);
			$this->setParameter('mode', $parameter);
		}
	}

	/**
	 * @param order_CartInfo $value
	 * @return order_CartLineInfo[]
	 */
	protected function getLines($value)
	{
		if ($this->getDocumentModelName() == 'order/cartshipping')
		{
			$lines = array();
			foreach ($this->getParameter('mode')->getValueForQuery() as $mode)
			{
				$lines = array_merge($lines, $value->getCartLineArrayByShippingMode($mode));
			}
			return $lines;
		}
		else 
		{
			return $value->getCartLineArray();
		}
	}
}