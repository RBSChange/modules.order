<?php
/**
 * order_FeesService
 * @package modules.order
 */
class order_FeesService extends order_CartmodifierService
{
	
	const DEFAULT_SHIPPING_STRATEGY = 'order_DefaultShippingStrategy';
	
	/**
	 * @var order_FeesService
	 */
	private static $instance;

	/**
	 * @return order_FeesService
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
	 * @return order_persistentdocument_fees
	 */
	public function getNewDocumentInstance()
	{
		return $this->getNewDocumentInstanceByModelName('modules_order/fees');
	}

	/**
	 * Create a query based on 'modules_order/fees' model.
	 * Return document that are instance of modules_order/fees,
	 * including potential children.
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createQuery()
	{
		return $this->pp->createQuery('modules_order/fees');
	}
	
	/**
	 * Create a query based on 'modules_order/fees' model.
	 * Only documents that are strictly instance of modules_order/fees
	 * (not children) will be retrieved
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createStrictQuery()
	{
		return $this->pp->createQuery('modules_order/fees', false);
	}
	
	/**
	 * this method is call before save the duplicate document.
	 * @param order_persistentdocument_fees $newDocument
	 * @param order_persistentdocument_fees $originalDocument
	 * @param Integer $parentNodeId
	 */
	protected function preDuplicate($newDocument, $originalDocument, $parentNodeId)
	{
		$newDocument->setLabel(LocaleService::getInstance()->transBO('m.generic.backoffice.duplicate-prefix', array('ucf'), array('number' => '')) . ' ' . $originalDocument->getLabel());
		$newDocument->setPublicationstatus(f_persistentdocument_PersistentDocument::STATUS_DEACTIVATED);
	}
	
	/**
	 * @param string $applicationStrategy
	 * @return order_FeesApplicationStrategy
	 */
	public function getNewApplicationStrategyInstance($applicationStrategy)
	{
		if (f_util_StringUtils::isEmpty($applicationStrategy) || !f_util_ClassUtils::classExists($applicationStrategy))
		{
			$applicationStrategy = 'order_NotFoundFeesApplicationStrategy';
		}
		$instance =  new $applicationStrategy();
		return $instance;
	}
	
	/**
	 * @param order_persistentdocument_fees $document
	 * @return DOMDocument
	 */
	public function getEditParametersBinding($document)
	{
		$applicationStrategy = $this->getNewApplicationStrategyInstance($document->getApplicationstrategy());			
		
		
		$xslPath = FileResolver::getInstance()->setPackageName('modules_order')
			->setDirectory('templates/fees')->getPath('strategyParameters.xsl');

		$panelName = $applicationStrategy->getEditorDefinitionPanelName() . '.xml';
		
		$parametersDefPath = FileResolver::getInstance()->setPackageName('modules_' . $applicationStrategy->getEditorModuleName())
			->setDirectory('lib/strategies')->getPath($panelName);

		$skinDefDoc = new DOMDocument('1.0', 'UTF-8');
		$skinDefDoc->load($parametersDefPath);
			
		$xsl = new DOMDocument('1.0', 'UTF-8');
		$xsl->load($xslPath);
		foreach ($xsl->getElementsByTagNameNS("*", "include") as $item) 
		{
			if ($item->getAttribute('href') === 'field.xsl')
			{
				$fieldsXSL = FileResolver::getInstance()->setPackageName('modules_uixul')
					->setDirectory('forms/editor/xul')->getPath('field.xsl');
				$item->setAttribute('href', $fieldsXSL);
			}
		}
		
		$xslt = new XSLTProcessor();
		$xslt->registerPHPFunctions();
		$xslt->importStylesheet($xsl);
		self::$applicationStrategy = $applicationStrategy;  
		$xslt->setParameter('', 'moduleName', $applicationStrategy->getEditorModuleName());
		$xslt->setParameter('', 'panelName', $applicationStrategy->getEditorDefinitionPanelName());
		$xslt->setParameter('', 'extendStrategySection', uixul_lib_BindingObject::getUrl('modules.catalog.editors.fees#cStrategySections'));
		self::$parameters = array();

		$panelDoc = $xslt->transformToDoc($skinDefDoc);
		return $panelDoc;		
	}
	
