<?php
/**
 * Controls all downloads
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 10.25.2013
 * @package shop_downloadable
 */
class DownloadController extends Page_Controller
{
	private static $allowed_actions = array('zip', 'download', 'process');

	private static $url_handlers = array(
		'zip'           => 'zip',
		'process/$ID'   => 'process',
		'$Hash'         => 'download',
	);


	/**
	 * @param SS_HTTPRequest $req
	 * @return HTMLText
	 */
	public function zip(SS_HTTPRequest $req) {
		$orderID = (int)$req->postVar('OrderID');
		$hashes  = $req->postVar('Files');
		$files   = array();

		// check inputs - should we respond more intelligently if they just didn't check anything?
		if (empty($hashes)) $this->httpError(404);

		// grab a list of file objects
		foreach ($hashes as $hash) {
			$link = DownloadLink::get_by_hash($hash);
			if ($link && $link->exists()) {
				$file = $link->File();
				if ($file && $file->exists()) {
					$files[] = $file;
				}
			}
		}

		if (count($files) == 0) $this->httpError(404);

		// is there already an existing temp file for this combination?
		$existingFile = DownloadTempFile::get_by_files($files);
		if ($existingFile && $existingFile->exists()) {
			if ($existingFile->ProcessingState == DownloadTempFile::COMPLETE) {
				$this->addToLog($orderID, $files, $existingFile);
				return $this->sendTempFile($existingFile);
			} else {
				return $this->displayCrunchingPage($existingFile);
			}
		}

		// display a temporary loading page and start processing the zip
		Session::set('DownloadableProcessingOrderID', $orderID);
		return $this->initiateOfflineProcessing($files);
	}


	/**
	 * @param SS_HTTPRequest $req
	 * @return HTMLText
	 */
	public function process(SS_HTTPRequest $req) {
		$id = (int)$req->param('ID');
		if (!$id) $this->httpError(404);
		$file = DownloadTempFile::get()->byID($id);
		if (!$file || !$file->exists()) $this->httpError(404);

		// if the processing was already complete, just send them the file
		// this probably means they got overzealous and refreshed the browser
		switch ($file->ProcessingState) {
			case DownloadTempFile::COMPLETE:
				// This means the file was being processed but has finished
				// in the background and in the meantime we refreshed or something
				// so just send them the file.
				$this->addToLog(Session::get('DownloadableProcessingOrderID'), $file->SourceFiles(), $file);
				return $this->sendTempFile($file);
			break;

			case DownloadTempFile::ACTIVE:
				// This means the processing was started and is hopefully happening in the
				// background. We display the "crunching" page so the user knows what's
				// happening, and it will refresh every second or so to check on the status.
				if (!$file->isZombie()) {
					return $this->displayCrunchingPage($file);
				}

				// otherwise fall through and restart processing

			case DownloadTempFile::PENDING:
				ini_set('max_execution_time', 0);
				$file->process();
				$this->addToLog(Session::get('DownloadableProcessingOrderID'), $file->SourceFiles(), $file);
				return $this->sendTempFile($file);
		}
	}


	/**
	 * @param SS_HTTPRequest $req
	 * @return HTMLText
	 */
	public function download(SS_HTTPRequest $req) {
		// find the download link
		$hash = $req->param('Hash');
		if (empty($hash)) $this->httpError(400); // bad request
		$link = DownloadLink::get_by_hash($hash);
		if (!$link || !$link->exists()) $this->httpError(403); // access denied

		// check that the order exists and is valid
		$order = $link->Order();
		if (!$order || !$order->exists() || !$order->DownloadsAvailable()) $this->httpError(403);

		// check the the file still exists
		$file = $link->File();
		if (!$file || !$file->exists()) $this->httpError(404);

		// if the file is under the "small file" tipping point, just pass it through
		$smallSize = File::ini2bytes( Config::inst()->get('Downloadable', 'small_file_size') );
		$fileSize = $file->getAbsoluteSize();
		if ($fileSize < $smallSize) {
			$this->addToLog($order->ID, $file);
			$this->sendFile($file);
		} else {
			// this will get logged when crunching is complete
			Session::set('DownloadableProcessingOrderID', $order->ID);
			return $this->initiateOfflineProcessing(array($file));
		}
	}


