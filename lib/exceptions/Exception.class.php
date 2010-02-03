<?php
/**
 * @package order 
 */
class order_Exception extends BaseException
{
	/**
	 * @var String
	 */
	private $frontEndUserMessage=null;
	
	/**
	 * @param String $message
	 */
	public function setFrontEndUserMessage($message)
	{
		$this->frontEndUserMessage = $message;
	}
	
	/**
	 * @returns String
	 *
	 */
	public function getFrontEndUserMessage()
	{
		return $this->frontEndUserMessage;
	}
	
	
	
}