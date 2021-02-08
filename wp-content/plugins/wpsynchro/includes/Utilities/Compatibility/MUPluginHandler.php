<?php
namespace WPSynchro\Utilities\Compatibility;

/**
 * Class for handling installing/remove/updating MU plugin
 *
 * @since 1.1.0 
 *
 */
class MUPluginHandler
{

    private $errors = array();
    private $plugin_file = "wpsynchro-mu-plugin-compat.php";

    public function __construct()
    {
        
    }

    /**
     *  Enable MU Plugin
     *  @since 1.1.0       
     */
    public function enablePlugin()
    {

        $this->copyAndEnable();

        if (count($this->errors) > 0) {
            return $this->errors;
        } else {
            return true;
        }
    }

    /**
     *  Disable MU plugin
     *  @since 1.1.0       
     */
    public function disablePlugin()
    {

        $this->disableAndDelete();

        if (count($this->errors) > 0) {
            return $this->errors;
        } else {
            return true;
        }
    }

    /**
     *  Copy and enable MU plugin code
     *  @since 1.1.0       
     */
    private function copyAndEnable()
    {
        // Check if MU dir exists
        if (!file_exists(WPMU_PLUGIN_DIR)) {
            // Create it
            $mkdir_result = wp_mkdir_p(WPMU_PLUGIN_DIR);
            if (!$mkdir_result) {
                $this->errors[] = sprintf(__("MU plugin dir %s could not be created - It is probably a permission issue", "wpsynchro"), WPMU_PLUGIN_DIR);
                return;
            }
        }

        // Check for writable
        if (!is_writeable(WPMU_PLUGIN_DIR)) {
            $this->errors[] = sprintf(__("MU plugin dir %s is not writable - It is probably a permission issue", "wpsynchro"), WPMU_PLUGIN_DIR);
            return;
        }

        // Check if it already exist
        $plugin_path_source = trailingslashit(dirname(__FILE__)) . $this->plugin_file;
        $plugin_path_target = trailingslashit(WPMU_PLUGIN_DIR) . $this->plugin_file;

        if (file_exists($plugin_path_target)) {
            // Already installed i guess, so just return
            return;
        }

        // Copy plugin file
        $copy_result = copy($plugin_path_source, $plugin_path_target);
        if (!$copy_result) {
            $this->errors[] = sprintf(__("MU plugin %s could not be copied to MU plugin dir - It is probably a permission issue", "wpsynchro"), WPMU_PLUGIN_DIR);
        }
    }

    /**
     *  Delete MU plugin
     *  @since 1.1.0       
     */
    private function disableAndDelete()
    {
        $plugin_full_path = trailingslashit(WPMU_PLUGIN_DIR) . $this->plugin_file;
        if (!file_exists($plugin_full_path)) {
            // Doesnt exist, so np
            return;
        }

        $delete_result = unlink($plugin_full_path);
        if (!$delete_result) {
            $this->errors[] = sprintf(__("MU plugin %s could not be deleted - It is probably a permission issue", "wpsynchro"), $plugin_full_path);
        }
    }

    /**
     *  Check if MU plugin needs update and update if needed
     *  @since 1.1.0       
     */
    public function checkNeedsUpdate()
    {
        $enable_muplugin = get_option('wpsynchro_muplugin_enabled');
        if ($enable_muplugin && strlen($enable_muplugin) > 0) {
            if (defined("WPSYNCHRO_NEWEST_MU_COMPATIBILITY_VERSION") && defined("WPSYNCHRO_MU_COMPATIBILITY_VERSION")) {
                if (WPSYNCHRO_NEWEST_MU_COMPATIBILITY_VERSION != WPSYNCHRO_MU_COMPATIBILITY_VERSION) {
                    $this->disableAndDelete();
                    $this->enablePlugin();
                }
            } else {
                // Enable, but is not running in MU plugin, so copy it
                $this->enablePlugin();
            }
        }
    }
}
