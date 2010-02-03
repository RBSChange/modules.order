<?php
class order_SommeProductAttributeFilter extends f_persistentdocument_DocumentFilterImpl
{
	public function __construct()
	{
		$parameters = array();
		$attributeFolder = catalog_AttributefolderService::getInstance()->getAttributeFolder();
		$attributesDef = ($attributeFolder) ? $attributeFolder->getAttributes() : array();
		
		$parameter = f_persistentdocument_DocumentFilterRestrictionParameter::getNewInstance();
		foreach ($attributesDef as $attributeDef) 
		{
			if ($attributeDef['type'] == 'text') {continue;}
			$name = $attributeDef['code'];
			$type = $attributeDef['type'] == 'text' ? 'String' : 'Double';
			$beanprop = new BeanPropertyInfoImpl($name, $type);
			$beanprop->setLabelKey($attributeDef['label']);	
			$parameter->addAllowedProperty($name, $beanprop);
			$parameter->setAllowedRestrictions($name, array('ge', 'gt', 'le', 'lt'));
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
	private function getTestVal($paremeter, $value)
	{
		$somme = 0;
		$attributeName = $paremeter->getPropertyName();
		foreach ($value->getCartLineArray() as $cartLine) 
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