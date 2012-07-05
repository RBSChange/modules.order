<?php
require_once PROJECT_HOME . '/libs/fpdf/fpdf.php';

class order_FPDFExpeditionGenerator extends FPDF
{	
	/**
	 * order_persistentdocument_expedition $expedition
	 */
	protected $expedition;
	
	/**
	 * @var float
	 */
	protected $addressXAbsolutePosition;
	
	/**
	 * @var float
	 */
	protected $addressYAbsolutePosition;
	
	/**
	 * @var float
	 */
	protected $bodyYAbsolutePosition;
	
	/**
	 * @var array<string, string>
	 */
	protected $merchantAddress;
	
	/**
	 * @var string logoPath
	 */
	protected $logoPath;
	
	/**
	 * @var float logoWidth
	 */
	protected $logoWidth;
	
	/**
	 * @var float logoHeight
	 */
	protected $logoHeight;
	
	/**
	 * @var string expeditionTexts
	 */
	protected $expeditionTexts;
	
	/**
	 * @var float[]
	 */
	protected $margins;
	
	/**
	 * @var string
	 */
	protected $font;
	
	/**
	 * @var float
	 */
	protected  $topRidghtCellMaxWidth;
	
	/**
	 * @var integer[]
	 */
	protected $orderLineHeaderFillColorRGB;
	
	/**
	 * @var integer[]
	 */
	protected $orderLineFillColorRGB;
	
	/**
	 * @var array<string, string>
	 */
	protected $customerInfoHeaders;
	
	/**
	 * @var expeditionLineHeaders
	 */
	protected $expeditionLineHeaders;
	
	/**
	 * @var float
	 */
	protected $expeditionlineHeight;
	
	/**
	 * format the text in parameter to the 
	 * FPDF default format (UTF-8 to CP1252)
	 * @param string $UTF8string
	 */
	protected function convertUTF8String($UTF8string)
	{
		return mb_convert_encoding($UTF8string, 'CP1252', 'UTF-8');
	}
	
	/**
	 * @param order_persistentdocument_expedition $expedition
	 */
	public function __construct($expedition, $orientation = 'P', $unit = 'mm', $size = 'A4')
	{
		parent::FPDF($orientation, $unit, $size);
		if ($expedition instanceof order_persistentdocument_expedition)
		{	
			$this->expedition = $expedition;
			
			$configXMLPath = null;
			$ref = $expedition->getOrder()->getShop()->getCodeReference();
			$lang = $expedition->getOrder()->getLang();
				
			if ($ref)
			{
				$configXMLPath = FileResolver::getInstance()->setPackageName('modules_order')
				->setDirectory('templates')
				->getPath('FPDFExpeditionInfos-'. $ref. '-'. $lang . '.xml');
			}
			if ($configXMLPath == null)
			{
				//get the merchant address from XML Configuration
				$configXMLPath = FileResolver::getInstance()->setPackageName('modules_order')
				->setDirectory('templates')
				->getPath('FPDFExpeditionInfos-default.xml');
			}
				
			$configXML = f_util_DOMUtils::fromPath($configXMLPath);
			$this->parseXML($configXML);
			//FPDF Config
			$this->SetAutoPageBreak(true, $this->margins[3]);
			$this->SetMargins($this->margins[0], $this->margins[2], $this->margins[1]);
		}
		else
		{
			throw new Exception('Invalid parameter $expedition give to ' . __CLASS__ . ' constructor');
		}
	}
	
