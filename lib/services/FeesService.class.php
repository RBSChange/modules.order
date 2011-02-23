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
	 * @param order_persistentdocument_fees $document
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
	protected function preInsert($document, $parentNodeId)
	{
		parent::preInsert($document, $parentNodeId);
		$document->setInsertInTree(false);
		if ($document->getShop() === null)
		{
			$document->setShop(catalog_persistentdocument_shop::getInstanceById($parentNodeId));
		}
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
	 * @return order_persistentdocument_fees
	 */
	public function generateDefaultForShippingFilter($shippingfilter, $strategyClassName = null)
	{
		if ($shippingfilter->getFeesId() === null)
		{
			try 
			{
				$this->tm->beginTransaction();
				$fees = $this->getNewDefaultFees($shippingfilter, $strategyClassName);
				$fees->save();	
				$shippingfilter->setFeesId($fees->getId());
				$shippingfilter->save();		
				$this->tm->commit();
			} 
			catch (Exception $e) 
			{
				$this->tm->rollBack($e);
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
	 * @param Integer $parentNodeId Parent node ID where to save the document (optionnal => can be null !).
	 * @return void
	 */
//	protected function preSave($document, $parentNodeId)
//	{
//		parent::preSave($document, $parentNodeId);
//
//	}

	/**
	 * @param order_persistentdocument_fees $document
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
//	protected function postInsert($document, $parentNodeId)
//	{
//		parent::postInsert($document, $parentNodeId);
//	}

	/**
	 * @param order_persistentdocument_fees $document
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
//	protected function preUpdate($document, $parentNodeId)
//	{
//		parent::preUpdate($document, $parentNodeId);
//	}

	/**
	 * @param order_persistentdocument_fees $document
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
//	protected function postUpdate($document, $parentNodeId)
//	{
//		parent::postUpdate($document, $parentNodeId);
//	}

	/**
	 * @param order_persistentdocument_fees $document
	 * @param Integer $parentNodeId Parent node ID where to save the document.
	 * @return void
	 */
//	protected function postSave($document, $parentNodeId)
//	{
//		parent::postSave($document, $parentNodeId);
//	}

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

	/**
	 * @param order_persistentdocument_fees $document
	 * @return void
	 */
//	protected function preDeleteLocalized($document)
//	{
//		parent::preDeleteLocalized($document);
//	}

	/**
	 * @param order_persistentdocument_fees $document
	 * @return void
	 */
//	protected function postDelete($document)
//	{
//		parent::postDelete($document);
//	}

	/**
	 * @param order_persistentdocument_fees $document
	 * @return void
	 */
//	protected function postDeleteLocalized($document)
//	{
//		parent::postDeleteLocalized($document);
//	}

	/**
	 * @param order_persistentdocument_fees $document
	 * @return boolean true if the document is publishable, false if it is not.
	 */
//	public function isPublishable($document)
//	{
//		$result = parent::isPublishable($document);
//		return $result;
//	}


	/**
	 * Methode à surcharger pour effectuer des post traitement apres le changement de status du document
	 * utiliser $document->getPublicationstatus() pour retrouver le nouveau status du document.
	 * @param order_persistentdocument_fees $document
	 * @param String $oldPublicationStatus
	 * @param array<"cause" => String, "modifiedPropertyNames" => array, "oldPropertyValues" => array> $params
	 * @return void
	 */
//	protected function publicationStatusChanged($document, $oldPublicationStatus, $params)
//	{
//		parent::publicationStatusChanged($document, $oldPublicationStatus, $params);
//	}

	/**
	 * Correction document is available via $args['correction'].
	 * @param f_persistentdocument_PersistentDocument $document
	 * @param Array<String=>mixed> $args
	 */
//	protected function onCorrectionActivated($document, $args)
//	{
//		parent::onCorrectionActivated($document, $args);
//	}

	/**
	 * @param order_persistentdocument_fees $document
	 * @param String $tag
	 * @return void
	 */
//	public function tagAdded($document, $tag)
//	{
//		parent::tagAdded($document, $tag);
//	}

	/**
	 * @param order_persistentdocument_fees $document
	 * @param String $tag
	 * @return void
	 */
//	public function tagRemoved($document, $tag)
//	{
//		parent::tagRemoved($document, $tag);
//	}

	/**
	 * @param order_persistentdocument_fees $fromDocument
	 * @param f_persistentdocument_PersistentDocument $toDocument
	 * @param String $tag
	 * @return void
	 */
//	public function tagMovedFrom($fromDocument, $toDocument, $tag)
//	{
//		parent::tagMovedFrom($fromDocument, $toDocument, $tag);
//	}

	/**
	 * @param f_persistentdocument_PersistentDocument $fromDocument
	 * @param order_persistentdocument_fees $toDocument
	 * @param String $tag
	 * @return void
	 */
//	public function tagMovedTo($fromDocument, $toDocument, $tag)
//	{
//		parent::tagMovedTo($fromDocument, $toDocument, $tag);
//	}

	/**
	 * Called before the moveToOperation starts. The method is executed INSIDE a
	 * transaction.
	 *
	 * @param f_persistentdocument_PersistentDocument $document
	 * @param Integer $destId
	 */
//	protected function onMoveToStart($document, $destId)
//	{
//		parent::onMoveToStart($document, $destId);
//	}

	/**
	 * @param order_persistentdocument_fees $document
	 * @param Integer $destId
	 * @return void
	 */
//	protected function onDocumentMoved($document, $destId)
//	{
//		parent::onDocumentMoved($document, $destId);
//	}

	/**
	 * this method is call before saving the duplicate document.
	 * If this method not override in the document service, the document isn't duplicable.
	 * An IllegalOperationException is so launched.
	 *
	 * @param order_persistentdocument_fees $newDocument
	 * @param order_persistentdocument_fees $originalDocument
	 * @param Integer $parentNodeId
	 *
	 * @throws IllegalOperationException
	 */
//	protected function preDuplicate($newDocument, $originalDocument, $parentNodeId)
//	{
//		throw new IllegalOperationException('This document cannot be duplicated.');
//	}

	/**
	 * this method is call after saving the duplicate document.
	 * $newDocument has an id affected.
	 * Traitment of the children of $originalDocument.
	 *
	 * @param order_persistentdocument_fees $newDocument
	 * @param order_persistentdocument_fees $originalDocument
	 * @param Integer $parentNodeId
	 *
	 * @throws IllegalOperationException
	 */
//	protected function postDuplicate($newDocument, $originalDocument, $parentNodeId)
//	{
//	}

	/**
	 * Returns the URL of the document if has no URL Rewriting rule.
	 *
	 * @param order_persistentdocument_fees $document
	 * @param string $lang
	 * @param array $parameters
	 * @return string
	 */
//	public function generateUrl($document, $lang, $parameters)
//	{
//	}

	/**
	 * Filter the parameters used to generate the document url.
	 *
	 * @param f_persistentdocument_PersistentDocument $document
	 * @param string $lang
	 * @param array $parameters may be an empty array
	 */
//	public function filterDocumentUrlParams($document, $lang, $parameters)
//	{
//		$parameters = parent::filterDocumentUrlParams($document, $lang, $parameters)
//		return $parameters;
//	}

	/**
	 * @param order_persistentdocument_fees $document
	 * @return integer | null
	 */
//	public function getWebsiteId($document)
//	{
//		return parent::getWebsiteId($document);
//	}

	/**
	 * @param order_persistentdocument_fees $document
	 * @return website_persistentdocument_page | null
	 */
//	public function getDisplayPage($document)
//	{
//		return parent::getDisplayPage($document);
//	}

	/**
	 * @param order_persistentdocument_fees $document
	 * @param string $forModuleName
	 * @param array $allowedSections
	 * @return array
	 */
//	public function getResume($document, $forModuleName, $allowedSections = null)
//	{
//		$resume = parent::getResume($document, $forModuleName, $allowedSections);
//		return $resume;
//	}

	/**
	 * @param order_persistentdocument_fees $document
	 * @param string $bockName
	 * @return array with entries 'module' and 'template'. 
	 */
//	public function getSolrserachResultItemTemplate($document, $bockName)
//	{
//		return array('module' => 'order', 'template' => 'Order-Inc-FeesResultDetail');
//	}

	/**
	 * @param order_persistentdocument_fees $document
	 * @param string $moduleName
	 * @param string $treeType
	 * @param array<string, string> $nodeAttributes
	 */
//	public function addTreeAttributes($document, $moduleName, $treeType, &$nodeAttributes)
//	{
//	}
			
}