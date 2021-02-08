<?php

/**
 * Class for handling REST service "initate" - Starting a synchronization
 * @since 1.0.0
 */

namespace WPSynchro\REST;

use WPSynchro\Utilities\Compatibility\MUPluginHandler;
use WPSynchro\Transport\TransferToken;

class Initiate
{
    public function service($request)
    {
        global $wpsynchro_container;
        $common = $wpsynchro_container->get('class.CommonFunctions');

        $sync_response = new \stdClass();
        $sync_response->errors = [];
        $token_lifespan = 10800;

        $allowed_types = ['push', 'pull', 'local'];
        if (isset($request['type']) && in_array($request['type'], $allowed_types)) {
            $type = $request['type'];
            // Get allowed methods for this site
            $methods_allowed = get_option('wpsynchro_allowed_methods', false);
            if (!$methods_allowed) {
                $methods_allowed = new \stdClass();
                $methods_allowed->pull = false;
                $methods_allowed->push = false;
            }

            // Check the type and if it is allowed
            if ($type == 'pull' && !$methods_allowed->pull) {
                $sync_response->errors[] = __('Pulling from this site is not allowed - Change configuration on remote server', 'wpsynchro');
            } elseif ($type == 'push' && !$methods_allowed->push) {
                $sync_response->errors[] = __('Pushing to this site is not allowed - Change configuration on remote server', 'wpsynchro');
            }

            // Check licensing
            if (\WPSynchro\CommonFunctions::isPremiumVersion()) {
                global $wpsynchro_container;
                $licensing = $wpsynchro_container->get('class.Licensing');
                $licensecheck = $licensing->verifyLicense();

                if ($licensecheck == false) {
                    $sync_response->errors[] = $licensing->getLicenseErrorMessage();
                }
            }
        } else {
            $sync_response->errors[] = __('Remote host does not allow that - Make sure it is same WP Synchro version', 'wpsynchro');
        }

        if (count($sync_response->errors) > 0) {
            global $wpsynchro_container;
            $returnresult = $wpsynchro_container->get('class.ReturnResult');
            $returnresult->init();
            $returnresult->setDataObject($sync_response);
            return $returnresult->echoDataFromRestAndExit();
        }

        // Create a new transfer object
        $token_class = new TransferToken();
        $token = $token_class->setNewToken($token_lifespan);
        $sync_response->token = $token;

        // Check if MU plugin needs update
        $muplugin_handler = new MUPluginHandler();
        $muplugin_handler->checkNeedsUpdate();

        // Return
        global $wpsynchro_container;
        $returnresult = $wpsynchro_container->get('class.ReturnResult');
        $returnresult->init();
        $returnresult->setDataObject($sync_response);
        return $returnresult->echoDataFromRestAndExit();
    }
}
