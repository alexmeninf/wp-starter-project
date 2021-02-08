<?php

namespace WPSynchro\Initiate;

use WPSynchro\Logger\Logger;
use WPSynchro\Installation;

/**
 * Retrieves initiate token from url
 * @since 1.6.0
 */
class InitiateTokenRetrieval
{
    public $installation;
    // Data retrieved
    public $service_result;
    public $token = '';
    public $errors_from_remote = [];
    // Logger - if needed
    public $logger = null;

    const REST_PATH = '/wp-json/wpsynchro/v1/initiate/';

    /**
     *  Constructor
     */
    public function __construct(Logger $logger, Installation $installation)
    {
        $this->logger = $logger;
        $this->installation = $installation;
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
     *  Get initiate token
     *  @since 1.6.0
     */
    public function getInitiateToken()
    {
        global $wpsynchro_container;

        // Get url
        $url = untrailingslashit($this->installation->site_url) . self::REST_PATH . '?type=' . $this->installation->type;

        // Setup job
        $job = $wpsynchro_container->get('class.Job');

        // Get remote transfer object
        $remotetransport = $wpsynchro_container->get('class.RemoteTransfer');
        $remotetransport->setInstallation($this->installation);
        $remotetransport->setJob($job);
        $remotetransport->init();
        $remotetransport->setUrl($url);
        $remotetransport->setEncryptionKey($this->installation->access_key);
        $remotetransport->setNoRetries();

        $this->service_result = $remotetransport->remotePOST();
        $body = $this->service_result->getBody();

        $this->errors_from_remote = $job->errors;

        if ($this->service_result->isSuccess()) {
            if (isset($body->token)) {
                $this->logger->log('DEBUG', 'Got initiate token: ' . $body->token);
                $this->token = $body->token;
                return true;
            } elseif (count($job->errors) > 0) {
                $this->logger->log('CRITICAL', 'Failed initializing - Got error response from remote initiate service -  Response: ', $this->errors_from_remote);
            } else {
                $this->errors_from_remote[] = __("Could not initate with site: " . $this->installation->site_url . " - If it is the remote site, this is normally caused by using the wrong access key to this site.","wpsynchro");
                $this->logger->log('CRITICAL', 'Failed initializing - Could not fetch a initiation token from remote -  Response body: ', $body);
            }
        } else {
            if (count($job->errors) > 0) {
                $this->logger->log('CRITICAL', 'Failed initializing - Got error response from remote initiate service -  Response: ', $this->errors_from_remote);
            } else {
                $this->logger->log('CRITICAL', 'Failed during initialization, which means we can not continue the synchronization.');
            }
        }
        return false;
    }

    /**
     *  Get errors
     */
    public function getErrors()
    {
        return $this->service_result->getErrors();
    }

    /**
     *  Get warnings
     */
    public function getWarnings()
    {
        return $this->service_result->getWarnings();
    }
}
