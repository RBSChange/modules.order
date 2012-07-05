<?php
class order_AddressCartFilter extends f_persistentdocument_DocumentFilterImpl
{
	public function __construct()
	{
		$parameters = array();
		
		$info = new BeanPropertyInfoImpl('addrtype', 'String');
		$info->setLabelKey('type d\'adresse');
		$info->setListId('modules_order/addrtypefilter');
		$parameter = new f_persistentdocument_DocumentFilterValueParameter($info);
		$parameters['addrtype'] = $parameter;

		
		$info = new BeanPropertyInfoImpl('addrpart', 'String');
		$info->setLabelKey('partie de l\'adresse');
		$info->setListId('modules_order/addrpartfilter');
		$parameter = new f_persistentdocument_DocumentFilterValueParameter($info);
		$parameters['addrpart'] = $parameter;
		
		$info = new BeanPropertyInfoImpl('zone', BeanPropertyType::DOCUMENT, 'zone_persistentdocument_zone');
		$info->setLabelKey('zone');
		$parameter = new f_persistentdocument_DocumentFilterValueParameter($info);
		$parameters['zone'] = $parameter;	
			
		$this->setParameters($parameters);
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
			$code = $this->getCartAddress($value);
			if ($code !== null)
			{
				$param = $this->getParameter('zone');
				$zoneIds = explode(',', $param->getValue());	
				foreach ($zoneIds as $zoneId) 
				{
					try 
					{
						$zone = DocumentHelper::getDocumentInstance($zoneId, 'modules_zone/zone');
						if (zone_ZoneService::getInstance()->isValidCode($zone, $code))
						{
							return true;
						}
					}
					catch (Exception $e)
					{
						Framework::warn(__METHOD__ . " Invalid zone id $zoneId " . $e->getMessage());
					}
				}
			}
			
		}
		return false;
	}
	
	/**
	 * @param order_CartInfo $value
	 * @return string
	 */
	private function getCartAddress($value)
	{
		$addressInfo = $value->getAddressInfo();
		if ($addressInfo === null)
		{
			return null;
		}
		$addr = customer_AddressService::getInstance()->getNewDocumentInstance();
		if ($this->getParameter('addrtype')->getValue() == 'billing' && !$value->getAddressInfo()->useSameAddressForBilling)
		{
			 $value->getAddressInfo()->exportBillingAddress($addr);
		}
		else
		{
			$value->getAddressInfo()->exportShippingAddress($addr);
		}
		switch ($this->getParameter('addrpart')->getValue()) {
			case 'country':
				return $addr->getCountry() ?  $addr->getCountry()->getCode() : null;
			case 'zipCode':
				return $addr->getZipCode();
		}
		return null;
	}
}