<?php
class order_MessageService extends f_persistentdocument_DocumentService
{
	/**
	 * @var order_MessageService
	 */
	private static $instance;

	/**
	 * @return order_MessageService
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
	 * @return order_persistentdocument_message
	 */
	public function getNewDocumentInstance()
	{
		return $this->getNewDocumentInstanceByModelName('modules_order/message');
	}

	/**
	 * Create a query based on 'modules_order/message' model
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createQuery()
	{
		return $this->pp->createQuery('modules_order/message');
	}
	
	/**
	 * @see f_persistentdocument_DocumentService::preInsert()
	 *
	 * @param order_persistentdocument_message $document
	 * @param Integer $parentNodeId
	 */
	protected function preInsert($document, $parentNodeId)
	{
		$order = $document->getOrder();
		$update = !$order->isModified();
		$order->setNeedsAnswer(!($document->getSender() instanceof users_persistentdocument_backenduser));
		if ($update && $order->isModified())
		{
			$this->pp->updateDocument($order);
		}
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @return order_persistentdocument_message[]
	 */
	public function getByOrder($order)
	{
		$query = $this->createQuery()
			->add(Restrictions::eq('order.id', $order->getId()))
			->addOrder(Order::desc('document_creationdate'))
			->addOrder(Order::desc('document_id'));
		return $query->find();
	}
	
	/**
	 * @param order_persistentdocument_order $order
	 * @param Boolean $includeDetails
	 * @return Array<String => String>
	 */
	public function getInfosByOrder($order, $includeDetails = false)
	{
		$dateTimeFormat = customer_ModuleService::getInstance()->getUIDateTimeFormat();
		
		$infos = array('fromCustomerCount' => 0, 'toCustomerCount' => 0);
		$messages = $this->getByOrder($order);
		$infos['totalCount'] = strval(count($messages));
		if ($includeDetails)
		{
			$infos['messages'] = array();
		}
		foreach ($messages as $message)
		{
			if ($includeDetails)
			{
				$messageInfo = array();
				$messageInfo['date'] = date_DateFormat::format($message->getUICreationdate(), $dateTimeFormat);
				$messageInfo['content'] = $message->getContentAsHtml();
				if ($message->isSentByCustomer())
				{
					$infos['fromCustomerCount']++;
					$messageInfo['messageType'] = 'fromCustomer';
					$messageInfo['senderFullName'] = '';
					$messageInfo['label'] = f_Locale::translateUI('&modules.order.bo.general.Message-sent-by-customer;');
				}
				else 
				{
					$infos['toCustomerCount']++;
					$messageInfo['messageType'] = 'toCustomer';
					$messageInfo['senderFullName'] = $message->getSender()->getFullname();
					$messageInfo['label'] = f_Locale::translateUI('&modules.order.bo.general.Message-sent-to-customer;', array('sender' => $messageInfo['senderFullName']));
				}
				$infos['messages'][] = $messageInfo;
			}
			else 
			{
				if ($message->isSentByCustomer())
				{
					$infos['fromCustomerCount']++;
				}
				else 
				{
					$infos['toCustomerCount']++;
				}	
			}			
		}
		$infos['fromCustomerCount'] = strval($infos['fromCustomerCount']);
		$infos['toCustomerCount'] = strval($infos['toCustomerCount']);
		return $infos;
	}
}