<?php
class order_persistentdocument_message extends order_persistentdocument_messagebase
{
	/**
	 * @return boolean
	 */
	public function isSentByCustomer()
	{
		return ($this->getSender()->getId() == $this->getOrder()->getCustomer()->getUser()->getId());
	}
	
	// DEPRECATED
	
	/**
	 * @deprecated (will be removed in 4.0) use getCreationdate() or getUICreationdate()
	 */
	public function getMessageDate()
	{
		return $this->getCreationdate();
	}
}