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
		if ($document instanceof generic_persistentdocument_folder && 
			!($document instanceof order_persistentdocument_smartfolder))
		{
			$dateLabel = $document->getLabel();
			$matches = null;			
			if (preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $dateLabel, $matches) && $this->getTreeType() == "wlist")
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
					->addOrder(Order::desc('id'))
					->setFirstResult($offset)->setMaxResults($pageSize);
				return $query->find();
			}
			else
			{
				return generic_FolderService::getInstance()->createQuery()
				->add(Restrictions::childOf($document->getId()))
				->addOrder(Order::desc('label'))
				->find();
			}
			return array();
		}
		return parent::getVirtualChildren($document, $subModelNames, $propertyName);
	}
}
