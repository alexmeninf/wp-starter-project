<?php
namespace WPSynchro\Transport;

interface RemoteConnection
{

    public function init();

    public function setUrl($url);

    public function remotePOST();
    
    public function setDataObject($object);
    
    public function setSendDataAsJSON();
    
    public function setMaxRequestSize($maxsize);
    
    public function addFiledata(\WPSynchro\Transport\TransferFile $file);
}
