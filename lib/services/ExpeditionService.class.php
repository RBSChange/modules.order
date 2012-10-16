<?php
/**
 * order_ExpeditionService
 * @package modules.order
 */
class order_ExpeditionService extends f_persistentdocument_DocumentService
{
	const PREPARE = "prepare";
	const SHIPPED = "shipped";	
	const CANCELED = "canceled";	
	
	/**
	 * @var order_ExpeditionService
	 */
	private static $instance;

	/**
	 * @return order_ExpeditionService
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
	 * @return order_persistentdocument_expedition
	 */
	public function getNewDocumentInstance()
	{
		return $this->getNewDocumentInstanceByModelName('modules_order/expedition');
	}

	/**
	 * Create a query based on 'modules_order/expedition' model.
	 * Return document that are instance of modules_order/expedition,
	 * including potential children.
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createQuery()
	{
		return $this->pp->createQuery('modules_order/expedition');
	}
	
	/**
	 * Create a query based on 'modules_order/expedition' model.
	 * Only documents that are strictly instance of modules_order/expedition
	 * (not children) will be retrieved
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createStrictQuery()
	{
		return $this->pp->createQuery('modules_order/expedition', false);
	}
	
	/**
	 * @param integer $orderId
	 * @return boolean
	 */
	public function existForOrderId($orderId)
	{
		$result = order_ExpeditionlineService::getInstance()->createQuery()->setProjection(Projections::count('id', 'countId'))
		->add(Restrictions::eq('orderId', $orderId))->findColumn('countId');
		return is_array($result) && $result[0] > 0;
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @return order_persistentdocument_expedition[]
	 */
	public function getByOrder($order)
	{
		$query = $this->createQuery()->add(Restrictions::eq('order', $order))
				->addOrder(Order::asc('document_label'));
		return $query->find();
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @return order_persistentdocument_expedition[]
	 */
	public function getShippedByOrder($order)
	{
		$query = $this->createQuery()->add(Restrictions::eq('order', $order))
				->add(Restrictions::eq('status', self::SHIPPED))
				->addOrder(Order::asc('document_label'));
		return $query->find();
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @return order_persistentdocument_expedition[]
	 */
	public function getByOrderForDisplay($order)
	{
		$query = $this->createQuery()
				->add(Restrictions::published())
				->add(Restrictions::eq('order', $order))
				->add(Restrictions::ne('status', self::CANCELED))
				->addOrder(Order::asc('document_label'));
		return $query->find();
	}
	
	/**
	 * @param order_persistentdocument_expedition $expedition
	 * @return order_persistentdocument_expeditionline[]
	 */
	public function getLinesForDisplay($expedition)
	{
		$lines = $expedition->getLineArray();
		
		$shippmentMode = $expedition->getShippingMode();
		if ($shippmentMode)
		{
			$sms = $shippmentMode->getDocumentService();
			foreach ($lines as $line) 
			{
				$sms->completeExpeditionLineForDisplay($line, $shippmentMode, $expedition);		
			}
		}
		
		return $lines;
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @param integer $shippingModeId
	 * @return order_persistentdocument_expedition[]
	 */
	private function getByOrderAndShippingMode($order, $shippingModeId)
	{
		$query = $this->createQuery()->add(Restrictions::eq('order', $order))
				->add(Restrictions::eq('shippingModeId', $shippingModeId))
				->addOrder(Order::asc('document_label'));
		return $query->find();
	}
	
	/**
	 * @param order_persistentdocument_expedition $expedition
	 * @return Array<String=>String>
	 */
	public function getNotificationParameters($expedition)
	{
		$numbers = array();
		if (($tn = $expedition->getTrackingNumber()) != null)
		{
			$tn = f_util_HtmlUtils::textToHtml($tn);
			$trackingUrl = $expedition->getTrackingURL();
			if ($trackingUrl !== null)
			{
				$numbers[$tn] = '<a href="' . $trackingUrl . '">' . $tn . '</a>';
			}
			else
			{
				$numbers[$tn] = $tn;
			}
		}
		foreach ($expedition->getLineArray() as $line)
		{
			/* @var $line order_persistentdocument_expeditionline */
			if (($tn = $line->getTrackingNumber()) != null)
			{
				$tn = f_util_HtmlUtils::textToHtml($tn);
				$trackingUrl = $line->getEvaluatedTrackingURL();
				if ($trackingUrl)
				{
					$numbers[$tn] = '<a href="' . $trackingUrl . '">' . $tn . '</a>';
				}
				else
				{
					$numbers[$tn] = $tn;
				}
			}
		}
		
		
		$template = TemplateLoader::getInstance()->setPackageName('modules_order')->setMimeContentType(K::HTML)
			->setDirectory('templates/mails')->load('Order-Inc-ExpeditionLines');
		$template->setAttribute('expedition', $expedition);
		
		$params = array(
			'packageNumber' =>  implode(', ', $numbers),
			'trackingNumber' => implode(', ', array_keys($numbers)) ,
			'expeditionUrl' => LinkHelper::getDocumentUrlForWebsite($expedition, $expedition->getOrder()->getWebsite(), $expedition->getOrder()->getLang()),
			'expeditionDetail' => $template->execute()
		);
		
		$shippingMode = $expedition->getShippingMode();
		if ($shippingMode instanceof shipping_persistentdocument_mode)
		{
			$params = array_merge($params, $shippingMode->getDocumentService()->getNotificationParameters($shippingMode, $expedition));
		}
		
		return $params;
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @param order_persistentdocument_bill $bill
	 * @return order_persistentdocument_expedition
	 */
	public function createForOrder($order, $bill = null)
	{
		$shippingModes = $order->getShippingDataArray();
		if (!is_array($shippingModes) || (count($shippingModes) == 1 && isset($shippingModes[0])))
		{
			$expedition = $this->generateDefaultExpedition($order, $bill);
			if ($expedition !== null)
			{
				$expedition->save();
				if ($expedition->getStatus() == self::PREPARE)
				{
					return $expedition;
				}
			}
			return null;
		}
		
		$result = null;
		foreach ($shippingModes as $shippingModeId => $datas) 
		{
			if ($shippingModeId == 0)
			{
				$shippingMode = shipping_persistentdocument_mode::getInstanceById($order->getShippingModeId()); 
			}
			else
			{
				$shippingMode = shipping_persistentdocument_mode::getInstanceById($shippingModeId); 
			}
			
			$expedition = $this->generateForShippingMode($order, $shippingMode, $datas['lines']);
			if ($expedition !== null)
			{
				$expedition->save();
				if ($expedition->getStatus() == self::PREPARE)
				{
					$result = $expedition;
				}
			}
		}
		return $result;
	}
	
	/**
	 * @param order_persistentdocument_orderpreparation $orderpreparation
	 * @return order_persistentdocument_expedition[]
	 */
	public function createForOrderPreparation($orderpreparation)
	{
		$expeditions = array();
		try 
		{
			$this->getTransactionManager()->beginTransaction();
			$order = $orderpreparation->getOrderInstance();
			$defaultShippingModeId = intval($order->getShippingModeId());
						
			foreach ($orderpreparation->getLinesArray() as $lineInfo)
			{
				$orderLineId = 	$lineInfo['id'];
				$quantity = $lineInfo['quantity'];
				$orderLine = order_persistentdocument_orderline::getInstanceById($orderLineId);
				$shippingModeId = intval($orderLine->getShippingModeId());
				if ($shippingModeId <= 0) {$shippingModeId = $defaultShippingModeId;}
				
				if (isset($expeditions[$shippingModeId]))
				{
					$expedition = $expeditions[$shippingModeId];
				}
				else
				{
					$expedition = $this->getNewDocumentInstance();
					$expeditions[$shippingModeId] = $expedition;
					$expedition->setOrder($order);
					
					//$expedition->setBill(null);
					//$expedition->setAmount(0);
					
					$expedition->setStatus(self::PREPARE);
					$addressId = $order->getAddressIdByModeId($shippingModeId);
					$address = ($addressId) ?  order_persistentdocument_shippingaddress::getInstanceById($addressId) : $order->getShippingAddress();
					$expedition->setAddress($address);
					$expedition->setShippingModeId(($shippingModeId) ? $shippingModeId : null);
					
					if ($shippingModeId)
					{
						$shippingMode = shipping_persistentdocument_mode::getInstanceById($shippingModeId);
						$shippingMode->getDocumentService()->completeExpedtionForMode($expedition, $shippingMode);
					}
				}
				
				$expLine = $this->getNewExpeditionLine();
				$expLine->setLabel($orderLine->getLabel());
				$expLine->setQuantity($quantity);
				$expLine->setOrderId($order->getId());
				$expLine->setOrderlineid($orderLineId);	
				$expLine->setTrackingURL($expedition->getOriginalTrackingURL());
				$expedition->addLine($expLine);
			}
			
			foreach ($expeditions as $expedition) 
			{
				$expedition->setLabel(order_ExpeditionNumberGenerator::getInstance()->generate($expedition));
				$this->save($expedition);
			}
			
			$orderpreparation->getDocumentService()->file($orderpreparation->getId());
			$this->getTransactionManager()->commit();
		} 
		catch (Exception $e) 
		{
			$this->getTransactionManager()->rollBack($e);
			throw $e;
		}
		return $expeditions;
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @param shipping_persistentdocument_mode $shippingMode
	 * @param array index
	 * @return order_persistentdocument_expedition
	 */
	private function generateForShippingMode($order, $shippingMode, $linesIndex)
	{
		$lines = array();
		foreach ($linesIndex as $index) 
		{
			$line = $order->getLine($index);
			$lines[$line->getId()] = $line->getQuantity();
		}
		
		$preparedLines = array();
		$previousExpeditions = $this->getByOrderAndShippingMode($order, $shippingMode->getId());	
		foreach ($previousExpeditions as $expedition) 
		{
			$this->getOrderLineIds($expedition, $preparedLines);
		}
		
		foreach ($preparedLines as $id => $qtt) 
		{
			if (isset($lines[$id]))
			{
				$lines[$id] -= $qtt;
				if ($lines[$id] <= 0)
				{
					unset($lines[$id]);
				}
			}
			else
			{
				Framework::warn(__METHOD__ . " order line $id not found");
			}
		}
		
		if (count($lines) > 0)
		{
			$addressId = $order->getAddressIdByModeId($shippingMode->getId());
			$address = ($addressId) ? order_persistentdocument_shippingaddress::getInstanceById($addressId):  $order->getShippingAddress();
			$expedition = $this->getNewDocumentInstance();
			$expedition->setOrder($order);
			$expedition->setStatus(self::PREPARE);
			$expedition->setAddress($address);
			$expedition->setLabel(order_ExpeditionNumberGenerator::getInstance()->generate($expedition));
			$shippingMode->getDocumentService()->completeExpedtionForMode($expedition, $shippingMode);
			foreach ($lines as $id => $qtt) 
			{
				$line = order_persistentdocument_orderline::getInstanceById($id);
				$expLine = $this->getNewExpeditionLine();
				$expLine->setLabel($line->getLabel());
				$expLine->setQuantity($qtt);
				$expLine->setOrderId($order->getId());
				$expLine->setOrderlineid($id);	
				$expLine->setTrackingURL($expedition->getOriginalTrackingURL());
				$expedition->addLine($expLine);
			}		
			return $expedition;
		}
		return null;
	}	
	
	/**
	 * @return order_persistentdocument_expeditionline
	 */
	public function getNewExpeditionLine()
	{
		return order_ExpeditionlineService::getInstance()->getNewDocumentInstance();
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @param order_persistentdocument_bill $bill
	 * @return order_persistentdocument_expedition
	 */
	private function generateDefaultExpedition($order, $bill)
	{
		$previousExpeditions = $this->getByOrderAndShippingMode($order, $order->getShippingModeId());	
		
		$expedition = $this->getNewDocumentInstance();
		$expedition->setOrder($order);
		$expedition->setStatus(self::PREPARE);
		$expedition->setAddress($order->getShippingAddress());
		$expedition->setShippingModeId($order->getShippingModeId());
		$expedition->setBill($bill);
		
		$shippingMode = $expedition->getShippingMode();
		$expedition->setTransporteur($shippingMode->getCodeReference());
		$expedition->setTrackingURL($shippingMode->getTrackingUrl());
		
		$expedition->setAmount($order->getShippingFeesWithTax());
		$expedition->setTax($order->getShippingFeesWithTax() - $order->getShippingFeesWithoutTax());
		$shippingMode->getDocumentService()->completeExpedtionForMode($expedition, $shippingMode);
		
		$previouslines = array();
		foreach ($previousExpeditions as $previousExpedition) 
		{
			$this->getOrderLineIds($previousExpedition, $previouslines);
		}
		foreach ($order->getLineArray() as $line) 
		{
			$qtt = $line->getQuantity();
			if (isset($previouslines[$line->getId()]))
			{
				$qtt -= $previouslines[$line->getId()];
			}
			if ($qtt <= 0)
			{
				continue;
			}
			$expLine = $this->getNewExpeditionLine();
			$expLine->setLabel($line->getLabel());
			$expLine->setQuantity($qtt);
			$expLine->setOrderId($order->getId());
			$expLine->setOrderlineid($line->getId());	
			$expLine->setTrackingURL($expedition->getOriginalTrackingURL());
			$expedition->addLine($expLine);
		}
		
		if ($expedition->getLineCount() > 0)
		{
			$expedition->setLabel(order_ExpeditionNumberGenerator::getInstance()->generate($expedition));	
			return $expedition;
		}
		return null;
	}
	
	/**
	 * @param order_persistentdocument_expedition $expedition
	 * @param array $array
	 */
	private function getOrderLineIds($expedition, &$array)
	{
		foreach ($expedition->getLineArray() as $line) 
		{
			if (isset($array[$line->getOrderlineid()]))
			{
				$array[$line->getOrderlineid()] += $line->getQuantity();
			}
			else
			{
				$array[$line->getOrderlineid()] = $line->getQuantity();
			}
		}
		return $array;
	}
		
	/**
	 * @param order_persistentdocument_order $order
	 * @return array
	 */
	public function getBoList($order)
	{
		$result = array();
		foreach ($this->getByOrder($order) as $bill) 
		{
			$result[] = $this->buildBoRow($bill);
		}
		return $result;	
	}
	
	/**
	 * @param order_persistentdocument_expedition $expedition
	 * @return array
	 */
	private function buildBoRow($expedition)
	{
		$trackingNumbers = $this->getTrackingNumbers($expedition);
		$packetNumbers = $this->getPacketNumbers($expedition);
		$result = array(
			'id' => $expedition->getId(),
			'lang' => $expedition->getLang(),
			'type' => str_replace('/', '_', $expedition->getDocumentModelName()),
			'st' => $expedition->getStatus(),
			'status' => $expedition->getBoStatusLabel(),
			'label' => $expedition->getLabel(),
			'trackingnumber' => (count($trackingNumbers)) ? implode(', ', $trackingNumbers) : '',
			'trackingurl' => (count($trackingNumbers))? $expedition->getTrackingURL() : null,
			'transporteur' => $expedition->getTransporteur(),
			'packetnumber' => (count($packetNumbers)) ? implode(', ', $packetNumbers) : ''
		);		
		return $result;
	}
	
	/**
	 * @param order_persistentdocument_expedition $expedition
	 * @return string[]
	 */
	public function getPacketNumbers($expedition)
	{
		$packetNumbers = array();
		foreach ($expedition->getLineArray() as $line)
		{
			/* @var $line order_persistentdocument_expeditionline */
			if ($line->getPacketNumber())
			{
				$packetNumbers[$line->getPacketNumber()] = true;
			}
		}
		return array_keys($packetNumbers);
	}
	
	/**
	 * @param order_persistentdocument_expedition $expedition
	 * @return string[]
	 */
	public function getTrackingNumbers($expedition)
	{
		$trackingNumbers = array();
		foreach ($expedition->getLineArray() as $line)
		{
			/* @var $line order_persistentdocument_expeditionline */
			if ($line->getTrackingNumber())
			{
				$trackingNumbers[$line->getTrackingNumber()] = true;
			}
		}
		return array_keys($trackingNumbers);
	}
	
	/**
	 * @param order_persistentdocument_expedition $expedition
	 * @return array
	 */	
	public function buildShipExpeditionDialogParams($expedition)
	{
		$trackingNumber = $expedition->getTrackingNumber();
		$result = array(
			'id' => $expedition->getId(),
			'lang' => $expedition->getLang(),
			'label' => $expedition->getLabel(),
			'transporteur' => $expedition->getTransporteur()
		);		
		return $result;
	}
	
	/**
	 * @return string
	 */
	protected function getDefaultCancelTextMessage()
	{
		$user = users_UserService::getInstance()->getCurrentUser();
		$name = ($user !== null) ? $user->getFullname() : 'Anonymous';
		return LocaleService::getInstance()->transFO('m.order.document.expedition.cancel-by', array('ucf'), array('name' => $name));
	}
	
	
	/**
	 * @param order_persistentdocument_expedition $expedition
	 * @return array
	 */
	public function cancelExpeditionFromBo($expedition)
	{
		$message = $this->getDefaultCancelTextMessage();
		$this->cancelExpedition($expedition, $message);
		return $this->buildBoRow($expedition);
	}
	
	/**
	 * 
	 * @param order_persistentdocument_expedition $expedition
	 * @param string $message
	 * @throws Exception
	 */
	public function cancelExpedition($expedition, $message)
	{
		try
		{
			$this->tm->beginTransaction();
			$expedition->setShippingDate(null);
			$expedition->setStatus(self::CANCELED);
			$expedition->setTrackingText($message);
			$this->save($expedition);
			
			$order = $expedition->getOrder();		
			order_ModuleService::getInstance()->sendCustomerNotification('modules_order/expedition_canceled', $order, $expedition->getBill(), $expedition);
			
			if ($this->isCompleteForOrder($order))
			{
				if ($this->hasShippedExpeditionFromOrder($order))
				{
					order_OrderService::getInstance()->completeOrder($order);
				}
				else
				{
					order_OrderService::getInstance()->cancelOrder($order);
				}
			}
			elseif (order_ModuleService::getInstance()->isDefaultExpeditionGenerationEnabled())
			{
				$this->createForOrder($order);
			}
			$this->tm->commit();
		}
		catch (Exception $e)
		{
			$this->tm->rollBack($e);
			throw $e;
		}		
	}
	
	/**
	 * @see order_OrderService::cancelOrder()
	 * @param order_persistentdocument_order $order
	 */
	public function cancelPrepareByOrder($order)
	{
		$query = $this->createQuery()->add(Restrictions::eq('order', $order))->add(Restrictions::eq('status', self::PREPARE));
		$message = $this->getDefaultCancelTextMessage();
		foreach ($query->find() as $expedition)
		{
			$expedition->setShippingDate(null);
			$expedition->setStatus(self::CANCELED);
			$expedition->setTrackingText($message);
			$this->save($expedition);
		}
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @return boolean
	 */
	public function hasShippedExpeditionFromOrder($order)
	{
		$result = $this->createQuery()
			->add(Restrictions::eq('order', $order))
			->add(Restrictions::eq('status', self::SHIPPED))
			->setProjection(Projections::rowCount('countShipped'))->findColumn('countShipped');
		return (count($result)) ? $result[0] > 0 : false;
	}
	
	/**
	 * @param order_persistentdocument_expedition $expedition
	 * @param string $shippingDate
	 * @param string $trackingNumber
	 * @param string $packetNumber
	 * @return array
	 */
	public function shipExpeditionFromBo($expedition, $shippingDate, $trackingNumber, $packetNumber = null)
	{
		if ($expedition->getStatus() == self::PREPARE	&& f_util_StringUtils::isNotEmpty($shippingDate))
		{
			$message = null;
			$this->shipExpedition($expedition, date_Converter::convertDateToGMT($shippingDate), $trackingNumber, null, $packetNumber);
		}
		return $this->buildBoRow($expedition);
	}	
	
	/**
	 * @param order_persistentdocument_expedition $expedition
	 * @param string $shippingDate
	 * @param string $trackingNumber
	 * @param string $message
	 * @param string $packetNumber
	 */
	public function shipExpedition($expedition, $shippingDate, $trackingNumber, $message = null, $packetNumber = null)
	{
		if ($expedition->getStatus() == self::PREPARE)
		{
			if (empty($shippingDate))
			{
				$shippingDate = date_Calendar::getInstance()->toString();
			}
			$tm = $this->getTransactionManager();
			
			try 
			{
				$tm->beginTransaction();
				if ($packetNumber == null) 
				{
					$packetNumber = order_PacketNumberGenerator::getInstance()->generate($expedition);
				}
				$expedition->setStatus(self::SHIPPED);
				$expedition->setTrackingText($message);
				$expedition->setTrackingNumber($trackingNumber);
				$expedition->setPacketNumber($packetNumber);			
				$expedition->setShippingDate($shippingDate);
				
				$mode = $expedition->getShippingMode();
				$sms = $mode->getDocumentService();
				$sms->completeExpeditionForShipping($mode, $expedition);
				
				$trackingNumber = $expedition->getTrackingNumber();
				$shippingDate =  $expedition->getShippingDate();
				$packetNumber =  $expedition->getPacketNumber();
				
				foreach ($expedition->getLineArray() as $expeditionLine) 
				{
					/* @var $expeditionLine order_persistentdocument_expeditionline */
					if ($expeditionLine->getShippingDate() == null) {$expeditionLine->setShippingDate($shippingDate);}
					if ($expeditionLine->getTrackingNumber() == null) {$expeditionLine->setTrackingNumber($trackingNumber);}
					if ($expeditionLine->getPacketNumber() == null) {$expeditionLine->setPacketNumber($packetNumber);}
					
					if ($expeditionLine->isModified())
					{
						$expeditionLine->save();
					}
				}
						
				$this->save($expedition);	
				$order = $expedition->getOrder();
				
				$completlyShipped = (($expedition->getStatus() === self::SHIPPED) && $this->isCompleteForOrder($order));				
				$sms->sendShippedNotification($mode, $expedition, $completlyShipped);
				
				if ($completlyShipped)
				{
					order_OrderService::getInstance()->completeOrder($order);
				}
				elseif (order_ModuleService::getInstance()->isDefaultExpeditionGenerationEnabled())
				{
					$this->createForOrder($order);
				}
				$tm->commit();
			}
			catch (Exception $e)
			{
				$tm->rollBack($e);
				throw $e;
			}
		}
		else
		{
			throw new Exception('Error on expedition shipping');
		}
	}
	
	/**
	 * Return true if all orderline as SHIPPED or CANCELED
	 * @param order_persistentdocument_order $order
	 * @return boolean
	 */
	public function isCompleteForOrder($order)
	{
		$result = array();
		foreach ($order->getLineArray() as $orderline) 
		{
			/* @var $orderline order_persistentdocument_orderline */
			$result[intval($orderline->getId())] = array('id' => $orderline->getId(), 'quantity' => $orderline->getQuantity());
		}
		
		$rows = order_ExpeditionlineService::getInstance()->createQuery()
				->setProjection(Projections::groupProperty('orderlineid', 'id'), Projections::sum('quantity', 'quantity'))
				->add(Restrictions::eq('orderId', $order->getId()))
				->add(Restrictions::in('expedition.status', array(self::SHIPPED, self::CANCELED)))
				->find();
	
		foreach ($rows as $rowInfo) 
		{
			$orderlineid = intval($rowInfo['id']);
			if (isset($result[$orderlineid]))
			{
				$newQtt = $result[$orderlineid]['quantity'] - $rowInfo['quantity'];
				if ($newQtt > 0)
				{
					$result[$orderlineid]['quantity'] = $newQtt;
				}
				else
				{
					unset($result[$orderlineid]);
				}
			}
		}		
		return count($result) == 0;
	}
	
	/**
	 * Retour ordeline witgh quantity not present in expedition
	 * @param order_persistentdocument_order $order
	 * @return array<'id' => orderLineId, 'quantity' => integer>
	 */	
	public function getOrderLinesWithNotExpedition($order)
	{
		$result = array();
		foreach ($order->getLineArray() as $orderline)
		{
			/* @var $orderline order_persistentdocument_orderline */
			$result[intval($orderline->getId())] = array('id' => $orderline->getId(), 'quantity' => $orderline->getQuantity());
		}
		
		$rows = order_ExpeditionlineService::getInstance()->createQuery()
		->setProjection(Projections::groupProperty('orderlineid', 'id'), Projections::sum('quantity', 'quantity'))
		->add(Restrictions::eq('orderId', $order->getId()))
		->find();

		foreach ($rows as $rowInfo)
		{
			
			$orderlineid = intval($rowInfo['id']);
			if (isset($result[$orderlineid]))
			{
				$newQtt = $result[$orderlineid]['quantity'] - $rowInfo['quantity'];
				if ($newQtt > 0)
				{
					$result[$orderlineid]['quantity'] = $newQtt;
				}
				else
				{
					unset($result[$orderlineid]);
				}
			}
		}
		return array_values($result);
	}
	
	/**
	 * Finalize all expeditions for order
	 * Create a canceled expedition if needed for unexpedied orderline
	 * Cancel all prepered expedition
	 * @param order_persistentdocument_order $order
	 * @return order_persistentdocument_expedition[] Modified expedition
	 */
	public function cleanUpExpeditionsForOrder($order)
	{
		$result = array();
		$tm = $this->getTransactionManager();
		try
		{
			$tm->beginTransaction();
			$lineInfos = $this->getOrderLinesWithNotExpedition($order);
			if (count($lineInfos))
			{
				$canceledExp = $this->getNewDocumentInstance();
				$canceledExp->setOrder($order);
				$canceledExp->setStatus(self::CANCELED);
				$canceledExp->setLabel(self::CANCELED);
					
				foreach ($lineInfos as $lineInfo)
				{
					$orderLineId = 	$lineInfo['id'];
					$quantity = $lineInfo['quantity'];
					$orderLine = order_persistentdocument_orderline::getInstanceById($orderLineId);
					$expLine = $this->getNewExpeditionLine();
					$expLine->setLabel($orderLine->getLabel());
					$expLine->setQuantity($quantity);
					$expLine->setOrderId($order->getId());
					$expLine->setOrderlineid($orderLineId);			
					$canceledExp->addLine($expLine);				
				}
				$this->save($canceledExp);
				$result[] = $canceledExp;
			}
			
			$preparedExpeditions = $this->createQuery()
				->add(Restrictions::eq('status', self::PREPARE))
				->add(Restrictions::eq('order', $order))
				->find();
			
			foreach ($preparedExpeditions as $value)
			{
				/* @var $value order_persistentdocument_expedition */
				$value->setShippingDate(null);
				$value->setPacketNumber(null);
				$value->setTrackingNumber(null);
				foreach ($value->getLineArray() as $line)
				{
					/* @var $line order_persistentdocument_expeditionline */
					$line->setShippingDate(null);
					$line->setPacketNumber(null);
					$line->setTrackingNumber(null);
					if ($line->isModified())
					{
						$line->save();
					}
				}
				$value->setStatus(self::CANCELED);
				$this->save($value);
				$result[] = $value;
			}			
			$tm->commit();
		} 
		catch (Exception $e) 
		{
			$tm->rollback($e);
			throw $e;
		}
		return $result;
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @return string [nothing|valid|preparation|partialy-shipped|shipped]
	 */
	public function evaluateGlobalStatusForOrder($order)
	{
		$value = 'nothing';
		if ($order->getOrderStatus() === order_OrderService::COMPLETE)
		{
			$value = 'shipped';
		}
		elseif ($order->getOrderStatus() === order_OrderService::IN_PROGRESS)
		{
			
			if (order_ModuleService::getInstance()->isDefaultExpeditionGenerationEnabled())
			{
				$value = 'preparation';
			}
			else
			{
				$value = 'valid';
			}
			
			$expArray = $this->getByOrder($order);
			if (count($expArray))
			{
				$value = ($this->hasShippedExpeditionFromOrder($order)) ? 'partialy-shipped' : 'preparation';
			}
			elseif (order_ModuleService::getInstance()->useOrderPreparationEnabled())
			{
				if (count(order_OrderpreparationService::getInstance()->getByOrder($order)))
				{
					$value = 'preparation';
				}
			}
		} 
		return $value;
	}
	

	/**
	 * @param string $packetNumber
	 * @param string $expeditionStatus (prepare|shipped|canceled)
	 * @param string $packetStatus (in_transit|received|delivered)
	 * @return order_persistentdocument_expedition[]
	 */
	public function getByPacketNumber($packetNumber, $expeditionStatus = null, $packetStatus = null)
	{
		if (empty($packetNumber))
		{
			return array();
		}
		
		$query = $this->createQuery();
		if ($expeditionStatus !== null)
		{
			$query->add(Restrictions::eq('status', $expeditionStatus));
		}		
		
		$lineQuery = $query->createCriteria('line')->add(Restrictions::eq('packetNumber', $packetNumber, true));
		if ($packetStatus !== null)
		{
			$lineQuery->add(Restrictions::eq('status', $packetStatus));
		}	
		return $query->find();
	}	
		
	/**
	 * @param order_persistentdocument_expedition $document
	 * @param string $forModuleName
	 * @param array $allowedSections
	 * @return array
	 */
	public function getResume($document, $forModuleName, $allowedSections = null)
	{
		$resume = parent::getResume($document, $forModuleName, $allowedSections);
		
		$trackingNumber = $document->getTrackingNumber();
		$resume['properties']['tracking'] = array(
			'label' => ($trackingNumber) ? $trackingNumber : '-', 
			'href' => ($trackingNumber) ? $document->getTrackingURL() : null
		);
		
		$resume['properties']['status'] = array('label' => $document->getBoStatusLabel());
		
		if ($document->getStatus() == self::PREPARE)
		{
			$resume['properties']['status']['rowData'] = JsonService::getInstance()->encode($this->buildShipExpeditionDialogParams($document));
		}
		
		$addressData = array();
		$address = $document->getAddress();
		if ($address instanceof customer_persistentdocument_address)
		{
			$addressData['label'] = $address->getLabel();
			$addressData['email'] = $address->getEmail();
			$addressData['company'] = $address->getCompany();
			$addressData['addressLine1'] = $address->getAddressLine1();
			$addressData['addressLine2'] = $address->getAddressLine2();
			$addressData['addressLine3'] = $address->getAddressLine3();
			$addressData['zipCode'] = $address->getZipCode();
			$addressData['city'] = $address->getCity();
			$addressData['province'] = $address->getProvince();
			$addressData['country'] = $address->getCountryName();
			$addressData['phone'] = $address->getPhone();
			$addressData['fax'] = $address->getFax();
			$addressData['mobilephone'] = $address->getMobilephone();
		}
		$resume['address'] = $addressData;
		
		$resume['lines'] = array();
		foreach ($this->getLinesForDisplay($document) as $line)
		{
			/* @var $line order_persistentdocument_expeditionline */
			$resume['lines'][] = array(
				'id' => $line->getId(),
				'label' => $line->getLabel(),
				'quantity' => $line->getQuantity(),
				'codeReference' => $line->getCodeReference(),
				'packetnumber'=> $line->getPacketNumber(),
			);
		}
		
		return $resume;
	}
	
	/**
	 * @param order_persistentdocument_expedition $document
	 * @return website_persistentdocument_page or null
	 */
	public function getDisplayPage($document)
	{
		if ($document->isPublished())
		{
			$mode = $document->getShippingMode();
			$page = null;
			if ($mode instanceof shipping_persistentdocument_mode)
			{
				$page = $mode->getDocumentService()->getDisplayPageForExpedition($mode, $document);
			}
						
			if ($page === null)
			{
				if (customer_CustomerService::getInstance()->getCurrentCustomer() === $document->getOrder()->getCustomer())
				{
					return parent::getDisplayPage($document);
				}
			}
			elseif ($page !== false)
			{
				return $page;
			} 
		}
		return null;
	}
	
	/**
	 * @param order_persistentdocument_expedition $expedition
	 * @param order_persistentdocument_expeditionline[] $lines
	 * @param string $date
	 * @param string $message
	 * @return order_persistentdocument_expeditionline[] lines marked received
	 */
	public function receiveLines($expedition, $lines, $date = null, $message = null)
	{
		$linesReceived = array();		
		if ($date === null)
		{
			$date = date_Calendar::getInstance()->toString();
		}
		
		$tm = $this->getTransactionManager();
		try
		{
			$tm->beginTransaction();
			foreach ($lines as $line)
			{
				/* @var $line order_persistentdocument_expeditionline */
				if ($line->getStatus() === order_ExpeditionlineService::IN_TRANSIT && $expedition->getIndexofLine($line) >= 0)
				{
					$line->setStatus(order_ExpeditionlineService::RECEIVED);
					$line->setDescription($message);
					$line->setReceiveDate($date);
					$line->save();
					$linesReceived[] = $line;
				}
			}
			
			$shipped = true;
			foreach ($expedition->getLineArray() as $line)
			{
				/* @var $line order_persistentdocument_expeditionline */
				if ($line->getStatus() === order_ExpeditionlineService::IN_TRANSIT)
				{
					$shipped = false;
					break;
				}
			}
			
			if ($shipped && $expedition->getStatus() === self::PREPARE)
			{
				$this->shipExpedition($expedition, $date, $expedition->getTrackingNumber(), null, $expedition->getPacketNumber());
			}
			$tm->commit();
		} 
		catch (Exception $e) 
		{
			$tm->rollback($e);
			throw $e;
		}
		
		return $linesReceived;
	}	
	
	/**
	 * @param order_persistentdocument_expedition $expedition
	 * @param order_persistentdocument_expeditionline[] $lines
	 * @param string $date
	 * @param string $message
	 * @return order_persistentdocument_expeditionline[] lines marked delivered
	 */	
	public function deliverLines($expedition, $lines, $date = null, $message = null)
	{
		$linesDelivered = array();
		
		if ($date === null)
		{
			$date = date_Calendar::getInstance()->toString();
		}
		$tm = $this->getTransactionManager();
		try
		{
			$tm->beginTransaction();
			foreach ($lines as $line)
			{
				/* @var $line order_persistentdocument_expeditionline */
				if ($line->getStatus() === order_ExpeditionlineService::RECEIVED && $expedition->getIndexofLine($line) >= 0)
				{
					$line->setStatus(order_ExpeditionlineService::DELIVERED);
					$line->setDescription($message);
					$line->setDeliveryDate($date);
					$line->save();
					$linesDelivered[] = $line;
				}
			}
			$tm->commit();
		}
		catch (Exception $e)
		{
			$tm->rollback($e);
			throw $e;
		}
		
		return $linesDelivered;		
	}
}