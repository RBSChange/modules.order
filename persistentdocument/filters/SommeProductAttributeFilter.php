<?php
class order_SommeProductAttributeFilter extends order_LinesCartFilterBase
{
	public function __construct()
	{
		parent::__construct();
		
		$parameter = f_persistentdocument_DocumentFilterRestrictionParameter::getNewInstance();
		$attributeFolder = catalog_AttributefolderService::getInstance()->getAttributeFolder();
		$attributesDef = ($attributeFolder) ? $attributeFolder->getAttributes() : array();
		foreach ($attributesDef as $attributeDef)
		{
			/* @var $attributeDef catalog_AttributeDefinition */
			if ($attributeDef->getType() == 'text')
			{
				continue;
			}
			$name = $attributeDef->getCode();
			$beanprop = new BeanPropertyInfoImpl($name, 'Double');
			$beanprop->setLabelKey($attributeDef->getLabel());
			$parameter->addAllowedProperty($name, $beanprop);
			$parameter->setAllowedRestrictions($name, array('ge', 'gt', 'le', 'lt'));
		}
		$this->setParameter('attribute', $parameter);
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
			$param = $this->getParameter('attribute');
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
	protected function getTestVal($paremeter, $value)
	{
		$somme = 0;
		$attributeName = $paremeter->getPropertyName();
		foreach ($this->getLines($value) as $cartLine)
		{
			$product = $cartLine->getProduct();
			if ($product)
			{
				$attrs = $product->getAttributes();
				if (isset($attrs[$attributeName]))
				{
					$somme += doubleval($attrs[$attributeName]) * $cartLine->getQuantity();
				}
			}
		}
		return $somme;
	}
}