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
		$order = $bill->getOrder();
		$shop = $order->getShop();
		$customer = $order->getCustomer();
		
		// Instanciation de la classe dérivée
		$pdf = new order_billToPDF($bill);
		f_util_FileUtils::writeAndCreateContainer($filePath, $pdf->generatePDF(), f_util_FileUtils::OVERRIDE);
	}
}

class order_billToPDF extends FPDF
{
	const LEFT_MARGIN = 10;
	const RIGHT_MARGIN = 10;
	const TOP_MARGIN = 10;
	const BOTTOM_MARGIN = 10;
	
	/**
	 * @var order_persistentdocument_bill
	 */
	private $bill;

	/**
	 * @var string
	 */
	private $logoPath;
	
	/**
	 * @var string
	 */
	private $logoWidth;
	
	/**
	 * @var string
	 */
	private $logoHeight;
	
	/**
	 * @var integer
	 */
	private $orderLineHeight;
	
	/**
	 * @var integer[]
	 */
	private $orderLineFillColorRGB;
	
	/**
	 * @var integer[]
	 */
	private $orderLineHeaderFillColorRGB;
	
	/**
	 * @var string[]
	 */
	private $billTexts = array();
	
	/**
	 * @var string[]
	 */
	private $merchantAddress = array();
	
	/**
	 * @var string[]
	 */
	private $orderLineHeaderTxtAndWidth = array();
	
	/**
	 * @var string[]
	 */
	private $summaryTxt = array();
	
	/**
	 * @var integer
	 */
	private $productLabelMaxWidth;
	
	/**
	 * @var integer
	 */
	private $width;
	
	/**
	 * @var integer
	 */
	private $height;
	
	/**
	 * @var string
	 */
	private $currency;
	
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
			$this->width = (210 - (self::LEFT_MARGIN + self::RIGHT_MARGIN));
			$this->height = (297 - (self::BOTTOM_MARGIN + self::TOP_MARGIN));
			
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
				
			$merchantAddressLines = $configXML->getElementsByTagName('merchantAddress')->item(0)->getElementsByTagName('line');
				
