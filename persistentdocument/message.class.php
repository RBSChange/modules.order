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
	
	// DEPRECATED
	
	/**
	 * Get the readable message date.
	 * @return String
	 * @deprecated use getCreationdate() or getUICreationdate()
	 */
	public function getMessageDate()
	{
		return $this->getCreationdate();
	}
}