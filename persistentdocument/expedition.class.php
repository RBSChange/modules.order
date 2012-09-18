<?php
/**
 * Class where to put your custom methods for document order_persistentdocument_expedition
 * @package modules.order.persistentdocument
 */
class order_persistentdocument_expedition extends order_persistentdocument_expeditionbase 
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
	 * @return string
	 */
	public function getStatusLabel()
	{
		return LocaleService::getInstance()->trans('m.order.frontoffice.status.expedition.' . $this->getStatus(), array('ucf', 'html'));
	}
	
	/**
	 * @return string
	 */
	public function getBoStatusLabel()
	{
		return LocaleService::getInstance()->trans('m.order.frontoffice.status.expedition.' . $this->getStatus(), array('ucf', 'html'));
	}
	
	/**
	 * @return string
	 */
	public function getOriginalTrackingURL()
	{
		return parent::getTrackingURL();
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
	
	/**
	 * @return string
	 */
	public function getBoExplinesJSON()
	{
		$json = array();
		foreach ($this->getLinesForDisplay() as $line) 
		{
			/* @var $line order_persistentdocument_expeditionline */
			$data = array('id' => $line->getId(),
						  'st' => $this->getStatus(),
						  'label' => $line->getLabel(),
						  'codereference' => $line->getCodeReference(),
						  'quantity' => $line->getQuantity(),
						  'packetnumber' => $line->getPacketNumber(),
						  'trackingnumber' => $line->getTrackingNumber(),
					);
			$json[] = $data;
		}
		return JsonService::getInstance()->encode($json);
	}
	
	/**
	 * @param string $string
	 */
	public function setBoExplinesJSON($string)
	{
		if (!empty($string))
		{
			$tm = f_persistentdocument_TransactionManager::getInstance();
			try 
			{
				$tm->beginTransaction();
				$array = JsonService::getInstance()->decode($string);
				foreach ($array as $lineInfo)
				{
					if (isset($lineInfo['id']) && array_key_exists('trackingnumber', $lineInfo) && array_key_exists('packetnumber', $lineInfo))
					{
						$line = order_persistentdocument_expeditionline::getInstanceById($lineInfo['id']);
						$trackingnumber = $lineInfo['trackingnumber'] == '' ? null : $lineInfo['trackingnumber'];
						$line->setTrackingNumber($trackingnumber);
						
						$packetnumber = $lineInfo['packetnumber'] == '' ? null : $lineInfo['packetnumber'];
						$line->setPacketNumber($packetnumber);
						
						if ($line->isModified())
						{
							$line->save();
							$this->setModificationdate(null);
						}
					}
				}
				$tm->commit();
			} 
			catch (Exception $e) 
			{
				$tm->rollBack($e);
				throw $e;
			}
		}
	}
}