	/**
	 *
	 * @param DOMDocument $configXML
	 */
	protected function parseXML($configXML)
	{
		$designConfiguration = $configXML->getElementsByTagName('design')->item(0)->attributes;
		$this->margins = explode(',', $designConfiguration->getNamedItem('margins')->nodeValue);
		$this->font = $designConfiguration->getNamedItem('font')->nodeValue;
		$this->topRidghtCellMaxWidth = $designConfiguration->getNamedItem('topRidghtCellMaxWidth')->nodeValue;
		$this->expeditionlineHeight = $designConfiguration->getNamedItem('expeditionlineHeight')->nodeValue;
		
		
		$this->orderLineHeaderFillColorRGB = explode(',', $designConfiguration->getNamedItem('orderLineHeaderFillColorRGB')->nodeValue);
		$this->orderLineFillColorRGB = explode(',', $designConfiguration->getNamedItem('orderLineFillColorRGB')->nodeValue);
		
		$positions = $configXML->getElementsByTagName('positions')->item(0)->attributes;
		$this->addressXAbsolutePosition = $positions->getNamedItem('addressXAbsolutePosition')->nodeValue;
		$this->addressYAbsolutePosition = $positions->getNamedItem('addressYAbsolutePosition')->nodeValue;
		$this->bodyYAbsolutePosition = $positions->getNamedItem('bodyYAbsolutePosition')->nodeValue;
	
		$logoName = $configXML->getElementsByTagName('logo')->item(0)->textContent;
		$this->logoPath = f_util_FileUtils::buildProjectPath('media','frontoffice','order', $logoName);
		$this->logoWidth = $configXML->getElementsByTagName('logo')->item(0)->attributes->getNamedItem('width')->nodeValue;
		$this->logoHeight = $configXML->getElementsByTagName('logo')->item(0)->attributes->getNamedItem('height')->nodeValue;
		
		$merchantAddressLines = $configXML->getElementsByTagName('merchantAddress')->item(0)->getElementsByTagName('line');
	
		$this->merchantAddress['lines'] = array();
		foreach ($merchantAddressLines as $merchantLine)
		{
			$this->merchantAddress['lines'][] = $merchantLine->textContent;
		}
	
		foreach ($configXML->getElementsByTagName('merchantAddress')->item(0)->childNodes as $merchantAddressInfo)
		{
			/* @var $merchantAddressInfo DOMNode */
			$this->merchantAddress[$merchantAddressInfo->localName] = $merchantAddressInfo->textContent;
		}
			
		$expeditionTxts = $configXML->getElementsByTagName('expeditionTexts')->item(0)->childNodes;
			
		foreach ($expeditionTxts as $expeditionTxt)
		{
			/* @var $expeditionTxt DOMNode */
			$this->expeditionTexts[$expeditionTxt->localName] = $expeditionTxt->textContent;
		}
		
		$customerInfoHeaders = $configXML->getElementsByTagName('customerInfoHeaders')->item(0)->childNodes;
		foreach ($customerInfoHeaders as $customerInfoHeader)
		{
			$this->customerInfoHeaders[$customerInfoHeader->textContent] = $customerInfoHeader->attributes->getNamedItem('size')->nodeValue;
		}
		
		$expeditionLineHeaders = $configXML->getElementsByTagName('expeditionLineHeaders')->item(0)->childNodes;
		foreach ($expeditionLineHeaders as $expeditionLineHeader)
		{
			$this->expeditionLineHeaders[$expeditionLineHeader->textContent] = $expeditionLineHeader->attributes->getNamedItem('size')->nodeValue;
		}
	}
		
	function Header()
	{
		// Logo
		$this->Image($this->logoPath, $this->lMargin, $this->tMargin, $this->logoWidth, $this->logoHeight);
	
		$this->SetFont($this->font, 'B', 15);
		$this->Cell($this->logoWidth);
	
		$title = $this->convertUTF8String($this->expeditionTexts['title']);
		$titleSize = $this->GetStringWidth($title);
		$x = (($this->w) / 2) - ($titleSize / 2);
	
		$this->SetX($x);
		$this->Cell($titleSize, 8, $title, 0, 0, 'C');
		$this->Ln(9);
		$this->SetX($x);
		$this->SetFontSize(8);
		$this->Cell($titleSize, 4, $this->convertUTF8String($this->expeditionTexts['companyStatus']), 0, 0, 'C');
		$this->Ln();
		$this->SetX($x);
		$this->MultiCell($titleSize, 4, $this->convertUTF8String(implode(PHP_EOL, $this->merchantAddress['lines'])), 0, 'C');
		$this->Ln(10);
	
		$this->generateTopRightCell();
		$this->ln(20);
	}
	
