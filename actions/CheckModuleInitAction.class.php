<?php
/**
 * order_CheckModuleInitAction
 * @package modules.order.actions
 */
class order_CheckModuleInitAction extends change_JSONAction
{
	/**
	 * @param change_Context $context
	 * @param change_Request $request
	 */
	public function _execute($context, $request)
	{
		$result = array();
		$ms = order_ModuleService::getInstance();
		$result['generateDefaultExpedition'] = $ms->isDefaultExpeditionGenerationEnabled();
		$result['useOrderPreparation'] = $ms->useOrderPreparationEnabled();
		
		return $this->sendJSON($result);
	}
}