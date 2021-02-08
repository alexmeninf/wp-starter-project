<?php

namespace WPSynchro\Transport;

use WPSynchro\Utilities\Configuration;
use WPSynchro\Installation;
use WPSynchro\Job;

/**
 * Class for handling transport of data between sites in WP Synchro
 * @since 1.3.0
 */
class RemoteTransport implements RemoteConnection
{
    public $url;
    public $args;
    public $job;
    public $installation;
    public $timer;
    public $transfer = null;
    public $send_data_as_json = false;
    public $max_requestsize = 0;
    public $blocking_request = true;
    // Specific token and encryption to use (for cases when we dont want to auto-get it from sync controller, like health check)
    public $token = null;
    public $encryption_key = null;
    // Configuration
    public $max_retries = 5;
    public $seconds_sleep_between_retries = 1;

    /**
     *  Set installation
     *  @since 1.6.0
     */
    public function setInstallation(Installation $installation = null)
    {
        if (is_object($installation)) {
            $this->installation = $installation;
        }
    }

    /**
     *  Set installation
     *  @since 1.6.0
     */
    public function setJob(Job $job = null)
    {
        if (is_object($job)) {
            $this->job = $job;
        }
    }

    /**
     *  Set no retries
     *  @since 1.6.0
     */
    public function setNoRetries()
    {
        $this->max_retries = 0;
    }

    /**
     *  Initialize request object
     *  @since 1.3.0
     */
    public function init()
    {
        // Get needed objects
        global $wpsynchro_container;
        $sync_controller = $wpsynchro_container->get("class.SynchronizeController");
        if (is_null($this->job)) {
            $this->job = $sync_controller->job;
        }
        if (is_null($this->installation)) {
            $this->installation = $sync_controller->installation;
        }

        // Get timer
        global $wpsynchro_container;
        $this->timer = $wpsynchro_container->get("class.SyncTimerList");

        // Get transfer object and setup it up
        global $wpsynchro_container;
        $this->transfer = $wpsynchro_container->get("class.Transfer");
        $this->transfer->setShouldEncrypt(true);
        $this->transfer->setShouldDeflate(true);

        // Setup WP remote post args
        $this->args = array(
            'method' => 'POST',
            'redirection' => 2,
            'httpversion' => '1.0',
            'sslverify' => (isset($this->installation->verify_ssl) ? $this->installation->verify_ssl : true),
            'headers' => array(
                'Content-Type' => $this->transfer->getContentType(),
            ),
        );

        // Set basic authentication if needed   
        if (isset($this->installation->connection_type) && $this->installation->connection_type === 'basicauth') {
            $this->setBasicAuthentication($this->installation->basic_auth_username, $this->installation->basic_auth_password);
        }
    }

    /**
     *  Set URL on request
     *  @since 1.3.0
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     *  Set max size on request
     *  @since 1.3.0
     */
    public function setMaxRequestSize($maxsize)
    {
        $this->max_requestsize = $maxsize;
    }

    /**
     *  Add data to request
     *  @since 1.3.0
     */
    public function setDataObject($object)
    {
        return $this->transfer->setDataObject($object);
    }

    /**
     *  Set token on request
     *  @since 1.6.0
     */
    public function setToken($token)
    {
        $this->token = $token;
    }

    /**
     *  Set encryption key on request
     *  @since 1.6.0
     */
    public function setEncryptionKey($key)
    {
        $this->encryption_key = $key;
    }

    /**
     *  Send data as JSON
     *  @since 1.3.0
     */
    public function setSendDataAsJSON()
    {
        $this->send_data_as_json = true;
        $this->args["headers"]["Content-Type"] = "application/json; charset=utf-8";
    }

    /**
     *  Set basic authentication on request
     *  @since 1.6.0
     */
    public function setBasicAuthentication($username, $password)
    {
        $this->args["headers"]["Authorization"] = "Basic " . base64_encode($username . ":" . $password);
    }

