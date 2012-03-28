<?php
require_once WEBEDIT_HOME . '/libs/fpdf/fpdf.php';

class order_FPDFBillGenerator {
	
	/**
	 * @param order_persistentdocument_bill $bill
	 * @param string $filePath
	 * @throws Exception
	 */
	public function writePDF($bill, $filePath)
	{
		$pdf = new order_billToPDF($bill);
		f_util_FileUtils::writeAndCreateContainer($filePath, $pdf->generatePDF(), f_util_FileUtils::OVERRIDE);
	}
}

class order_billToPDF extends FPDF
{	
	/**
	 * @var order_persistentdocument_bill
	 */
	protected $bill;

	/**
	 * @var string
	 */
	protected $logoPath;
	
	/**
	 * @var string
	 */
	protected $logoWidth;
	
	/**
	 * @var string
	 */
	protected $logoHeight;
	
	/**
	 * @var integer
	 */
	protected $orderLineHeight;
	
	/**
	 * @var integer[]
	 */
	protected $orderLineFillColorRGB;
	
	/**
	 * @var integer[]
	 */
	protected $orderLineHeaderFillColorRGB;
	
	/**
	 * @var string[]
	 */
	protected $billTexts = array();
	
	/**
	 * @var string[]
	 */
	protected $merchantAddress = array();
	
	/**
	 * @var string[]
	 */
	protected $orderLineHeaderTxtAndWidth = array();
	
	/**
	 * @var string[]
	 */
	protected $summaryTxtAndWidth = array();
	
	/**
	 * @var integer
	 */
	protected $summaryLineHeight;
	
	/**
	 * @var integer
	 */
	protected $productLabelMaxWidth;
	
	/**
	 * @var array<string label, integer size>
	 */
	protected $customerInfoHeaders = array();
	
	/**
	 * @var float
	 */
	protected $addressYAbsolutePosition;
	
	/**
	 * @var float
	 */
	protected $addressXAbsolutePosition;
	
	/**
	 * @var float
	 */
	protected $bodyYAbsolutePosition;
	
	/**
	 * @var string
	 */
	protected $font;
	
	/**
	 * @param float[]
	 */
	protected $margins = array();
	
	public function convertUTF8String($UTF8string)
	{
		return mb_convert_encoding($UTF8string, 'CP1252', 'UTF-8');
	}
	
	/**
	 *
	 * @param order_persistentdocument_bill $bill
	 * @param customer_persistentdocument_address $merchantAddress
	 * @param string $logoPath
	 * @param string $title
	 */
	public function __construct($bill, $orientation = 'P', $unit = 'mm', $size = 'A4')
	{
		parent::FPDF($orientation, $unit, $size);
		if ($bill instanceof order_persistentdocument_bill)
		{	
			$this->bill = $bill;
			$configXMLPath = null;
			$ref = $bill->getOrder()->getShop()->getCodeReference();
			$lang = $bill->getOrder()->getLang();
			
			if ($ref)
			{
				$configXMLPath = FileResolver::getInstance()->setPackageName('modules_order')
				->setDirectory('templates')
				->getPath('FPDFBillInfos-'. $ref. '-'. $lang . '.xml');
			}
			if ($configXMLPath == null)
			{
				//get the merchant address from XML Configuration
				$configXMLPath = FileResolver::getInstance()->setPackageName('modules_order')
					->setDirectory('templates')
					->getPath('FPDFBillInfos-default.xml');
			}
			
			$configXML = f_util_DOMUtils::fromPath($configXMLPath);
			$this->parseXML($configXML);
			//FPDF Config
			$this->SetAutoPageBreak(true, $this->margins[3]);
			$this->SetMargins($this->margins[0], $this->margins[2], $this->margins[1]);
		}
		else
		{
			throw new Exception('Invalid parameter $bill give to ' . __CLASS__ . ' constructor');
		}
	}
	
