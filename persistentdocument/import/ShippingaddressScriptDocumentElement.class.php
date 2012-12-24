<?php
/**
 * order_ShippingaddressScriptDocumentElement
 * @package modules.order.persistentdocument.import
 */
class order_ShippingaddressScriptDocumentElement extends import_ScriptDocumentElement
{
	/**
	 * @return order_persistentdocument_shippingaddress
	 */
	protected function initPersistentDocument()
	{
		return order_ShippingaddressService::getInstance()->getNewDocumentInstance();
	}
	
	/**
	 * @return f_persistentdocument_PersistentDocumentModel
	 */
	protected function getDocumentModel()
	{
		return f_persistentdocument_PersistentDocumentModel::getInstanceFromDocumentModelName('modules_order/shippingaddress');
	}

	protected function getDocumentProperties()
	{
		$properties = parent::getDocumentProperties();
		if (isset($properties['fromAddress']))
		{
			$address = $this->script->getDocumentElementById($properties['fromAddress'])->getObject();
		    if ($address instanceof customer_persistentdocument_address)
		    {
			    $sas = order_ShippingaddressService::getInstance();
			    $properties['label'] = $address->getLabel();
			    $properties['titleid'] = $address->getTitleid();
			    $properties['firstname'] = $address->getFirstname();
			    $properties['lastname'] = $address->getLastname();
			    $properties['email'] = $address->getEmail();
			    $properties['company'] = $address->getCompany();
			    $properties['addressLine1'] = $address->getAddressLine1();
			    $properties['addressLine2'] = $address->getAddressLine2();
			    $properties['addressLine3'] = $address->getAddressLine3();
			    $properties['zipCode'] = $address->getZipCode();
			    $properties['city'] = $address->getCity();
			    $properties['province'] = $address->getProvince();
			    $properties['countryid'] = $address->getCountryid();
			    $properties['phone'] = $address->getPhone();
			    $properties['fax'] = $address->getFax();
			    $properties['mobilephone'] = $address->getMobilephone();
		    }
		    else
		    {
		    	throw new Exception("FromAddress value isn't an address: " . $properties['fromAddress']);
		    }
			unset($properties['fromAddress']);
		}
	    return $properties;	
	}
}