<?php
class order_DiscountProductCartFilter extends order_LinesCartFilterBase
{
	public function __construct()
	{
		parent::__construct();
		
		$info = new BeanPropertyInfoImpl('restriction', 'String');
		$info->setListId('modules_filter/oneallnone');
		$parameter = new f_persistentdocument_DocumentFilterValueParameter($info);
		$this->setParameter('restriction', $parameter);
		
		$info = new BeanPropertyInfoImpl('value', 'String');
		$info->setListId('modules_catalog/discountornot');
		$parameter = new f_persistentdocument_DocumentFilterValueParameter($info);
		$this->setParameter('value', $parameter);
	}
	
	/**
	 * @return string
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
			$discount = $this->getParameter('value')->getValueForQuery() == 'discount';
			$all = true;
			$one = false;
			$none = true;
			foreach ($this->getLines($value) as $line)
			{
				$oldValue = $line->getOldValueWithTax();
				if (($oldValue !== null && ($oldValue > 0)) == $discount)
				{
					$one = true;
					$none = false;
				}
				else 
				{
					$all = false;
				}
			}
			
			switch ($this->getParameter('restriction')->getValueForQuery())
			{
				case 'ALL': return $all;
				case 'ONE': return $one;
				case 'NONE': return $none;
			}
		}
		return false;
	}
}