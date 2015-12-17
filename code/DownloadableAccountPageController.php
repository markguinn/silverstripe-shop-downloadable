<?php
/**
 * Adds an action to the accounts page to display all downloads ever.
 * This is automatically added to AccountPage_Controller via yml.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 10.31.2013
 * @package shop_downloadable
 */
class DownloadableAccountPageController extends Extension
{
    private static $allowed_actions = array('downloads');

    private static $sort_options = array(
        'PurchaseDate'  => 'Date Purchased',
        'Filename'      => 'File Name',
        'Size'          => 'File Size',
    );

    /**
     * This may need to be optimised. We'll just have to see how it performs.
     *
     * @param SS_HTTPRequest $req
     * @return array
     */
    public function downloads(SS_HTTPRequest $req)
    {
        $downloads = new ArrayList();
        $member = Member::currentUser();
        if (!$member || !$member->exists()) {
            $this->httpError(401);
        }

        // create a dropdown for sorting
        $sortOptions = Config::inst()->get('DownloadableAccountPageController', 'sort_options');
        if ($sortOptions) {
            $sort = $req->requestVar('sort');

            if (empty($sort)) {
                reset($sortOptions);
                $sort = key($sortOptions);
            }

            $sortControl = new DropdownField('download-sort', 'Sort By:', $sortOptions, $sort);
        } else {
            $sort = 'PurchaseDate';
            $sortControl = '';
        }

        // create a list of downloads
        $orders = $member->getPastOrders();
        if (!empty($orders)) {
            foreach ($orders as $order) {
                if ($order->DownloadsAvailable()) {
                    $downloads->merge($order->getDownloads());
                }
            }
        }

        Requirements::javascript(SHOP_DOWNLOADABLE_FOLDER . '/javascript/AccountPage_downloads.js');

        return array(
            'Title'         => 'Digital Purchases',
            'Content'       => '',
            'SortControl'   => $sortControl,
            'HasDownloads'  => $downloads->count() > 0,
            'Downloads'     => $downloads->sort($sort),
        );
    }

    
    /**
     * @return string
     */
    public function DownloadZipLink()
    {
        return Config::inst()->get('Downloadable', 'download_link_base') . '/zip';
    }
}
