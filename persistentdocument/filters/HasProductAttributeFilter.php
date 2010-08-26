<?php
class order_HasProductAttributeFilter extends order_LinesCartFilterBase
{
	public function __construct()
	{
		parent::__construct();
		
		$info = new BeanPropertyInfoImpl('qtt', 'String');
		$info->setLabelKey('Quantité de produit');
		$info->setListId('modules_order/qttfilter');
		$parameter = new f_persistentdocument_DocumentFilterValueParameter($info);
		$this->setParameter('qtt', $parameter);
		
		$parameter = f_persistentdocument_DocumentFilterRestrictionParameter::getNewInstance();
		$attributeFolder = catalog_AttributefolderService::getInstance()->getAttributeFolder();
		$attributesDef = ($attributeFolder) ? $attributeFolder->getAttributes() : array();
		foreach ($attributesDef as $attributeDef)
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
		$this->setParameter('attribute', $parameter);
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
			$lines = $this->getLines($value);
			if (count($lines) == 0)
			{
				return false;
			}
			
			$qtt = $this->getProductQtt();
			$param = $this->getParameter('attribute');
			$restriction = $param->getRestriction();
			$val = $param->getParameter()->getValue();
			$count = 0;
			foreach ($lines as $line)
			{
				$testVal = $this->getTestVal($param, $line);
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
				else if ($qtt === 'ALL' && $count == count($lines))
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
	 * @param order_CartLineInfo $line
	 * @return mixed
	 */
	private function getTestVal($paremeter, $line)
	{
		$attributeName = $paremeter->getPropertyName();
		$product = $line->getProduct();
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