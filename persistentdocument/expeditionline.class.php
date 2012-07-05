<?php
/**
 * Class where to put your custom methods for document order_persistentdocument_expeditionline
 * @package modules.order.persistentdocument
 */
class order_persistentdocument_expeditionline extends order_persistentdocument_expeditionlinebase 
{
	/**
	 * @param string $packetNumber
	 * @return boolean
	 */
	protected function setPacketNumberInternal($packetNumber)
	{
		if ($packetNumber != null)
		{
			$packetNumber = f_util_StringUtils::toUpper(strval($packetNumber));
		}
		return parent::setPacketNumberInternal($packetNumber);
	}
	
	/**
	 * @param unknown_type $trackingNumber
	 * @return boolean
	 */
	protected function setTrackingNumberInternal($trackingNumber)
	{
		if ($trackingNumber != null)
		{
			$trackingNumber = f_util_StringUtils::toUpper(strval($trackingNumber));
		}
		return parent::setTrackingNumberInternal($trackingNumber);
	}
	
	/**
	 * @return string
	 */
	public function getEvaluatedTrackingURL()
	{
		$url = $this->getTrackingURL();
		if (empty($url)) {
			return null;
		}
		return str_replace('{NumeroColis}', $this->getTrackingNumber(), $url);
	}
	
	/**
	 * @return order_persistentdocument_orderline
	 */
	private function getOrderLine()
	{
		return DocumentHelper::getDocumentInstance($this->getOrderlineid(), 'modules_order/orderline');
	}
	
	/**
	 * @return string
	 */	
	public function getCodeReference()
	{
		return $this->getOrderLine()->getCodeReference();	
	}
	
	/**
	 * @return string
	 */	
	public function getCodeReferenceAsHtml()
	{
		return $this->getOrderLine()->getCodeReferenceAsHtml();	
	}
	
	/**
	 * @return catalog_persistentdocument_product or null
	 */	
	public function getProduct()
	{
		return $this->getOrderLine()->getProduct();	
	}
	
	/**
	 * @return double
	 */	
	public function getOrderProductQuantity()
	{
		return $this->getOrderLine()->getQuantity();
	}	
	
	private $URL = null;
	
	/**
	 * @return string
	 */
	public function getURL()
	{
		return $this->URL;
	}

	/**
	 * @param string $URL
	 */
	public function setURL($URL)
	{
		$this->URL = $URL;
	}
	
	/**
	 * @return integer
	 */
	public function getURLClick()
	{
		if ($this->hasMeta('MediaAccessGranted'))
		{
			return $this->getMeta('MediaAccessGranted');
		}
		return 0;
	}
	
	/**
	 * @return string
	 */
	public function getStatusLabel()
	{
		return LocaleService::getInstance()->trans('m.order.document.expeditionline.status-' . $this->getStatus(), array('ucf', 'html'));
	}
}