<?php
/**
 * @package modules.order.lib
 */
class order_CartLineInfo
{
	/**
	 * @var Integer
	 */
	private $productId = null;
	
	/**
	 * @var Integer
	 */
	private $priceId = null;
	
	/**
	 * @var double
	 */
	private $quantity = 0;
	
	/**
	 * @var string
	 */		
	private $taxCode = null;

	/**
	 * @var double
	 */
	private $valueWithTax = 0;
	
	/**
	 * @var double
	 */
	private $valueWithoutTax = 0;
	
	/**
	 * @var double
	 */
	private $oldValueWithTax = 0;
	
	/**
	 * @var double
	 */
	private $oldValueWithoutTax = 0;
	
	/**
	 * @var string
	 */	
	private $discountDetail = null;
	
	/**
	 * @var double
	 */	
	private $ecoTax = 0;

	/**
	 * @return Integer
	 */
	public function getProductId()
	{
		return $this->productId;
	}

	/**
	 * @param Integer $productId
	 */
	public function setProductId($productId)
	{
		$this->productId = $productId;
	}
	
	/**
	 * @param catalog_persistentdocument_product $product
	 */
	public function setProduct($product)
	{
		if ($product !== null)
		{
			$this->setProductId($product->getId());
		}
		else
		{
			$this->setProductId(null);
		}
	}
	
	/**
	 * @return catalog_persistentdocument_product $product
	 */
	public function getProduct()
	{
		if ($this->productId === null)
		{
			return null;
		}
		$product = DocumentHelper::getDocumentInstance($this->productId, "modules_catalog/product");
		$product->getDocumentService()->updateProductFromCartProperties($product, $this->properties);
		return $product;
	}

	/**
	 * @return Double
	 */
	public function getQuantity()
	{
		return $this->quantity;
	}

	/**
	 * @param Double $quantity
	 */
	public function setQuantity($quantity)
	{
		// Never go below zero if someone is clever enough to set $value to a negative number
		$this->quantity = max(0, $quantity);
	}
	
	/**
	 * @return Integer
	 */
	public function getPriceId()
	{
		return $this->priceId;
	}
	
	/**
	 * @param Integer $priceId
	 */
	public function setPriceId($priceId)
	{
		$this->priceId = $priceId;
	}

	/**
	 * @return catalog_persistentdocument_price
	 */
	public function getPrice()
	{
		if ($this->priceId)
		{
			DocumentHelper::getDocumentInstance($this->priceId, "modules_catalog/price");
		}
		return null;
	}
	
	/**
	 * @param catalog_persistentdocument_price $price
	 */
	public function setPrice($price)
	{
		if ($price instanceof catalog_persistentdocument_price) 
		{
			$this->setPriceId($price->getId());
		}
		else
		{
			$this->setPriceId(null);
		}
	}
	
	/**
	 * @param catalog_persistentdocument_price $price
	 */
	public function importPrice($price)
	{
		if ($price instanceof catalog_persistentdocument_price) 
		{
			$this->setPriceId($price->getId());
			$this->setEcoTax($price->getEcoTax());
			$this->setValueWithoutTax($price->getValueWithoutTax());
			$this->setValueWithTax($price->getValueWithTax());
			$this->setOldValueWithoutTax($price->getOldValueWithoutTax());
			$this->setOldValueWithTax($price->getOldValueWithTax());
			$this->setDiscountDetail($price->getDiscountDetail());
			$this->setTaxCode($price->getTaxCode());
		}
		else
		{
			$this->setPriceId(null);
			$this->setEcoTax(0);
			$this->setValueWithoutTax(0);
			$this->setValueWithTax(0);
			$this->setOldValueWithoutTax(0);
			$this->setOldValueWithTax(0);
			$this->setDiscountDetail(null);
			$this->setTaxCode(null);
		}
	}
	
	/**
	 * @return string
	 */
	public function getTaxCode()
	{
		return $this->taxCode;
	}
	
	/**
	 * @param string $taxCode
	 */
	public function setTaxCode($taxCode)
	{
		$this->taxCode = $taxCode;
	}
	
	/**
	 * @return Double
	 */
	public function getTaxRate()
	{
		if ($this->taxCode !== null)
		{
			return catalog_PriceHelper::getTaxRateByCode($this->taxCode);
		}
		else
		{
			return catalog_PriceHelper::getTaxRateByValue($this->getValueWithTax(), $this->getValueWithoutTax());
		}
	}
	
	/**
	 * @return string
	 */
	public function getFormattedTaxCode()
	{
		return catalog_PriceHelper::formatTaxRate($this->getTaxRate());
	}

