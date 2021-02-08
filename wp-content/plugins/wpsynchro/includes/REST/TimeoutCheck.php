<?php
namespace WPSynchro\REST;

/**
 * Class for handling REST service "TimeoutCheck"
 *
 * @since 1.3.2
 */
class TimeoutCheck
{

    public function service($request)
    {

        $start = $_SERVER["REQUEST_TIME_FLOAT"];

        global $wpsynchro_container;
        $commonfunctions = $wpsynchro_container->get("class.CommonFunctions");
        $max_execution_time = $commonfunctions->getPHPMaxExecutionTime();

        $time_to_return = $start + $max_execution_time - 0.5;    // Subtract 500ms, to prevent hitting the actual max_execution_time         
        time_sleep_until($time_to_return);

        return new \WP_REST_Response(null, 200);
    }
}