	/**
	 * Design the top right cell
	 */
	protected function generateTopRightCell()
	{
		$maxWidth = $this->topRidghtCellMaxWidth;
		$y = $this->tMargin;
		$x = $this->w - $this->rMargin - $maxWidth;
		$this->SetFontSize(8);
		$this->SetXY($x, $y);
		$this->MultiCell(($maxWidth * 0.25), 5, 'tel' . PHP_EOL . 'fax' . PHP_EOL . 'e-mail', 0, 'R');
		$x += $maxWidth * 0.25;
		$addressInfosTxt = $this->convertUTF8String(
			$this->merchantAddress['tel'] . PHP_EOL .
			$this->merchantAddress['fax'] . PHP_EOL .
			$this->merchantAddress['email']
		);
		$width = $maxWidth * 0.75;
		$this->SetXY($x, $y);
		$this->MultiCell($width, 5, $addressInfosTxt, 0, 'R');
		$this->SetFontSize(9);
		$this->SetX($x - ($maxWidth * 0.25));
		$pageNbTxt = $this->convertUTF8String($this->expeditionTexts['page']) . $this->PageNo() . '/{nb}';
		$this->Cell($maxWidth, 5, $pageNbTxt, 0, 0, 'R');
	}
	
	function Footer()
	{
		$this->SetY(- $this->margins[3]);
		$this->SetFont($this->font, '', 6);
		$footerTop = $this->convertUTF8String($this->expeditionTexts['footerTop']);
		$this->Cell($this->GetStringWidth($footerTop), 5, $footerTop);
		$this->Ln();
		$frameSize = $this->w - $this->rMargin - $this->lMargin;
		$this->MultiCell($frameSize, 3, $this->convertUTF8String($this->expeditionTexts['footerFramed']), 1, 'C');
		$this->SetFontSize(7);
		$this->MultiCell($frameSize, 3, $this->convertUTF8String($this->expeditionTexts['footer']), 0, 'C');
	}
	
	/**
	 * @return string
	 */
	public function generatePDF()
	{
		$this->AliasNbPages();
		$this->SetTitle(LocaleService::getInstance()->trans('m.order.fo.packet-form', array('ucf', 'lab')) . ' ' . $this->expedition->getLabel(), true);
		$this->AddPage();
		$lineHeight = 3;
		$this->SetFont($this->font);
	
		$this->generateExpeditionInfo();
		$this->generateCustomerInfos();
		$this->generateShippingInfos();
		$this->generateBody();
		
		return $this->Output($this->expedition->getLabel(), 'I');
	}
	
	/**
	 * Design the expedition informations
	 */
	protected function generateExpeditionInfo()
	{
		$this->SetFontSize(13);
		$this->setFillColorByHeader();
		$expeditionNb = $this->convertUTF8String($this->expeditionTexts['expeditionNo']) . ' ' . $this->convertUTF8String($this->expedition->getLabel());
		$this->Cell($this->GetStringWidth($expeditionNb) + 2, 10, $expeditionNb, 1, 0, 'J', true);
		$date = $this->convertUTF8String($this->expeditionTexts['date'] . date_Formatter::toDefaultDateTime($this->expedition->getOrder()->getUICreationdate()));
		$this->Ln(15);
		$yBillingAddress = $this->GetY();
	
		//Addresses
		$w = 75;
		$h = 5;
		$this->SetXY($this->addressXAbsolutePosition, $this->addressYAbsolutePosition);
		$this->SetFont($this->font, '', 12);
		$this->Ln();
		$this->SetX($this->w / 2);
		$this->generateAddressCell($this->expedition->getAddress(), $w, $h);
		$this->Ln();
	
		$this->Cell($this->GetStringWidth($date), 10, $date);
		$this->ln(15);
	}
	
	/**
	 * Design the customer informations
	 */
	protected function generateCustomerInfos()
	{
		$widthWithoutMargin = $this->w - $this->lMargin - $this->rMargin;
		//Sum of width must be equal to $this->w
		if (round(array_sum($this->customerInfoHeaders)) != round($widthWithoutMargin))
		{
			Framework::error(__CLASS__ . ': sum of customer info headers columns is not equal to the fixed width. Your customer info headers columns doesn\'t have the good size');
		}
	
		$this->SetFontSize(10);
		$columnsWidth = array();
		foreach ($this->customerInfoHeaders as $label => $size)
		{
			$this->Cell($size, 5, $this->convertUTF8String($label), 1, 0, 'C', true);
			$columnsWidth[] = $size;
		}
		$this->Ln();
		$this->Cell($columnsWidth[0], 5, $this->convertUTF8String($this->expedition->getOrder()->getCustomer()->getCodeReference()), 1, 0, 'C');
		$this->Cell($columnsWidth[1], 5, $this->convertUTF8String($this->expedition->getOrder()->getOrderNumber()), 1, 0, 'C');
		$this->Cell($columnsWidth[2], 5, $this->convertUTF8String($this->expedition->getBoStatusLabel()), 1, 0, 'C');
		$this->Cell($columnsWidth[3], 5, $this->convertUTF8String($this->expedition->getShippingMode()->getLabel()), 1, 0, 'C');
		
		/* @var $expedition order_persistentdocument_expedition */
	
		$this->Ln(10);
	}
	
