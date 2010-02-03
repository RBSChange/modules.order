<?php
abstract class order_tests_AbstractBaseUnitTest extends order_tests_AbstractBaseTest
{
	/**
	 * @return void
	 */
	public function prepareTestCase()
	{
		$this->resetDatabase();
	}
	
	/**
	 * @param Array $documents
	 */
	protected function echoDocumentIds($documents)
	{
		$ids = array();
		foreach ($documents as $document)
		{
			$ids[] = $document->getId();
		}
		echo('ids: ' . implode(', ', $ids) . "\n");
	}
}