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
			self::$instance = new self();
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
		$old = array();				
		foreach ($cart->getDiscountArray() as $modifierInfo) 
		{
			$old[$modifierInfo->getId()] = array('discount', $modifierInfo);
		}
		$cart->clearDiscountArray();
		
		foreach ($cart->getFeesArray() as $feesInfo) 
		{
			$old[$feesInfo->getId()] = array('fees', $feesInfo);
		}
		$cart->clearFeesArray();
		
		$newModifiers = array();				
		$query = $this->createQuery()->add(Restrictions::published())
			->add(Restrictions::eq('shop', $cart->getShop()))
			->add(Restrictions::eq('exclusive', true))
			->addOrder(Order::desc('applicationPriority'))
			->addOrder(Order::asc('id'));
			
		foreach ($query->find() as $modifier)
		{
			$mid = $modifier->getId();
			if ($modifier->getDocumentService()->validateForCart($modifier, $cart))
			{
				if (isset($old[$mid])) 
				{
					if ($old[$mid][0] === 'discount') {$cart->addDiscount($old[$mid][1]);} else {$cart->addFeesInfo($old[$mid][1]);}
				}
				if ($modifier->applyToCart($cart))
				{
					if (isset($old[$mid])) {unset($old[$mid]);}
					$newModifiers[$mid] = $modifier;
				}
			}
		}
		
		if (count($newModifiers) == 0)
		{
			$exludeModifier = array();
			$query = $this->createQuery()->add(Restrictions::published())
				->add(Restrictions::eq('shop', $cart->getShop()))
				->add(Restrictions::eq('exclusive', false))
				->add(Restrictions::isNotEmpty('excludeModifier'))
				->addOrder(Order::desc('applicationPriority'))
				->addOrder(Order::asc('id'));
			
			foreach ($query->find() as $modifier)
			{
				$mid = $modifier->getId();
				if ($modifier->getDocumentService()->validateForCart($modifier, $cart))
				{
					if (isset($old[$mid])) 
					{
						if ($old[$mid][0] === 'discount') {$cart->addDiscount($old[$mid][1]);} else {$cart->addFeesInfo($old[$mid][1]);}
					}
					if ($modifier->applyToCart($cart))
					{
						foreach ($modifier->getExcludeModifierArray() as $toExclude) 
						{
							$exludeModifier[] = $toExclude->getId();
						}
						if (isset($old[$mid])) {unset($old[$mid]);}
						$newModifiers[$mid] = $modifier;
					}
				}
			}
	
			$query = $this->createQuery()->add(Restrictions::published())
				->add(Restrictions::eq('shop', $cart->getShop()))
				->add(Restrictions::eq('exclusive', false))
				->add(Restrictions::isEmpty('excludeModifier'))
				->addOrder(Order::desc('applicationPriority'))
				->addOrder(Order::asc('id'));
				
			if (count($exludeModifier) > 0)
			{
				$query->add(Restrictions::notin('id', $exludeModifier));
			}	
					
			foreach ($query->find() as $modifier)
			{
				$mid = $modifier->getId();
				if ($modifier->getDocumentService()->validateForCart($modifier, $cart))
				{
					if (isset($old[$mid])) 
					{
						if ($old[$mid][0] === 'discount') {$cart->addDiscount($old[$mid][1]);} else {$cart->addFeesInfo($old[$mid][1]);}
					}
					if ($modifier->applyToCart($cart))
					{
						if (isset($old[$mid])) {unset($old[$mid]);}
						$newModifiers[$mid] = $modifier;
					}
				}
			}			
		}
		
		foreach ($old as $data) 
		{
			list($t, $modifierInfo) = $data;
			$mid = $modifierInfo->getId();
			$model = $this->getPersistentProvider()->getDocumentModelName($mid);
			if ($model)
			{
				$modifier = order_persistentdocument_cartmodifier::getInstanceById($mid);
				if ($t === 'discount') 
				{	
					if ($cart->getDiscountById($mid) === null)
					{
						$cart->addDiscount($modifierInfo);
					}
				} 
				else 
				{
					if ($cart->getFeesById($mid) === null)
					{
						$cart->addFeesInfo($modifierInfo);
					}
				}
				$modifier->removeFromCart($cart);
			}
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