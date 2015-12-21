<?php
/**
 * Associates a link hash to a given file and order. This could be
 * overkill, but it does keep the hash from being guessable and/or
 * taking a long time to look up.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 10.28.2013
 * @package shop_downloadable
 */
class DownloadLink extends DataObject
{
    private static $db = array(
        'Hash'      => 'Varchar(255)',
    );

    private static $has_one = array(
        'File'      => 'File',
        'Order'     => 'Order',
    );

    private static $indexes = array(
        'Hash'      => true,
    );


    /**
     * @param int|File  $file
     * @param int|Order $order
     * @return DownloadLink
     */
    public static function generate($file, $order)
    {
        $file   = is_object($file) ? $file->ID : $file;
        $order  = is_object($order) ? $order->ID : $order;

        $rec = new DownloadLink;
        $rec->Hash      = sha1(uniqid() . $file . $order);
        $rec->FileID    = $file;
        $rec->OrderID   = $order;
        $rec->write();
        return $rec;
    }


    /**
     * @param int|File  $file
     * @param int|Order $order
     * @return DownloadLink
     */
    public static function find_or_make($file, $order)
    {
        $rec = DownloadLink::get()->filter(array(
            'FileID'    => is_object($file) ? $file->ID : $file,
            'OrderID'   => is_object($order) ? $order->ID : $order,
        ))->first();

        return ($rec && $rec->exists())
            ? $rec
            : self::generate($file, $order);
    }


    /**
     * @param string $hash
     * @return DownloadLink
     */
    public static function get_by_hash($hash)
    {
        return self::get()->filter(array('Hash' => $hash))->first();
    }


    /**
     * @return string
     */
    public function getURL()
    {
        return Config::inst()->get('Downloadable', 'download_link_base') . '/' . $this->Hash;
    }


    /**
     * @return string
     */
    public function getAbsoluteURL()
    {
        return Director::absoluteURL($this->getURL());
    }


    /**
     * This is just here for guessability for template authors
     * @return string
     */
    public function Link()
    {
        return $this->getURL();
    }


    /**
     * This is just here for guessability for template authors
     * @return string
     */
    public function AbsoluteLink()
    {
        return $this->getAbsoluteURL();
    }
}
