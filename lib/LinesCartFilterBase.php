<?php
abstract class order_LinesCartFilterBase extends f_persistentdocument_DocumentFilterImpl
{
	public function __construct()
	{
		if ($this->getDocumentModelName() == 'order/cartshipping')
		{
			$info = new BeanPropertyInfoImpl('mode', BeanPropertyType::STRING);
			$info->setListId('modules_catalog/shippingmodeoptions');
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
			$mode = $this->getParameter('mode')->getValueForQuery();
			if ($mode == 'free')
			{
				return $value->getCartLineArrayByShippingMode(null);
			}
			else if ($mode == 'current')
			{
				return $value->getCartLineArrayByShippingMode($value->getCurrentTestFilter());
			}
			else if (is_numeric($mode))
			{
				return $value->getCartLineArrayByShippingMode(DocumentHelper::getDocumentInstance($mode, 'modules_shipping/mode'));
			}
			return array();
		}
		else 
		{
			return $value->getCartLineArray();
		}
	}
}