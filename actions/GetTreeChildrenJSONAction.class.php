<?php
class order_GetTreeChildrenJSONAction extends generic_GetTreeChildrenJSONAction
{
	/**
	 * @param f_persistentdocument_PersistentDocument $document
	 * @param string[] $subModelNames
	 * @param string $propertyName
	 * @return array<f_persistentdocument_PersistentDocument>
	 */
	protected function getVirtualChildren($document, $subModelNames, $propertyName)
	{
		if ($document instanceof generic_persistentdocument_folder && !($document instanceof order_persistentdocument_smartfolder))
		{
			$dateLabel = $document->getLabel();
			$matches = null;
			
			if (preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $dateLabel, $matches))
			{
				$startdate = date_Converter::convertDateToGMT($matches[0] . ' 00:00:00');
				$endate = date_Calendar::getInstance($startdate)->add(date_Calendar::DAY, 1)->toString();
				$offset = $this->getStartIndex();
				$pageSize = $this->getPageSize();
				$countQuery = order_OrderService::getInstance()->createQuery()
					->add(Restrictions::between('creationdate', $startdate, $endate))
					->setProjection(Projections::rowCount('countItems'));
				$resultCount = $countQuery->find();
				$this->setTotal(intval($resultCount[0]['countItems']));
				$query = order_OrderService::getInstance()->createQuery()
					->add(Restrictions::between('creationdate', $startdate, $endate))
					->addOrder(Order::desc('document_creationdate'))
					->setFirstResult($offset)->setMaxResults($pageSize);
				return $query->find();
			}
			return array();
		}
		else if ($document instanceof order_persistentdocument_smartfolder)
		{
			$queryIntersection = f_persistentdocument_DocumentFilterService::getInstance()->getQueryIntersectionFromJson($document->getQuery());
			$totalCount = 0;
			$result = $queryIntersection->findAtOffset($this->getStartIndex(), $this->getPageSize(), $totalCount);
			$this->setTotal($totalCount);		
			return $result;
		}
		return parent::getVirtualChildren($document, $subModelNames, $propertyName);
	}
	
	/**
	 * @param Integer $offset
	 * @param Integer $count
	 * @param Integer $totalCount 
	 * @return f_persistentdocument_PersistentDocument[]
	 */
	function findAtOffset($offset, $count, &$totalCount = null)
	{
		$ids = $this->findIds();
		$totalCount = count($ids);
		if ($totalCount || $offset >= $totalCount)
		{
			return array();
		}
		$pp = f_persistentdocument_PersistentProvider::getInstance();
		return $pp->find($pp->createQuery($this->getDocumentModel()->getName())->add(Restrictions::in("id", array_slice($ids, $offset, $count))));
	}
}
