<?php
/**
 * order_PrintOrderPropertiesView
 * @package modules.order.views
 */
class order_PrintOrderPropertiesView extends change_View
{
	/**
	 * @param change_Context $context
	 * @param change_Request $request
	 */
	public function _execute($context, $request)
	{
		$this->setTemplateName('Order-Action-PrintOrder-Properties');
		$this->includeStyle();

		$order = $request->getAttribute('order');
		$this->setAttribute('order', $order);
		$this->setAttribute('shop', $order->getShop());
		
		$payments = array();
		foreach (order_BillService::getInstance()->getByOrder($order) as $bill)
		{
			/* @var $bill order_persistentdocument_bill */
			$connector = DocumentHelper::getDocumentInstanceIfExists($bill->getConnectorId());
			if ($connector instanceof payment_persistentdocument_connector)
			{
				$connector->getDocumentService()->setPaymentInfo($connector, $bill);
				$template = 'Payment-Block-Payment-' . $connector->getTemplateViewName();
				$payments[] = array('bill' => $bill, 'connector' => $connector, 'template' => $template);
			}
		}
		$this->setAttribute('payments', $payments);
		
		$expeditions = array();
		foreach (order_ExpeditionService::getInstance()->getByOrderForDisplay($order) as $expedition)
		{
			/* @var $bill order_persistentdocument_expedition */
			$lines = $expedition->getDocumentService()->getLinesForDisplay($expedition);
			$expeditions[] = array('expedition' => $expedition, 'lines' => $lines);
		}
		$this->setAttribute('expeditions', $expeditions);
	}

	protected function includeStyle()
	{
		$ss = StyleService::getInstance();
		$ss->registerStyle('modules.order.printOrder');
		$ss->registerStyle('modules.order.printOrderPrint', StyleService::MEDIA_PRINT);
		$this->setAttribute('cssInclusion', $ss->execute('html'));
	}
}