			foreach ($merchantAddressLines as $merchantLine)
			{
				$this->merchantAddress[] = $merchantLine->textContent;
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
			
			$summaryTxts = $configXML->getElementsByTagName('summary')->item(0)->childNodes;
				
			foreach ($summaryTxts as $summaryTxt)
			{
				/* @var $summaryTxt DOMNode */
				$this->summaryTxt[] = $summaryTxt->textContent;
			}
			
			$designConfiguration = $configXML->getElementsByTagName('design')->item(0)->attributes;
			$this->orderLineHeight = $designConfiguration->getNamedItem('orderlineHeight')->nodeValue;
			$this->orderLineHeaderFillColorRGB = explode(',', $designConfiguration->getNamedItem('orderLineHeaderFillColorRGB')->nodeValue);
			$this->orderLineFillColorRGB = explode(',', $designConfiguration->getNamedItem('orderLineFillColorRGB')->nodeValue);
			
			$this->currency = $this->bill->getCurrency() == 'EUR' ? chr(128) : utf8_decode($this->bill->getCurrency());
			
			//FPDF Config
			$this->SetAutoPageBreak(true, 25);

		}
		else
		{
			throw new Exception('Invalid parameter $bill give to ' . __CLASS__ . ' constructor');
		}
	}

	function Header()
	{
		// Logo
		$this->Image($this->logoPath, self::LEFT_MARGIN, self::TOP_MARGIN, $this->logoWidth, $this->logoHeight);
		// Police Arial bold 15
		$this->SetFont('Times', 'B', 15);

		$title = utf8_decode($this->billTexts['title']);
		$titleSize = $this->GetStringWidth($title) + 2;
		// Right shift
		$this->Cell($this->width - $titleSize);
		$this->Cell($titleSize, 10, $title, 1, 0, 'C');
		$this->Ln(12);
		$billNb = $this->billTexts['billNo'] . ' ' . $this->bill->getLabel();
		$date = $this->billTexts['date'] . date_Formatter::format($this->bill->getCreationdate());
		$this->SetFontSize(10);
		$billInfosSize = 50;
		//Right shift
		$this->Cell($this->width - $billInfosSize);
		$this->MultiCell($billInfosSize, 6, utf8_decode($billNb . PHP_EOL . $date . PHP_EOL . 'Page ' . $this->PageNo() . '/{nb}'), 1, 'L');
		$this->Ln();

		// New line
		$this->Ln(10);
	}

	function Footer()
	{
		// Positionning at 1,5 cm of bottom
		$this->SetY(-15);
		// Police Arial italic 8
		$this->SetFont('Times', 'I', 8);
		// Page number
		$this->Cell(0, 10, $this->billTexts['footer'], 0, 0, 'C');
	}

	public function generatePDF()
	{
		$this->AliasNbPages();
		$this->AddPage();
		$this->SetFont('Times', '', 12);

		//Address
		$w = 50;
		$h = 5;
		$y = $this->GetY();
		$this->generateAddressCell($this->bill->getAddress(), $w, $h);
		$this->SetXY(($this->width + self::LEFT_MARGIN) - $w, $y);
		$this->MultiCell($w, $h, utf8_decode(implode(PHP_EOL, $this->merchantAddress)), 1);
		
		$this->Cell(70, 10, utf8_decode($this->billTexts['customerReferenceTxt'] . $this->bill->getOrder()->getCustomer()->getCodeReference()));

		$this->Ln(15);

		$orderLines = $this->bill->getOrder()->getLineArray();

		$this->MultiCell($this->width, 10, $this->billTexts['orderLineTxt'], 1, 'L');


		//Order line headers
		//Colors
		$this->SetFillColor(
			$this->orderLineHeaderFillColorRGB[0], 
			$this->orderLineHeaderFillColorRGB[1], 
			$this->orderLineHeaderFillColorRGB[2]
		);
		
		//Sum of width must be equal to $this->width
		if (array_sum($this->orderLineHeaderTxtAndWidth) !== $this->width)
		{
			Framework::error(__CLASS__ . ': sum of order lines columns is not equal to the fixed width. Your orderlines columns doesn\'t have the good size');
		}
		
		$orderLineHeaderTxts = array();
		foreach ($this->orderLineHeaderTxtAndWidth as $text => $width)
		{
			$this->Cell($width, 8, utf8_decode($text), 1, 0, 'C', true);
			$orderLineWidth[] = $width;
		}
		$this->ln();
		//Order lines
		$this->SetFillColor(
			$this->orderLineFillColorRGB[0], 
			$this->orderLineFillColorRGB[1], 
			$this->orderLineFillColorRGB[2]
		);
		$fill = false;
		foreach ($orderLines as $orderLine)
		{
			//if page change, replicate the orderLine Header
			if (($this->GetY() + (2 * self::BOTTOM_MARGIN)) > $this->height)
			{
				$this->SetFontSize(10);
				$this->Ln(10);
				$this->SetFillColor(
					$this->orderLineHeaderFillColorRGB[0], 
					$this->orderLineHeaderFillColorRGB[1], 
					$this->orderLineHeaderFillColorRGB[2]
				);
				foreach ($this->orderLineHeaderTxtAndWidth as $text => $width)
				{
					$this->Cell($width, 8, utf8_decode($text), 1, 0, 'C', true);
				}
				$this->SetFillColor(
					$this->orderLineFillColorRGB[0],
					$this->orderLineFillColorRGB[1],
					$this->orderLineFillColorRGB[2]
				);
				$this->Ln();
			}
			$this->SetFontSize(8);
			$this->generateOrderLineCells($orderLineWidth, $orderLine, $fill);

			$this->Ln();
			$fill = !$fill;
		}
		
		$this->SetFontSize(8);
		$this->generateSummary();

		return $this->Output('', 'S');
	}

	private function generateAddressCell($address, $w = 50, $h = 6, $fontSize = 10)
	{
		$this->SetFontSize($fontSize);

		$addressTxt = ($address->getCivility() ? $address->getCivility() . '. ' : '') . $address->getFirstname() . ' ' . $address->getLastname() . PHP_EOL;
		$addressTxt .= $address->getAddressLine1() . PHP_EOL;
		if ($address->getAddressLine2())
		{
			$addressTxt .= $address->getAddressLine2() . PHP_EOL;
		}
		if ($address->getAddressLine3())
		{
			$addressTxt .= $address->getAddressLine3() . PHP_EOL;
		}
		$addressTxt .= $address->getZipCode() . ' ' . $address->getCity() . ' ' . $address->getCountryName() . PHP_EOL;
		if ($address->getPhone())
		{
			$addressTxt .= $address->getPhone() . PHP_EOL;
		}
		if ($address->getMobilephone())
		{
			$addressTxt .= $address->getMobilephone();
		}
		if ($address->getFax())
		{
			$addressTxt .= $address->getFax();
		}

		$this->MultiCell($w, $h, utf8_decode($addressTxt), 1);
	}
	
	/**
	 * Design the order lines
	 * @param string[] $orderLineWidth
	 * @param order_persistentdocument_orderline $orderLine
	 * @param boolean $fill
	 * @param integer $articleMaxLength
	 */
	private function generateOrderLineCells($orderLineWidth, $orderLine, $fill, $articleMaxLength = 70)
	{
		Framework::fatal(__METHOD__ . ' ' . var_export($orderLineWidth, true));
		
		$articleText = utf8_decode($orderLine->getProduct()->getLabel());
			
		//reduce the width of the article name, if there is too large
		while (($this->GetStringWidth($articleText) + 1) > $this->productLabelMaxWidth)
		{
			$articleText = f_util_StringUtils::shortenString($articleText, $articleMaxLength);
			$articleMaxLength -= 5;
		}
		
		$this->Cell($orderLineWidth[0], $this->orderLineHeight, utf8_decode($orderLine->getQuantity()), 1, 0, 'C', $fill);
		$this->Cell($orderLineWidth[1], $this->orderLineHeight, $articleText, 1, 0, 'L', $fill);
		$this->Cell($orderLineWidth[2], $this->orderLineHeight, utf8_decode($orderLine->getProduct()->getCodeReference()), 1, 0, 'L', $fill);
		$this->Cell($orderLineWidth[3], $this->orderLineHeight, utf8_decode($orderLine->getUnitPriceWithoutTax() . ' ') . $this->currency, 1, 0, 'R', $fill);
		$this->Cell($orderLineWidth[4], $this->orderLineHeight, utf8_decode($orderLine->getAmountWithoutTax() . ' ') . $this->currency, 1, 0, 'R', $fill);
	}
	
	/**
	 * Generate the total price summary
	 */
	private function generateSummary()
	{
		$totalSummaryLabel = $this->summaryTxt[0] . PHP_EOL;
		$totalSummaryValue = $this->bill->getOrder()->getTotalAmountWithoutTax() . ' ' . utf8_encode($this->currency) . PHP_EOL;
		foreach ($this->bill->getOrder()->getDiscountDataArrayForDisplay() as $discount)
		{
			$totalSummaryLabel .= $discount['label'] . ' HT : ' . PHP_EOL;
			$totalSummaryValue .= $discount['valueWithTax'] . ' ' . utf8_encode($this->currency) . PHP_EOL;
			$totalSummaryLabel .= $discount['label'] . ' TTC : ' . PHP_EOL;
			$totalSummaryValue .= $discount['valueWithoutTax'] . ' ' . utf8_encode($this->currency) . PHP_EOL;
		}
		$totalSummaryLabel .= $this->summaryTxt[1] . PHP_EOL;
		$totalSummaryValue .=  $this->bill->getOrder()->getShippingFeesWithoutTax() . ' ' . utf8_encode($this->currency) . PHP_EOL;
		$totalSummaryLabel .= $this->summaryTxt[2] . PHP_EOL;
		$totalSummaryValue .=  $this->bill->getOrder()->getShippingFeesWithTax() . ' ' . utf8_encode($this->currency) . PHP_EOL;
		
		foreach ($this->bill->getOrder()->getTaxRates() as $rateFormated => $value)
		{
			$totalSummaryLabel .= $this->summaryTxt[3] . $rateFormated . ': ' . PHP_EOL;
			$totalSummaryValue .= catalog_PriceFormatter::getInstance()->round($value) . ' ' . utf8_encode($this->currency) . PHP_EOL;
		}
		
		$totalSummaryLabel .= $this->summaryTxt[4] . PHP_EOL;
		$totalSummaryValue .= $this->bill->getOrder()->getPaymentConnectorLabel() . PHP_EOL;
		
		//Summary design
		$w = 30;
		$h = 4;
		$numberOfLine = 7;
		
		//if the block of summary is too big, add a page and design on it
		if (($this->GetY() + ($numberOfLine * $h)) > $this->height)
		{
			$this->AddPage();
		}
		
		$y = $this->GetY();
		$this->MultiCell(($this->width - $w), $h, utf8_decode($totalSummaryLabel), 0, 'R');
		$this->SetXY(($this->width + self::LEFT_MARGIN) - $w, $y);
		$this->MultiCell($w, $h, utf8_decode($totalSummaryValue), 0, 'L');
		
		$this->SetFont('', 'B', 10);
		$this->Cell(($this->width - $w), $h, utf8_decode($this->summaryTxt[5]), 0, 0, 'R');
		$this->Cell($w, $h, utf8_decode($this->bill->getOrder()->getTotalAmountWithTax()) . ' ' . $this->currency, 0, 0, 'L');
	}
	
}