	/**
	 * @var array
	 */
	private static $parameters;
	
	/**
	 * @var order_FeesApplicationStrategy
	 */
	private static $applicationStrategy;
	
	public static function XSLSetDefaultParInfo($elementArray)
	{
		$moduleName = self::$applicationStrategy->getEditorModuleName();
		$panelName = self::$applicationStrategy->getEditorDefinitionPanelName();
		
		$element = $elementArray[0];
		$name = $element->getAttribute("name");
		if (!$name || in_array($name, self::$parameters))
		{
			throw new Exception('Invalid empty field name:' . $name);
		}
		self::$parameters[] = $name;
		$element->setAttribute('id', $moduleName . '_strategy_' . $panelName . '_' . $name);		
		if (!$element->hasAttribute('type'))
		{
			$element->setAttribute('type', 'text');			
		}
		if ($element->hasAttribute('labeli18n'))
		{
			$key = $element->getAttribute('labeli18n');
			$element->removeAttribute('labeli18n');
			$element->setAttribute('label', LocaleService::getInstance()->transBO($key, array('ucf', 'attr')));
		}
		else if (!$element->hasAttribute('label'))
		{
			$key = strtolower('m.' . $moduleName.'.bo.strategy.'. $panelName . '-' . $name);
			$element->setAttribute('label', LocaleService::getInstance()->transBO($key, array('ucf', 'attr')));
		}
		
		if (!$element->hasAttribute('hidehelp'))
		{
			$key = strtolower('m.' . $moduleName.'.bo.strategy.'. $panelName . '-' . $name  . '-help');
			$help = LocaleService::getInstance()->transBO($key, array('ucf'));
			if ($help !== $key)
			{
				$element->setAttribute('shorthelp', $help);
			}
			else
			{
				$element->setAttribute('hidehelp', 'true');
			}
		}
		return '';
	}
	
	public static function XSLParameters()
	{
		return JsonService::getInstance()->encode(self::$parameters);
	}	
	
	public static function XSLGetImage($elementArray)
	{
		$element = $elementArray[0];
		$imageURL = $element->getAttribute('image');
		return str_replace('{IconsBase}', MediaHelper::getIconBaseUrl(), $imageURL);
	}	
	
	public static function XSLGetLabel($elementArray)
	{
		$element = $elementArray[0];
		if ($element->hasAttribute('labeli18n'))
		{
			return LocaleService::getInstance()->transBO($element->getAttribute('labeli18n'), array('ucf', 'attr'));
		}
		else if ($element->hasAttribute('label'))
		{
			return $element->getAttribute('label');
		} 
		else if ($element->hasAttribute('name'))
		{
			$key = strtolower('m.' .  self::$applicationStrategy->getEditorModuleName().'.bo.strategy.'. self::$applicationStrategy->getEditorDefinitionPanelName() . '-' . $element->getAttribute('name'));
			return LocaleService::getInstance()->transBO($key, array('ucf', 'attr'));
		}
		return '';
	}
	
	
	/**
	 * @param order_persistentdocument_fees $document
	 * @param String[] $propertiesName
	 * @param Array $datas
	 */
	public function addFormProperties($document, $propertiesName, &$datas)
	{
		if (in_array('strategyParametersJSON', $propertiesName))
		{
			$datas["strategyParametersJSON"] = $document->getStrategyInstance()->getParameters();
			$datas["strategyParametersJSON"]["strategyClassName"] = $document->getApplicationstrategy();
		}			
	}
	
