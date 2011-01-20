<?php
/**
 * @package modules.order.lib.feeders
 */
class order_OrderedTogetherProductFeeder extends catalog_ProductFeeder
{
	/**
	 * @var order_OrderedTogetherProductFeeder
	 */
	private static $instance;

	/**
	 * @return catalog_SameShelvesProductFeeder
	 */
	public static function getInstance()
	{
		if (self::$instance === null)
		{
			$finalClassName = Injection::getFinalClassName(get_class());
			self::$instance = new $finalClassName();
		}
		return self::$instance;
	}

	/**
	 * @param array<String, mixed> $parameters
	 * @return array<array>
	 */
	public function getProductArray($parameters)
	{
		$query = order_OrderlineService::getInstance()->createQuery();
		if (count($parameters['excludedId']) > 0)
		{
			$query->add(Restrictions::notin('productId', $parameters['excludedId']));
		}
		$query->createCriteria('order')->createCriteria('line')
			->add(Restrictions::eq('productId', $parameters['productId']));
			
		$query->createPropertyCriteria('productId', 'modules_catalog/product')
				->setProjection(Projections::groupProperty('id', 'productId'), Projections::rowCount('count'));
		
		$productCounts = array();
		foreach ($query->find() as $row)
		{
			$productId = $row['productId'];
			$productCounts[$productId] = intval($row['count']);
		}
		
		arsort($productCounts);
		$result = array();
		foreach (array_slice($productCounts, 0, $parameters['maxResults'], true) as $id => $count)
		{
			$result[] = array(catalog_persistentdocument_product::getInstanceById($id), $count);
		}
		return $result;
	}
}