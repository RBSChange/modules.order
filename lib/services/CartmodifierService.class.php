<?php
/**
 * order_CartmodifierService
 * @package modules.order
 */
class order_CartmodifierService extends f_persistentdocument_DocumentService
{
	/**
	 * @var order_CartmodifierService
	 */
	private static $instance;

	/**
	 * @return order_CartmodifierService
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
	 * @return order_persistentdocument_cartmodifier
	 */
	public function getNewDocumentInstance()
	{
		return $this->getNewDocumentInstanceByModelName('modules_order/cartmodifier');
	}

	/**
	 * Create a query based on 'modules_order/cartmodifier' model.
	 * Return document that are instance of modules_order/cartmodifier,
	 * including potential children.
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createQuery()
	{
		return $this->pp->createQuery('modules_order/cartmodifier');
	}
	
	/**
	 * Create a query based on 'modules_order/cartmodifier' model.
	 * Only documents that are strictly instance of modules_order/cartmodifier
	 * (not children) will be retrieved
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createStrictQuery()
	{
		return $this->pp->createQuery('modules_order/cartmodifier', false);
	}
	
	/**
	 * @param order_CartInfo $cart
	 */
	public function refreshModifiersForCart($cart)
	{
		$oldDiscountInfoArray = array();				
		foreach ($cart->getDiscountArray() as $discountInfo) 
		{
			$oldDiscountInfoArray[$discountInfo->getId()] = $discountInfo;
		}
		
		$newModifiers = array();				
		$query = $this->createQuery()->add(Restrictions::published())
			->add(Restrictions::eq('shop', $cart->getShop()))
			->add(Restrictions::eq('exclusive', true));
		foreach ($query->find() as $modifier)
		{
			if ($modifier->getDocumentService()->validateForCart($modifier, $cart))
			{
				if ($modifier->applyToCart($cart))
				{
					if (isset($oldDiscountInfoArray[$modifier->getId()]))
					{
						unset($oldDiscountInfoArray[$modifier->getId()]);
					}
					$newModifiers[$modifier->getId()] = $modifier;
				}
			}
		}
		
		if (count($newModifiers) == 0)
		{
			$exludeDiscount = array();
			$query = $this->createQuery()->add(Restrictions::published())
				->add(Restrictions::eq('shop', $cart->getShop()))
				->add(Restrictions::eq('exclusive', false))
				->add(Restrictions::isNotEmpty('excludeModifier'));
			foreach ($query->find() as $modifier)
			{
				if ($modifier->getDocumentService()->validateForCart($modifier, $cart))
				{
					if ($modifier->applyToCart($cart))
					{
						foreach ($modifier->getExcludeModifierArray() as $toExclude) 
						{
							$exludeDiscount[] = $toExclude->getId();
						}
						if (isset($oldDiscountInfoArray[$modifier->getId()]))
						{
							unset($oldDiscountInfoArray[$modifier->getId()]);
						}
						$newModifiers[$modifier->getId()] = $modifier;
					}
				}
			}
	
			$query = $this->createQuery()->add(Restrictions::published())
				->add(Restrictions::eq('shop', $cart->getShop()))
				->add(Restrictions::eq('exclusive', false))
				->add(Restrictions::isEmpty('excludeModifier'));
			if (count($exludeDiscount) > 0)
			{
				$query->add(Restrictions::notin('id', $exludeDiscount));
			}			
			foreach ($query->find() as $modifier)
			{
				if ($modifier->getDocumentService()->validateForCart($modifier, $cart))
				{
					if ($modifier->applyToCart($cart))
					{
						if (isset($oldDiscountInfoArray[$modifier->getId()]))
						{
							unset($oldDiscountInfoArray[$modifier->getId()]);
						}
						$newModifiers[$modifier->getId()] = $modifier;
					}
				}
			}			
		}
		
		foreach ($oldDiscountInfoArray as $discountInfo) 
		{
			$modifier = order_persistentdocument_cartmodifier::getInstanceById($discountInfo->getId());
			$modifier->removeFromCart($cart);
		}
	}
	
	/**
	 * @param order_persistentdocument_cartmodifier $modifier
	 * @param order_CartInfo $cart
	 * @return boolean
	 */
	public function validateForCart($modifier, $cart)
	{
		return false;
	}
	
	/**
	 * @param order_persistentdocument_cartmodifier $modifier
	 * @param order_CartInfo $cart
	 * @return boolean
	 */
	public function applyToCart($modifier, $cart)
	{
		return false;
	}
	
	/**
	 * @param order_persistentdocument_cartmodifier $modifier
	 * @param order_CartInfo $cart
	 * @return boolean
	 */
	public function removeFromCart($modifier, $cart)
	{
		return true;
	}
		
	/**
	 * @param order_persistentdocument_cartmodifier $document
	 * @param order_persistentdocument_order $order
	 * @param order_DiscountInfo $discount
	 * @return array
	 */
	public function updateOrder($document, $order, $discountInfo)
	{
		return array();
	}
}