	/**
	 * Design the shipping informations
	 */
	protected function generateShippingInfos()
	{
		$widthWithoutMargin = $this->w - $this->lMargin - $this->rMargin;
		$ls = LocaleService::getInstance();
		$shippingCells = array();
		
		if ($this->expedition->getShippingDate())
		{
			$shippingCells[$ls->trans('m.order.document.expedition.shippingdate')] = date_Formatter::toDefaultDateTime($this->expedition->getUIShippingDate());
		}
		if ($this->expedition->getTrackingNumber())
		{
			$shippingCells[$ls->trans('m.order.frontoffice.tracking-number')] = $this->expedition->getTrackingNumber();
		}
		if ($this->expedition->getTrackingText())
		{
			$shippingCells[$ls->trans('m.order.frontoffice.tracking-text')] = $this->expedition->getTrackingText();
		}
		
	
		$this->SetFontSize(10);

		if (count($shippingCells) > 0)
		{
			$columnWidth = (210 - $this->lMargin - $this->rMargin)/count($shippingCells);
			foreach ($shippingCells as $label => $value)
			{
				$this->Cell($columnWidth, 5, $this->convertUTF8String($label), 1, 0, 'C', true);
			}
			$this->Ln();
			
			foreach ($shippingCells as $value)
			{
				$this->Cell($columnWidth, 5, $this->convertUTF8String($value), 1, 0, 'C');
			}
		
			/* @var $expedition order_persistentdocument_expedition */
		
			$this->Ln(10);
		}
	}
	
	protected function generateBody()
	{
		$this->SetFontSize(13);
		$ls = LocaleService::getInstance();
		$title = $this->convertUTF8String($ls->trans('m.order.frontoffice.expedition-lines', array('ucf')));
		$this->Cell($this->GetStringWidth($title), 5, $title);
		$this->Ln(10);

		$packetByExpedition = array();
		$packetIndex = array();
			
		foreach ($this->expedition->getLineArray() as $line)
		{
			/* @var $line order_persistentdocument_expeditionline */
			$packetNumber = $line->getPacketNumber() ? $line->getPacketNumber() : '-';
			if (!isset($packetIndex[$packetNumber]))
			{
				$packetIndex[$packetNumber] = count($packetIndex);
			}
			$packetByExpedition[$packetIndex[$packetNumber]][] = $line;
		}
		
		foreach ($packetByExpedition as $packetLines)
		{
			$packetLine = $packetLines[0];
			$packetInfos = array();
			if ($packetLine->getPacketNumber())
			{
				$packetInfos[] = $ls->trans('m.order.frontoffice.packet', array('ucf', 'lab')) . ' ' . $packetLine->getPacketNumber();
			}
			if ($packetLine->getTrackingNumber())
			{
				$packetInfos[] = $ls->trans('m.order.frontoffice.tracking-number', array('ucf', 'lab')) . ' ' . $packetLine->getTrackingNumber();
				if ($packetLine->getTrackingText())
				{
					$packetInfos[] =  $ls->trans('m.order.frontoffice.tracking-text', array('ucf', 'lab')) . ' ' . $packetLine->getTrackingText();
				}
			}
			if ($packetLine->getStatus())
			{
				$packetInfos[] = $ls->trans('m.order.frontoffice.status', array('ucf', 'lab')) . ' ' . $packetLine->getStatusLabel();
			}
			if ($packetLine->getReceiveDate())
			{
				$packetInfos[] = $ls->trans('m.order.frontoffice.receive-date', array('ucf', 'lab')) . ' ' . date_Formatter::toDefaultDateTime($packetLine->getUIReceiveDate());
			}
			if ($packetLine->getDeliveryDate())
			{
				$packetInfos[] = $ls->trans('m.order.frontoffice.delivery-date', array('ucf', 'lab')) . ' ' . date_Formatter::toDefaultDateTime($packetLine->getUIDeliveryDate());
			}
			
			foreach ($packetInfos as $packetInfo)
			{
				$text = $this->convertUTF8String($packetInfo);
				$this->Cell($this->GetStringWidth($text), 5, $text);
				$this->Ln();
			}
			$this->setFillColorByHeader();
			$this->generateExpeditionLineHeaders();
			
			$fill = false;
			$bottom = $this->h - $this->margins[3];
			foreach ($packetLines as $line)
			{
				/* @var $orderLine order_persistentdocument_orderLine */
				$case = $this->GetY() + $this->expeditionlineHeight;
			
				//if page change, replicate the orderLine Header
				if ($case > $bottom)
				{
					$this->setFillColorByHeader();
					$this->generateExpeditionLineHeaders();
				}
				$this->setFillColorByLine();
				$this->generateExpeditionLineCells($line, $fill);
				$this->Ln();
				$fill = !$fill;
			}
			$this->Ln(10);
		}
		
		
	}
	
