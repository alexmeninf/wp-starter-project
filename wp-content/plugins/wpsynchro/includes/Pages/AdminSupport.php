<?php

/**
 * Class for handling what to show when clicking on support in the menu in wp-admin
 * @since 1.0.3
 */

namespace WPSynchro\Pages;

use WPSynchro\CommonFunctions;

class AdminSupport
{
    private $show_delete_settings_notice = false;

    /**
     *  Called from WP menu to show support
     *  @since 1.0.3
     */
    public static function render()
    {
        $instance = new self;
        // Handle post
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $instance->handlePOST();
        }
        $instance->handleGET();
    }

    /**
     *  Handle the update of data from support screen
     *  @since 1.0.3
     */
    private function handlePOST()
    {
        // Check if it is delete settings
        if (isset($_POST['deletesettings']) && $_POST['deletesettings'] == 1) {
            $this->cleanUpPluginInstallation();
            $this->show_delete_settings_notice = true;
            return;
        }
    }

    /**
     *  Show WP Synchro support screen
     *  @since 1.0.3
     */
    private function handleGET()
    {
        global $wpsynchro_container;
        $debug_obj = $wpsynchro_container->get('class.DebugInformation');
        $debug_json = $debug_obj->getJSONDebugInformation();

        if (\WPSynchro\CommonFunctions::isPremiumVersion()) {
            // Licensing
            $licensing = $wpsynchro_container->get('class.Licensing');
        } 
        
        $commonfunctions = new CommonFunctions();

        ?>
        <div id="wpsynchro-support" class="wrap wpsynchro">
            <h2 class="pagetitle"><img src="<?= $commonfunctions->getAssetUrl("icon.png") ?>" width="35" height="35" />WP Synchro <?= WPSYNCHRO_VERSION ?> <?php echo(\WPSynchro\CommonFunctions::isPremiumVersion() ? 'PRO' : 'FREE'); ?> - <?php _e('Support', 'wpsynchro'); ?></h2>

            <?php
            if ($this->show_delete_settings_notice) {
                ?>
                <div class="notice notice-success wpsynchro-notice">
                    <p><?php _e('WP Synchro data clean up completed - It is nice and clean now', 'wpsynchro'); ?></p>
                </div>
                <?php
            } ?>

            <p><?php _e('Here is how you get help on a support issue for WP Synchro.', 'wpsynchro'); ?></p>
            <div class="sectionheader"><span class="dashicons dashicons-lightbulb"></span> <?php _e('Getting support', 'wpsynchro'); ?></div>
            <?php
            if (\WPSynchro\CommonFunctions::isPremiumVersion() && $licensing->verifyLicense()) {
                ?>
                <p><?php _e('You are on the PRO version with a validated license, so you have access to priority email support.', 'wpsynchro'); ?></p>
                <p><?php _e('Contact us on', 'wpsynchro'); ?> <a href="mailto:support@wpsynchro.com">support@wpsynchro.com</a>.</p>
                <p><?php _e('Be sure to include relevant information, such as:', 'wpsynchro'); ?></p>

                <ul>
                    <li> - <?php _e('Description of problem(s)', 'wpsynchro'); ?></li>
                    <li> - <?php _e('Screenshot of problem(s)', 'wpsynchro'); ?></li>
                    <li> - <?php _e('Result of Health check just below', 'wpsynchro'); ?></li>
                    <li> - <?php _e('Log file from synchronization (found in menu "Logs")', 'wpsynchro'); ?></li>                    
                </ul>
                <p><?php _e('We will then get back to you as soon as we have investigated and we will ask for further information if needed.', 'wpsynchro'); ?></p>

                <?php
            } else {
                ?>
                <p><?php _e('You are using the free version of WP Synchro, which we also provide email support for.', 'wpsynchro'); ?></p>
                <p><?php _e('Users on the PRO version have priority support, so free version support requests can take more time depending on support load.<br>Check out <a href="https://wpsynchro.com" target="_blank">https://wpsynchro.com</a> on how to get the PRO version. The PRO version also contains more useful features, such as synchronizing files.', 'wpsynchro'); ?></p>
                <p><?php _e('If you just have a bug report, security issue or a good idea for WP Synchro, we would still like to hear from you.', 'wpsynchro'); ?></p>
                <p><?php _e('Contact us on', 'wpsynchro'); ?> <a href="mailto:support@wpsynchro.com">support@wpsynchro.com</a>.</p>
                <p><?php _e('Be sure to include relevant information, such as:', 'wpsynchro'); ?></p>

                <ul>
                    <li> - <?php _e('Description of problem(s)', 'wpsynchro'); ?></li>
                    <li> - <?php _e('Screenshot of problem(s)', 'wpsynchro'); ?></li>
                    <li> - <?php _e('Result of Health check just below', 'wpsynchro'); ?></li>
                    <li> - <?php _e('Log file from synchronization (found in menu "Logs")', 'wpsynchro'); ?></li>                    
                </ul>
                <?php
            } ?>              

            <div class="sectionheader"><span class="dashicons dashicons-awards"></span> <?php _e('Health check', 'wpsynchro'); ?></div>

            <healthcheck showinline></healthcheck>

            <div class="sectionheader"><span class="dashicons dashicons-admin-generic"></span> <?php _e('Debug JSON information', 'wpsynchro'); ?></div>
            <p><?php _e('Contains debug information about the installation, such as configuration and file locations. No personal information is included.', 'wpsynchro'); ?></p>
            <textarea class="debugjson"><?php echo $debug_json; ?></textarea>

            <div class="sectionheader"><span class="dashicons dashicons-no"></span> <?php _e('Delete WP Synchro data', 'wpsynchro'); ?></div>
            <p><?php _e('Delete all data related to WP Synchro, in database and files. Can be used to clean up after WP Synchro if needed.', 'wpsynchro'); ?><br><?php _e('Does not reset access key and license key setup, but removes data like log files and installations.', 'wpsynchro'); ?></p>

            <form  method="POST" >
                <input type="hidden" name="deletesettings" value="1" />
                <p><button type="submit" class="deletesettingsbutton" /><?php _e('Delete all WP Synchro data', 'wpsynchro'); ?></button></p>

            </form>


        </div>
        <?php
    }

    /**
    *  Clean up WP Synchro installation (used in setup)
    */
    public function cleanUpPluginInstallation()
    {
        global $wpsynchro_container;        
        $common = $wpsynchro_container->get('class.CommonFunctions');

        // Setup
        $log_dir = $common->getLogLocation();
        $db_prefix = 'wpsynchro_';
    
        // Clean files
        @array_map('unlink', glob("$log_dir*.log"));
        @array_map('unlink', glob("$log_dir*.sql"));
        @array_map('unlink', glob("$log_dir*.txt"));
        @array_map('unlink', glob("$log_dir*.tmp"));

        // Delete from database
        $options_to_keep = [
            'wpsynchro_license_key',
            'wpsynchro_dbversion',
            'wpsynchro_accesskey',
            'wpsynchro_allowed_methods',
            'wpsynchro_muplugin_enabled',
            'wpsynchro_debuglogging_enabled',
        ];

        global $wpdb;
        $wpdb->query('delete FROM ' . $wpdb->options . " WHERE option_name like '" . $db_prefix . "%' and option_name not in ('" . implode("','", $options_to_keep) . "') ");
    }
}