	/**
	 * Sends the given file by whatever method is appropriate. Uses php's
	 * header() instead of silverstripe's HTTPResponse class to prevent other
	 * headers from being added and so we can just use readfile() instead of
	 * pulling the whole file into a string for setBody().
	 *
	 * @param File $file
	 */
	protected function sendFile(File $file) {
		// this is for optional compatibility with markguinn/silverstripe-cloudassets
		if ($file->hasExtension('CloudFileExtension') && $file->CloudStatus === 'Live') {
			header('Location: ' . $file->getAbsoluteURL());
			exit;
		}

		// this is the normal way to send the files
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="' . $file->Name . '"');

		if (Config::inst()->get('Downloadable', 'use_xsendfile')) {
			header('X-Sendfile: ' . $file->getURL());
		} else {
			header('Content-Length: ' . $file->getAbsoluteSize());
			readfile($file->getFullPath());
		}

		exit;
	}


	/**
	 * Sends the temp file in a safe way for large files.
	 * If X-sendfile is enabled it uses that, otherwise it uses a
	 * redirect. This is safe because the temp files all have random
	 * names and will be deleted within a few days.
	 *
	 * @param DownloadTempFile $file
	 * @return SS_HTTPResponse
	 */
	protected function sendTempFile(DownloadTempFile $file) {
		$file->LastUsedAt = date('Y-m-d H:i:s');
		$file->write();

		if (Config::inst()->get('Downloadable', 'use_xsendfile')) {
			$this->sendFile($file);
		} else {
			return $this->redirect($file->Link());
		}
	}


	/**
	 * @param array $files
	 * @return HTMLText
	 */
	protected function initiateOfflineProcessing(array $files) {
		// Create an empty DownloadTempFile
		$parent = Folder::find_or_make(Config::inst()->get('Downloadable', 'zip_folder'));
		$dl = new DownloadTempFile();
		$dl->setParentID($parent->ID);
		$dl->Title = sha1( uniqid() );
		$dl->Name = $dl->Title . '.' . (count($files)==1 ? $files[0]->getExtension() : 'zip');
		$dl->write();

		foreach ($files as $file) $dl->SourceFiles()->add($file);
		$dl->updateFileKey();
		$dl->write();

		// Display the "crunching" page so the user isn't left wondering what's going on
		return $this->displayCrunchingPage($dl);
	}


	/**
	 * @param DownloadTempFile $dl
	 * @return HTMLText
	 */
	protected function displayCrunchingPage(DownloadTempFile $dl) {
		$crunchingPage = Config::inst()->get('Downloadable', 'crunching_page');
		if ($crunchingPage) $crunchingPage = SiteTree::get_by_link($crunchingPage);

		if (!$crunchingPage || !$crunchingPage->exists()) {
			$crunchingPage = new Page();
			$crunchingPage->Title = _t('Downloadable.CRUNCHINGTITLE', 'Processing Your Download');
			$crunchingPage->Content = _t('Downloadable.CRUNCHINGBODY', '<p>Please wait while your download is prepared.</p>');
		}

		// Just in case
		$this->dataRecord = $crunchingPage;

		// Add a meta tag that will refresh with the request that actually does the processing
		// In the future this could be wrapped in a <noscript> and we could do some better ajax
		// work to make this more userfriendly (such as a progress bar for multiple files, etc)
		Requirements::insertHeadTags('<meta http-equiv="refresh" content="1; url=' . $dl->getProcessingLink() . '">');

		// And....render
		return $this->customise($crunchingPage)->renderWith(array('CrunchingPage','Page','Page'));
	}


	/**
	 * @param int|Order        $orderID
	 * @param array|File       $files
	 * @param DownloadTempFile $tempFile
	 */
	protected function addToLog($orderID, $files, DownloadTempFile $tempFile=null) {
		if (!is_array($files) && !$files instanceof SS_List) $files = array($files);
		if (is_object($orderID)) $orderID = $orderID->ID;
		$log = new DownloadLog;
		$log->URL = $this->getRequest()->getURL();
		$log->OrderID = $orderID;
		if ($tempFile) $log->TempFileID = $tempFile->ID;
		$log->write();
		foreach ($files as $file) $log->Files()->add($file);
		$log->write();
	}
}