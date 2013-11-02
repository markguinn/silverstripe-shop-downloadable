<?php
/**
 * This is the main extension for products with downloadable files.
 * It should be attached to any product or variation types that
 * can have such files.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 10.25.2013
 * @package shop_downloadable
 */
class Downloadable extends DataExtension
{
	/** @var string - name of the tab for files in the CMS */
	private static $tab_name            = 'Downloads';

	/** @var string - where are these files uploaded by default in the cms */
	private static $source_folder       = 'product-files';

	/** @var string - location of temporary zip files and large file copies */
	private static $zip_folder          = 'temp-order-files';

	/** @var string - e.g. http://example.com/downloads/filekey */
	private static $download_link_base  = 'downloads';

	/** @var string - anything below this will be passed on directly via readfile or x-sendfile with no temp file created */
	private static $small_file_size     = '100M';

	/** @var bool - use the X-Sendfile header instead of readfile, must have mod_xsendfile (or equivalent) installed */
	private static $use_xsendfile       = false;

	/** @var string - url segment/path of a page to display instead of the default while copying/zipping files */
	private static $crunching_page      = '';

	/** @var int - how many minutes before crunching is restarted on a zip file */
	private static $crunching_zombie_window = 5;

	/** @var int - how many hours before the temp files are ok to delete (still depends on cron job) */
	private static $delete_temp_files_after = 48;


	private static $many_many = array(
		'DownloadableFiles' => 'File',
	);


	/**
	 * @param FieldList $fields
	 */
	public function updateCMSFields(FieldList $fields) {
		$tab = Config::inst()->get('Downloadable', 'tab_name');
		$upload = UploadField::create('DownloadableFiles', '')
			->setFolderName( Config::inst()->get('Downloadable', 'source_folder') );
		$fields->addFieldToTab("Root.$tab", $upload);
	}


	/**
	 * TODO: this should take into account products and variations
	 * @return bool
	 */
	public function HasDownloads() {
		return $this->owner->DownloadableFiles()->count() > 0;
	}

}
