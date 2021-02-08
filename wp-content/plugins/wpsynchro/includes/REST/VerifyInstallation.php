<?php
namespace WPSynchro\REST;

use WPSynchro\Initiate\InitiateTokenRetrieval;
use WPSynchro\Masterdata\MasterdataRetrieval;
use WPSynchro\Logger\NullLogger;
use WPSynchro\Job;
use WPSynchro\Installation;
use WPSynchro\Transport\TransferToken;
use WPSynchro\Transport\TransferAccessKey;

/**
 * Class for handling REST service "installation/verify" - Verifying and gathering masterdata from both source and target
 * @since 1.6.0
 */
class VerifyInstallation
{
    // REST Response
    public $response;
    // Job object
    public $job;

    public function __construct()
    {
        // Generate response
        $this->response = new \stdClass();
        $this->response->errors = [];
        $this->response->warnings = [];
        $this->response->source_masterdata = [];
        $this->response->target_masterdata = [];

        // Set job
        $this->job = new Job();
    }


    public function service($request)
    {
        global $wpsynchro_container;
        $common = $wpsynchro_container->get("class.CommonFunctions");

        // Get data to check on
        $body = $request->get_json_params();
        $installation = Installation::map($body);

        /**
         *  Step0: Validate
         */
        $this->validate($installation);
        if (count($this->response->errors)>0) {
            return new \WP_REST_Response($this->response, 400);
        }

        /**
         *  Step1: Verify that we can connect to url and get a remote token
         */        
        $remote_rest = trailingslashit($installation->site_url) . "wp-json/";
        $remote_token = $this->doInitiateOnRemote($installation);
        $this->job->remote_transfer_token = TransferToken::getTransferToken($installation->access_key, $remote_token);
        $this->response->remote_transfer_token = $this->job->remote_transfer_token;
        if (count($this->response->errors)>0) {
            return new \WP_REST_Response($this->response, 400);
        } 

        /**
         *  Step2: Get local transfer token
         */        
        $local_rest = get_rest_url();
        $inst_factory = $wpsynchro_container->get("class.InstallationFactory");
        $local_installation = $inst_factory->getLocalInstallation();
        $local_token = $this->doInitiateOnRemote($local_installation);
        $this->job->local_transfer_token = TransferToken::getTransferToken(TransferAccessKey::getAccessKey(), $local_token);
        if (count($this->response->errors)>0) {
            return new \WP_REST_Response($this->response, 400);
        }

        /**
         *  Set data in job object
         */
        global $wpsynchro_container;
        $sync_controller = $wpsynchro_container->get("class.SynchronizeController");
        $sync_controller->job = $this->job;

        if ($installation->type === 'pull') {
            $this->job->from_rest_base_url = $remote_rest;
            $this->job->from_token = $this->job->remote_transfer_token;
            $this->job->from_accesskey = $installation->access_key;
            $this->job->to_rest_base_url = $local_rest;
            $this->job->to_token = $this->job->local_transfer_token;
            $this->job->to_accesskey = TransferAccessKey::getAccessKey();
        } else {
            $this->job->to_rest_base_url = $remote_rest;
            $this->job->to_token = $this->job->remote_transfer_token;
            $this->job->to_accesskey = $installation->access_key;
            $this->job->from_rest_base_url = $local_rest;
            $this->job->from_token = $this->job->local_transfer_token;
            $this->job->from_accesskey = TransferAccessKey::getAccessKey();
        }
        if (count($this->response->errors)>0) {
            return new \WP_REST_Response($this->response, 400);
        }

        /**
         * Step3: Get masterdata from remote site
         */    
        $remote_masterdata = $this->doMasterdataOnRemote($installation);
        if (count($this->response->errors)>0) {
            return new \WP_REST_Response($this->response, 400);
        }

        /**
        * Step4: Get masterdata from local site
        */  
        $local_masterdata = $this->doMasterdataOnRemote($local_installation);
        if (count($this->response->errors)>0) {
            return new \WP_REST_Response($this->response, 400);
        }

        /**
         *  Set data in response
         */
        if ($installation->type === 'pull') {
            $this->response->source_masterdata = $remote_masterdata;
            $this->response->target_masterdata = $local_masterdata;
        } else {
            $this->response->source_masterdata = $local_masterdata;
            $this->response->target_masterdata = $remote_masterdata;
        }
      
        return new \WP_REST_Response($this->response, 200);
    }

    /**
     *  Validate input
     */
    public function validate(Installation $installation)
    {
        // Valdidate url
        if (!filter_var($installation->site_url, FILTER_VALIDATE_URL)) {
            $this->response->errors[] = __("The website url does not seem to be valid - Please enter a valid website url", "wpsynchro");
        }
        // Validate access key
        if (strlen($installation->access_key)< 10) {
            $this->response->errors[] = __("The access key does not seem to be valid - Please enter a valid access key", "wpsynchro");
        }
        // Validate type
        $allowed_types = ["push","pull"];
        if (!in_array($installation->type, $allowed_types)) {
            $this->response->errors[] = __("The type of installation does not seem to be valid - Please choose a valid installation type", "wpsynchro");
        }
    }

    /**
     *  Get initiation token from url
     */
    public function doInitiateOnRemote(Installation $installation)
    {
        $logger = new NullLogger();

        $initiate_retrieval = new InitiateTokenRetrieval($logger, $installation);   
        $result = $initiate_retrieval->getInitiateToken();

        $initiate_errors = $initiate_retrieval->errors_from_remote;

        $initialize_default_error = sprintf(__("Could not initialize with %s - Check that WP Synchro is installed, connection to the site is not blocked and that health check runs without errors on the site", "wpsynchro"), $installation->site_url);            

        if ($result && isset($initiate_retrieval->token) && strlen($initiate_retrieval->token) > 0) {
            return $initiate_retrieval->token;
        } else if (count($initiate_errors) > 0) {         
            $this->response->errors[] = $initialize_default_error;
            foreach ($initiate_errors as $error) {
                $this->response->errors[] = $error;
            }            
        } else {
            $this->response->errors[] = $initialize_default_error;
        }

        return "";
    }

    /**
     * Get masterdata from remote
     */
    public function doMasterdataOnRemote(Installation $installation)
    {
        // Get masterdata retrival object
        $retrieval = new MasterdataRetrieval($installation);  
        $retrieval->setDataToRetrieve(['dbtables','filedetails']);
        $result = $retrieval->getMasterdata();

        if ($result) {
            if (is_object($retrieval->data) && isset($retrieval->data->base)) {
                return $retrieval->data;
            } else {
                $this->response->errors[] = sprintf(__("Tried to get masterdata from %s, but got wrong response. Make sure WP Synchro health check runs without error on both sites.", "wpsynchro"), $installation->site_url);
                return [];
            }
        } else {
            $this->response->errors[] = sprintf(__("Could not get masterdata from %s - Check that WP Synchro is installed, connection to the site is not blocked and that health check runs without errors on the site", "wpsynchro"), $installation->site_url);
            return [];
        }
    }
}
