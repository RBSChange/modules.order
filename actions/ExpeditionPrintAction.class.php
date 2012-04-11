<?php
/**
 * order_ExpeditionPrintAction
 * @package modules.order.actions
 */
class order_ExpeditionPrintAction extends f_action_BaseAction
{
	/**
	 * @return boolean
	 */
	public function isSecure()
	{
		return false;
	}
	
	/**
	 *
	 * @param $context Context       	
	 * @param $request Request       	
	 */
	public function _execute($context, $request)
	{
		$expeditionLineId = $this->getDocumentIdFromRequest($request);
		try 
		{
			$expeditionLine = order_persistentdocument_expeditionline::getInstanceById($expeditionLineId);
			
			if ($request->hasParameter('QRcode'))
			{
				return $this->generateQRcode($expeditionLine);
			}
			elseif ($request->hasParameter('Barcode'))
			{
				return $this->generateBarCode($expeditionLine);
			}
			else
			{
				return $this->generatePDF($expeditionLine);
			}
		} 
		catch (Exception $e)
		{
			Framework::fatal($e);
		}
		return View::NONE;
	}
	
	/**
	 * @param order_persistentdocument_expeditionline $expeditionLine
	 */	
	protected function generateQRcode($expeditionLine)
	{
		$expeditions = $expeditionLine->getExpeditionArrayInverse();
		if (count($expeditions) == 1)
		{
			$expedition = $expeditions[0];
			$address = $expedition->getAddress();
			if ($address instanceof  order_persistentdocument_shippingaddress)
			{
				$storeExtranet = store_StoreextranetService::getInstance()->getByStoreId($address->getTargetId());
				$page = $storeExtranet->getPacketPage();
				if ($page)
				{
					$params = array('storeParam' => array('packetNumber' => $expeditionLine->getPacketNumber()));
					$url = LinkHelper::getDocumentUrlForWebsite($page, $storeExtranet->getWebsite(), null,  $params);
					$path = f_util_FileUtils::buildWebeditPath('libs', 'qrcode', 'qrlib.php');
					include $path;
					QRcode::png($url, false, QR_ECLEVEL_L, 4, 1);
				}
			}
		}
		return View::NONE;
	}
		
	/**
	 * 
	 * @param order_persistentdocument_expeditionline $expeditionLine
	 */
	protected function generateBarCode($expeditionLine)
	{
		$path = f_util_FileUtils::buildWebeditPath('libs', 'barcode', 'php-barcode-2.0.1.php');	
		require_once($path);
		$width = 280;
		$barCodeHeight = 50;
		
		$ifw = imagefontwidth(3);
		$ifh = imagefontheight(3) - 1;
		$height = $barCodeHeight + $ifh + 2;
		
		$code = $expeditionLine->getPacketNumber();
		$im     = imagecreatetruecolor($width, $height);
		$black  = ImageColorAllocate($im,0x00,0x00,0x00);
		$white  = ImageColorAllocate($im,0xff,0xff,0xff);
		imagefilledrectangle($im, 0, 0, $width, $height, $white);
		Barcode::gd($im, $black, intval($width / 2), intval($barCodeHeight / 2), 0, "code128", $code, 1, $barCodeHeight);
		header('Content-type: image/png');		
		imagestring($im, 3, intval((($width)-($ifw * strlen($code)))/2)+1, $height - $ifh, $code, $black);	
		imagepng($im);
		imagedestroy($im);		
		return View::NONE;
	}
	
	/**
	 * @param order_persistentdocument_expeditionline $expeditionLine
	 */
	protected function generatePDF($expeditionLine)
	{
		$fpdf = new order_FPDFExpeditionGenerator($expeditionLine);
		$fpdf->generatePDF();
	}
}