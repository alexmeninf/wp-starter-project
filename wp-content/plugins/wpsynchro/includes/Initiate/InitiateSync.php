<?php
namespace WPSynchro\Initiate;

use WPSynchro\Transport\TransferToken;
use WPSynchro\Transport\TransferAccessKey;
use WPSynchro\Installation;

/**
 * Class for handling the initiate of the sync
 *
 * @since 1.0.0
 */
class InitiateSync
{

    // Base data
    public $installation = null;
    public $job = null;
    public $logger = null;
    public $timer = null;

    /**
     *  Constructor
     */
    public function __construct()
    {
    }

    /**
     *  Initiate sync
     *  @since 1.0.0
     */
    public function initiateSynchronization(&$installation, &$job)
    {
        $this->installation = $installation;
        $this->job = $job;

        global $wpsynchro_container;

        // Start timer        
        $this->timer = $wpsynchro_container->get("class.SyncTimerList");
        $initiate_timer = $this->timer->startTimer("initiate", "overall", "timer");

        // Init logging
        $this->logger = $wpsynchro_container->get("class.Logger");
        $common = $wpsynchro_container->get("class.CommonFunctions");

        $this->logger->log("INFO", "Initating with remote and local host with remaining time:" . $this->timer->getRemainingSyncTime());

        // Start synchronization in metadatalog
        $metadatalog = $wpsynchro_container->get('class.SyncMetadataLog');
        $metadatalog->startSynchronization($this->job->id, $this->installation->id, $this->installation->getOverviewDescription());

        // Start by getting local transfer token    
        $local_rest = trailingslashit(get_rest_url()); 
        $inst_factory = $wpsynchro_container->get("class.InstallationFactory");
        $local_installation = $inst_factory->getLocalInstallation();
        $local_token = $this->getTransferToken($local_installation);
        // Check token
        if (strlen($local_token) < 20) {
            $this->logger->log("CRITICAL", __("Failed initializing - Could not get a valid token from local server", "wpsynchro"));
        }

        // Get remote transfertoken
        $remote_rest = trailingslashit($this->installation->site_url) . "wp-json/";
        $remote_token = $this->getTransferToken($this->installation);
        // Check token
        if (strlen($remote_token) < 20) {
            $this->logger->log("CRITICAL", __("Failed initializing - Could not get a valid token from remote server", "wpsynchro"));
        }

        // If no errors, set transfer tokens in job object
        if (count($this->job->errors) == 0) {
            // Set tokens in job
            $local_transfer_token = TransferToken::getTransferToken(TransferAccessKey::getAccessKey(), $local_token);
            $remote_transfer_token = TransferToken::getTransferToken($this->installation->access_key, $remote_token);

            if ($this->installation->type == 'pull') {
                $this->job->from_rest_base_url = $remote_rest;
                $this->job->from_token = $remote_transfer_token;
                $this->job->from_accesskey = $this->installation->access_key;
                $this->job->to_rest_base_url = $local_rest;
                $this->job->to_token = $local_transfer_token;
                $this->job->to_accesskey = TransferAccessKey::getAccessKey();
            } else {
                $this->job->to_rest_base_url = $remote_rest;
                $this->job->to_token = $remote_transfer_token;
                $this->job->to_accesskey = $this->installation->access_key;
                $this->job->from_rest_base_url = $local_rest;
                $this->job->from_token = $local_transfer_token;
                $this->job->from_accesskey = TransferAccessKey::getAccessKey();
            }

            $this->job->local_transfer_token = $local_transfer_token;
            $this->job->remote_transfer_token = $remote_transfer_token;

            // Final checks
            if (strlen($this->job->to_token) < 10) {
                $this->logger->log("CRITICAL", __("Failed initializing - No 'to' token could be found after initialize", "wpsynchro"));
            }
            if (strlen($this->job->from_token) < 10) {
                $this->logger->log("CRITICAL", __("Failed initializing - No 'from' token could be found after initialize", "wpsynchro"));
            }
        }

        $this->logger->log("INFO", "Initation completed on: " . $this->timer->endTimer($initiate_timer) . " seconds");

        if (count($this->job->errors) == 0) {
            $this->job->initiation_completed = true;
        }
    }

    /**
     *  Retrieve transfer token
     *  @since 1.6.0
     */
    public function getTransferToken(Installation $installation)
    {
        if (count($this->job->errors) > 0) {
            return;
        }

        $this->logger->log("DEBUG", "Calling initate remote service with url: " . $installation->site_url);

        $initiate_retrieval = new InitiateTokenRetrieval($this->logger, $installation);
        $result = $initiate_retrieval->getInitiateToken();

        $initiate_errors = $initiate_retrieval->errors_from_remote;
      
        if (count($initiate_errors) > 0) {         
            $this->job->errors = array_merge($this->job->errors, $initiate_errors);                     
        } else if ($result && isset($initiate_retrieval->token) && strlen($initiate_retrieval->token) > 0) {
            return $initiate_retrieval->token;
        } else {
            $this->response->errors[] = sprintf(__("Could not initialize with %s - Check that WP Synchro is installed, connection to the site is not blocked and that health check runs without errors on the site", "wpsynchro"), $installation->site_url);            
        }
    }

}
