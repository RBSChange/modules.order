<?php
/**
 * @package modules.order.lib
 */
class order_CartModifierInfo
{
	/**
	 * @var integer
	 */
	private $id;
	
	/**
	 * @var string
	 */
	private $label;
	
	/**
	 * @var double
	 */
	private $valueWithTax = 0;
	
	/**
	 * @var double
	 */
	private $valueWithoutTax = 0;

	/**
	 * @var array
	 */
	private $parameters;
	
	/**
	 * @return integer
	 */
	public function getId()
	{
		return $this->id;
	}
	
	/**
	 * @return string
	 */
	public function getLabel()
	{
		return $this->label;
	}
	
	/**
	 * @return double
	 */
	public function getValueWithoutTax()
	{
		return $this->valueWithoutTax;
	}
	
	/**
	 * @return double
	 */
	public function getValueWithTax()
	{
		return $this->valueWithTax;
	}
	
	/**
	 * @param integer $id
	 */
	public function setId($id)
	{
		$this->id = $id;
	}
	
	/**
	 * @param string $label
	 */
	public function setLabel($label)
	{
		$this->label = $label;
	}
	
	/**
	 * @param double $valueWithoutTax
	 */
	public function setValueWithoutTax($valueWithoutTax)
	{
		$this->valueWithoutTax = $valueWithoutTax;
	}
	
	/**
	 * @param double $valueWithTax
	 */
	public function setValueWithTax($valueWithTax)
	{
		$this->valueWithTax = $valueWithTax;
	}

	/**
	 * @return double
	 */
	public function getTaxRate()
	{
		return catalog_TaxService::getInstance()->getTaxRateByValue($this->getValueWithTax(), $this->getValueWithoutTax());
	}
	
	/**
	 * @return string
	 */
	public function getFormattedTaxRate()
	{
		return catalog_TaxService::getInstance()->formatRate($this->getTaxRate());
	}	
		
	/**
	 * @param string $name
	 * @param mixed $value
	 */
	public function setParameter($name, $value)
	{
		if ($this->parameters === null)
		{
			$this->parameters = array($name => $value);
		}
		else
		{
			$this->parameters[$name] = $value;
		}
	}
	
	/**
	 * @param string $name
	 * @return mixed
	 */
	public function getParameter($name)
	{
		if ($this->parameters === null || !isset($this->parameters[$name]))
		{
			return null;
		}
		return $this->parameters[$name];
	}
	
	
	// DEPRECATED
	
	/**
	 * @deprecated
	 */
	public function getFormattedTaxCode()
	{
		return $this->getFormattedTaxRate();
	}
}

class order_DiscountInfo extends order_CartModifierInfo
{
	
}

class order_CouponInfo extends order_DiscountInfo
{
	
}

class order_FeesInfo extends order_CartModifierInfo
{

}
