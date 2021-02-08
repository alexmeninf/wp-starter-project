<?php
/**
 * Plugin Name: WP Synchro MU plugin
 * Plugin URI:  wpsynchro.com
 * Description: Optimizing site compatibility and speed for WP Synchro specific operations
 * Author:      WP Synchro
 * Author URI:  wpsynchro.com
 * Version:     1.0.3
 */
define('WPSYNCHRO_MU_COMPATIBILITY_VERSION', '1.0.3');

// Check if it is a WP Synchro REST request
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (strpos($request_uri, "wp-json/wpsynchro/v") > -1) {

    // Figure out the location of plugins
    if (defined('WP_PLUGIN_DIR')) {
        $plugins_location = trailingslashit(WP_PLUGIN_DIR);
    } else if (defined('WP_CONTENT_DIR')) {
        $plugins_location = trailingslashit(WP_CONTENT_DIR) . 'plugins/';
    } else {
        $plugins_location = trailingslashit(dirname(dirname(__FILE__))) . 'plugins/';
    }

    // Load the compatibility class of WP Synchro
    $compatibility_class_location = $plugins_location . "wpsynchro/includes/Utilities/Compatibility/Compatibility.php";
    if (!file_exists($compatibility_class_location)) {
        return;
    }
    include_once($compatibility_class_location);
    if (class_exists("\WPSynchro\Utilities\Compatibility\Compatibility")) {
        new \WPSynchro\Utilities\Compatibility\Compatibility;  
    }
}



