<?php
/**
 * order_MostFrequentlyOrderedPublishedInShopFilter
 * @package modules.order.persistentdocument.filters
 */
class order_MostFrequentlyOrderedPublishedInShopFilter extends order_MostFrequentlyOrderedFilter
{
public function __construct()
	{
		parent::__construct();
		
		// Parameter "shop".
		$info = new BeanPropertyInfoImpl('shop', 'module_catalog/shop');
		$info->setListId('modules_catalog/shops');
		$parameter = new f_persistentdocument_DocumentFilterValueParameter($info);
		$this->setParameter('shop', $parameter);
	}
	
	/**
	 * @return f_persistentdocument_criteria_Query
	 */
	public function getQuery()
	{
		$query = order_OrderlineService::getInstance()->createQuery();
		$criteria = $query->createPropertyCriteria('productId', $this->getDocumentModelName())->add(Restrictions::published());
		$criteria->createCriteria('compiledproduct')->add(Restrictions::published())->add(Restrictions::eq('shopId', $this->getParameter('shop')->getValueForQuery()));
		$query->setProjection(Projections::groupProperty('productId'), Projections::rowCount('count'));
		$rows = $query->find();
		
		if (count($rows) === 0)
		{
			return new filter_StaticQuery($this->getDocumentModelName(), array());
		}
		
		usort($rows, array($this, "sortProducts"));
		$rows = array_slice($rows, 0, $this->getParameter('maxcount')->getValueForQuery());
		$rows = array_map(array($this, "extractId"), $rows);
		
		return catalog_ProductService::getInstance()->createQuery()->add(Restrictions::in('id', $rows));
	}
}