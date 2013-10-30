<?php
/**
 * Keeps track of each download
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 10.25.2013
 * @package shop_downloadable
 */
class DownloadLog extends DataObject
{
	private static $db = array(
		'URL'       => 'Varchar(255)',      // logs url used (since file might be gone)
	);

	private static $has_one = array(
		'Order'     => 'Order',
		'TempFile'  => 'DownloadTempFile',  // this may or may not still exist, but is good to have in case it does
	);

	private static $many_many = array(
		'Files'     => 'File',
	);
}