	/**
	 * @return string
	 */
	public function getDiscountDetail()
	{
		return $this->discountDetail;
	}
	
	/**
	 * @return double
	 */
	public function getEcoTax()
	{
		return $this->ecoTax;
	}
	
	/**
	 * @return double
	 */
	public function getTotalEcoTax()
	{
		return $this->ecoTax * $this->quantity;
	}
	
	/**
	 * @return double
	 */
	public function getOldValueWithoutTax()
	{
		return $this->oldValueWithoutTax;
	}
	
	/**
	 * @return double
	 */
	public function getTotalOldValueWithoutTax()
	{
		return $this->oldValueWithoutTax * $this->quantity;
	}
	
	/**
	 * @return double
	 */
	public function getOldValueWithTax()
	{
		return $this->oldValueWithTax;
	}
	
	/**
	 * @return double
	 */
	public function getTotalOldValueWithTax()
	{
		return $this->oldValueWithTax * $this->quantity;
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
	public function getTotalValueWithoutTax()
	{
		return $this->valueWithoutTax * $this->quantity;
	}
	
	/**
	 * @return double
	 */
	public function getValueWithTax()
	{
		return $this->valueWithTax;
	}
	
	/**
	 * @return double
	 */
	public function getTotalValueWithTax()
	{
		return $this->valueWithTax * $this->quantity;
	}
	
	/**
	 * @return double
	 */
	public function getTotalTax()
	{
		return $this->getTotalValueWithTax() -  $this->getTotalValueWithoutTax();
	}
	
	/**
	 * @param string $discountDetail
	 */
	public function setDiscountDetail($discountDetail)
	{
		$this->discountDetail = $discountDetail;
	}
	
	/**
	 * @param double $ecoTax
	 */
	public function setEcoTax($ecoTax)
	{
		$this->ecoTax = $ecoTax;
	}
	
	/**
	 * @param double $oldValueWithoutTax
	 */
	public function setOldValueWithoutTax($oldValueWithoutTax)
	{
		$this->oldValueWithoutTax = $oldValueWithoutTax;
	}
	
	/**
	 * @param double $oldValueWithTax
	 */
	public function setOldValueWithTax($oldValueWithTax)
	{
		$this->oldValueWithTax = $oldValueWithTax;
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
	 * @var Array<String, Mixed>
	 */
	private $properties = array();

	/**
	 * @return Array<String, Mixed>
	 */
	public function getPropertiesArray()
	{
		return $this->properties;
	}

	/**
	 * @param Array<String, Mixed> $properties
	 */
	public function setPropertiesArray($properties)
	{
		$this->properties = $properties;
	}

	/**
	 * @param String $key
	 * @return Boolean
	 */
	public function hasProperties($key)
	{
		return isset($this->properties[$key]);
	}

	/**
	 * @param String $key
	 * @return Mixed
	 */
	public function getProperties($key)
	{
		return $this->hasProperties($key) ? $this->properties[$key] : null;
	}

	/**
	 * @param String $key
	 * @param Mixed $value
	 */
	public function setProperties($key, $value)
	{
		$this->properties[$key] = $value;
	}
	
	/**
	 * @param Array<String, Mixed> $array
	 */
	public function mergePropertiesArray($array)
	{
		if (is_array($array))
		{
			$this->properties = array_merge($this->properties, $array);
		}
	}
	
	/**
	 * @param Double $value
	 */
	public function addToQuantity($value)
	{
		$this->setQuantity($this->getQuantity() + $value);
		return $this;
	}
	
	/**
	 * @param Boolean $aBoolean
	 */
	function setABoolean($aBoolean)
	{
		$this->aBoolean = $aBoolean;
	}
	
	/**
	 * @return Boolean
	 */
	function getABoolean()
	{
		return $this->aBoolean;
	}
	
	/**
	 * @return integer
	 */
	public function getShippingModeId()
	{
		if (isset($this->properties['shippingModeId']))
		{
			return $this->properties['shippingModeId'];
		}
		return null;
	}
	
	/**
	 * @return shipping_persistentdocument_mode
	 */	
	public function getShippingMode()
	{
		$shippingModeId = $this->getShippingModeId();
		if (intval($shippingModeId) > 0)
		{
			try 
			{
				return DocumentHelper::getDocumentInstance($shippingModeId, 'modules_shipping/mode');
			}
			catch (Exception $e)
			{
				Framework::warn($e->getMessage());
			}
		}
		return null;
	}
	
	// DEPRECATED
	
	/**
	 * @deprecated with no replacement
	 */
	public function isBasicLine()
	{
		return true;
	}
}