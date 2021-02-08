<?php
namespace WPSynchro\Transport;

/**
 * Class for handling transport of data between sites in WP Synchro for Unit/Integration testing
 * @since 1.3.0
 */
class RemoteTestTransport implements RemoteConnection
{

    public $shallreturn = null;

    public function remotePOST()
    {
        return $this->shallreturn;
    }

    public function init()
    {
        
    }

    public function setUrl($url)
    {
        
    }

    public function setDataObject($object)
    {
        
    }

    public function setSendDataAsJSON()
    {
        
    }

    public function setMaxRequestSize($maxsize)
    {
        
    }

    public function addFiledata(\WPSynchro\Transport\TransferFile $file)
    {
        
    }
}
