<?php
class order_CartLineScriptElement extends import_ScriptBaseElement
{
	/**
	 * @var order_CartLineInfo
	 */
	private $lineInfo;
	
	public function process()
	{
		$lineInfo = new order_CartLineInfo();
		
		foreach ($this->attributes as $name => $value) 
		{
			switch ($name)
			{
				case 'product-refid':
					$lineInfo->setProduct($this->script->getElementById($value)->getPersistentDocument());
					break;
				case 'quantity':
					$lineInfo->setQuantity($value);
					break;		
				default:
					throw new Exception("Invalid attribute : $name => $value");
			}
		}
		
		$this->lineInfo = $lineInfo;
	}
   
	public function endProcess()
	{
		$cartInfoScript = $this->getAncestorByClassName("order_CartInfoScriptElement");
		if ($cartInfoScript !== null)
		{
			$cartInfoScript->addLine($this->lineInfo);
		}
	}
}