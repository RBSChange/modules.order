<?php
/**
 * order_patch_0310
 * @package modules.order
 */
class order_patch_0310 extends patch_BasePatch
{
 
	/**
	 * Entry point of the patch execution.
	 */
	public function execute()
	{
		$stmt = $this->executeSQLSelect("select document_id, couponid, couponvaluewithtax, ordernumber, globalproperties FROM m_order_doc_order WHERE couponvaluewithtax IS NOT NULL");
		$rows = $stmt->fetchAll();
		$pdo = $this->getPersistentProvider()->getDriver();
		foreach ($rows as $row)
		{
			$id = intval($row['document_id']);
			$globalProperties = unserialize($row['globalproperties']);
			if (isset($globalProperties['__coupon']) && is_array($globalProperties['__coupon']))
			{
				$valueWithTax = 0;
				$valueWithoutTax = 0;
				
				if (isset($globalProperties['__coupon']['valueWithTax']))
				{
					$valueWithTax = doubleval($globalProperties['__coupon']['valueWithTax']);
					unset($globalProperties['__coupon']['valueWithTax']);
				}
				
				if (isset($globalProperties['__coupon']['valueWithoutTax']))
				{
					$valueWithoutTax = doubleval($globalProperties['__coupon']['valueWithoutTax']);
					unset($globalProperties['__coupon']['valueWithoutTax']);
				}

				if ($valueWithTax > 0)
				{
					$this->log("Add reduction " . $globalProperties['__coupon']['code'] . " (" . sprintf($globalProperties['priceFormat'], $valueWithTax) . ") on order " . $row['ordernumber'] . " / " . $id);	
					$discountData = array('id' => 0, 
									'label' => $globalProperties['__coupon']['code'],
									'valueWithTax' => $valueWithTax,
									'valueWithoutTax' => $valueWithoutTax);
					if (is_array($globalProperties['__discount']))
					{
						$globalProperties['__discount'][] = $discountData;
					}
					else
					{
						$globalProperties['__discount'] = array($discountData);
					}
					$sql = "UPDATE m_order_doc_order SET couponvaluewithtax = null, globalproperties=" .$pdo->quote(serialize($globalProperties))  . " WHERE document_id = " .$id;
					$this->executeSQLQuery($sql);
				}
			}
		}	
	}

	/**
	 * @return String
	 */
	protected final function getModuleName()
	{
		return 'order';
	}

	/**
	 * @return String
	 */
	protected final function getNumber()
	{
		return '0310';
	}
}