	/**
	 * 
	 * @param DOMDocument $configXML
	 */
	protected function parseXML($configXML)
	{
		$positions = $configXML->getElementsByTagName('positions')->item(0)->attributes;
		$this->addressXAbsolutePosition = $positions->getNamedItem('addressXAbsolutePosition')->nodeValue;
		$this->addressYAbsolutePosition = $positions->getNamedItem('addressYAbsolutePosition')->nodeValue;
		$this->bodyYAbsolutePosition = $positions->getNamedItem('bodyYAbsolutePosition')->nodeValue;
		
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
		
		$orderLineHeaders = $configXML->getElementsByTagName('orderLineHeaders')->item(0)->getElementsByTagName('line');
			
		foreach ($orderLineHeaders as $orderLineHeader)
		{
			/* @var $orderLineHeader DOMNode */
			$this->orderLineHeaderTxtAndWidth[$orderLineHeader->textContent] = $orderLineHeader->attributes->getNamedItem('size')->nodeValue;
			if ($orderLineHeader->attributes->getNamedItem('isProductLabel'))
			{
				$this->productLabelMaxWidth = $orderLineHeader->attributes->getNamedItem('size')->nodeValue;
			}
		}
			
		$logoName = $configXML->getElementsByTagName('logo')->item(0)->textContent;
		$this->logoPath = f_util_FileUtils::buildWebeditPath('media','frontoffice','order', $logoName);
		$this->logoWidth = $configXML->getElementsByTagName('logo')->item(0)->attributes->getNamedItem('width')->nodeValue;
		$this->logoHeight = $configXML->getElementsByTagName('logo')->item(0)->attributes->getNamedItem('height')->nodeValue;
			
		$billTxts = $configXML->getElementsByTagName('billTexts')->item(0)->childNodes;
			
		foreach ($billTxts as $billTxt)
		{
			/* @var $billTxt DOMNode */
			$this->billTexts[$billTxt->localName] = $billTxt->textContent;
		}
			
		$summaryTxts = $configXML->getElementsByTagName('priceSummary')->item(0)->childNodes;
		
		foreach ($summaryTxts as $summaryTxt)
		{
			/* @var $summaryTxt DOMNode */
			$this->summaryTxtAndWidth[$summaryTxt->textContent] = $summaryTxt->attributes->getNamedItem('size')->nodeValue;
		}
		$this->summaryLineHeight = $configXML->getElementsByTagName('priceSummary')->item(0)->attributes->getNamedItem('summaryLineHeight')->nodeValue;
			
		$designConfiguration = $configXML->getElementsByTagName('design')->item(0)->attributes;
		$this->orderLineHeight = $designConfiguration->getNamedItem('orderlineHeight')->nodeValue;
		$this->orderLineHeaderFillColorRGB = explode(',', $designConfiguration->getNamedItem('orderLineHeaderFillColorRGB')->nodeValue);
		$this->orderLineFillColorRGB = explode(',', $designConfiguration->getNamedItem('orderLineFillColorRGB')->nodeValue);
		$this->margins = explode(',', $designConfiguration->getNamedItem('margins')->nodeValue);
		$this->font = $designConfiguration->getNamedItem('font')->nodeValue;
			
		foreach ($configXML->getElementsByTagName('customerInfoHeaders')->item(0)->childNodes as $customerInfo)
		{
			/* @var $customerInfo DOMNode */
			$this->customerInfoHeaders[$customerInfo->textContent] = $customerInfo->attributes->getNamedItem('size')->nodeValue;
			
		}
		
	}

	function Header()
	{
		$y = $this->GetY();
		// Logo
		$this->Image($this->logoPath, $this->lMargin, $this->tMargin, $this->logoWidth, $this->logoHeight);

		$this->SetFont($this->font, 'B', 15);
		$this->Cell($this->logoWidth);
				
		$title = $this->convertUTF8String($this->billTexts['title']);
		$titleSize = $this->GetStringWidth($title);
		$x = (($this->w) / 2) - ($titleSize / 2);
		
		$this->SetX($x);
		$this->Cell($titleSize, 8, $title, 0, 0, 'C');
		$this->Ln(9);
		$this->SetX($x);
		$this->SetFontSize(8);
		$this->Cell($titleSize, 4, $this->convertUTF8String($this->billTexts['companyStatus']), 0, 0, 'C');
		$this->Ln();
		$this->SetX($x);
		$this->MultiCell($titleSize, 4, $this->convertUTF8String(implode(PHP_EOL, $this->merchantAddress['lines'])), 0, 'C');
		$this->Ln(10);
		
		$x = $this->w - $this->rMargin - 45;
		$this->SetFontSize(8);
		$this->SetXY($x, $y);
		$this->MultiCell(15, 5, 'tel' . PHP_EOL . 'fax' . PHP_EOL . 'e-mail', 0, 'R');
		$x += 15;
		$addressInfosTxt = $this->convertUTF8String(
			$this->merchantAddress['tel'] . PHP_EOL . 
			$this->merchantAddress['fax'] . PHP_EOL .
			$this->merchantAddress['email']
		);
		$width = 30;
		$this->SetXY($x, $y);
		$this->MultiCell($width, 5, $addressInfosTxt, 0, 'R');
		$this->SetX($x);
		$this->SetFontSize(9);
		$this->Cell(30, 5, $this->convertUTF8String($this->billTexts['page']) . $this->PageNo() . '/{nb}', 0, 0, 'R');
		$this->ln(20);
	}

