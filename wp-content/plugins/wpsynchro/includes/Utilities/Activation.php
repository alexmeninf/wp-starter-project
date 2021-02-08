<?php

namespace WPSynchro\Utilities;

use WPSynchro\Utilities\DatabaseTables;
use WPSynchro\Transport\TransferAccessKey;
use WPSynchro\Utilities\Upgrade\DatabaseUpgrade;

/**
 * Class for handling activate/deactivate/uninstall tasks for WP Synchro
 * @since 1.0.0
 */
class Activation
{
    /**
     *  Activate
     *  @since 1.0.0
     */
    public static function activate($networkwide)
    {
        /**
         *  If multisite and network activated, give error to prevent it from happening
         */
        if (is_multisite() && $networkwide) {
            wp_die(__('WP Synchro does not support being network activated - Activate it on the sites needed instead. Beware that multisite is not supported, so use at own risk.', 'wpsynchro'), '', ['back_link' => true]);
        }

        /**
         *  Make sure there is a default access key for installation
         */
        $accesskey = get_option('wpsynchro_accesskey');
        if (!$accesskey || strlen($accesskey) < 10) {
            $new_accesskey = TransferAccessKey::generateAccesskey();
            update_option('wpsynchro_accesskey', $new_accesskey, false);
        }

        /**
         * Create uploads log dir
         */
        $commonfunctions = new \WPSynchro\CommonFunctions();
        $commonfunctions->createLogLocation();

        /**
         * Check PHP/MySQL/WP versions
         */
        $compat_errors = $commonfunctions->checkEnvCompatability();
        // @codeCoverageIgnoreStart
        if (count($compat_errors) > 0) {
            foreach ($compat_errors as $error) {
                echo $error . '<br>';
            }
            die();
        }
        // @codeCoverageIgnoreEnd

        /**
         * Check that DB contains current WP Synchro DB version
         */
        DatabaseUpgrade::checkDBVersion();

        /**
         * Set a license key if empty
         */
        $licensekey = get_option('wpsynchro_license_key');
        if (!$licensekey) {
            update_option('wpsynchro_license_key', '', false);
        }

        /**
         *  Active the MU plugin if enabled
         */
        $enable_muplugin = get_option('wpsynchro_muplugin_enabled');
        if ($enable_muplugin && strlen($enable_muplugin) > 0) {
            $mupluginhandler = new \WPSynchro\Utilities\Compatibility\MUPluginHandler();
            $mupluginhandler->enablePlugin();
        }

        /**
         *  Create tables
         */
        DatabaseTables::createSyncListTable();
        DatabaseTables::createFilePopulationTable();
    }

    /**
     *  Deactivation
     *  @since 1.0.0
     */
    public static function deactivate()
    {
        // Deactivate MU plugin if exists
        $mupluginhandler = new \WPSynchro\Utilities\Compatibility\MUPluginHandler();
        $mupluginhandler->disablePlugin();
    }

    /**
    *  Uninstall
    *  @since 1.6.0
    */
    public static function uninstall()
    {
        // Deactivate MU plugin if exists
        $mupluginhandler = new \WPSynchro\Utilities\Compatibility\MUPluginHandler();
        $mupluginhandler->disablePlugin();
        
        // Remove database tables
        global $wpdb;
        $tablename = $wpdb->prefix . DatabaseTables::FILE_POPULATION;
        $wpdb->query('drop table if exists `' . $tablename . '`');
        $tablename = $wpdb->prefix . DatabaseTables::SYNC_LIST;
        $wpdb->query('drop table if exists `' . $tablename . '`');

        // Remove all database entries
        global $wpdb;
        $wpdb->query('delete FROM ' . $wpdb->options . " WHERE option_name like '%wpsynchro%' ");

        // Remove log dir and all files
        global $wpsynchro_container;
        $common = $wpsynchro_container->get('class.CommonFunctions');
        $log_dir = $common->getLogLocation();
        $filelist = scandir($log_dir);
        foreach ($filelist as $file) {
            @unlink($log_dir . '/' . $file);
        }
        @rmdir($log_dir);

        // Thats all, all should be clear
        // kk bye thx
    }
}
