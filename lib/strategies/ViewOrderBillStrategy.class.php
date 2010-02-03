<?php
class order_ViewOrderBillStrategy extends media_DisplaySecuremediaStrategy
{
	/**
	 * @param media_persistentdocument_securemedia $media
	 * @return Integer
	 */
	public function canDisplayMedia($media)
	{
		$foUser = users_UserService::getInstance()->getCurrentFrontEndUser();
		if ($foUser === null)
		{
			// let other strategies answer
			return self::NOT_CONCERNED;
		}
		$order = order_OrderService::getInstance()->createQuery()->add(Restrictions::eq("bill", $media))->findUnique();
		if ($order === null)
		{
			// let other strategies answer
			return self::NOT_CONCERNED;
		}
		if ($foUser->getId() == $order->getCustomer()->getUser()->getId())
		{
			return self::OK;
		}
		return self::KO;
	}
}