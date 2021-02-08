<?php
namespace WPSynchro\Masterdata;

use WPSynchro\Installation;

/**
 * Retrives masterdata retrival
 * @since 1.6.0
 */
class MasterdataRetrieval
{
    public $installation = "";
    public $data_to_retrieve = [];
    // Request security (optional)
    public $token = null;
    public $encryption_key = null;
    // Data retrieved
    public $data = [];

    const REST_PATH = "/wp-json/wpsynchro/v1/masterdata/";

    public function __construct(Installation $installation)
    {
        $this->installation = $installation;
    }

    /**
     *  Set the data to retrieve
     *  @since 1.6.0
     */
    public function setDataToRetrieve($data_arr)
    {
        $this->data_to_retrieve = $data_arr;
    }

    /**
     *  Set token on request (optional)
     *  @since 1.6.0
     */
    public function setToken($token)
    {
        $this->token = $token;
    }

    /**
     *  Set encryption key on request (optional)
     *  @since 1.6.0
     */
    public function setEncryptionKey($key)
    {
        $this->encryption_key = $key;
    }

    /**
     *  Get masterdata
     *  @since 1.6.0
     */
    public function getMasterdata()
    {

        // Generate query string
        $querystring = "";
        foreach ($this->data_to_retrieve as $slug) {
            $querystring .= "&type[]=" . $slug;
        }
        $querystring = trim($querystring, "&");
 
        $url = untrailingslashit($this->installation->site_url) . self::REST_PATH . "?" . $querystring;

        // Get remote transfer object
        global $wpsynchro_container;
        $remotetransport = $wpsynchro_container->get('class.RemoteTransfer');
        $remotetransport->setInstallation($this->installation);
        $remotetransport->init();
        $remotetransport->setUrl($url);

        // Check for specific token and encryption key
        if (!is_null($this->token) && !is_null($this->encryption_key)) {
            $remotetransport->setToken($this->token);
            $remotetransport->setEncryptionKey($this->encryption_key);
        }

        // Execute request
        $transportresult = $remotetransport->remotePOST();

        // Handle result
        if ($transportresult->isSuccess()) {
            $this->data = $transportresult->getBody();
            return true;
        } else {     
            return false;
        }
    }
}
