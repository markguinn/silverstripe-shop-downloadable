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
		if (!isset($this->owner->_hasDownloads)) {
			$items = $this->owner->Items();
			$this->hasDownloads = false;
			foreach ($items as $item) {
				$buyable = $item->Buyable();
				if ($buyable && $buyable->exists() && $buyable->hasExtension('Downloadable')) {
					if ($buyable->HasDownloads()) {
						$this->owner->_hasDownloads = true;
						break;
					}
				}
			}
		}

		return $this->owner->_hasDownloads;
	}


	/**
	 * @return bool
	 */
	public function DownloadsAvailable() {
		return $this->HasDownloads() && $this->owner->IsPaid();
	}


	/**
	 * @return ArrayList
	 */
	public function Downloads() {
		$downloads = new ArrayList();

		foreach ($this->owner->Items() as $item) {
			$buyable = $item->Buyable();
			if ($buyable && $buyable->exists() && $buyable->hasExtension('Downloadable') && $buyable->HasDownloads()) {
				foreach ($buyable->DownloadableFiles() as $file) {
					$downloads->push(new ArrayData(array(
						'Product'       => $buyable,
						'File'          => $file,
						'Order'         => $this->owner,
						'Filename'      => $file->Name,
						'PurchaseDate'  => $this->owner->Placed,
						'Size'          => $file->getAbsoluteSize(),
						'Link'          => DownloadLink::find_or_make($file->ID, $this->owner->ID),
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