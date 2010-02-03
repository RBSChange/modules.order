<?php
class order_HasProductAttributeFilter extends f_persistentdocument_DocumentFilterImpl
{
	private $attributesDef;
	
	public function __construct()
	{
		$parameters = array();
		$info = new BeanPropertyInfoImpl('qtt', 'String');
		$info->setLabelKey('Quantité de produit');
		$info->setListId('modules_order/qttfilter');
		$parameter = new f_persistentdocument_DocumentFilterValueParameter($info);
		$parameters['qtt'] = $parameter;
		
		$attributeFolder = catalog_AttributefolderService::getInstance()->getAttributeFolder();
		$this->attributesDef = ($attributeFolder) ? $attributeFolder->getAttributes() : array();	
		$parameter = f_persistentdocument_DocumentFilterRestrictionParameter::getNewInstance();
		foreach ($this->attributesDef as $attributeDef) 
		{
			$name = $attributeDef['code'];
			$type = $attributeDef['type'] == 'text' ? 'String' : 'Double';
			$beanprop = new BeanPropertyInfoImpl($name, $type);
			$beanprop->setLabelKey($attributeDef['label']);	
			$parameter->addAllowedProperty($name, $beanprop);
			if ($type == 'Double')
			{
				$parameter->setAllowedRestrictions($name, array('eq', 'ne', 'ge', 'gt', 'le', 'lt'));
			}
			else
			{
				$parameter->setAllowedRestrictions($name, array('eq', 'ne'));
			}
		}
		$parameters['attribute'] = $parameter;		
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
			$lines = $value->getCartLineArray();
			if (count($lines) == 0) {return false;}
			
			$qtt = $this->getProductQtt();
			$param = $this->getParameter('attribute');
			$restriction = $param->getRestriction();
			$val = $param->getParameter()->getValue();
			$count = 0;
			foreach ($lines as $cartLine) 
			{
				$testVal = $this->getTestVal($param, $cartLine);
				if ($this->evalRestriction($testVal, $restriction, $val))
				{
					$count++;
				}
			}
			
			if ($count > 0)
			{
				if ($qtt === 'ONE')
				{
					return true;
				}
				elseif ($qtt === 'ALL' && $count == count($lines))
				{
					return true;
				}
			}
			else if ($qtt === 'NONE')
			{
				return true;
			}
		}
		return false;
	}
	
	/**	
	 * @return string ONE | ALL | NONE
	 */
	private function getProductQtt()
	{
		return $this->getParameter('qtt')->getValueForQuery();
	}
	
	/**
	 * @param f_persistentdocument_DocumentFilterRestrictionParameter $paremeter
	 * @param order_CartLineInfo $cartLine
	 * @return mixed
	 */
	private function getTestVal($paremeter, $cartLine)
	{
		$attributeName = $paremeter->getPropertyName();
		$product = $cartLine->getProduct();
		if ($product)
		{
			$attrs = $product->getAttributes();
			if (isset($attrs[$attributeName]))
			{
				return $attrs[$attributeName];
			}
		}
		return null;
	}
}