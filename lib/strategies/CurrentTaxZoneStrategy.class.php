<?php
class order_CurrentTaxZoneStrategy 
{
	
	private $taxZones = array();
	
	public function __construct()
	{
		if (Framework::isInfoEnabled())
		{
			Framework::info(__METHOD__);
		}
	}
	
	/**
	 * @param catalog_persistentdocument_shop $shop
	 * @param order_CartInfo $cart
	 * @return string | null
	 */
	public function getCurrentTaxZone($shop, $cart)
	{
		if ($shop === null) {return null;}

		if ($cart === null)
		{
			$addressInfo = order_CartService::getInstance()->getDocumentInstanceFromSession()->getAddressInfo();
		}
		else 
		{
			$addressInfo = $cart->getAddressInfo();
		}
		
		if ($addressInfo !== null && $addressInfo->shippingAddress !== null)
		{
			$countryId = $addressInfo->shippingAddress->CountryId;
			if ($countryId)
			{	
				if (isset($this->taxZones[$countryId]))
				{
					return $this->taxZones[$countryId];
				}
				$taxZones = catalog_TaxService::getInstance()->getTaxZonesForShop($shop);
				foreach ($taxZones as $taxZone) 
				{
					$zones = catalog_TaxService::getInstance()->getZonesForTaxZone($taxZone);
					if ($this->checkCountryIdInZones($zones, $countryId))
					{
						$this->taxZones[$countryId] = $taxZone;
						return $taxZone;
					}
				}
				
				if ($cart !== null)
				{
					//Invalid country No Tax Zone defined
					if (Framework::isInfoEnabled())
					{
						Framework::info(__METHOD__ . " INVALID country $countryId for shop " . $shop->getId());
					}
					return null;
				}
			}
		}
		$taxZone = $shop->getDefaultTaxZone();
		return $shop->getDefaultTaxZone();
	}
	
	/**
	 * @param zone_persistentdocument_zone[] $zones
	 * @param integer $countryId
	 * @return boolean
	 */
	public function checkCountryIdInZones($zones, $countryId)
	{
		$results = array();
		foreach ($zones as $zone) 
		{
			foreach (zone_CountryService::getInstance()->getCountries($zone) as $country)
			{
				if ($country->getId() == $countryId)
				{
					return true;
				}
			}
		}
		return false;
	}
}