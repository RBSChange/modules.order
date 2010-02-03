<?php
/**
 * order_patch_0305
 * @package modules.order
 */
class order_patch_0305 extends patch_BasePatch
{
 
	/**
	 * Entry point of the patch execution.
	 */
	public function execute()
	{
		parent::execute();
		
		$data = $this->executeSQLSelect("SELECT document_id FROM m_order_doc_order WHERE document_modelversion = '1.0'")
				->fetchAll();
		echo "Migrate: "  . count($data) ." Orders \n";
		foreach ($data as $row) 
		{
			$this->migrateOrderId($row['document_id']);
		}
	}
	
	private function migrateOrderId($orderId)
	{
		try 
		{
			$this->beginTransaction();
			echo "Update: $orderId \n";
			$order = $this->getOldOrder($orderId);
			$shop = $order->getShop();
			$this->migrateOrder($order, $shop);
			$this->commit();	
		}
		catch (Exception $e)
		{
			$this->rollBack($e);
			echo "ERROR On migrate: $orderId \n";
		}
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @param catalog_persistentdocument_shop $shop
	 */
	private function migrateOrder($order, $shop)
	{
		$oldInfos = $this->executeSQLSelect("SELECT document_id, `document_metas`,  `amountwithtax`,  `amountwithouttax`,  
		`amountwithmodificatorswithtax` , `amountwithmodificatorswithouttax`, 
		`shippingmodeid`, `shippingmode`, `shippingmodetaxcode`, `shippingmodecodereference`,
		`shippingfeeswithtax`, `shippingfeeswithouttax`, `billingmode`, `billingmodeid`, `billingmodetaxcode`, 
		`billingmodecodereference`, `billingfeeswithtax`, `billingfeeswithouttax`, `currencycode`, `currencysymbol`,
		`billingproperties`, `shippingproperties`, `globalproperties`, `discountline`,
		`synchrostatus` FROM m_order_doc_order WHERE document_id = " . $order->getId())->fetchAll();		
		$oldOrderInfos = $oldInfos[0];
		
		$shippingFilter = $this->getSippingFilter($shop, $oldOrderInfos['shippingmodeid']);
		if ($shippingFilter->isNew())
		{
			$shippingFilter->setTaxCode($oldOrderInfos['shippingmodetaxcode']);
			$shippingFilter->setValueWithTax($oldOrderInfos['shippingfeeswithtax']);
			$shippingFilter->setValueWithoutTax($oldOrderInfos['shippingfeeswithouttax']);
			$shippingFilter->setShop($shop);
			$shippingFilter->save($shop->getId());
			echo "Add New shippingFilter: "  . $shippingFilter->__toString() .  " \n";
		}
		
		$paymentfilter = $this->getPaymentFilter($shop, $oldOrderInfos['billingmodeid']);
		if ($paymentfilter->isNew())
		{
			$paymentfilter->setShop($shop);
			$paymentfilter->save($shop->getId());	
			echo "Add New paymentfilter: "  . $paymentfilter->__toString() .  " \n";
		}		

		$order->setModelversion('3.0');
		$order->setBillingMode($oldOrderInfos['billingmode']);
		$order->setBillingModeCodeReference($oldOrderInfos['shippingmodecodereference']);
		
		$order->setCurrencyCode($oldOrderInfos['currencycode']);
		$order->setPriceFormat($shop->getDocumentService()->getPriceFormat($shop));
			
		$order->setShippingMode($oldOrderInfos['shippingmode']);
		$order->setShippingModeCodeReference($oldOrderInfos['shippingmodecodereference']);
		$order->setShippingModeTaxCode($oldOrderInfos['shippingmodetaxcode']);
		$order->setShippingModeTaxRate(catalog_PriceHelper::getTaxRateByCode($oldOrderInfos['shippingmodetaxcode']));
			
		$this->getPersistentProvider()->updateDocument($order);

		foreach ($order->getLineArray() as $key => $line) 
		{
			$this->migrateOrderLine($line, $key);
		}
	}
	
	/**
	 * @param order_persistentdocument_orderline $line
	 * @param integer $key
	 */
	private function migrateOrderLine($line, $key)
	{
		echo "\tUpdate line $key: "  . $line->__toString() .  " \n";
		
		$infos = $this->executeSQLSelect("SELECT document_id, gencode, baseamountwithtax, baseunitpricewithouttax,
			taxamount, taxrate, taxcode	FROM m_order_doc_orderline WHERE document_id = " . $line->getId())->fetchAll();		
		$orderLineInfos = $infos[0];		
		$line->setGenCode($orderLineInfos['gencode']);
		$line->setBaseUnitPriceWithTax($orderLineInfos['baseamountwithtax']);
		$line->setBaseUnitPriceWithoutTax($orderLineInfos['baseunitpricewithouttax']);
		$line->setTaxAmount($orderLineInfos['taxamount']);
		$line->setTaxRate($orderLineInfos['taxrate']);
		$line->setTaxCode($orderLineInfos['taxcode']);
		
		$line->setModelversion('3.0');
		$line->save();
	}
	
	/**
	 * @param catalog_persistentdocument_shop $shop
	 * @param integer $shippingmodeid
	 * @return catalog_persistentdocument_shippingfilter
	 */
	private function getSippingFilter($shop, $shippingmodeid)
	{
		$shippingmode = DocumentHelper::getDocumentInstance($shippingmodeid);		
		$shippingfilter = catalog_ShippingfilterService::getInstance()->createQuery()
			->add(Restrictions::eq('shop', $shop))
			->add(Restrictions::eq('mode', $shippingmode))->findUnique();
		
		if ($shippingfilter == null)
		{
			$shippingfilter = catalog_ShippingfilterService::getInstance()->getNewDocumentInstance();
			$shippingfilter->setLabel($shippingmode->getLabel());
			$shippingfilter->setMode($shippingmode);			
		}
		return $shippingfilter;
	}
	
	/**
	 * @param catalog_persistentdocument_shop $shop
	 * @param integer $billingmodeid
	 * @return catalog_persistentdocument_paymentfilter
	 */
	private function getPaymentFilter($shop, $billingmodeid)
	{
		$billingmode = DocumentHelper::getDocumentInstance($billingmodeid);		
		$paymentfilter = catalog_PaymentfilterService::getInstance()->createQuery()
			->add(Restrictions::eq('shop', $shop))
			->add(Restrictions::eq('connector', $billingmode))->findUnique();
		
		if ($paymentfilter == null)
		{
			$paymentfilter = catalog_PaymentfilterService::getInstance()->getNewDocumentInstance();
			$paymentfilter->setLabel($billingmode->getLabel());
			$paymentfilter->setConnector($billingmode);
		}
		return $paymentfilter;
	}	
	
	/**
	 * @param integer $orderId
	 * @return order_persistentdocument_order
	 */
	private function getOldOrder($orderId)
	{
		return DocumentHelper::getDocumentInstance($orderId);
	}
	
	/**
	 * Returns the name of the module the patch belongs to.
	 *
	 * @return String
	 */
	protected final function getModuleName()
	{
		return 'order';
	}

	/**
	 * Returns the number of the current patch.
	 * @return String
	 */
	protected final function getNumber()
	{
		return '0305';
	}
}