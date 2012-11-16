<?php
/**
 * @package modules.order.lib
 */
class order_CartLineInfo
{
	/**
	 * @var integer
	 */
	private $productId = null;
	
	/**
	 * @var integer
	 */
	private $priceId = null;
	
	/**
	 * @var double
	 */
	private $quantity = 0;
	
	/**
	 * @var string
	 */		
	private $taxCategory = null;

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
	 * @var string
	 */
	private $key = null;
	
	/**
	 * @var array
	 */
	private $priceParts;
	
	/**
	 * @var array<string, mixed>
	 */
	private $properties = array();
	
	/**
	 * @var catalog_persistentdocument_price
	 */
	private $price = null;
	
	public function __sleep()
	{
		$prefix = "\0" . get_class() . "\0";
		return array_map(function($p) use ($prefix) {return $prefix.$p;},
    		array("properties", "key", "ecoTax", "discountDetail", "oldValueWithoutTax", "oldValueWithoutTax",
    			"valueWithoutTax", "valueWithTax", "productId", "priceId", "quantity", "taxCategory", "priceParts"));
	}

	/**
	 * @return integer
	 */
	public function getProductId()
	{
		return $this->productId;
	}
	
	/**
	 * @param integer $productId
	 */
	public function setProductId($productId)
	{
		$this->productId = $productId;
		$this->key = null;
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
	 * @return string
	 */
	public function getKey()
	{
		if ($this->key === null)
		{
			$this->key = $this->getProduct()->getCartLineKey();
		}
		return $this->key;
	}

	/**
	 * @return float
	 */
	public function getQuantity()
	{
		return $this->quantity;
	}
	
	/**
	 * @param float $quantity
	 */
	public function setQuantity($quantity)
	{
		// Never go below zero if someone is clever enough to set $value to a negative number
		$this->quantity = max(0, $quantity);
	}
	
	/**
	 * @return integer
	 */
	public function getPriceId()
	{
		return $this->priceId;
	}
	
	/**
	 * @param integer $priceId
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
		if ($this->price === null)
		{
			if ($this->priceId !== null && $this->priceId > 0)
			{
				$this->price = catalog_persistentdocument_price::getInstanceById($this->priceId);
			}
		}
		return $this->price;
	}
	
	/**
	 * @param catalog_persistentdocument_price $price
	 */
	public function setPrice($price)
	{
		if ($price instanceof catalog_persistentdocument_price) 
		{
			$this->price = $price;
			$this->setPriceId($price->getId() > 0 ? $price->getId() : null);
		}
		else
		{
			$this->price = null;
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
			$this->setTaxCategory($price->getTaxCategory());
			if ($price->hasPricePart())
			{
				$this->priceParts = array();
				foreach ($price->getPricePartArray() as $pricePart)
				{
					/* @var $pricePart catalog_persistentdocument_price */
					$this->priceParts[] = array('valueWithTax' => $pricePart->getValueWithTax(), 
						'valueWithoutTax' => $pricePart->getValueWithoutTax(), 
						'taxCategory' => $pricePart->getTaxCategory(),
						'productId'  => $pricePart->getProductId());
				}
			}
			else
			{
				$this->priceParts = null;
			}
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
			$this->setTaxCategory(null);
			$this->priceParts = null;
		}
	}
	
	/**
	 * @return string
	 */
	public function getTaxCategory()
	{
		return $this->taxCategory;
	}
	
	/**
	 * @param string $taxCategory
	 */
	public function setTaxCategory($taxCategory)
	{
		$this->taxCategory = $taxCategory;
	}
	
	/**
	 * @return float
	 */
	public function getTaxRate()
	{
		return catalog_TaxService::getInstance()->getTaxRateByValue($this->getValueWithTax(), $this->getValueWithoutTax());
	}
	
	/**
	 * @return array<string, float>
	 */
	public function getTaxArray()
	{
		$ts = catalog_TaxService::getInstance();
		$result = array();
		if ($this->priceParts === null)
		{
			$value = $this->getTotalValueWithTax() - $this->getTotalValueWithoutTax();
			if ($value > 0)
			{
				$result[$ts->formatRate($this->getTaxRate())] = $value;
			}
		}
		else
		{
			$qtt = $this->getQuantity();
			foreach ($this->priceParts as $pricePart)
			{			
				$valueWithTax = $pricePart['valueWithTax'];
				$valueWithoutTax = $pricePart['valueWithoutTax'];
				$value = ($valueWithTax - $valueWithoutTax) * $qtt;
				if ($value > 0)
				{
					$formattedTaxRate = $ts->formatRate($ts->getTaxRateByValue($valueWithTax, $valueWithoutTax));
					if (isset($result[$formattedTaxRate]))
					{
						$result[$formattedTaxRate] += $value;
					}
					else
					{
						$result[$formattedTaxRate] = $value;
					}
				}
			}
		}
		return $result;
	}
	
	/**
	 * @return string
	 */
	public function getFormattedTaxRate()
	{
		$tr = $this->getTaxArray();
		if (count($tr) === 0)
		{
			return LocaleService::getInstance()->trans('m.order.fo.no-tax');;
		}
		elseif (count($tr) > 1)
		{
			return LocaleService::getInstance()->trans('m.order.fo.mixed-tax');
		}
		else
		{
			return f_util_ArrayUtils::firstElement(array_keys($tr));
		}
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
		$this->key = null;
	}

	/**
	 * @param string $key
	 * @return boolean
	 */
	public function hasProperty($key)
	{
		return isset($this->properties[$key]);
	}

	/**
	 * @param string $key
	 * @return Mixed
	 */
	public function getProperty($key)
	{
		return $this->hasProperty($key) ? $this->properties[$key] : null;
	}

	/**
	 * @param string $key
	 * @param Mixed $value
	 */
	public function setProperty($key, $value)
	{
		if ($value === null)
		{	
			unset($this->properties[$key]);
		}
		else
		{
			$this->properties[$key] = $value;
		}
		$this->key = null;
	}
	
	/**
	 * @param Array<String, Mixed> $array
	 */
	public function mergePropertiesArray($array)
	{
		if (is_array($array))
		{
			$this->properties = array_merge($this->properties, $array);
			$this->key = null;
		}
	}
	
	/**
	 * @param float $value
	 */
	public function addToQuantity($value)
	{
		$this->setQuantity($this->getQuantity() + $value);
		return $this;
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
}