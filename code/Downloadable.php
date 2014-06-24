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


	private static $db = array(
		'IncludeParentDownloads'    => 'Boolean',
		'IncludeChildDownloads'     => 'Boolean',
	);

	private static $defaults = array(
		'IncludeParentDownloads'    => true,
		'IncludeChildDownloads'     => true,
	);

	private static $many_many = array(
		'DownloadableFiles'         => 'File',
	);


	/**
	 * @param FieldList $fields
	 */
	public function updateCMSFields(FieldList $fields) {
		$tabName    = Config::inst()->get('Downloadable', 'tab_name');
		$tabFields  = array();

		$upload     = new UploadField('DownloadableFiles', '');
		$upload->setFolderName( Config::inst()->get('Downloadable', 'source_folder') );
		$tabFields[] = $upload;

		// For certain types of products, it makes sense to include downloads
		// from parent (ProductVariation) or child products (GroupedProduct)
		// NOTE: there could be better ways to do this that don't involve checking
		// for specific classes. The advantage here is that the fields show up
		// even if the product has not yet been saved or doesn't yet have a
		// parent or child products.
		$p = $this->owner instanceof ProductVariation ? $this->owner->Product() : $this->owner->Parent();
		if ($p && $p->exists() && $p->hasExtension('Downloadable')) {
			$tabFields[] = new CheckboxField('IncludeParentDownloads', 'Include downloads from parent product in purchase');
		} elseif (class_exists('GroupedProduct') && $this->owner instanceof GroupedProduct) {
			$tabFields[] = new CheckboxField('IncludeChildDownloads', 'Include downloads from child products in purchase');
		}

		// this will just add unnecessary queries slowing down the page load
		//$tabFields[] = new LiteralField('DownloadCount', '<p>Total Downloads: <strong>' . $this->owner->getDownloads()->count() . '</strong></p>');

		// Product variations don't have tabs, so we need to be able
		// to handle either case.
		if ($fields->first() instanceof TabSet) {
			$fields->addFieldsToTab("Root.$tabName", $tabFields);
		} else {
			$fields->push(new HeaderField('DownloadsHeader', $tabName));
			foreach ($tabFields as $f) $fields->push($f);
		}
	}


	/**
	 * @return bool
	 */
	public function HasDownloads() {
		return $this->owner->getDownloads()->count() > 0;
	}


	/**
	 * @param array $blacklist [optional] - list of id's to blacklist (only used internally for avoiding endless loops)
	 * @return SS_List
	 */
	public function getDownloads($blacklist=array()) {
		$blacklist[$this->owner->ID] = $this->owner->ID;
		if (!isset($this->_downloads)) {
			if ($this->owner->IncludeParentDownloads || $this->owner->IncludeChildDownloads) {
				$files = new ArrayList();
				$files->merge( $this->owner->DownloadableFiles() );

				if ($this->owner->IncludeParentDownloads) {
                    if ($this->owner instanceof ProductVariation) {
                        $p = $this->owner->Product();
                    } elseif ($this->owner->hasMethod('Parent')) {
                        $p = $this->owner->Parent();
                    } else {
                        $p = null;
                    }

					if ($p && $p->exists() && $p->hasExtension('Downloadable') && !isset($blacklist[$p->ID])) {
						$files->merge( $p->getDownloads($blacklist) );
					}
				}

				if ($this->owner->IncludeChildDownloads) {
					if ($this->owner->hasMethod('ChildProducts')) {
                        $kids = $this->owner->ChildProducts();
                    } elseif ($this->owner->hasMethod('Children')) {
                        $kids = $this->owner->Children();
                    } else {
                        $kids = array();
                    }

					foreach ($kids as $kid) {
						if ($kid->hasExtension('Downloadable') && !isset($blacklist[$kid->ID])) {
							$files->merge( $kid->getDownloads($blacklist) );
						}
					}
				}

				$this->_downloads = $files;
			} else {
				$this->_downloads = $this->owner->DownloadableFiles();
			}
		}

		return $this->_downloads;
	}
	protected $_downloads;

}
