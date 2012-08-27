<?php
class order_ContainedProductOrderFilter extends f_persistentdocument_DocumentFilterImpl
{
	/**
	 * @return string[]
	 */
	public static function getAliases()
	{
		return array('featurepacka_ContainedProductOrderFilter');
	}
	
	public function __construct()
	{
		$info = new BeanPropertyInfoImpl('product', BeanPropertyType::DOCUMENT, 'catalog_persistentdocument_product');
		$parameter = new f_persistentdocument_DocumentFilterValueParameter($info);
		$parameter->setCustomPropertyAttribute('product', 'dialog', 'productselector');
		$allow = DocumentHelper::expandAllowAttribute('[modules_catalog_product],[modules_catalog_declinedproduct]');
		$parameter->setCustomPropertyAttribute('product', 'allow', $allow);
		$this->setParameter('product', $parameter);
	}
	
	/**
	 * @return String
	 */
	public static function getDocumentModelName()
	{
		return 'modules_order/order';
	}
	
	/**
	 * @return f_persistentdocument_criteria_Query
	 */
	public function getQuery()
	{
		$products = $this->getParameter('product')->getValueForQuery();
		$productIds = array();
		foreach ($products as $product)
		{
			if ($product instanceof catalog_persistentdocument_declinedproduct)
			{
				$productIds = array_merge($productIds, DocumentHelper::getIdArrayFromDocumentArray($product->getDeclinationArray()));
			}
			else
			{
				$productIds[] = $product->getId();
			}
		}
		$query = order_OrderService::getInstance()->createQuery();
		$criteria1 = $query->createCriteria('line');
		$criteria1->add(Restrictions::in('productId', $productIds));
		return $query;
	}
}