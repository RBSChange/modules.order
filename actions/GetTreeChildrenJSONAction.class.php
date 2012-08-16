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
		if ($document->getDocumentModelName() === 'modules_generic/folder')
		{
			$dateLabel = $document->getLabel();
			$matches = null;			
			if (preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $dateLabel, $matches) && $this->getTreeType() == "wlist")
			{
				
				$startdate = date_Converter::convertDateToGMT($matches[0] . ' 00:00:00');
				$endate = date_Calendar::getInstance($startdate)->add(date_Calendar::DAY, 1)->toString();
				$offset = $this->getStartIndex();
				$pageSize = $this->getPageSize();
				$orderBy = $this->getOrderBy();
				$resultCount = 0;
				$result = order_OrderService::getInstance()->getVirtualChildrenByDate($startdate, $endate, $offset, $pageSize, $resultCount, $orderBy);
				$this->setTotal($resultCount);
				return $result;
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
