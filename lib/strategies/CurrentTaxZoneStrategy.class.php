<?php
class order_CurrentTaxZoneStrategy 
{	
	/**
	 * @param catalog_persistentdocument_shop $shop
	 * @param order_CartInfo $cart
	 * @return string | null
	 */
	public function getCurrentTaxZone($shop, $cart)
	{
		if (Framework::isInfoEnabled())
		{
			Framework::info(__METHOD__);
		}
		if ($shop === null) {return null;}

		if ($cart === null )
		{
			if (order_CartService::getInstance()->hasCartInSession())
			{
				$addressInfo = order_CartService::getInstance()->getDocumentInstanceFromSession()->getAddressInfo();
			}
			else
			{
				$addressInfo = null;
			}
		}
		else 
		{
			$addressInfo = $cart->getAddressInfo();
		}
		$billingArea = $shop->getCurrentBillingArea();
		
		if ($addressInfo !== null && $addressInfo->shippingAddress !== null)
		{
			$countryId = intval($addressInfo->shippingAddress->CountryId);		
			$country = ($countryId > 0) ? DocumentHelper::getDocumentInstanceIfExists($countryId) : null;
			
			if ($country instanceof zone_persistentdocument_country)
			{	
				$result = null;	
				$countryCode = $country->getCode();
				$depCode = null;
				
				if ($countryCode === 'FR')
				{
					$zipcode = $addressInfo->shippingAddress->Zipcode;
					if (strlen($zipcode) === 5)
					{
						$depCode = substr($zipcode, 0, 2);
					}
					
				}
				
				//Check By Code
				$taxZones = catalog_TaxService::getInstance()->getZonesCodeForBillingArea($billingArea);
				foreach ($taxZones as $taxZone) 
				{
					if (strpos($taxZone, $countryCode) === 0)
					{
						$result = $taxZone;
					}
					
					if ($depCode == $taxZone && $countryCode == 'FR')
					{
						$result = $taxZone;
						break;
					}
				}
				
				if ($result)
				{
					return $result;
				}
				
				// Check by Country
				foreach ($taxZones as $taxZone)
				{
					$zones = zone_ZoneService::getInstance()->getZonesByCode($taxZone);
					if ($this->checkCountryIdInZones($zones, $countryId))
					{
						return $taxZone;
					}
				}
				
				if ($cart !== null)
				{
					Framework::error(__METHOD__ . " No taxzone found for country $countryCode in shop " . $shop->getId() . ' use: ' . $billingArea->getDefaultZone());
				}
			}
		}
		
		return $billingArea->getDefaultZone();
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