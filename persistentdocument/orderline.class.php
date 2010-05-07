<?php
/**
 * order_persistentdocument_orderline
 * @package modules.order
 */
class order_persistentdocument_orderline extends order_persistentdocument_orderlinebase 
{
	/**
	 * @return catalog_persistentdocument_product or null
	 */
	public function getProduct()
	{
		try 
		{
			return DocumentHelper::getDocumentInstance($this->getProductId(), 'modules_catalog/product');
		}
		catch (Exception $e)
		{
			if (Framework::isInfoEnabled())
			{
				Framework::info(__METHOD__ . ' ' . $e->getMessage());
			}		
		}
		return null;
	}
	
	/**
	 * @return string
	 */
	public function getLabelAsHtml()
	{
		$product = $this->getProduct();
		if ($product)
		{
			$l = $this->getOrderLabelAsHtml();
			if (f_util_StringUtils::isEmpty($l))
			{
				return $product->getOrderLabelAsHtml();
			}
			return $l;
		}
		$l = $this->getOrderLabel();
		if (f_util_StringUtils::isEmpty($l))
		{
			return $this->getLabel();
		}
		return $l; 	
	}
	
	
	
	/**
	 * @param String $propertyName
	 * @param Mixed $value serializable data.
	 */
	public function setGlobalProperty($propertyName, $value)
	{
		$properties = $this->setOrderlineProperty($propertyName, $value, $this->getGlobalProperties());
		$this->setGlobalProperties(serialize($properties));
	}

	/**
	 * @param String $propertyName
	 * @return Mixed
	 */
	public function getGlobalProperty($propertyName)
	{
		return $this->getOrderlineProperty($propertyName, $this->getGlobalProperties());
	}
	
	
	public function setGlobalPropertyArray($properties)
	{
		$this->setGlobalProperties(serialize($properties));
	}
	
	/**
	 * @param Array<String, Mixed> $array
	 */
	public function mergeGlobalProperties($properties)
	{
		if (is_array($properties))
		{
			foreach ($properties as $key => $value)
			{
				$this->setGlobalProperty($key, $value);
			}
		}
	}
	
	/**
	 * @return Boolean
	 */
	public function showCommentLink()
	{
		return order_ModuleService::getInstance()->areCommentsEnabled() && !comment_CommentService::getInstance()->hasCurrentUserCommented($this->getProductId());
	}
	
	// Private methods.
	
	/**
	 * @param String $name
	 * @param Array $properties
	 * @return mixed
	 */
	private function getOrderlineProperty($propertyName, $properties)
	{
		if (!is_null($properties))
		{
			$properties = unserialize($properties);
			if (isset($properties[$propertyName]))
			{
				$value = $properties[$propertyName];
			}
			else
			{
				$value = null;
			}
		}
		else
		{
			$value = null;
		}
		return $value;
	}

	/**
	 * @param String $propertyName
	 * @param mixed $value
	 * @param Array $properties
	 */
	private function setOrderlineProperty($propertyName, $value, $properties)
	{
		if (!is_null($properties))
		{
			$properties = unserialize($properties);
		}
		else
		{
			$properties = array();
		}
		if (is_null($value))
		{
			unset($properties[$propertyName]);
		}
		else
		{
			$properties[$propertyName] = $value;
		}
		return $properties;
	}
	

	
	/**
	 * @return Double
	 */
	public function getBaseUnitPriceWithoutTax()
	{
		return $this->getGlobalProperty('baseUnitPriceWithoutTax');
	}
	
	/**
	 * @return Double
	 */
	public function getBaseUnitPriceWithTax()
	{
		return $this->getGlobalProperty('baseUnitPriceWithTax');
	}
	
	/**
	 * @return String
	 */
	public function getGenCode()
	{
		return $this->getGlobalProperty('genCode');
	}
	
	/**
	 * @return Double
	 */
	public function getTaxAmount()
	{
		return $this->getGlobalProperty('taxAmount');
	}
	/**
	 * @param Double $taxAmount
	 */
	public function setTaxAmount($taxAmount)
	{
		$this->setGlobalProperty('taxAmount', $taxAmount);
	}
	
	/**
	 * @return String
	 */
	public function getTaxCode()
	{
		return $this->getGlobalProperty('taxCode');
	}
	/**
	 * @param String $taxCode
	 */
	public function setTaxCode($taxCode)
	{
		$this->setGlobalProperty('taxCode', $taxCode);
	}	
	/**
	 * @return Double
	 */
	public function getTaxRate()
	{
		return $this->getGlobalProperty('taxRate');
	}
	
	/**
	 * @param Double $taxRate
	 */
	public function setTaxRate($taxRate)
	{
		$this->setGlobalProperty('taxRate', $taxRate);
	}
	
	/**
	 * @param Double $baseUnitPriceWithoutTax
	 */
	public function setBaseUnitPriceWithoutTax($baseUnitPriceWithoutTax)
	{
		$this->setGlobalProperty('baseUnitPriceWithoutTax', $baseUnitPriceWithoutTax);
	}
	
	/**
	 * @param Double $baseUnitPriceWithTax
	 */
	public function setBaseUnitPriceWithTax($baseUnitPriceWithTax)
	{
		$this->setGlobalProperty('baseUnitPriceWithTax', $baseUnitPriceWithTax);
	}
	
	/**
	 * @param String $genCode
	 */
	public function setGenCode($genCode)
	{
		$this->setGlobalProperty('genCode', $genCode);
	}
	

	
	/**
	 * @param string $label
	 */
	public function setOrderLabel($label)
	{
		$this->setGlobalProperty('orderLabel', $label);
	}	

	/**
	 * @return string
	 */
	public function getOrderLabel()
	{
		return $this->getGlobalProperty('orderLabel');
	}		

	/**
	 * @param string $label
	 */
	public function setOrderLabelAsHtml($label)
	{
		$this->setGlobalProperty('orderLabelAsHtml', $label);
	}	

	/**
	 * @return string
	 */
	public function getOrderLabelAsHtml()
	{
		return  $this->getGlobalProperty('orderLabelAsHtml');
	}
	
	/**
	 * @deprecated 
	 * @return catalog_persistentdocument_product 
	 */	
	public function getSynchronizedProduct()
	{
		throw new Exception(__METHOD__ .' is removed');
	}
	
	/**
	 * @deprecated 
	 * @return Integer
	 */
	public function getArticleId()
	{
		throw new Exception(__METHOD__ .' is removed');
	}
	
	/**
	 * @deprecated 
	 * @param Integer $articleId
	 */
	public function setArticleId($articleId)
	{
		throw new Exception(__METHOD__ .' is removed');
	}	
}