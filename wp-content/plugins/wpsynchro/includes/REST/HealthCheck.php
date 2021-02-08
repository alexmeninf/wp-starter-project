<?php

namespace WPSynchro\REST;

use WPSynchro\Masterdata\MasterdataRetrieval;
use WPSynchro\Transport\TransferToken;
use WPSynchro\Transport\TransferAccessKey;
use WPSynchro\REST\MasterData;
use WPSynchro\Logger\NullLogger;
use WPSynchro\Initiate\InitiateTokenRetrieval;

/**
 * Class for handling REST service "healthcheck"
 * Call should already be verified by permissions callback
 *
 * @since 1.1
 */
class HealthCheck
{
    public function service($request)
    {
        global $wpsynchro_container;
        $commonfunctions = $wpsynchro_container->get('class.CommonFunctions');

        $healthcheck = new \stdClass();
        $healthcheck->errors = array();
        $healthcheck->warnings = array();

        /**
         *  Check environment, WP/PHP/SQL
         */
        $errors_from_env = $commonfunctions->checkEnvCompatability();
        $env_okay = true;
        if (count($errors_from_env) > 0) {
            $healthcheck->errors = array_merge($healthcheck->errors, $errors_from_env);
            $env_okay = false;
        }

        /**
         *  Check that database is current, but not newer
         */
        $dbversion = get_option('wpsynchro_dbversion');
        if (!$dbversion || $dbversion == "") {
            $dbversion = 0;
        }
        if ($dbversion > WPSYNCHRO_DB_VERSION) {
            $healthcheck->errors[] = __("WP Synchro database version is newer than the currently installed plugin version - Please upgrade plugin to newest version - Continue at own risk", "wpsynchro");
        }

        /**
         *  Check that local installation has access key set
         */
        $accesskey = TransferAccessKey::getAccessKey();
        $accesskey_okay = true;
        if (strlen(trim($accesskey)) < 20) {
            $healthcheck->errors[] = __("Access key for this site is not set - This needs to be configured for WP Synchro to work.", "wpsynchro");
            $accesskey_okay = false;
        }

        /*
         *  Check proper PHP extensions
         */
        $required_php_extensions = array("curl", "mbstring", "openssl", "mysqli");
        $php_extensions_loaded = get_loaded_extensions();
        $missing_extensions = array();
        foreach ($required_php_extensions as $required_php_extension) {
            if (!in_array($required_php_extension, $php_extensions_loaded)) {
                $missing_extensions[] = $required_php_extension;
            }
        }
        if (count($missing_extensions) > 0) {
            $healthcheck->errors[] = sprintf(__("Missing PHP extensions for WP Synchro to work. Enable extension(s) '%s' to php.ini and reload.", "wpsynchro"), implode(", ", $missing_extensions));
        }

        /*
         *  Check that permalink structure is NOT plain
         */
        $permalink_structure = get_option('permalink_structure');
        $permalinks_okay = true;
        if (trim($permalink_structure) == "") {
            $healthcheck->errors[] = __("Plain permalinks is not supported in WP Synchro. You should change it to %postname% instead", "wpsynchro");
            $permalinks_okay = false;
        }

        /**
         *  Check that SAVEQUERIES are not active
         */
        if (defined("SAVEQUERIES") && SAVEQUERIES == true) {
            $healthcheck->errors[] = __("SAVEQUERIES constant is set. This is normally only for debugging. It will generate out of memory errors with WP Synchro synchronizations", "wpsynchro");
        }

        /**
         *  Check license okay, if PRO
         */
        $licenseokay = true;
        if (\WPSynchro\CommonFunctions::isPremiumVersion()) {
            $licensing = $wpsynchro_container->get("class.Licensing");
            if ($licensing->hasProblemWithLicensing()) {
                $licenseokay = false;
                $healthcheck->errors[] = $licensing->getLicenseErrorMessage();
            }
        }

        /**
         *  Check local REST urls for connectivity and proper response
         */
        $initiate_token = "";
        if ($accesskey_okay && $permalinks_okay && $env_okay && $licenseokay) {
            $initiate_server_okay = false;

            $logger = new NullLogger();
            $inst_factory = $wpsynchro_container->get("class.InstallationFactory");
            $local_installation = $inst_factory->getLocalInstallation();
            $retrieval = new InitiateTokenRetrieval($logger, $local_installation);
            $result = $retrieval->getInitiateToken();

            if ($result && isset($retrieval->token) && strlen($retrieval->token) > 0) {
                $initiate_token = $retrieval->token;
                $initiate_server_okay = true;
            } else {
                $healthcheck->errors = array_merge($healthcheck->errors, $retrieval->getErrors());
                $healthcheck->warnings = array_merge($healthcheck->warnings, $retrieval->getWarnings());
                $healthcheck->errors[] = __("REST error - Can not reach 'initiate' REST service - Check that REST services is accessible and not being blocked", "wpsynchro");
            }

            if ($initiate_server_okay) {

                // Create a transfer token based on the token we just got
                $transfer_token = TransferToken::getTransferToken(TransferAccessKey::getAccessKey(), $initiate_token);

                // Get masterdata retrival object
                $retrieval = new MasterdataRetrieval($local_installation);
                $retrieval->setDataToRetrieve(['dbtables', 'filedetails']);
                $retrieval->setToken($transfer_token);
                $retrieval->setEncryptionKey(TransferAccessKey::getAccessKey());
                $result = $retrieval->getMasterdata();

                // Check for errors
                if ($result) {
                    if (!$retrieval->data->dbtables) {
                        $healthcheck->errors[] = __("REST error - Masterdata REST service returns improper response - Data was not returned in usable way - Check PHP error log", "wpsynchro");
                    }
                } else {
                    $healthcheck->errors[] = __("REST error - Can not reach 'masterdata' REST service - Check that WP Synchro is activated and REST service accessible", "wpsynchro");
                }
            }
        }

        /**
         *  Check writable log directory
         */
        $commonfunctions->createLogLocation();
        $log_location = $commonfunctions->getLogLocation();
        $log_dir = realpath($log_location);
        if (!is_writable($log_dir)) {
            $healthcheck->errors[] = sprintf(__("WP Synchro log dir is not writable for PHP - Path: %s ", "wpsynchro"), $log_dir);
        }

        /**
         *  Check other relevant dir for writability (typically for files sync)
         */
        if (\WPSynchro\CommonFunctions::isPremiumVersion()) {
            $paths_check = array(
                // Document root
                $_SERVER['DOCUMENT_ROOT'],
                // Absolut directory of WP_CONTENT folder, or whatever it is called
                WP_CONTENT_DIR,
                // One dir above webroot
                dirname(realpath($_SERVER['DOCUMENT_ROOT']))
            );
            foreach ($paths_check as $path) {
                if (!MasterData::checkReadWriteOnDir($path)) {
                    $healthcheck->warnings[] = sprintf(__("Path that WP Synchro might use for synchronization is not writable- Path: %s -  This can be caused by PHP's open_basedir setting or file permissions", "wpsynchro"), $path);
                }
            }
        }

        /**
         *  If no errors or warnings, set timestamp in database
         */
        if (count($healthcheck->errors) == 0) {
            update_site_option("wpsynchro_healthcheck_timestamp", time());
        }

        return new \WP_REST_Response($healthcheck, 200);
    }
}