	function Footer()
	{
		$this->SetY(- $this->margins[3]);
		$this->SetFont($this->font, '', 6);
		$footerTop = $this->convertUTF8String($this->billTexts['footerTop']);
		$this->Cell($this->GetStringWidth($footerTop), 5, $footerTop);
		$this->Ln();
		$frameSize = $this->w - $this->rMargin - $this->lMargin;
		$this->MultiCell($frameSize, 3, $this->convertUTF8String($this->billTexts['footerFramed']), 1, 'C');
		$this->SetFontSize(7);
		$this->MultiCell($frameSize, 3, $this->convertUTF8String($this->billTexts['footer']), 0, 'C');
	}

	protected function generateBillInfo()
	{
		$this->SetFontSize(13);
		$this->setFillColorByHeader();
		$billNb = $this->convertUTF8String($this->billTexts['billNo']) . ' ' . $this->convertUTF8String($this->bill->getLabel());
		$this->Cell($this->GetStringWidth($billNb) + 2, 10, $billNb, 1, 0, 'J', true);
		$date = $this->convertUTF8String($this->billTexts['date'] . date_Formatter::toDefaultDateTime($this->bill->getUICreationdate()));
		$this->Ln(15);
		$yBillingAddress = $this->GetY();
		
		//Addresses
		$w = 75;
		$h = 5;
		$this->SetXY($this->addressXAbsolutePosition, $this->addressYAbsolutePosition);
		$this->SetFont($this->font, '', 12);
		$this->Ln();
		$this->SetX($this->w / 2);
		$this->generateAddressCell($this->bill->getAddress(), $w, $h);
		$this->Ln();
		
		$this->Cell($this->GetStringWidth($date), 10, $date);
		$this->ln(15);
	}
	
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
		$this->Cell($columnsWidth[0], 5, $this->convertUTF8String($this->bill->getOrder()->getCustomer()->getCodeReference()), 1, 0, 'C');
		$this->Cell($columnsWidth[1], 5, $this->convertUTF8String($this->bill->getOrder()->getOrderNumber()), 1, 0, 'C');
		/* @var $expedition order_persistentdocument_expedition */
		
