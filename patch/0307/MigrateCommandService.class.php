<?php

class order_MigrateCommandService extends BaseService
{

	/**
	 * @var order_MigrateCommandService
	 */
	private static $instance;
	
	/**
	 * @return order_MigrateCommandService
	 */
	public static function getInstance()
	{
		if (self::$instance === null)
		{
			self::$instance = self::getServiceClassInstance(get_class());
		}
		return self::$instance;
	}
	
	/**
	 * | document_id | document_model | document_label | document_author | document_authorid | 
	 * document_creationdate | document_modificationdate | document_publicationstatus | document_lang | 
	 * document_modelversion | document_version | document_startpublicationdate | document_endpublicationdate | document_metas | 
	 * ordernumber  | orderstatus | customer | shopid | websiteid | 
	 * line | totalamountwithtax | totalamountwithouttax | currencycode | 
	 * shippingmodeid | shippingfeeswithtax | shippingfeeswithouttax | shippingaddress | shippingproperties
	 * billingmodeid | billingaddress | couponid | couponvaluewithtax | billingproperties 
	 * globalproperties | paymentdate | shipmentdate | lastcommentreminder | commentuser | commentadmin | bill
	 * @param array $rawDataArray
	 * @return order_persistentdocument_order
	 */
	public function updateOrder($rawDataArray)
	{
		$order = $this->getOrderById($rawDataArray['document_id']);					
		$gP = is_null($rawDataArray['globalproperties']) ? array() : unserialize($rawDataArray['globalproperties']);		
		$orderStatus = $rawDataArray['orderstatus'];	
		$finalOrderStatus = '';	
		switch ($orderStatus)
		{
			case 'complete':
			case 'canceled':	
			case 'in_progress':
				return $order;
			case 'SHIPPED':
				$finalOrderStatus = 'complete';
				break;
			case 'PAYMENT_FAILED':
			case 'CANCELED':
				$finalOrderStatus = 'canceled';
				break;
			default:
				$finalOrderStatus = 'in_progress';
		}
		
		echo "Migrate order " . $order . " with status " . $rawDataArray['orderstatus'] . "-> " . $finalOrderStatus ."\n"; 
		
		if ($finalOrderStatus == 'canceled')
		{
			$order->setOrderStatus($finalOrderStatus);
			return $order;
		}
		
		$bill = $this->getBillByOrder($order);
		$bP = is_null($rawDataArray['billingproperties']) ? array() : unserialize($rawDataArray['billingproperties']);
		if ($finalOrderStatus == 'complete')
		{
			$billSatus = 'PAYMENT_SUCCESS';
		} 
		else if (isset($bP['bankstatus']))
		{
			$billSatus = $bP['bankstatus'];
		}
		else
		{
			$billSatus = 'PAYMENT_FAILED';
		}
		switch ($billSatus)
		{
			case 'PAYMENT_SUCCESS':
				$bill->setStatus(order_BillService::SUCCESS);
				$bill->setPublicationstatus('PUBLICATED');
				break;
			case 'PAYMENT_DELAYED':
				$bill->setStatus(order_BillService::WAITING);
				break;
			case 'PAYMENT_FAILED':
				$bill->setStatus(order_BillService::FAILED);
				break;
			default:
				$bill->setStatus(order_BillService::FAILED);
		}
			
		if ($bill->isNew() && $bill->getStatus() != 'failed')
		{
			$bill->setCreationdate($rawDataArray['document_creationdate']);
			$bill->setConnectorId($rawDataArray['billingmodeid']);
			$bill->setAmount($rawDataArray['totalamountwithtax']);
			$bill->setLabel($rawDataArray['ordernumber']);
			$bill->setAddress($this->getAddress($rawDataArray['billingaddress']));
			$bill->setCurrency($rawDataArray['currencycode']);
			$bill->setTransactionDate($rawDataArray['paymentdate']);
			$bill->setLang($rawDataArray['document_lang']);
		
			$bill->setTransactionData(isset($bP['bankresponse']) ? $bP['bankresponse'] : null);
			
			$bill->setTransactionId(isset($bP['banktrsid']) ? $bP['banktrsid'] : null);
			$bill->setTransactionText(isset($bP['banktrs']) ? $bP['banktrs'] : null);
			
			try 
			{
				$media = isset($rawDataArray['bill']) ? DocumentHelper::getDocumentInstance($rawDataArray['bill']) : null;
				$bill->setArchive($media);
			}
			catch (Exception $e)
			{
				echo 'Invalid generated bill media ' . $rawDataArray['bill'] . ':' . $e->getMessage(). "\n";
			}
			$bill->save();
			
			echo "Add bill " . $bill->getId() . " " . $bill->getStatus() . " number ". $bill->getLabel(). "\n";
			//Removed from order 
			//ordernumber, paymentdate, bill, billingproperties
			//billingproperties: bankresponse, bankstatus, banktrsid, banktrs, billingMode, billingModeCodeReference
		}
		
		//Removed from order : shipmentDate, shippingproperties
		//shippingproperties: shippingMode, shippingModeCodeReference, packageTrackingURL, packageTrackingNumber, shippingModeTaxCode, shippingModeTaxRate
		$sP = is_null($rawDataArray['shippingproperties']) ? array() : unserialize($rawDataArray['shippingproperties']);
		if (isset( $sP['shippingModeTaxCode']))
		{
			$gP['shippingModeTaxCode'] = $sP['shippingModeTaxCode'];
		}
		if (isset( $sP['shippingModeTaxRate']))
		{
			$gP['shippingModeTaxRate'] = $sP['shippingModeTaxRate'];
		}
		
		if (count($gP))
		{
			$order->setGlobalProperties(serialize($gP));
		}
		else
		{
			$order->setGlobalProperties(null);
		}
		
		$expedition = order_ExpeditionService::getInstance()->createForOrder($order);
		if ($expedition)
		{
			$expedition->setCreationdate($rawDataArray['document_creationdate']);
			if ($rawDataArray['shipmentdate'] != null)
			{
				$expStatus = order_ExpeditionService::SHIPPED;
				$expedition->setShippingDate($rawDataArray['shipmentdate']);
			}
			else
			{
				$expStatus = order_ExpeditionService::PREPARE;
			}
			$expedition->setStatus($expStatus);
			
			$expedition->setBill($bill);
			$expedition->setTransporteur(isset($sP['shippingModeCodeReference'])? $sP['shippingModeCodeReference'] : null);
			$expedition->setTrackingNumber(isset($sP['packageTrackingNumber'])? $sP['packageTrackingNumber'] : null);
			$expedition->setTrackingURL(isset($sP['packageTrackingURL'])? $sP['packageTrackingURL'] : null);
			
			$number =  isset($sP['packageTrackingNumber']) ? $sP['packageTrackingNumber'] : '';
			$url = isset($sP['packageTrackingURL']) ? $sP['packageTrackingURL'] : '';
			$expedition->setTrackingURL(str_replace('{NumeroColis}', $number, $url));				
			$expedition->save();
			echo "Add Expedition " . $expedition->getId() . " " . $expedition->getStatus() . " package ". $number. "\n";
		}

		$order->setOrderStatus($finalOrderStatus);
		return $order;
	}
	
	/**
	 * @param integer $id
	 * @return order_persistentdocument_order
	 */
	private function getOrderById($id)
	{
		return DocumentHelper::getDocumentInstance($id , 'modules_order/order');
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @return order_persistentdocument_bill
	 */
	private function getBillByOrder($order)
	{
		$billArray = $order->getBillArrayInverse();
		if (count($billArray) == 0)
		{
			$bill = order_BillService::getInstance()->getNewDocumentInstance();
			$bill->setOrder($order);
			return $bill;
		}
		return $billArray[0];
	}
	
	private function getAddress($id)
	{
		if (intval($id) > 0)
		{
			try 
			{
				return DocumentHelper::getDocumentInstance($id, 'modules_customer/address');
			}
			catch (Exception $e)
			{
				echo "Invalid address id $id:" . $e->getMessage() . "\n";
			}
		}
		return null;
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @return order_persistentdocument_expedition
	 */
	private function getExpeditionByOrder($order)
	{
		$expeditionArray = $order->getExpeditionArrayInverse();
		if (count($expeditionArray) == 0)
		{
			$expedition = order_ExpeditionService::getInstance()->getNewDocumentInstance();
			$expedition->setOrder($order);
			return $expedition;
		}
		return $expeditionArray[0];
	}
}