    /**
     *  Set request as non-blocking
     *  @since 1.6.0
     */
    public function setNonBlocking()
    {
        $this->args["blocking"] = false;
        $this->args["timeout"] = 0;
        $this->blocking_request = false;
    }

    /**
     *  Add file to request
     *  @since 1.3.0
     */
    public function addFiledata(\WPSynchro\Transport\TransferFile $file)
    {
        $current_request_size = $this->transfer->getRequestSize();
        $overhead_per_file = $this->transfer->getFileOverhead();

        // Check if there is more space
        if ($current_request_size + ($overhead_per_file * 2) > $this->max_requestsize) {
            return false;
        }

        global $wpsynchro_container;
        $logger = $wpsynchro_container->get("class.Logger");

        if (!file_exists($file->filename) || !is_readable($file->filename)) {
            $file->is_error = true;
            return true;
        }

        // Load file data into object, or part of it, if too big for remaining space
        $filesize = filesize($file->filename);

        if (($filesize + $current_request_size + $overhead_per_file) > $this->max_requestsize || $file->is_partial) {
            $logger->log("DEBUG", "No space for entire file, will chunk it: " . $file->filename);
            // Partial
            if (($current_request_size + $overhead_per_file) < $this->max_requestsize) {
                // Check if there is room for any more data
                $available_space_for_chunk = $this->max_requestsize - ($current_request_size + $overhead_per_file);
                if ($file->is_partial) {
                    // Already chunked, so continue from last position
                    $already_transferred_bytes = $file->partial_start;
                    $logger->log("DEBUG", "Already chunked, start position: " . $already_transferred_bytes . " and available: " . $available_space_for_chunk);
                    $file->data = file_get_contents($file->filename, false, null, $already_transferred_bytes, $available_space_for_chunk);
                } else {
                    // First read of chunked part, so start from 0
                    $logger->log("DEBUG", "First chunk, start position: 0 and available: " . $available_space_for_chunk);
                    if ($file->is_dir) {
                        // dir
                        $file->data = "";
                    } else {
                        // filename
                        $file->data = file_get_contents($file->filename, false, null, 0, $available_space_for_chunk);
                    }

                    $file->is_partial = true;
                    $file->partial_start = 0;
                }
            }
        } else {
            // Check if file
            if (!$file->is_dir) {
                // File can fit
                $logger->log("DEBUG", "File can be contained fully in request: " . $file->filename);
                $file->data = file_get_contents($file->filename);
            }
        }

        // Add file to transfer object
        $this->transfer->addFiledata($file);

        // Remove data again, as it is copied in transfer object and we dont want to send it in json also
        $file->data = "";

        return true;
    }

    /**
     *  Handle all POST requests to REST services
     *  @since 1.3.0
     */
    public function remotePOST()
    {
        $wpremoteresult = new RemoteTransportResult();

        if (isset($this->job->errors) && count($this->job->errors) > 0) {
            return $wpremoteresult;
        }

        // Adjust request
        $this->addTokenToRequest();
        if ($this->blocking_request === true) {
            $this->args["timeout"] = ceil($this->timer->getRemainingSyncTime() + Configuration::factory()->get("request_timeout_margin"));
        }

        if ($this->send_data_as_json) {
            $this->args["body"] = json_encode($this->transfer->getDataObject());
        } else {
            $this->args["body"] = $this->transfer->getDataString();
        }

        // Do request
        $result = $this->doRequest($wpremoteresult);
        if (!$result) {
            // Retry if there is time for that
            $this->handleRetries("POST", $wpremoteresult);
        }

        // Save errors to job
        if (isset($this->job->errors)) {
            $this->job->errors = array_merge($this->job->errors, $wpremoteresult->getErrors());
        }
        if (isset($this->job->warnings)) {
            $this->job->warnings = array_merge($this->job->warnings, $wpremoteresult->getWarnings());
        }

        $wpremoteresult->writeMessagesToLog();

        return $wpremoteresult;
    }

