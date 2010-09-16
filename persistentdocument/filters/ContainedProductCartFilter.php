<?php
class order_ContainedProductCartFilter extends order_LinesCartFilterBase
{
	public function __construct()
	{
		parent::__construct();
		$info = new BeanPropertyInfoImpl('product', BeanPropertyType::DOCUMENT, 'catalog_persistentdocument_product');
		$info->setLabelKey('&modules.order.bo.documentfilters.parameter.cart-product;');
		$parameter = new f_persistentdocument_DocumentFilterValueParameter($info);
		$parameter->setCustomPropertyAttribute('product', 'dialog', 'productselector');	
		$allow = DocumentHelper::expandAllowAttribute('[modules_catalog_product],!modules_catalog_declinedproduct');
		$parameter->setCustomPropertyAttribute('product', 'allow', $allow);
		$this->setParameter('product', $parameter);

		$info = new BeanPropertyInfoImpl('quantity', BeanPropertyType::INTEGER);
		$info->setLabelKey('&modules.order.bo.documentfilters.parameter.cart-cumulative-quantity;');
		$parameter = f_persistentdocument_DocumentFilterRestrictionParameter::getNewInstance($info);
		$this->setParameter('quantity', $parameter);
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
			$quantity = 0;
			$productIds = DocumentHelper::getIdArrayFromDocumentArray($this->getParameter('product')->getValueForQuery());
			foreach ($this->getLines($value) as $line)
			{
				$productId = $line->getProductId();
				$product = $line->getProduct();
				if ($product instanceof catalog_persistentdocument_productdeclination)
				{
					$productId = $product->getRelatedDeclinedProduct()->getId();
				}
				if (in_array($productId, $productIds))
				{
					$quantity += $line->getQuantity();
				}
			}
			
			$quantityParam = $this->getParameter('quantity');
			$neededQuantity = $quantityParam->getParameter()->getValueForQuery();
			switch ($quantityParam->getRestriction())
			{
				case 'eq': return $quantity == $neededQuantity;
				case 'ge': return $quantity >= $neededQuantity;
				case 'gt': return $quantity > $neededQuantity;
				case 'le': return $quantity <= $neededQuantity;
				case 'lt': return $quantity < $neededQuantity;
				case 'ne': return $quantity != $neededQuantity;
			}
		}
		return false;
	}
}