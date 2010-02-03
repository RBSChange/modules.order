<?php
class order_persistentdocument_message extends order_persistentdocument_messagebase
{
	/**
	 * @return Boolean
	 */
	public function isSentByCustomer()
	{
		return ($this->getSender()->getId() == $this->getOrder()->getCustomer()->getUser()->getId());
	}
	
	/**
	 * Get the readable message date.
	 * @return String
	 */
	public function getMessageDate()
	{
		if (defined('DEFAULT_TIMEZONE'))
		{
			date_default_timezone_set(DEFAULT_TIMEZONE);        
		}
		$offset = intval(date('Z'));
		$creationDate = date_Calendar::getInstance($this->getCreationdate());
		$creationDate->add(date_Calendar::SECOND, $offset);
		return $creationDate->toString();
	}
}