    /**
     *  Check which token, if any, to add to request
     *  @since 1.3.0
     */
    public function addTokenToRequest()
    {

        // Check if token and encryption was set manually
        if (!is_null($this->token) && !is_null($this->encryption_key)) {
            $this->url = add_query_arg('token', $this->token, $this->url);
            $this->transfer->setEncryptionKey($this->encryption_key);
            return;
        }

        // If to get it from job object, check that data is present
        if (!isset($this->job->from_token) || strlen($this->job->from_token) == 0) {
            return;
        }
        if (!isset($this->job->to_token) || strlen($this->job->to_token) == 0) {
            return;
        }

        // Check if it is local or remote and set appropriate encryptionkey on transfer object
        if (strpos($this->url, $this->job->from_rest_base_url) !== false) {
            $this->token = $this->job->from_token;
            $this->encryption_key = $this->job->from_accesskey;
        } else {
            $this->token = $this->job->to_token;
            $this->encryption_key = $this->job->to_accesskey;
        }
        $this->transfer->setEncryptionKey($this->encryption_key);

        if (strlen($this->token) > 0) {
            // If token is set, add it to url
            $this->url = add_query_arg('token', $this->token, $this->url);
        }
    }

    /**
     *  Handle retries of HTTP requests
     *  @since 1.3.0
     */
    public function handleRetries($type, &$wpremoteresult)
    {
        $min_time_to_retry = 3; // seconds

        $retries = 0;
        $wpremoteresult->debugs[] = sprintf(__("Entering retry with remaining time %f", "wpsynchro"), $this->timer->getRemainingSyncTime());
        // Unexpected response, so retry
        while ($retries < $this->max_retries) {

            // Check if it is possible within timeframe
            if (!$this->timer->shouldContinueWithLastrunTime($min_time_to_retry)) {
                $wpremoteresult->debugs[] = sprintf(__("Aborting retries because we dont have enough time - Tried %d times ", "wpsynchro"), $retries);
                break;
            }
            sleep($this->seconds_sleep_between_retries);

            // Try again
            if ($this->blocking_request === true) {
                $this->args["timeout"] = ceil($this->timer->getRemainingSyncTime());
            }

            // Do request
            $result = $this->doRequest($wpremoteresult);
            if ($result) {
                return;
            } else {
                $retries++;
                $wpremoteresult->debugs[] = sprintf(__("Got error connecting to service %s - Retry %d of %d ", "wpsynchro"), $this->url, $retries, $this->max_retries);
            }
        }
        $parsed_url = parse_url($this->url);
        $parsed_host = "unknown";
        if (isset($parsed_url["host"])) {
            $parsed_host = $parsed_url["host"];
        }
        $wpremoteresult->errors[] = sprintf(__("Could not connect to %s REST service (HTTP statuscode: %d)", "wpsynchro"), $parsed_host, $wpremoteresult->statuscode);
    }

    /**
     *  Do actual request and handle error scenarios
     */
    private function doRequest($wpremoteresult)
    {
        $response = wp_remote_post($this->url, $this->args);
        if ($this->blocking_request === false) {
            return true;
        }
        if (is_wp_error($response)) {
            $errormsg = $response->get_error_message();
            $parsedurl = parse_url($this->url);
            if (strpos($errormsg, "cURL error 60") > -1) {
                $this->job->errors[] = sprintf(__("SSL certificate is not valid or self-signed on host %s. To allow non-valid SSL certificates when running a synchronization, make sure it is set to allowed.", "wpsynchro"), $parsedurl['host']);
            } else {
                $this->job->errors[] = sprintf(__("REST error - Can not reach REST service on host %s. Error message: %s", "wpsynchro"), $parsedurl['host'], $errormsg);
            }
            return false;
        } else {
            $statuscode = wp_remote_retrieve_response_code($response);
            $wpremoteresult->parseResponse($response, $this->url, $this->args, $this->encryption_key);

            if ($statuscode === 200) {
                return true;
            } else {
                $wpremoteresult->debugs[] = "Call to REST service at url " . $this->url . " failed with HTTP error code: " . $statuscode . " - ";
                return false;
            }
        }
    }
}
