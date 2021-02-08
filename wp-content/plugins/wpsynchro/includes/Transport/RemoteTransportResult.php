<?php

namespace WPSynchro\Transport;

/**
 * Class for handling result of transport
 * @since 1.3.0
 */
class RemoteTransportResult
{
    public $url;
    public $args;
    public $encryption_key;
    // Data
    public $response_object;
    public $body;
    public $files;
    public $body_length;
    // HTTP
    public $statuscode;
    // Errors, warnings etc
    public $errors = array();
    public $warnings = array();
    public $infos = array();
    public $debugs = array();
    // Success or not
    public $success = false;

    public function getBody()
    {
        return $this->body;
    }

    public function getFiles()
    {
        return $this->files;
    }

    public function getBodyLength()
    {
        return $this->body_length;
    }

    public function getStatuscode()
    {
        return $this->statuscode;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getWarnings()
    {
        return $this->warnings;
    }

    public function getInfos()
    {
        return $this->infos;
    }

    public function getDebugs()
    {
        return $this->debugs;
    }

    public function isSuccess()
    {
        return $this->success;
    }

    public function getResponseObject()
    {
        return $this->response_object;
    }

    public function writeMessagesToLog()
    {
        global $wpsynchro_container;
        $logger = $wpsynchro_container->get("class.Logger");

        foreach ($this->errors as $errortext) {
            $logger->log("ERROR", $errortext);
        }
        $this->errors = array();

        foreach ($this->warnings as $warningtext) {
            $logger->log("WARNING", $warningtext);
        }
        $this->warnings = array();

        foreach ($this->infos as $infolog) {
            $logger->log("INFO", $infolog);
        }
        $this->infos = array();

        foreach ($this->debugs as $debuglog) {
            $logger->log("DEBUG", $debuglog);
        }
        $this->debugs = array();
    }

    public function parseResponse(&$response, $url, $args, $encryption_key)
    {
        $this->url = $url;
        $this->args = $args;
        $this->encryption_key = $encryption_key;
        $this->response_object = $response;

        // Check if WP error
        if (is_wp_error($this->response_object)) {
            $errormsg = $this->response_object->get_error_message();
            if (strpos($errormsg, "cURL error 60") > -1) {
                $this->errors[] = __("Remote or local SSL certificate is not valid or self-signed. To allow non-valid SSL certificates, you need to edit the installation and change it.", "wpsynchro");
                $this->errors[] = "Remote or local SSL certificate is not valid or self-signed.";
                $this->debugs[] = print_r($this->response_object, true);
            } else {
                $this->debugs[] = "Remote service '" . $this->url . "' failed with WP error: " . $errormsg;
                $this->debugs[] = print_r($this->response_object, true);
            }
        } else {

            // Check statuscode
            $this->statuscode = wp_remote_retrieve_response_code($this->response_object);

            // check if wpsynchrotransfer or json
            $body_data = wp_remote_retrieve_body($this->response_object);

            // Check if there is any PHP notices/warnings etc found in response
            if (strpos($body_data, "<b>Notice</b>:") > -1 || strpos($body_data, "<b>Warning</b>:") > -1 || strpos($body_data, "<b>Fatal error</b>:") > -1) {
                $sitename = $url = strtok($this->url, '?');
                $this->errors[] = sprintf("Found one or more PHP notices/warnings/errors in response on url: %s. These must be fixed or suppressed before WP Synchro can complete synchronization. The errors should also be in PHP error log on the site. Also make sure WP_DEBUG is set to false in wp-config.php.", $sitename);
            }

            // Check for authentication on remote
            $www_authenticate_header = wp_remote_retrieve_header($this->response_object, "WWW-Authenticate");
            if (strlen($www_authenticate_header) > 0 && $this->statuscode == 401) {
                // Host is using autentication in some form
                $parsed_url = parse_url($url);
                if (strpos($www_authenticate_header, "Basic realm") !== false) {
                    // Using Basic authentication
                    $this->errors[] = sprintf(__("The site %s is protected by Basic Authentication, which requires a username and password.
                    If this is the remote site, the username/password should be added to the synchronization configuration.
                    If the site is the 'local' site, you can add the username/password in the 'Setup' menu.", "wpsynchro"), $parsed_url['host']);
                } else {
                    $this->errors[] = sprintf(__("The site %s is protected by authentication of some kind, that we do not support.
                    Contact support for further help and tell them this: Auth header was: %s", "wpsynchro"), $parsed_url['host'], $www_authenticate_header);
                }
            }

            $this->body_length = strlen($body_data);
            if (substr($body_data, 0, strlen("WPSYNCHROTRANSFER")) == "WPSYNCHROTRANSFER") {
                global $wpsynchro_container;
                $transfer = $wpsynchro_container->get('class.Transfer');
                $transfer->setEncryptionKey($this->encryption_key);
                $transfer->populateFromString($body_data);
                $this->body = $transfer->getDataObject();
                $this->files = $transfer->getFiles();
            } else {
                // JSON
                $body_json = $this->cleanRemoteJSONData($body_data);
                $this->body = json_decode($body_json);
            }

            if ($this->statuscode == 200) {
                $this->success = true;
            } else {
                $this->debugs[] = "Error calling REST service - Got HTTP " . $this->statuscode . " on this url: " . $this->url;
                $this->debugs[] = htmlentities(substr(print_r($this->response_object, true), 0, 5000));
            }

            // Check for errors
            if (isset($this->body->errors)) {
                $this->errors = array_merge($this->errors, $this->body->errors);
                unset($this->body->errors);
            }

            // Check for warnings
            if (isset($this->body->warnings)) {
                $this->warnings = array_merge($this->warnings, $this->body->warnings);
                unset($this->body->warnings);
            }

            // Check for infos
            if (isset($this->body->infos)) {
                $this->infos = array_merge($this->infos, $this->body->infos);
                unset($this->body->infos);
            }

            // Check for debugs
            if (isset($this->body->debugs)) {
                $this->debugs = array_merge($this->debugs, $this->body->debugs);
                unset($this->body->debugs);
            }
        }
    }

    /**
     *  Cleanup response body data from posts/gets. Such as remove UTF8 which json_decode pukes over
     *  @since 1.0.3
     */
    public function cleanRemoteJSONData($response)
    {
        // Remove UTF8 BOM which json_decode does not like
        if (substr($response, 0, 3) == pack("CCC", 0xEF, 0xBB, 0xBF)) {
            $response = substr($response, 3);
        }
        return $response;
    }
}
