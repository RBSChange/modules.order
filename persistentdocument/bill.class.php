<?php
/**
 * Class where to put your custom methods for document order_persistentdocument_bill
 * @package modules.order.persistentdocument
 */
class order_persistentdocument_bill extends order_persistentdocument_billbase implements payment_Order
{
	/**
	 * @return String
	 */
	public function getStatusLabel()
	{
		return LocaleService::getInstance()->transFO('m.order.frontoffice.status.bill.' . $this->getStatus(), array('ucf', 'html'));
	}
	
	/**
	 * @return String
	 */
	public function getBoStatusLabel()
	{
		return LocaleService::getInstance()->transBO('m.order.frontoffice.status.bill.' . $this->getStatus(), array('ucf', 'html'));
	}
	
	/**
	 * @deprecated 
	 * @return string
	 */
	public function getArchiveBoURL()
	{
		if (!$this->getDocumentService()->generateBillIsActive())
		{
			return "-2";
		}
		if ($this->getStatus() != order_BillService::SUCCESS)
		{
			return "-1";
		}
		if ($this->getArchive() !== null)
		{
			$actionUrl = LinkHelper::getUIActionLink("media", "BoDisplay");
			$actionUrl->setQueryParameter('cmpref', $this->getArchive()->getId());
			return $actionUrl->getUrl();
		}
		return "";
	}
	
	/**
	 * @param double $value
	 * @return string
	 */
	public function getAmountFormated()
	{
		return catalog_PriceFormatter::getInstance()->format($this->getAmount(), $this->getCurrency());
	}
	
	/**
	 * @see payment_Order::getPaymentAmount()
	 *
	 * @return double
	 */
	function getPaymentAmount()
	{
		return $this->getAmount();
	}
	
	/**
	 * @see payment_Order::getPaymentBillingAddress()
	 *
	 * @return customer_persistentdocument_address
	 */
	function getPaymentBillingAddress()
	{
		return $this->getAddress();
	}
	
	/**
	 * @see payment_Order::getPaymentCallbackURL()
	 *
	 * @return string
	 */
	function getPaymentCallbackURL()
	{
		$ops = order_OrderProcessService::getInstance();
		$orderProcess = $ops->loadFromSession();
		$params = array('orderParam' => array('orderId' => $this->getOrder()->getId()));
		$url = $ops->getStepURL($orderProcess->getLastStep(), $orderProcess, $params);
		Framework::info(__METHOD__ . ': ' . $url);
		return $url;
	}
	
	/**
	 * @see payment_Order::getPaymentConnector()
	 *
	 * @return payment_persistentdocument_connector
	 */
	function getPaymentConnector()
	{
		$connectorId = $this->getConnectorId();
		if ($connectorId)
		{
			return DocumentHelper::getDocumentInstance($connectorId, 'modules_payment/connector');
		}
		return null;
	}
	
	/**
	 * @see payment_Order::getPaymentCurrency()
	 * "EUR", "GBP", "CHF"
	 * @return string
	 */
	function getPaymentCurrency()
	{
		return $this->getCurrency();
	}
	
	/**
	 * @see payment_Order::getPaymentDate()
	 *
	 * @return string
	 */
	function getPaymentDate()
	{
		return $this->getTransactionDate();
	}
	
	/**
	 * @see payment_Order::getPaymentId()
	 *
	 * @return integer
	 */
	function getPaymentId()
	{
		return $this->getId();
	}
	
	/**
	 * @see payment_Order::getPaymentLang()
	 *
	 * @return string
	 */
	function getPaymentLang()
	{
		return $this->getLang();
	}
	
	/**
	 * @see payment_Order::getPaymentReference()
	 *
	 * @return string
	 */
	function getPaymentReference()
	{
		return $this->getLabel() . '/' . $this->getId();
	}
	
	/**
	 * @see payment_Order::getPaymentResponse()
	 *
	 * @return string
	 */
	function getPaymentResponse()
	{
		return $this->getTransactionData();
	}
	
	/**
	 * @see payment_Order::getPaymentShippingAddress()
	 *
	 * @return customer_persistentdocument_address
	 */
	function getPaymentShippingAddress()
	{
		return $this->getOrder()->getShippingAddress();
	}
	
	/**
	 * @see payment_Order::getPaymentStatus()
	 *
	 * @return string
	 */
	function getPaymentStatus()
	{
		return $this->getStatus();
	}
	
	/**
	 * @see payment_Order::getPaymentTransactionId()
	 *
	 * @return string
	 */
	function getPaymentTransactionId()
	{
		return $this->getTransactionId();
	}
	
	/**
	 * @see payment_Order::getPaymentTransactionText()
	 *
	 */
	function getPaymentTransactionText()
	{
		return $this->getTransactionText();
	}
	
	/**
	 * @see payment_Order::getPaymentUser()
	 *
	 * @return users_persistentdocument_frontenduser
	 */
	function getPaymentUser()
	{
		return $this->getOrder()->getCustomer()->getUser();
	}
	
	/**
	 * @see payment_Order::setPaymentDate()
	 *
	 * @param string $date
	 */
	function setPaymentDate($date)
	{
		$this->setTransactionDate($date);
	}
	
	
	/**
	 * @see payment_Order::setPaymentResponse()
	 *
	 * @param string $response
	 */
	function setPaymentResponse($response)
	{
		$this->setTransactionData($response);
	}
	
	/**
	 * @see payment_Order::setPaymentTransactionId()
	 *
	 * @param string $transactionId
	 */
	function setPaymentTransactionId($transactionId)
	{
		$this->setTransactionId($transactionId);
	}
	
	/**
	 * @see payment_Order::setPaymentTransactionText()
	 *
	 * @param string $transactionText
	 */
	function setPaymentTransactionText($transactionText)
	{
		$this->setTransactionText($transactionText);
	}
	
	/**
	 * @see payment_Order::setPaymentStatus()
	 *
	 * @param string $status
	 */
	function setPaymentStatus($status)
	{
		$this->getDocumentService()->updatePaymentStatus($this, $status);
	}
	
	/**
	 * @return String
	 */
	function getClientIp()
	{
		return $this->getMeta('clientip');
	}
	
	/**
	 * @param String $ip
	 */
	function setClientIp($ip)
	{
		return $this->setMeta('clientip', $ip);
	}
}