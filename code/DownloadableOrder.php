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
    public function HasDownloads()
    {
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
     * Are downloads present and is the order in a state where downloads
     * would be available to the customer (i.e. paid for)?
     * @return bool
     */
    public function DownloadsAvailable()
    {
        // this gives another hook for application-specific logic (e.g. allowing partial payments)
        if ($this->owner->hasMethod('canDownload')) {
            return $this->owner->canDownload();
        } else {
            if ($this->owner->Status == 'AdminCancelled' || $this->owner->Status == 'MemberCancelled') {
                return false;
            }
            return $this->HasDownloads() && $this->owner->IsPaid();
        }
    }


    /**
     * @return ArrayList
     */
    public function getDownloads()
    {
        $downloads = new ArrayList();

        foreach ($this->owner->Items() as $item) {
            $buyable = $item->Buyable();
            if ($buyable && $buyable->exists() && $buyable->hasExtension('Downloadable') && $buyable->HasDownloads()) {
                foreach ($buyable->getDownloads() as $file) {
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
     * Returns the total of products that contain downloads
     * @return float
     */
    public function getDownloadsTotal()
    {
        $result = 0.0;

        if ($items = $this->owner->Items()) {
            foreach ($items as $item) {
                if ($buyable = $item->Buyable()) {
                    if ($buyable->hasExtension('Downloadable')) {
                        if ($buyable->HasDownloads()) {
                            $result += $item->Total();
                        }
                    }
                }
            }
        }

        return $result;
    }


    /**
     * @return string
     */
    public function DownloadZipLink()
    {
        return Config::inst()->get('Downloadable', 'download_link_base') . '/zip';
    }
}