		$this->Ln(10);
	}
	
	public function generatePDF()
	{
		$this->AliasNbPages();
		$this->AddPage();
		$this->generateBillInfo();
		$this->SetY($this->bodyYAbsolutePosition);
		$this->generateCustomerInfos();
		$this->generateBody();
		$this->generateSummary();

		return $this->Output('', 'S');
	}

	protected function generateBody()
	{
		$orderLines = $this->bill->getOrder()->getLineArray();
		
		$widthWithoutMargin = $this->w - $this->lMargin - $this->rMargin;
		//Sum of width must be equal to $this->w
		if (round(array_sum($this->orderLineHeaderTxtAndWidth)) != round($widthWithoutMargin))
		{
			Framework::error(__CLASS__ . ': sum of order lines columns is not equal to the fixed width. Your orderlines columns doesn\'t have the good size: (' . $widthWithoutMargin . 'mm)');
		}
		$this->generateOrderLineHeaders();
		
		//Order lines
		$fill = false;
		$bottom = $this->h - $this->margins[3];
		foreach ($orderLines as $orderLine)
		{
			/* @var $orderLine order_persistentdocument_orderLine */
			$case = $this->GetY() + $this->orderLineHeight;
				
			//if page change, replicate the orderLine Header
			if ($case > $bottom)
			{
				$this->generateOrderLineHeaders();
			}
			$this->generateOrderLineCells($orderLine, $fill);
			$this->Ln();
			$fill = !$fill;
		}
	}
	
	protected function generateOrderLineHeaders()
	{
		$this->SetFontSize(10);
		$this->Ln(10);
		$this->setFillColorByHeader();
		foreach ($this->orderLineHeaderTxtAndWidth as $text => $width)
		{
			$this->Cell($width, 8, $this->convertUTF8String($text), 1, 0, 'C', true);
		}
		$this->SetFillColor(
			$this->orderLineFillColorRGB[0],
			$this->orderLineFillColorRGB[1],
			$this->orderLineFillColorRGB[2]
		);
		$this->Ln();
	}
	
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
	
	/**
	 * Design the order lines
	 * @param order_persistentdocument_orderline $orderLine
	 * @param boolean $fill
	 * @param integer $articleMaxLength
	 */
	protected function generateOrderLineCells($orderLine, $fill, $articleMaxLength = 70)
	{
		$this->SetFontSize(8);
		
		$order = $this->bill->getOrder();
		
		$this->SetFillColor(
			$this->orderLineFillColorRGB[0],
			$this->orderLineFillColorRGB[1],
			$this->orderLineFillColorRGB[2]
		);
		
		$orderLineWidth = array_values($this->orderLineHeaderTxtAndWidth);
		
		$articleText = $this->convertUTF8String($orderLine->getLabel());
			
		//reduce the width of the article name, if there is too large
		while (($this->GetStringWidth($articleText) + 1) > $this->productLabelMaxWidth)
		{
			$articleText = f_util_StringUtils::shortenString($articleText, $articleMaxLength);
			$articleMaxLength -= 5;
		}
		$this->Cell($orderLineWidth[0], $this->orderLineHeight, $this->convertUTF8String($orderLine->getQuantity()), 1, 0, 'C', $fill);
		$this->Cell($orderLineWidth[1], $this->orderLineHeight, $articleText, 1, 0, 'L', $fill);
		$this->Cell($orderLineWidth[2], $this->orderLineHeight, $this->convertUTF8String($orderLine->getProduct()->getCodeReference()), 1, 0, 'L', $fill);
		$this->Cell($orderLineWidth[3], $this->orderLineHeight, $this->convertUTF8String($order->formatPrice($orderLine->getUnitPriceWithoutTax())), 1, 0, 'R', $fill);
		$this->Cell($orderLineWidth[4], $this->orderLineHeight, $this->convertUTF8String($order->formatPrice($orderLine->getAmountWithoutTax())), 1, 0, 'R', $fill);
	}
	
	/**
	 * Generate the total price summary
	 */
	protected function generateSummary()
	{
		$this->SetFontSize(9);
		$order = $this->bill->getOrder();

		$summaryWidths = array();
		$summaryTxts = array();
		foreach ($this->summaryTxtAndWidth as $key => $width)
		{
			$summaryWidths[] = $width;
			$summaryTxts[] = $key;
		}
		
		//Summary
		$totalSummaryLabels = array();
		$totalSummaryValues = array();
		
		$totalSummaryLabels[] = $this->billTexts['subTotal'] . ' ' . $this->billTexts['withoutTax'];
		$totalSummaryValues[] = $order->formatPrice($order->getLinesAmountWithoutTax());
		foreach ($order->getFeesDataArrayForDisplay() as $fees)
		{
			$totalSummaryLabels[] = $fees['label'] . ' ' . $this->billTexts['withoutTax'];
			$totalSummaryValues[] =  $fees['valueWithoutTax'];
		}
		if (count($order->getDiscountDataArrayForDisplay()))
		{
			foreach ($order->getDiscountDataArrayForDisplay() as $discount)
			{
				$totalSummaryLabels[] = $discount['label'] . ' ' . $this->billTexts['withoutTax'];
				$totalSummaryValues[] = $discount['valueWithoutTax'];
			}
		}
		
		$totalSummaryLabels[] = $this->billTexts['paymentConnector'];
		$totalSummaryValues[] = $order->getPaymentConnectorLabel();
		
		//search the longest text and value element to design summary
		$txtMaxWidth = 0;
		foreach ($totalSummaryLabels as $summaryTextElement)
		{
			if ($txtMaxWidth < strlen($summaryTextElement))
			{
				$txtMaxWidth = strlen($summaryTextElement);
			}
		}
		$valueMaxWidth = 0;
		foreach ($totalSummaryValues as $summaryValueElement)
		{
			if ($valueMaxWidth < strlen($summaryValueElement))
			{
				$valueMaxWidth = strlen($summaryValueElement);
			}
		}
		//Summary design
		$w = $txtMaxWidth + $valueMaxWidth;
		$h = $this->summaryLineHeight;
		$numberOfLine = count($totalSummaryValues) + 1;
		
		//if the block of summary is too big, add a page and design on it
		$summaryBlockSize = $this->GetY() + $this->margins[3] + ($numberOfLine * $h);
		if ($summaryBlockSize > $this->h)
		{
			$this->AddPage();
		}
		
		//headers
		$this->setFillColorByHeader();
		$this->Cell($summaryWidths[0], $this->summaryLineHeight, $this->convertUTF8String($summaryTxts[0]), 1, 0, 'C', true);
		$this->Cell($summaryWidths[1], $this->summaryLineHeight, $this->convertUTF8String($summaryTxts[1]), 1, 0, 'C', true);
		$this->Cell($summaryWidths[2], $this->summaryLineHeight, $this->convertUTF8String($summaryTxts[2]), 1, 0, 'C', true);
		$this->Cell($summaryWidths[3], $this->summaryLineHeight, $this->convertUTF8String($summaryTxts[3]), 1, 0, 'C', true);
		$this->Ln();
		$this->Cell(190, 0, '', 'T');
		$this->Ln();
		$y = $this->GetY();
		
		foreach ($order->getTaxRates() as $rateFormated => $value)
		{
			$basePrice = $value / catalog_TaxService::getInstance()->parseRate($rateFormated);
			$this->Cell($summaryWidths[0], $this->summaryLineHeight, $this->convertUTF8String($rateFormated), 0, 0, 'C');
			$this->Cell($summaryWidths[1], $this->summaryLineHeight, $this->convertUTF8String($order->formatPrice($basePrice)), 0, 0, 'C');
			$this->Cell($summaryWidths[2], $this->summaryLineHeight, $this->convertUTF8String($order->formatPrice($value)), 0, 0, 'C');
			$this->Ln();
		}
		
		
		$x = ($this->w - $this->lMargin) - $summaryWidths[3];
		$this->SetY($y);
		
		foreach ($totalSummaryLabels as $totalSummaryLabel)
		{
			$this->SetX($x);
			$this->Cell($summaryWidths[3], $this->summaryLineHeight, $this->convertUTF8String($totalSummaryLabel), 0, 0, 'L');
			$this->Ln();
		}
		$this->SetY($y);
		while ($numberOfLine)
		{
			$this->SetX($x);
			$this->Cell($summaryWidths[3], $this->summaryLineHeight, $this->convertUTF8String(':'), 0, 0, 'C');
			$this->Ln();
			$numberOfLine--;
		}
		$this->SetY($y);
		foreach ($totalSummaryValues as $totalSummaryValue)
		{
			$this->SetX($x);
			$this->Cell($summaryWidths[3], $this->summaryLineHeight, $this->convertUTF8String($totalSummaryValue), 0, 0, 'R');
			$this->Ln();
		}
		
		$this->SetX($x);
		$this->SetFont($this->font, 'B', 10);
		$this->Cell($summaryWidths[3], $this->summaryLineHeight, $this->convertUTF8String($this->billTexts['total'] . ' ' .  $this->billTexts['withoutTax']), 0, 0, 'L');
		$this->SetX($x);
		$this->Cell($summaryWidths[3], $this->summaryLineHeight, $this->convertUTF8String(':'), 0, 0, 'C');
		$this->SetX($x);
		$this->Cell($summaryWidths[3], $this->summaryLineHeight, $this->convertUTF8String($order->formatPrice($order->getTotalAmountWithoutTax())), 0, 0, 'R');
		$this->Ln();
		
		$this->SetX($x);
		$this->SetFont($this->font, 'B', 12);
		$this->Cell($summaryWidths[3], $this->summaryLineHeight, $this->convertUTF8String($this->billTexts['total'] . ' ' .  $this->billTexts['withTax']), 0, 0, 'L');
		$this->SetX($x);
		$this->Cell($summaryWidths[3], $this->summaryLineHeight, $this->convertUTF8String(':'), 0, 0, 'C');
		$this->SetX($x);
		$this->Cell($summaryWidths[3], $this->summaryLineHeight, $this->convertUTF8String($order->formatPrice($order->getTotalAmountWithTax())), 0, 0, 'R');
		$this->Ln(10);
		
		//borders
		$this->SetY($y);
		$countTaxes = count($order->getTaxRates());
		$countSummaryLines = count($totalSummaryValues) + 2;
		$h = $countTaxes > $countSummaryLines ? $countTaxes * $this->summaryLineHeight : $countSummaryLines * $this->summaryLineHeight;
		$this->Cell($summaryWidths[0], $h,'', 1, 0);
		$this->Cell($summaryWidths[1], $h,'', 1, 0);
		$this->Cell($summaryWidths[2], $h,'', 1, 0);
		$this->Cell($summaryWidths[3], $h,'', 1, 0);
		
}
	
	protected function setFillColorByHeader()
	{
		$this->SetFillColor($this->orderLineHeaderFillColorRGB[0], $this->orderLineHeaderFillColorRGB[1], $this->orderLineHeaderFillColorRGB[2]);
	}

	/**
	 * @param string $rateFormated
	 * @return float | null
	 */
	protected function getTaxRateByLabel($rateFormated)
	{
		$taxes = catalog_TaxService::getInstance()->createQuery()
		->add(Restrictions::eq('label', $rateFormated))
		->find();
		
		if (count($taxes))
		{
			return $taxes[0]->getRate();
		}
		return null;		
	}
	
}