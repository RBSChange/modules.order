<?php
/**
 * @package order 
 */
class order_Exception extends BaseException
{
	/**
	 * @var string
	 */
	private $frontEndUserMessage=null;
	
	/**
	 * @param string $message
	 */
	public function setFrontEndUserMessage($message)
	{
		$this->frontEndUserMessage = $message;
	}
	
	/**
	 * @returns string
	 */
	public function getFrontEndUserMessage()
	{
		return $this->frontEndUserMessage;
	}
}