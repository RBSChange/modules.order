<?php
class order_ODTBillGenerator {
	
	/**
	 * @param order_persistentdocument_bill $bill
	 * @param string $filePath
	 * @throws Exception
	 */
	public function writePDF($bill, $filePath)
	{
		$order = $bill->getOrder();
		$shop = $order->getShop();
		$customer = $order->getCustomer();
		
		// OK, ... I will code a dedicated getInfo method
		$data = order_OrderService::getInstance()->getInfo($order);
		foreach ($data['informations'] as $key => $value)
		{
			$data[$key] = $value;
		}
		unset($data['informations']);
		
		//
		$data["number"] = $bill->getLabel();
		$data["amountWithoutTax"] = $order->formatPrice($order->getLinesAmountWithoutTax());
		
		$lines = $data['billingAddressLine1'];
		if ($data['billingAddressLine2'] != "")
		{
			$lines .= "\n".$data['billingAddressLine2'];
		}
		if ($data['billingAddressLine3'] != "")
		{
			$lines .= "\n".$data['billingAddressLine3'];
		}
		$data['billingAddressLines'] = $lines;
		
		$taxes = array();
		foreach ($order->getTotalTaxInfoArray() as $subTotal)
		{
			$taxes[] = array("rate" => $subTotal['formattedTaxRate'],
				"amount" => $order->formatPrice($subTotal['taxAmount']));
		}
		$data['taxes'] = $taxes;
		// Discounts
		$discounts = array();
		$cartModificators = $order->getDiscountDataArray();
		if (count($cartModificators) > 0)
		{
			foreach ($cartModificators as $discount)
			{
				if ($discount["valueWithTax"] > 0)
				{
					$discounts[] = array("name" => $discount["label"],
						"value" => $order->formatPrice($discount["valueWithTax"]));
				}
			}
		}
		$data["discounts"] = $discounts;
		$lang = RequestContext::getInstance()->getLang();
		$data['creationdate'] = date_DateFormat::format($bill->getUICreationdate(), date_DateFormat::getDateFormatForLang($lang));
		$data['creationdatetime'] = date_DateFormat::format($bill->getUICreationdate(), date_DateFormat::getDateTimeFormatForLang($lang));
		$data['customerCode'] = $customer->getCode();
		
		$odt2pdf = new Odtphp2PDFClient(Framework::getConfigurationValue("modules/order/odtphp2pdfURL"));
		
		$ref = $shop->getCodeReference();
		$lang = $order->getLang();
		$billTemplate = FileResolver::getInstance()->setPackageName("modules_order")->setDirectory("templates")->getPath("billTemplate-".$ref."-".$lang.".odt");
		if ($billTemplate === null)
		{
			$billTemplate = FileResolver::getInstance()->setPackageName("modules_order")->setDirectory("templates")->getPath("billTemplate-".$ref.".odt");
			if ($billTemplate === null)
			{
				$billTemplate = FileResolver::getInstance()->setPackageName("modules_order")->setDirectory("templates")->getPath("billTemplate.odt");
			}
		}
		
		f_util_FileUtils::writeAndCreateContainer($filePath, $odt2pdf->getPdf($billTemplate, $data), f_util_FileUtils::OVERRIDE);
	}
}