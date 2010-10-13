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
		$lines = array();
		if ($expedition->getUseOrderlines())
		{
			foreach ($expedition->getOrder()->getLineArray() as $line) 
			{
				$expLine = order_ExpeditionlineService::getInstance()->getNewDocumentInstance();
				$expLine->setLabel($line->getLabel());
				$expLine->setQuantity($line->getQuantity());
				$expLine->setOrderlineid($line->getId());
				$lines[] = $expLine;	
			}
		}
		else
		{
			$lines = $expedition->getLineArray();
		}
		
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
		$trackingNumber = $expedition->getTrackingNumber();
		if (!is_null($trackingUrl = $expedition->getTrackingURL()))
		{
			$trackingNumber = '<a href="' . $trackingUrl . '">' . $trackingNumber . '</a>';
		}
		
		$template = TemplateLoader::getInstance()->setPackageName('modules_order')->setMimeContentType(K::HTML)
			->setDirectory('templates/mails')->load('Order-Inc-ExpeditionLines');
		$template->setAttribute('expedition', $expedition);
		
		return array(
			'packageNumber' => $trackingNumber,
			'trackingNumber' => $expedition->getTrackingNumber(),
			'expeditionDetail' => $template->execute()
		);
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
				$shippingMode = DocumentHelper::getDocumentInstance($order->getShippingModeId(), 'modules_shipping/mode'); 
			}
			else
			{
				$shippingMode = DocumentHelper::getDocumentInstance($shippingModeId, 'modules_shipping/mode'); 
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
	 * @param order_persistentdocument_order $order
	 * @return integer
	 */
	private function getLastExpeditionNumber($order)
	{
		$result = $this->createQuery()->setProjection(Projections::rowCount('count'))
			->add(Restrictions::eq('order', $order))->find();
		if (is_array($result) && count($result) == 1)
		{
			return $result[0]['count'];
		}
		return 0;
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
			$expedition = $this->getNewDocumentInstance();
			$expedition->setOrder($order);
			$expedition->setStatus(self::PREPARE);
			$expedition->setAddress($order->getShippingAddress());
			$expedition->setLabel(strval($this->getLastExpeditionNumber($order) + 1));
			Framework::info('Add Expedition ' . $expedition->getLabel() . ' for order '. $order->getId() . ' => ' . $order->getOrderNumber());
			$expedition->setUseOrderlines(false);
			foreach ($lines as $id => $qtt) 
			{
				$line = DocumentHelper::getDocumentInstance($id);
				Framework::info('Add Line ' . $line->getLabel() . ' '. $id . ' => ' . $qtt);
				$expLine = order_ExpeditionlineService::getInstance()->getNewDocumentInstance();
				$expLine->setLabel($line->getLabel());
				$expLine->setQuantity($qtt);
				$expLine->setOrderlineid($id);
				$expedition->addLine($expLine);
			}	
			
			$shippingMode->getDocumentService()->completeExpedtionForMode($expedition, $shippingMode);		
			return $expedition;
		}
		return null;
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
		$expedition->setTaxCode($order->getShippingModeTaxCode());
		
		$previouslines = array();
		foreach ($previousExpeditions as $previousExpedition) 
		{
			$this->getOrderLineIds($previousExpedition, $previouslines);
		}
		
		if (count($previouslines) == 0)
		{
			$expedition->setUseOrderlines(true);	
			$expedition->setLabel(strval($this->getLastExpeditionNumber($order) + 1));
			$shippingMode->getDocumentService()->completeExpedtionForMode($expedition, $shippingMode);
			return $expedition;			
		}
		
		$expedition->setUseOrderlines(false);
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
			$expLine = order_ExpeditionlineService::getInstance()->getNewDocumentInstance();
			$expLine->setLabel($line->getLabel());
			$expLine->setQuantity($qtt);
			$expLine->setOrderlineid($line->getId());
			$expedition->addLine($expLine);
		}
		
		if ($expedition->getLineCount() > 0)
		{
			$expedition->setLabel(strval($this->getLastExpeditionNumber($order) + 1));
			$shippingMode->getDocumentService()->completeExpedtionForMode($expedition, $shippingMode);	
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
		if ($expedition->getStatus() != self::CANCELED)
		{
			if ($expedition->getUseOrderlines())
			{
				foreach ($expedition->getOrder()->getLineArray() as $line) 
				{
					$array[$line->getId()] = $line->getQuantity();
				}
			}
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
		$trackingNumber = $expedition->getTrackingNumber();
		$result = array(
			'id' => $expedition->getId(),
			'lang' => $expedition->getLang(),
			'type' => str_replace('/', '_', $expedition->getDocumentModelName()),
			'st' => $expedition->getStatus(),
			'status' => $expedition->getBoStatusLabel(),
			'label' => $expedition->getLabel(),
			'trackingnumber' => $trackingNumber,
			'trackingurl' => ($trackingNumber) ? $expedition->getTrackingURL() : null,
			'transporteur' => $expedition->getTransporteur()
		);		
		return $result;
	}
	
	/**
	 * @param order_persistentdocument_expedition $expedition
	 * @return array
	 */
	public function cancelExpeditionFromBo($expedition)
	{
		try
		{
			$this->tm->beginTransaction();
			$expedition->setShippingDate(null);
			$expedition->setStatus(self::CANCELED);
			
			$backendUser = users_UserService::getInstance()->getCurrentBackEndUser();
			$expedition->setTrackingText('Cancel by :' . (($backendUser) ? $backendUser->getFullname() : 'UNKNOWN'));
			$this->save($expedition);
			$order = $expedition->getOrder();
			order_ModuleService::getInstance()->sendCustomerNotification('modules_order/expedition_canceled', $order, $expedition->getBill(), $expedition);
			$this->tm->commit();
		}
		catch (Exception $e)
		{
			$this->tm->rollBack($e);
			throw $e;
		}

		return $this->buildBoRow($expedition);
	}
	
	/**
	 * @param order_persistentdocument_expedition $expedition
	 * @param string $shippingDate
	 * @param string $trackingNumber
	 * @return array
	 */
	public function shipExpeditionFromBo($expedition, $shippingDate, $trackingNumber)
	{
		
		if ($expedition->getStatus() == self::PREPARE	&& f_util_StringUtils::isNotEmpty($shippingDate))
		{
			try 
			{
				$this->tm->beginTransaction();				
				$expedition->setStatus(self::SHIPPED);
				$expedition->setTrackingNumber($trackingNumber);
				$expedition->setShippingDate(date_Converter::convertDateToGMT($shippingDate));
				$this->save($expedition);	
				$order = $expedition->getOrder();
				order_ModuleService::getInstance()->sendCustomerNotification('modules_order/expedition_shipped', $order, $expedition->getBill(), $expedition);
				
				$nextExpedition = $this->createForOrder($order);
				if ($nextExpedition === null)
				{
					order_OrderService::getInstance()->completeOrder($order);	
				}
				$this->tm->commit();
			}
			catch (Exception $e)
			{
				$this->tm->rollBack($e);
				throw $e;
			}
		}
		else
		{
			throw new Exception('Error on expedition shipping');
		}
		return $this->buildBoRow($expedition);
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
		
		$resume['lines'] = array();
		foreach ($this->getLinesForDisplay($document) as $line)
		{
			$resume['lines'][] = array(
				'label' => $line->getLabel(),
				'quantity' => $line->getQuantity(),
				'codeReference' => $line->getCodeReference()
			);
		}
		
		return $resume;
	}
}