	/**
	 * @param catalog_persistentdocument_shippingfilter $shippingfilter
	 * @param string $strategyClassName
	 * @throws Exception
	 * @return order_persistentdocument_fees
	 */
	public function generateDefaultForShippingFilter($shippingfilter, $strategyClassName = null)
	{
		if ($shippingfilter->getFeesId() === null)
		{
			try 
			{
				$this->getTransactionManager()->beginTransaction();
				$fees = $this->getNewDefaultFees($shippingfilter, $strategyClassName);
				$fees->save();	
				$shippingfilter->setFeesId($fees->getId());
				$shippingfilter->save();		
				$this->getTransactionManager()->commit();
			} 
			catch (Exception $e) 
			{
				throw $this->getTransactionManager()->rollBack($e);
			}
		}
		return $this->getDocumentInstance($shippingfilter->getFeesId());
	}
	
	/**
	 * @param catalog_persistentdocument_shippingfilter $shippingfilter
	 * @param string $strategyClassName
	 * @return order_persistentdocument_fees
	 */
	public function getNewDefaultFees($shippingfilter, $strategyClassName = null)
	{
		$fees = $this->getNewDocumentInstance();
		if ($strategyClassName === null) {$strategyClassName = self::DEFAULT_SHIPPING_STRATEGY;}
		
		$fees->setApplicationstrategy($strategyClassName);
		$strategy = $fees->getStrategyInstance();
		
		$label = LocaleService::getInstance()->transFO('m.order.frontoffice.shipping-fees', array('ucf', 'lab')) . ' ' . $shippingfilter->getLabel();
		$fees->setLabel($label);
		$fees->setShop($shippingfilter->getShop());
		$fees->setBillingArea($shippingfilter->getBillingArea());
		$fees->setStartpublicationdate($shippingfilter->getStartpublicationdate());
		$fees->setEndpublicationdate($shippingfilter->getEndpublicationdate());
		$fees->setPublicationstatus($shippingfilter->getPublicationstatus());
		$fees->setStrategyParam('taxcategory', $shippingfilter->getTaxCategory());
		$fees->setStrategyParam('valuewithouttax', $shippingfilter->getValueWithoutTax());
		return $fees;
	}

	
	
	/**
	 * @param order_persistentdocument_fees $fees
	 * @param order_CartInfo $cart
	 * @return boolean
	 */
	public function validateForCart($fees, $cart)
	{
		$df = f_persistentdocument_DocumentFilterService::getInstance();
		if ($df->checkValueFromJson($fees->getQuery(), $cart))
		{
			return true;
		}
		return false;
	}
		
	/**
	 * @param order_persistentdocument_fees $fees
	 * @param order_CartInfo $cart
	 * @return boolean
	 */
	public function applyToCart($fees, $cart)
	{
		return $fees->getStrategyInstance()->applyToCart($cart);
	}
	
	/**
	 * @param order_persistentdocument_fees $fees
	 * @param order_CartInfo $cart
	 * @return boolean
	 */
	public function removeFromCart($fees, $cart)
	{
		return $fees->getStrategyInstance()->removeFromCart($cart);
	}
	
	/**
	 * @param order_persistentdocument_fees $fees
	 * @param order_persistentdocument_order $order
	 * @param order_FeesInfo $feesInfo
	 * @return array
	 */
	public function updateOrder($fees, $order, $feesInfo)
	{
		return $fees->getStrategyInstance()->updateOrder($order, $feesInfo);
	}
	
	/**
	 * @param order_persistentdocument_fees $fees
	 * @param catalog_persistentdocument_shippingfilter $shippingFilter
	 * @param order_CartInfo $cart
	 */
	public function simulateShippingFilter($fees, $shippingFilter, $cart)
	{
		$fees->getStrategyInstance()->updateShippingFilter($shippingFilter, $cart);
	}

	/**
	 * @param order_persistentdocument_fees $document
	 * @return void
	 */
	protected function preDelete($document)
	{
		$shipFilters = catalog_ShippingfilterService::getInstance()->createQuery()
			->add(Restrictions::eq('feesId', $document->getId()))->find();
		foreach ($shipFilters as $shipFilter) 
		{
			if ($shipFilter instanceof catalog_persistentdocument_shippingfilter)
			{
				$shipFilter->setFeesId(null);
				$shipFilter->save();
			}
		}
	}
}