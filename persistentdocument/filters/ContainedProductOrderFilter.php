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
		$info = new BeanPropertyInfoImpl('product', 'modules_catalog/product');
		$this->setParameter('product', new f_persistentdocument_DocumentFilterValueParameter($info));
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