<?php
namespace WPSynchro\REST;

/**
 * Class for handling REST service "synchronize"
 * Call should already be verified by permissions callback
 *
 * @since 1.0.0
 */
class Synchronize
{

    public function service($request)
    {
        // Extract parameters
        $parameters = $request->get_json_params();
        if (isset($parameters['instid'])) {
            $instid = $parameters['instid'];
        } else {
            $instid = '';
        }
        if (isset($parameters['jobid'])) {
            $jobid = $parameters['jobid'];
        } else {
            $jobid = '';
        }

        global $wpsynchro_container;
        $synchronize = $wpsynchro_container->get('class.SynchronizeController');
        $synchronize->setup($instid, $jobid);
        $sync_response = $synchronize->runSynchronization();

        if (isset($sync_response->errors) && count($sync_response->errors) > 0) {     
            // Set that we should not continue synchronization from frontend JS
            $sync_response->should_continue = false;
        } else {
            // Set to frontend to continue synchronization
            $sync_response->should_continue = true;
        }

        if (isset($sync_response->is_completed) && $sync_response->is_completed === true) {            
            $sync_response->should_continue = false;            
        }
        
        return new \WP_REST_Response($sync_response, 200);
    }
}
