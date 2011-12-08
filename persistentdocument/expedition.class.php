<?php
/**
 * Class where to put your custom methods for document order_persistentdocument_expedition
 * @package modules.order.persistentdocument
 */
class order_persistentdocument_expedition extends order_persistentdocument_expeditionbase 
{
	/**
	 * @return order_persistentdocument_expeditionline[]
	 */
	public function getLinesForDisplay()
	{
		return $this->getDocumentService()->getLinesForDisplay($this);
	}
	
	/**
	 * @return shipping_persistentdocument_mode
	 */
	public function getShippingMode()
	{
		$shippingId = intval($this->getShippingModeId());
		if ($shippingId > 0)
		{
			try 
			{
				return DocumentHelper::getDocumentInstance($shippingId, 'modules_shipping/mode');
			}
			catch (Exception $e)
			{
				Framework::exception($e);
			}
		}
		return null;
	}
	
	/**
	 * @return String
	 */
	public function getStatusLabel()
	{
		return LocaleService::getInstance()->transFO('m.order.frontoffice.status.expedition.' . $this->getStatus(), array('ucf', 'html'));
	}
	
	/**
	 * @return String
	 */
	public function getBoStatusLabel()
	{
		return LocaleService::getInstance()->transBO('m.order.frontoffice.status.expedition.' . $this->getStatus(), array('ucf', 'html'));
	}
	
	/**
	 * @return string
	 */
	public function getTrackingURL()
	{
		$url = parent::getTrackingURL();
		if (empty($url)) { return null; }
		return str_replace('{NumeroColis}', $this->getTrackingNumber(), $url);
	}
	
	/**
	 * @return boolean
	 */
	public function hasDetailPage()
	{
		return $this->getDocumentService()->getDisplayPage($this) !== null;
	}
}