<?php

/**
 * If the queued jobs module is installed, this will be used for offline processing of Zip files.
 *
 * @author  Mark Guinn <mark@adaircreative.com>
 * @date    05.09.2016
 * @package shop_downloadable
 */
if (!interface_exists('QueuedJob')) {
    return;
}

class FilePrepQueuedJob extends AbstractQueuedJob implements QueuedJob
{
    /**
     * The QueuedJob queue to use when processing updates
     * @config
     * @var int
     */
    private static $reindex_queue = 2; // QueuedJob::QUEUED;


    /**
     * @param DownloadTempFile $object
     */
    public function __construct($object)
    {
        $this->setObject($object);
    }


    /**
     * Helper method
     */
    public function triggerProcessing()
    {
        singleton('QueuedJobService')->queueJob($this);
    }


    /**
     * @return string
     */
    public function getTitle()
    {
        /** @var DownloadTempFile $obj */
        $obj = $this->getObject();
        return "Prep File For Download: " . ($obj ? $obj->getFriendlyName() : '???');
    }


    /**
     * Reprocess any needed fields
     */
    public function process()
    {
        ini_set('memory_limit', '1G');
        
        /** @var DownloadTempFile $obj */
        $obj = $this->getObject();
        $obj->process();
        $this->isComplete = true;
    }
}
