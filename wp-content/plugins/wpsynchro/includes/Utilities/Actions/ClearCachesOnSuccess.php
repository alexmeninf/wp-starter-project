<?php

/**
 * Action: Clear caches
 * @since 1.6.0
 */

namespace WPSynchro\Utilities\Actions;

use WPSynchro\Utilities\Actions\Action;

class ClearCachesOnSuccess implements Action
{

    /**
     * Initialize
     * @since 1.6.0
     */
    public function init()
    {
    }

    /**
     * Execute action
     * @since 1.6.0
     */
    public function doAction($params)
    {
        // WP Rocket
        if (function_exists('rocket_clean_domain')) {
            @\rocket_clean_domain();
        }

        // WP Super Cache
        if (function_exists('wp_cache_clean_cache')) {
            global $file_prefix;    // Global from WP Super cache, not the best way for a plugin, but thats how it is
            @\wp_cache_clean_cache($file_prefix, true);
        }

        // W3 Total cache
        if (function_exists('w3tc_flush_all')) {
            @\w3tc_flush_all();
        }
        
        // WP Fastest cache - Not supported yet
        if (false) {
        }

        // Comet Cache
        if (class_exists("\WebSharks\CometCache\Classes\ApiBase", false) && method_exists(\WebSharks\CometCache\Classes\ApiBase::class, "clear")) {
            @\WebSharks\CometCache\Classes\ApiBase::clear();
            @\WebSharks\CometCache\Classes\ApiBase::wipe();
            @\WebSharks\CometCache\Classes\ApiBase::purge();     
        }
            
        // WordPress object cache
        wp_cache_flush();
    }
}
