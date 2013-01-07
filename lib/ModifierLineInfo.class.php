<?php
/**
 * @package modules.order.lib
 */
class order_ModifierLineInfo extends order_CartLineInfo
{
	/**
	 * @var string
	 */
	private $label;
	
	/**
	 * @return integer
	 */
	public function getModifierId()
	{
		return $this->getProductId();
	}
	
	/**
	 * @param integer $modifierId
	 */
	public function setModifierId($modifierId)
	{
		$this->setProductId($modifierId);
	}
	
	/**
	 * @return null
	 */
	public function getProduct()
	{
		return null;
	}
	
	public function getKey()
	{
		return $this->getModifierId();
	}
	
	/**
	 * @return order_persistentdocument_cartmodifier
	 * @throws Exception
	 */
	public function getModifier()
	{
		return order_persistentdocument_cartmodifier::getInstanceById($this->getModifierId());
	}
	
	/**
	 * @return string
	 */
	public function getLabel()
	{
		return $this->getProperty('label');
	}
	
	/**
	 * @param string $label
	 */
	public function setLabel($label)
	{
		$this->setProperty('label', $label);
	}
	
	/**
	 * @return string
	 */
	public function getVisual()
	{
		$id = $this->getProperty('visualId');
		if ($id)
		{
			$visual = DocumentHelper::getDocumentInstanceIfExists($id);
			if ($visual instanceof media_persistentdocument_media)
			{
				return $visual;
			}
		}
		return null;
	}
}