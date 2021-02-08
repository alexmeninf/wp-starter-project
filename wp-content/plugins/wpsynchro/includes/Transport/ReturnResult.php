<?php
namespace WPSynchro\Transport;

use WPSynchro\Transport\TransferAccessKey;

/**
 * Class to return data from REST service (wrapper for Transfer object when returning with data)
 *
 * @since 1.3.0
 */
class ReturnResult
{

    public $httpstatus = 200;
    public $transfer;

    public function init()
    {
        global $wpsynchro_container;

        $commonfunctions = $wpsynchro_container->get('class.CommonFunctions');
        $this->transfer = $wpsynchro_container->get('class.Transfer');
        $this->transfer->setShouldEncrypt(true);
        $this->transfer->setShouldDeflate(true);
        $this->transfer->setEncryptionKey(TransferAccessKey::getAccessKey());
    }

    public function setHTTPStatus($httpcode)
    {
        $this->httpstatus = $httpcode;
    }

    public function echoDataFromRestAndExit()
    {
        // Normal scenario (we use exit to prevent WP from returning some default extra chars to stream)
        http_response_code($this->httpstatus);
        header("Content-Type: " . $this->transfer->getContentType());
        echo $this->transfer->getDataString();
        exit();
    }

    public function getData()
    {
        return $this->transfer->getDataString();
    }

    public function getHeaders()
    {
        $headers = array(
            'Content-Type' => $this->transfer->getContentType(),
            'Content-Transfer-Encoding' => 'Binary',
        );
        return $headers;
    }

    public function setDataObject($object)
    {
        $this->transfer->setDataObject($object);
    }

    public function setTransferObject($object)
    {
        $this->transfer = $object;
    }
}
