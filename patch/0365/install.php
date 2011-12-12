<?php
/**
 * order_patch_0365
 * @package modules.order
 */
class order_patch_0365 extends patch_BasePatch
{
	/**
	 * Entry point of the patch execution.
	 */
	public function execute()
	{
		$this->executeSQLQuery("UPDATE f_document SET document_model = 'modules_order/waitingresponseorderfolder' WHERE document_model = 'modules_featurepacka/waitingresponseorderfolder';");
		$this->executeSQLQuery("UPDATE m_generic_doc_folder SET document_model = 'modules_order/waitingresponseorderfolder', document_label = 'waitingresponseorderfolder' WHERE document_model = 'modules_featurepacka/waitingresponseorderfolder';");
		$this->executeSQLQuery("UPDATE f_relation SET document_model_id1 = 'modules_order/waitingresponseorderfolder' WHERE document_model_id1 = 'modules_featurepacka/waitingresponseorderfolder';");
		$this->executeSQLQuery("UPDATE f_relation SET document_model_id2 = 'modules_order/waitingresponseorderfolder' WHERE document_model_id2 = 'modules_featurepacka/waitingresponseorderfolder';");
		$this->executeSQLQuery("TRUNCATE f_cache;");
		$this->executeLocalXmlScript("update.xml");
	}
}