<?php
class order_TestFactory extends order_TestFactoryBase
{
	/**
	 * @var order_TestFactory
	 */
	private static $instance;

	/**
	 * @return order_TestFactory
	 * @throws Exception
	 */
	public static function getInstance()
	{
		if (PROFILE != 'test')
		{
			throw new Exception('This method is only usable in test mode.');
		}
		if (self::$instance === null)
		{
			self::$instance = new order_TestFactory;
			// register the testFactory in order to be cleared after each test case.
			tests_AbstractBaseTest::registerTestFactory(self::$instance);
		}
		return self::$instance;
	}

	/**
	 * Clear the TestFactory instance.
	 * 
	 * @return void
	 * @throws Exception
	 */
	public static function clearInstance()
	{
		if (PROFILE != 'test')
		{
			throw new Exception('This method is only usable in test mode.');
		}
		self::$instance = null;
	}
	
	/**
	 * Initialize documents default properties
	 * @return void
	 */
	public function init()
	{
		$this->setCouponDefaultProperty('label', 'coupon test');
		$this->setCouponDefaultProperty('catalog', catalog_TestFactory::getInstance()->getTestCatalog());
		$this->setCouponDefaultProperty('discountType', catalog_DiscountHelper::TYPE_PERCENTAGE);
		$this->setCouponDefaultProperty('value', 10);
		
		$this->setChequebillingmodeDefaultProperty('label', 'chequebillingmode test');
		$this->setCartruleresultDefaultProperty('label', 'cartruleresult test');
		$this->setCartruleDefaultProperty('label', 'cartrule test');
		$this->setOrderlineDefaultProperty('label', 'orderline test');
		$this->setMessageDefaultProperty('label', 'message test');
		$this->setBillingmodeDefaultProperty('label', 'billingmode test');
		$this->setConditionDefaultProperty('label', 'condition test');
		$this->setShippingmodeDefaultProperty('label', 'shippingmode test');
		$this->setCartruleconditionDefaultProperty('label', 'cartrulecondition test');
		$this->setDiscountorderlineDefaultProperty('label', 'discountorderline test');
		$this->setCybermutbillingmodeDefaultProperty('label', 'cybermutbillingmode test');
		$this->setPreferencesDefaultProperty('label', 'preferences test');
		$this->setOrderDefaultProperty('label', 'order test');
		$this->setAtosbillingmodeDefaultProperty('label', 'atosbillingmode test');
	}
}