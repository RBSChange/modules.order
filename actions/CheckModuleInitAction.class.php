<?php
/**
 * order_CheckModuleInitAction
 * @package modules.order.actions
 */
class order_CheckModuleInitAction extends f_action_BaseJSONAction
{
	/**
	 * @param Context $context
	 * @param Request $request
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