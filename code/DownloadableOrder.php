<?php
/**
 * Extension to the order class to facilitate downloads.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 10.25.2013
 * @package show_downloadable
 */
class DownloadableOrder extends DataExtension
{
	/**
	 * @return bool
	 */
	public function HasDownloads() {
		// TODO
		return true;
	}


	/**
	 * @return bool
	 */
	public function DownloadsAvailable() {
		// TODO
		return true; //$this->HasDownloads() && $this->owner->IsPaid();
	}


	/**
	 * @return ArrayList
	 */
	public function Downloads() {
		$downloads = new ArrayList();

		foreach ($this->owner->Items() as $item) {
			$buyable = $item->Buyable();
			if ($buyable->hasExtension('Downloadable') && $buyable->HasDownloads()) {
				foreach ($buyable->DownloadableFiles() as $file) {
					$downloads->push(new ArrayData(array(
						'Product'   => $buyable,
						'File'      => $file,
						'Order'     => $this->owner,
						'Link'      => DownloadLink::find_or_make($file->ID, $this->owner->ID),
					)));
				}
			}
		}

		return $downloads;
	}


	/**
	 * @return string
	 */
	public function DownloadZipLink() {
		return Config::inst()->get('Downloadable', 'download_link_base') . '/zip';
	}
}