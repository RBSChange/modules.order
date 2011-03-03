<?php
/**
 * order_patch_0313
 * @package modules.order
 */
class order_patch_0313 extends patch_BasePatch
{
	/**
	 * Entry point of the patch execution.
	 */
	public function execute()
	{
		$ns = notification_NotificationService::getInstance();
		$sysfolderId = ModuleService::getInstance()->getSystemFolderId('notification', 'order');
		
		$notif = $ns->getByCodeName("modules_order/expedition_canceled");
		if ($notif === null)
		{
			$notif = $ns->getNewDocumentInstance();
			$notif->setCodename("modules_order/expedition_canceled");
			$notif->setLabel("Expédition annulée");
			$notif->setAvailableparameters("{orderId}, {orderAmount}, {orderAmountWithTax}, {orderAmountWithoutTax}, {title}, {fullname}, {orderDetail}, {billingMode}, {shippingMode}, {shippingFeesWithTax}, {shippingFeesWithoutTax}, {date}, {billnumber}, {billdate}, {billtransaction}, {packageNumber}, {trackingNumber}, {expeditionDetail}, {website-url}");
			$notif->setSubject("Expédition annulée sur votre commande numéro {orderId} sur le site {website-url}");
			$notif->setHeader("<strong>Expédition annulée sur votre commande numéro {orderId}</strong>");
			$notif->setBody("<p>{packageNumber}</p><p>{trackingNumber}</p><p>{expeditionDetail}</p>");
			$notif->setTemplate("default");
			$notif->save($sysfolderId);
		}

		$notif = $ns->getByCodeName("modules_order/expedition_shipped");
		if ($notif === null)
		{
			$notif = $ns->getNewDocumentInstance();
			$notif->setCodename("modules_order/expedition_shipped");
			$notif->setLabel("Expédition effectuée");
			$notif->setAvailableparameters("{orderId}, {orderAmount}, {orderAmountWithTax}, {orderAmountWithoutTax}, {title}, {fullname}, {orderDetail}, {billingMode}, {shippingMode}, {shippingFeesWithTax}, {shippingFeesWithoutTax}, {date}, {billnumber}, {billdate}, {billtransaction}, {packageNumber}, {trackingNumber}, {expeditionDetail}, {website-url}");
			$notif->setSubject("Expédition effectuée sur votre commande numéro {orderId} sur le site {website-url}");
			$notif->setHeader("<strong>Expédition effectuée sur votre commande numéro {orderId}</strong>");
			$notif->setBody("<p>{packageNumber}</p><p>{trackingNumber}</p><p>{expeditionDetail}</p>");
			$notif->setTemplate("default");
			$notif->save($sysfolderId);
		}
		$this->execChangeCommand("compile-locales");
	}

	/**
	 * @return String
	 */
	protected final function getModuleName()
	{
		return 'order';
	}

	/**
	 * @return String
	 */
	protected final function getNumber()
	{
		return '0313';
	}
}
