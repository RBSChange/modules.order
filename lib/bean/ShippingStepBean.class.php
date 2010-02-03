<?php

/**
 * @package modules.order.lib.bean
 */
class order_AddressBean
{
	/**
	 * @var Integer
	 * @listId(modules_users/title)
	 */
	public $Title;
	
	/**
	 * @var String
	 * @required
	 */
	public $FirstName;
	
	/**
	 * @var String
	 * @required
	 */
	public $LastName;
	
	/**
	 * @var String
	 * @required
	 */
	public $Addressline1;

	/**
	 * @var String
	 */
	public $Addressline2;

	/**
	 * @var String
	 */
	public $Addressline3;

	/**
	 * @var String
	 * @required
	 */
	public $Zipcode;

	/**
	 * @var String
	 * @required
	 */
	public $City;

	/**
	 * @var String
	 */
	public $Province;

	/**
	 * @var Integer
	 * @required
	 */
	public $CountryId;
	
	/**
	 * @var String
	 */
	public $Company;

	/**
	 * @var String
	 */
	public $Email;

	/**
	 * @var String
	 */
	public $Phone;

	/**
	 * @var String
	 */
	public $Fax;
	
	
	/**
	 * @param customer_persistentdocument_address $persistentAddress
	 */
	public function import($persistentAddress)
	{
		$this->Title = $persistentAddress->getTitle();
		$this->FirstName = $persistentAddress->getFirstname();
		$this->LastName = $persistentAddress->getLastname();
		$this->Addressline1 = $persistentAddress->getAddressLine1();
		$this->Addressline2 = $persistentAddress->getAddressLine2();
		$this->Addressline3 = $persistentAddress->getAddressLine3();
		$this->Zipcode = $persistentAddress->getZipCode();
		$this->City = $persistentAddress->getCity();
		$this->Province = $persistentAddress->getProvince();
		$this->CountryId = $persistentAddress->getCountry() ? $persistentAddress->getCountry()->getId() : null;
		
		$this->Company = $persistentAddress->getCompany();
		$this->Email = $persistentAddress->getEmail();
		$this->Phone = $persistentAddress->getPhone();
		$this->Fax = $persistentAddress->getFax();			
	}
	
	/**
	 * @param customer_persistentdocument_address $persistentAddress
	 */
	public function export($persistentAddress)
	{
		$persistentAddress->setTitle($this->Title ? $this->Title : $persistentAddress->getTitle());
		$persistentAddress->setFirstname($this->FirstName);
		$persistentAddress->setLastname($this->LastName);
		$persistentAddress->setAddressLine1($this->Addressline1);
		$persistentAddress->setAddressLine2($this->Addressline2);
		$persistentAddress->setAddressLine3($this->Addressline3);
		$persistentAddress->setZipCode($this->Zipcode);
		$persistentAddress->setCity($this->City);
		$persistentAddress->setProvince($this->Province);
		$persistentAddress->setCountry($this->CountryId ? DocumentHelper::getDocumentInstance($this->CountryId) :  $persistentAddress->getCountry());
		
		$persistentAddress->setCompany($this->Company);
		$persistentAddress->setEmail($this->Email);
		$persistentAddress->setPhone($this->Phone);
		$persistentAddress->setFax($this->Fax);		
	}	
}

/**
 * @package modules.order.lib.bean
 */
class order_ShippingStepBean
{
	
	function __construct()
	{
		$this->shippingAddress = new order_AddressBean();
		$this->billingAddress = new order_AddressBean();
	}
	
	/**
	 * @var Integer
	 * @required
	 */
	public $shippingFilterId;	
	
	/**
	 * @var Integer
	 */
	public $shippingModeId;
	
	/**
	 * @var String
	 */
	public $shippingTaxCode;
	
	/**
	 * @var String
	 */
	public $shippingValueWithoutTax;
	
	/**
	 * @var String
	 */
	public $shippingvalueWithTax;
	
	/**
	 * @var order_AddressBean
	 */
	public $shippingAddress;
	
	/**
	 * @var Boolean
	 */
	public $useSameAddressForBilling = true;
		
	/**
	 * @var order_AddressBean
	 */
	public $billingAddress;
	
	
	public function selected($id, $first)
	{
		return (($id == $this->shippingFilterId) || (!$this->shippingFilterId && $first));
	}
	
	/**
	 * @param customer_persistentdocument_address $address
	 */
	public function importShippingAddress($address)
	{
		$this->shippingAddress->import($address);
	}
	
	/**
	 * @param customer_persistentdocument_address $address
	 */
	public function importBillingAddress($address)
	{
		$this->billingAddress->import($address);
	}
	
	/**
	 * @param customer_persistentdocument_address $address
	 */	
	public function exportShippingAddress($address)
	{
		$this->shippingAddress->export($address);
	}
	
	/**
	 * @param customer_persistentdocument_address $address
	 */	
	public function exportBillingAddress($address)
	{
		$this->billingAddress->export($address);	
	}
}