	protected function generateExpeditionLineHeaders()
	{
		$ls = LocaleService::getInstance();
		$expeditionLineColumnSizes = array_values($this->expeditionLineHeaders);
		$this->Cell($expeditionLineColumnSizes[0], 5, $this->convertUTF8String($ls->trans('m.order.frontoffice.designation', array('ucf'))), 1, 0, 'C', true);
		$this->Cell($expeditionLineColumnSizes[1], 5, $this->convertUTF8String($ls->trans('m.order.frontoffice.codereference', array('ucf'))), 1, 0, 'C', true);
		$this->Cell($expeditionLineColumnSizes[2], 5, $this->convertUTF8String($ls->trans('m.order.frontoffice.quantity', array('ucf'))), 1, 0, 'C', true);
		$this->Ln();
	}
	
	protected function generateExpeditionLineCells($expeditionLine, $fill)
	{
		$expeditionLineColumnSizes = array_values($this->expeditionLineHeaders);
		$product = $expeditionLine->getProduct();
		$this->Cell($expeditionLineColumnSizes[0], $this->expeditionlineHeight, $this->convertUTF8String($product->getLabel()), 1, 0, 'C', $fill);
		$this->Cell($expeditionLineColumnSizes[1], $this->expeditionlineHeight, $this->convertUTF8String($product->getCodeReference()), 1, 0, 'C', $fill);
		$this->Cell($expeditionLineColumnSizes[2], $this->expeditionlineHeight, $this->convertUTF8String($expeditionLine->getQuantity() . ' / ' . $expeditionLine->getOrderProductQuantity()), 1, 0, 'C', $fill);
	}
	
	/**
	 * Design an address cell
	 * @param customer_persistentdocument_address $address
	 * @param float $w
	 * @param float $h
	 * @param integer $fontSize
	 */
	protected function generateAddressCell($address, $w = 50, $h = 5, $fontSize = 10)
	{
		$this->SetFontSize($fontSize);
	
		$addressCivility = ($address->getCivility() ? $address->getCivility() . '. ' : '') . $address->getFirstname() . ' ' . $address->getLastname() . PHP_EOL;
		$addressTxt = $address->getAddressLine1() . PHP_EOL;
		if ($address->getAddressLine2())
		{
			$addressTxt .= $address->getAddressLine2() . PHP_EOL;
		}
		if ($address->getAddressLine3())
		{
			$addressTxt .= $address->getAddressLine3() . PHP_EOL;
		}
		$addressTxt .= $address->getZipCode() . ' ' . $address->getCity() . ' ' . $address->getCountryName() . PHP_EOL;
	
		$x = $this->GetX();
		$this->SetFont($this->font, 'B');
		$this->Cell($w, $h, $this->convertUTF8String($addressCivility), 'L');
		$this->Ln();
		$this->SetX($x);
		$this->SetFont($this->font, '');
		$this->MultiCell($w, $h, $this->convertUTF8String($addressTxt), 'L');
	}
	
	protected function setFillColorByHeader()
	{
		$this->SetFillColor($this->orderLineHeaderFillColorRGB[0], $this->orderLineHeaderFillColorRGB[1], $this->orderLineHeaderFillColorRGB[2]);
	}
	
	protected function setFillColorByLine()
	{
		$this->SetFillColor($this->orderLineFillColorRGB[0], $this->orderLineFillColorRGB[1], $this->orderLineFillColorRGB[2]);
	}
}