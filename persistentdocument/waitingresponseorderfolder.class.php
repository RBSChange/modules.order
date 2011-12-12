<?php
/**
 * @package modules.order.persistentdocument
 */
class order_persistentdocument_waitingresponseorderfolder extends order_persistentdocument_waitingresponseorderfolderbase 
{
	/**
	 * @return string
	 */
	public function getLabel()
	{
		return LocaleService::getInstance()->transBO('m.order.document.waitingresponseorderfolder.document-name', array('ucf'));
	}
}