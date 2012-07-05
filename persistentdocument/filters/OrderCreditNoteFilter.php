<?php
class order_OrderCreditNoteFilter extends f_persistentdocument_DocumentFilterImpl
{
	public function __construct()
	{
		$info = new BeanPropertyInfoImpl('status', 'String');
		$info->setListId('modules_order/creditnotefilter');
		$parameter = new f_persistentdocument_DocumentFilterValueParameter($info);
		$this->setParameter('status', $parameter);
	}
	
	/**
	 * @return string
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
		switch ($this->getParameter('status')->getValueForQuery())
		{
			case 'hascreditnote':
				$query = order_OrderService::getInstance()->createQuery();
				$subQuery = $query->createCriteria('creditnote')
							->add(Restrictions::isNotNull('id'));
				return $query;			
			case 'consumecreditnote':
				$query = order_OrderService::getInstance()->createQuery();
				$subQuery = $query->createCriteria('usecreditnote')
					->add(Restrictions::isNotNull('id'));
				return $query;			
			case 'nocreditnote':
				$query = order_OrderService::getInstance()->createQuery();
				$subQuery = $query->createLeftCriteria('creditnote')
								->add(Restrictions::isNull('id'));
				return $query;	
		}
		
	}
}