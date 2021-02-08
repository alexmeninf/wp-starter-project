<?php
namespace WPSynchro\Utilities;

/**
 * Load configuration parameters
 * @since 1.6.0
 */
class Configuration
{
    /**
     *  Configuration data
     */
    public $data = array(
         // Timers
        "overall_time_margin" => 0.9,       // As fraction of total time
        // Requests and transport
        "request_timeout_margin" => 1.5,    // Seconds
    );

    /**
     *  Constructor
     */
    public function __construct()
    {

        // Load configurations that change default data
        $enable_slow_hosting_optimize = get_option('wpsynchro_slow_hosting_optimize');
        if ($enable_slow_hosting_optimize && strlen($enable_slow_hosting_optimize) > 0) {
            $this->data['overall_time_margin'] = 0.7;
            $this->data['request_timeout_margin'] = 4;
        }
    }

    /**
     *  Get a specific configuration parameter
     */
    public function get($identification)
    {
        if (isset($this->data[$identification])) {
            return $this->data[$identification];
        }
        return null;
    }

    /**
     *  Factory method
     */
    public static function factory()
    {
        static $instance = false;
        if (! $instance) {
            $instance = new self;
        }

        return $instance;
    }
}
