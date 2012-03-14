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
		
		// OK, ... I will code a dedicated getInfo method
		$data = order_OrderService::getInstance()->getInfo($order);
		
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
	private $billTitle;

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
	 * @var string
	 */
	private $customerReferenceTxt;
	
	/**
	 * @var customer_persistentdocument_address
	 */
	private $merchantAddress;

	/**
	 * @var string
	 */
	private $footerText;

	/**
	 * @var integer
	 */
	private $width;
	
	/**
	 * @var integer
	 */
	private $height;
	
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
			
			//get the merchant address from XML Configuration
			$configXML = f_util_DOMUtils::fromPath(f_util_FileUtils::buildWebeditPath('modules', 'order', 'templates', 'defaultFPDFBillInfos.xml'));
				
			$merchantAddressConfiguration = $configXML->getElementsByTagName('merchantAddress')->item(0)->attributes;
			$merchantAddress = customer_persistentdocument_address::getNewInstance();
			$merchantAddress->setFirstname($merchantAddressConfiguration->getNamedItem('firstname')->nodeValue);
			$merchantAddress->setAddressLine1($merchantAddressConfiguration->getNamedItem('addressLine1')->nodeValue);
			$merchantAddress->setCity($merchantAddressConfiguration->getNamedItem('city')->nodeValue);
			$merchantAddress->setZipCode($merchantAddressConfiguration->getNamedItem('zipcode')->nodeValue);
			$merchantAddress->setPhone($merchantAddressConfiguration->getNamedItem('phone')->nodeValue);
			$merchantAddress->setFax($merchantAddressConfiguration->getNamedItem('fax')->nodeValue);
				
			$this->merchantAddress = $merchantAddress;
			
			$logoPath = $configXML->getElementsByTagName('logo')->item(0)->attributes->getNamedItem('path')->nodeValue;
			$this->logoPath = $logoPath ? $logoPath : f_util_FileUtils::buildWebeditPath('modules','order','webapp','media','frontoffice','order','defaultPDFLogo.png');
			
			$this->billTitle = $configXML->getElementsByTagName('title')->item(0)->textContent;
			$this->footerText = $configXML->getElementsByTagName('footer')->item(0)->textContent;
			
			$designConfiguration = $configXML->getElementsByTagName('design')->item(0)->attributes;
			$this->orderLineHeight = $designConfiguration->getNamedItem('orderlineHeight')->nodeValue;
			$this->orderLineHeaderFillColorRGB = explode(',', $designConfiguration->getNamedItem('orderLineHeaderFillColorRGB')->nodeValue);
			$this->orderLineFillColorRGB = explode(',', $designConfiguration->getNamedItem('orderLineFillColorRGB')->nodeValue);
			
			$textConfiguration = $configXML->getElementsByTagName('billTexts')->item(0)->attributes;
			$this->orderLineTxt = $textConfiguration->getNamedItem('orderLineTxt')->nodeValue;
			$this->customerReferenceTxt = $textConfiguration->getNamedItem('customerReferenceTxt')->nodeValue;
			
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
		$this->Image($this->logoPath, 10, 10, 100);
		// Police Arial bold 15
		$this->SetFont('Times', 'B', 15);

		$title = utf8_decode($this->billTitle);
		$titleSize = $this->GetStringWidth($title) + 2;
		// Right shift
		$this->Cell($this->width - $titleSize);
		$this->Cell($titleSize, 10, $title, 1, 0, 'C');
		$this->Ln(12);
		$billNb = 'Facture N° ' . $this->bill->getLabel();
		$date = 'Date : ' . date_Formatter::format($this->bill->getCreationdate());
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
		$this->Cell(0, 10, $this->footerText, 0, 0, 'C');
	}

	public function generatePDF()
	{
		$currency = $this->bill->getCurrency() == 'EUR' ? chr(128) : utf8_decode($this->bill->getCurrency());

		$this->AliasNbPages();
		$this->AddPage();
		$this->SetFont('Times', '', 12);

		//Address
		$w = 50;
		$y = $this->GetY();
		$this->generateAddressCell($this->bill->getAddress(), $w);
		$this->SetXY(($this->width + self::LEFT_MARGIN) - $w, $y);
		$this->generateAddressCell($this->merchantAddress, $w);

		$this->Cell(70, 10, utf8_decode($this->customerReferenceTxt . $this->bill->getOrder()->getCustomer()->getCodeReference()));

		$this->Ln(15);

		$orderLines = $this->bill->getOrder()->getLineArray();

		$this->MultiCell($this->width, 10, $this->orderLineTxt, 1, 'L');


		//Order line headers
		//Colors
		$this->SetFillColor(
			$this->orderLineHeaderFillColorRGB[0], 
			$this->orderLineHeaderFillColorRGB[1], 
			$this->orderLineHeaderFillColorRGB[2]
		);

		$articleLength = 72;
		$orderLineHeaderTxtAndWidth = array(
			'Quantité' => 15,
			'Article' => $articleLength,
			'Référence' => 43,
			'Prix unitaire (H.T.)' => 30,
			'Prix (H.T.)' => 30
		);
		//Sum of width must be equal to $this->width
		if (array_sum($orderLineHeaderTxtAndWidth) !== $this->width)
		{
			Framework::error(__CLASS__ . ': sum of order lines columns is not equal to the fixed width. Your orderlines columns doesn\'t have the good size');
		}

		foreach ($orderLineHeaderTxtAndWidth as $text => $width)
		{
			$this->Cell($width, 8, utf8_decode($text), 1, 0, 'C', true);
		}
		$this->ln();
		//Order lines
		$this->SetFontSize(8);
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
				$this->Ln(10);
				$this->SetFillColor(
					$this->orderLineHeaderFillColorRGB[0], 
					$this->orderLineHeaderFillColorRGB[1], 
					$this->orderLineHeaderFillColorRGB[2]
				);
				foreach ($orderLineHeaderTxtAndWidth as $text => $width)
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
			/* @var $orderLine order_persistentdocument_orderline */
			$articleText = utf8_decode($orderLine->getProduct()->getLabel());
			$articleMaxLength = 70;
			//reduce the width of the article name, if there is too large
			while (($this->GetStringWidth($articleText) + 1) > $articleLength)
			{
				$articleText = f_util_StringUtils::shortenString($articleText, $articleMaxLength);
				$articleMaxLength -= 5;
			} 
			
			$this->Cell($orderLineHeaderTxtAndWidth['Quantité'], $this->orderLineHeight, utf8_decode($orderLine->getQuantity()), 1, 0, 'C', $fill);
			$this->Cell($orderLineHeaderTxtAndWidth['Article'], $this->orderLineHeight, $articleText, 1, 0, 'L', $fill);
			$this->Cell($orderLineHeaderTxtAndWidth['Référence'], $this->orderLineHeight, utf8_decode($orderLine->getProduct()->getCodeReference()), 1, 0, 'L', $fill);
			$this->Cell($orderLineHeaderTxtAndWidth['Prix unitaire (H.T.)'], $this->orderLineHeight, utf8_decode($orderLine->getUnitPriceWithoutTax() . ' ') . $currency, 1, 0, 'R', $fill);
			$this->Cell($orderLineHeaderTxtAndWidth['Prix (H.T.)'], $this->orderLineHeight, utf8_decode($orderLine->getAmountWithoutTax() . ' ') . $currency, 1, 0, 'R', $fill);

			$this->Ln();
			$fill = !$fill;
		}
		
		$this->SetFontSize(8);
		$totalSummaryLabel = 'Total HT : ' . PHP_EOL;
		$totalSummaryValue = $this->bill->getOrder()->getTotalAmountWithoutTax() . ' ' . utf8_encode($currency) . PHP_EOL;
		foreach ($this->bill->getOrder()->getDiscountDataArrayForDisplay() as $discount)
		{
			$totalSummaryLabel .= $discount['label'] . ' HT : ' . PHP_EOL;
			$totalSummaryValue .= $discount['valueWithTax'] . ' ' . utf8_encode($currency) . PHP_EOL;
			$totalSummaryLabel .= $discount['label'] . ' TTC : ' . PHP_EOL;
			$totalSummaryValue .= $discount['valueWithoutTax'] . ' ' . utf8_encode($currency) . PHP_EOL;
		}
		$totalSummaryLabel .= 'Frais d\'envoi HT : ' . PHP_EOL;
		$totalSummaryValue .=  $this->bill->getOrder()->getShippingFeesWithoutTax() . ' ' . utf8_encode($currency) . PHP_EOL;
		$totalSummaryLabel .= 'Frais d\'envoi TTC : ' . PHP_EOL;
		$totalSummaryValue .=  $this->bill->getOrder()->getShippingFeesWithTax() . ' ' . utf8_encode($currency) . PHP_EOL;

		foreach ($this->bill->getOrder()->getTaxRates() as $rateFormated => $value)
		{
			$totalSummaryLabel .= 'TVA ' . $rateFormated . ' : ' . PHP_EOL;
			$totalSummaryValue .= catalog_PriceFormatter::getInstance()->round($value) . ' ' . utf8_encode($currency) . PHP_EOL;
		}

		$totalSummaryLabel .= 'Mode de paiement : ' . PHP_EOL;
		$totalSummaryValue .= $this->bill->getOrder()->getPaymentConnectorLabel() . PHP_EOL;
		
		//Summary design
		$w = 30;
		$h = 4;
		$numberOfLine = 7;
		
		//if the block of summary is too big, add a page and design on it
		Framework::fatal(__METHOD__ . ' ' . var_export(($this->GetY() + ($numberOfLine * $h)), true));
		if (($this->GetY() + ($numberOfLine * $h)) > $this->height)
		{
			Framework::fatal(__METHOD__ . ' ' . $this->height, true);
			$this->AddPage();
		}
		
		$y = $this->GetY();
		$this->MultiCell(($this->width - $w), $h, utf8_decode($totalSummaryLabel), 0, 'R');
		$this->SetXY(($this->width + self::LEFT_MARGIN) - $w, $y);
		$this->MultiCell($w, $h, utf8_decode($totalSummaryValue), 0, 'L');

		$this->SetFont('', 'B', 10);
		$this->Cell(($this->width - $w), $h, utf8_decode('Total TTC : '), 0, 0, 'R');
		$this->Cell($w, $h, utf8_decode($this->bill->getOrder()->getTotalAmountWithTax()) . ' ' . $currency, 0, 0, 'L');

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
	
}