<?php
/**
 * @date Wen Dec 05 10:50:22 CET 2007
 * @author intportg
 * @package modules.order
 */
abstract class order_tests_AbstractBaseTest extends f_tests_AbstractBaseTest
{
	/**
	 * @return String
	 */
	protected final function getPackageName()
	{
		return 'modules_order';
	}

	/**
	 * @return void
	 */
	protected function clearServicesCache()
	{
		parent::clearServicesCache();
		RequestContext::clearInstance();
		RequestContext::getInstance()->setLang('fr');
		self::clearModuleServiceCache();
		catalog_tests_AbstractBaseTest::clearModuleServiceCache();
		customer_tests_AbstractBaseTest::clearModuleServiceCache();
	}

	/**
	 * @return void
	 */
	public static function clearModuleServiceCache()
	{
		// Set the default strategies.
		order_OrderNumberGenerator::getInstance()->setStrategy(new order_OrderNumberDefaultStrategy());
	}
	
	/**
	 * An array of events parameters indexed by events names.
	 * @var Array<String, Array>
	 */
	private $events = array();

	/**
	 * Clears the occured events list.
	 * This method aims to be called before calling the method which we want to test.
	 * @return void
	 */
	protected function clearEventList()
	{
		$this->events = array();
	}

	/**
	 * @param String $eventName
	 * @param f_persistentdocument_PersistentDocument $sender
	 * @param array $params
	 * @return void
	 */
	protected function logEvent($eventName, $sender, $params)
	{
		if (!isset($this->events[$eventName]))
		{
			$this->events[$eventName] = array();
		}
		$this->events[$eventName][] = array('sender' => $sender, 'params' => $params);
	}

	/**
	 * @param String $eventName
	 * @param String $message
	 * @return void
	 */
	protected function assertEventOccured($eventName, $message = '')
	{
		$this->assertArrayHasKey($eventName, $this->events, $message);
	}

	/**
	 * @param String $eventName
	 * @param f_persistentdocument_PersistentDocument $document
	 * @param String $message
	 * @return void
	 */
	protected function assertEventOccuredForDocument($eventName, $document, $message = '')
	{
		$this->assertArrayHasKey($eventName, $this->events, $message);
		$documents = array();
		foreach ($this->events[$eventName] as $event)
		{
			if (isset($event['params']['document']))
			{
				$documents[] = $event['params']['document'];
			}
		}
		$this->assertTrue(in_array($document, $documents), $message);
	}

	/**
	 * Assert that the named element occured with all the specified params.
	 * The event may have other params too.
	 *
	 * @param String $eventName
	 * @param Array<String, mixed> $params
	 * @param String $message
	 * @return void
	 */
	protected function assertEventOccuredWithParams($eventName, $params, $message = '')
	{
		$this->assertArrayHasKey($eventName, $this->events, $message);
		$events = array();
		foreach ($this->events[$eventName] as $event)
		{
			$events[] = array_intersect_key($event['params'], $params);
		}
		$this->assertTrue(in_array($params, $events), $message);
	}	
	
	/**
	 * Assert that an event was not thrown.
	 */
	protected function assertEventDidntOccured($eventName, $message = '')
	{
		return $this->assertArrayNotHasKey($eventName, $this->events, $message);
	}
}