<?php
// change:addtocartbutton
//
// <tal:block change:addtocartbutton="shop shop; product product">

/**
 * @package order.lib.phptal
 */
class PHPTAL_Php_Attribute_CHANGE_addtocartbutton extends ChangeTalAttribute 
{
	
	protected function evaluateAll()
	{
		return true;
	}

	/**
	 * @param array $params
	 * @return string
	 */
	public static function renderAddtocartbutton($params)
	{
		$ls = LocaleService::getInstance();
		$html = '<input type="hidden" value="' . self::getFromParams('shop', $params)->getId() . '" name="shopId" />';
		
		// Product.
		$product = self::getFromParams('product', $params);
		if ($product instanceof catalog_persistentdocument_product)
		{
			$html .= '<input type="hidden" value="' . $product->getId() . '" name="productId" />';
		}
		
		// Quantity.
		$quantity = self::getFromParams('quantity', $params);
		if ($quantity > 0)
		{
			$html .= '<input type="hidden" value="' . $quantity . '" name="quantity" />';
		}
				
		// Backurl.
		$backurl = self::getFromParams('backurl', $params);
		$backurl = (!$backurl) ? LinkHelper::getCurrentUrl() : $backurl;
		$html .= '<input type="hidden" value="' . $backurl . '" name="backurl" />';
			
		$addToCart = $ls->transFO('m.order.fo.add-to-cart', array('ucf'));
		if (isset($params['class']))
		{
			$class = $params['class'];
		}
		else
		{
			$class = "button";
		}
		$html .= '<input type="submit" class="' . $class . '" value="' . $addToCart . '" title="' . $addToCart . '" />';
		
		return $html;
	}

	/**
	 * @param string $key
	 * @param array $params
	 * @return string
	 */
	private static function getFromParams($key, $params, $default = null)
	{
		return (array_key_exists($key, $params)) ? $params[$key] : $default;
	}
}