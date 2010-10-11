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
		$query->setProjection(Projections::groupProperty('productId'), Projections::rowCount('count'));
		
		$productInstances = array();
		$productCounts = array();
		foreach ($query->find() as $row)
		{
			$productId = $row['productId'];
			if (isset($productInstances[$productId]))
			{
				$product = $productInstances[$productId];
			}
			else 
			{
				$product = DocumentHelper::getDocumentInstance($productId);
				if ($product instanceof catalog_persistentdocument_productdeclination)
				{
					$product = $product->getRelatedDeclinedProduct();
					if (in_array($product->getId(), $parameters['excludedId']))
					{
						continue;
					}
					$productInstances[$product->getId()] = $product;
				}
				$productInstances[$productId] = $product;
			}
			
			$productId = $product->getId();
			if (isset($productCounts[$productId]))
			{
				$productCounts[$productId] += intval($row['count']);
			}
			else
			{
				$productCounts[$productId] = intval($row['count']);
			}
		}
		
		arsort($productCounts);
		$result = array();
		foreach (array_slice($productCounts, 0, $parameters['maxResults'], true) as $id => $count)
		{
			$result[] = array($productInstances[$id], $count);
		}
		return $result;
	}
}