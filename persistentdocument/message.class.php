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
}