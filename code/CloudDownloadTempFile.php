<?php
/**
 * This is for compatibility with the markguinn/silverstripe-cloudassets module.
 * It is not a required module and shouldn't give you any trouble if its not installed.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 01.29.2014
 * @package shop_downloadable
 */
class CloudDownloadTempFile extends DownloadTempFile
{
	public function Link() {
		$this->createLocalIfNeeded();
		return $this->CloudStatus == 'Live' ? $this->getCloudURL() : parent::Link();
	}

	public function RelativeLink() {
		$this->createLocalIfNeeded();
		return $this->CloudStatus == 'Live' ? $this->getCloudURL() : parent::RelativeLink();
	}

	public function getURL() {
		$this->createLocalIfNeeded();
		return $this->CloudStatus == 'Live' ? $this->getCloudURL() : parent::getURL();
	}

	public function getAbsoluteURL() {
		$this->createLocalIfNeeded();
		return $this->CloudStatus == 'Live' ? $this->getCloudURL() : parent::getAbsoluteURL();
	}

	public function getAbsoluteSize() {
		$this->createLocalIfNeeded();
		return $this->CloudStatus == 'Live' ? $this->CloudSize : parent::getAbsoluteSize();
	}

	public function exists() {
		$this->createLocalIfNeeded();
		return parent::exists();
	}

	/**
	 * Copies and/or zips the file(s) into the correct temporary folder.
	 * NOTE: there could possibly be some ways to avoid downloading and
	 * re-uploading the content for single files.
	 * @throws Exception
	 */
	public function process() {
		// Change to active
		$this->ProcessingState = self::ACTIVE;
		$this->ProcessingStartedAt = date('Y-m-d H:i:s');
		$this->write();

		// Do the stuff
		$files = $this->SourceFiles();
		if ($files->count() == 1) {
			$src = $files->first();
			$src->downloadFromCloud();
			$r = copy($src->getFullPath(), $this->getFullPath());
			if (!$r) throw new Exception("Unable to copy {$src->getFullPath()} to " . $this->getFullPath());
			$src->convertToPlaceholder();
		} else {
			$zip = new ZipArchive();
			$r = $zip->open($this->getFullPath(), ZipArchive::CREATE);
			if ($r !== true) throw new Exception("Unable to create zip file at " . $this->getFullPath());

			foreach ($files as $file) {
				$file->downloadFromCloud();
				$zip->addFile($file->getFullPath(), $file->Name);
			}

			$zip->close();

			// we have to wait until now to restore the files to placeholder
			foreach ($files as $file) {
				$file->convertToPlaceholder();
			}
		}

		// Change to complete
		$this->ProcessingState = self::COMPLETE;
		$this->write();
	}
}