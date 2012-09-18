<?php
// change:addtocartbutton
//
// <tal:block change:addtocartbutton="shop shop; product product">
// <tal:block change:addtocartbutton="shop shop; product product; name 'addToCart'; context contextdocument">

/**
 * @package order.lib.phptal
 */
class PHPTAL_Php_Attribute_CHANGE_Addtocartbutton extends ChangeTalAttribute 
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
		
		// Context.
		$context = self::getFromParams('context', $params);
		if ($context instanceof f_persistentdocument_PersistentDocument)
		{
			$html .= '<input type="hidden" value="' . $context->getId() . '" name="contextId" />';
		}
		
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
		
		// Button label.
		if (isset($params['label']))
		{
			$addToCart = $params['label'];
		}
		elseif (isset($params['labeli18n']))
		{
			$addToCart = $ls->trans($params['labeli18n'], array('ucf', 'attr'));
		}
		else
		{
			$addToCart = $ls->trans('m.order.fo.add-to-cart', array('ucf', 'attr'));
		}
		
		if (isset($params['class']))
		{
			$class = $params['class'];
		}
		else
		{
			$class = "button";
		}
		$html .= '<input type="submit" class="' . $class . '" value="' . $addToCart . '" title="' . $addToCart . '"';
		if (isset($params['name']))
		{
			$html .= ' name="' . $params['name'] . '"';
		}
		$html .= ' />';
		
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