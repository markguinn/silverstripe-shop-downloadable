<?php
/**
 * Saves the location of a temporary file used for downloads.
 * Could be a zip file or a single (probably larger) file.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 10.28.2013
 * @package shopw_downloadable
 */
class DownloadTempFile extends File
{
    const PENDING   = "Pending";
    const ACTIVE    = "Active";
    const COMPLETE  = "Complete";

    private static $db = array(
        'ProcessingStartedAt'   => 'Datetime',
        'ProcessingState'       => "Enum('Pending,Active,Complete','Pending')",
        'LastUsedAt'            => 'Datetime',
        'FileKey'               => 'Text',
    );

    private static $many_many = array(
        'SourceFiles'           => 'File',
    );


    /**
     * Checks to see if this tempfile contains the same files as
     * the list given, in which case it could be re-used.
     *
     * @param array $files
     * @return bool
     */
    public function check(array $files)
    {
        return false;
    }


    /**
     * Updates the "FileKey" that we use to check for existing caches with the same files.
     */
    public function updateFileKey()
    {
        $files = $this->SourceFiles()->sort('ID')->column('ID');
        $this->FileKey = implode(',', $files);
    }


    /**
     * @param array $files
     * @return DownloadTempFile
     */
    public static function get_by_files(array $files)
    {
        $ids = array();
        foreach ($files as $file) {
            if (is_numeric($file)) {
                $ids[] = $file;
            } else {
                $ids[] = $file->ID;
            }
        }
        sort($ids);
        $key = implode(',', $ids);
        return self::get()->filter('FileKey', $key)->first();
    }


    /**
     * @return string
     */
    public function getProcessingLink()
    {
        return Director::absoluteURL(Config::inst()->get('Downloadable', 'download_link_base') . '/process/' . $this->ID);
    }


    /**
     * Copies and/or zips the file(s) into the correct temporary folder.
     * TODO: investigate using this to stream directly: https://github.com/Grandt/PHPZip
     * or if ZipArchive is not installed.
     * @throws Exception
     */
    public function process()
    {
        // Change to active
        $this->ProcessingState = self::ACTIVE;
        $this->ProcessingStartedAt = date('Y-m-d H:i:s');
        $this->write();

        // Do the stuff
        $files = $this->SourceFiles();
        if ($files->count() == 1) {
            $src = $files->first()->getFullPath();
            $r = copy($src, $this->getFullPath());
            if (!$r) {
                throw new Exception("Unable to copy $src to " . $this->getFullPath());
            }
        } else {
            $zip = new ZipArchive();
            $r = $zip->open($this->getFullPath(), ZipArchive::CREATE);
            if ($r !== true) {
                throw new Exception("Unable to create zip file at " . $this->getFullPath());
            }

            foreach ($files as $file) {
                $zip->addFile($file->getFullPath(), $file->Name);
            }

            $zip->close();
        }

        // Change to complete
        $this->ProcessingState = self::COMPLETE;
        $this->write();
    }


    /**
     * Checks if the state is active and the start time was an unreasonable amount of time ago
     * @return bool
     */
    public function isZombie()
    {
        if ($this->ProcessingState != self::ACTIVE) {
            return false;
        }
        $ts = strtotime($this->ProcessingStartedAt);
        $window = Config::inst()->get('Downloadable', 'crunching_zombie_window') * 60;
        return (time() > $ts + $window);
    }


    /**
     * Is this file old enough to be deleted?
     * @return bool
     */
    public function isOutdated()
    {
        $ts = strtotime($this->LastUsedAt ? $this->LastUsedAt : $this->Created);
        $window = Config::inst()->get('Downloadable', 'delete_temp_files_after') * 60 * 60;
        return (time() > $ts + $window);
    }


    /**
     * Returns a more user-friendly filename for use when forcing a download.
     * For single files it uses the original file's name.
     * For zip files it uses 'order<NUM>-<COUNT>files-YYYY-mm-dd'
     * @return string
     */
    public function getFriendlyName()
    {
        $files = $this->SourceFiles();
        if ($files->count() == 1) {
            return $files->first()->Name;
        } else {
            $name = array();

            $links = DownloadLink::get()->filter('FileID', $this->ID);
            if ($links->count() == 1 && $links->first()->OrderID > 0) {
                $name[] = 'order' . $links->first()->Order()->Reference;
            }

            $name[] = $files->count() . 'files';
            $name[] = date('Y-m-d');
            $name[] = date('H:i:s');

            return implode('_', $name) . '.zip';
        }
    }
}
