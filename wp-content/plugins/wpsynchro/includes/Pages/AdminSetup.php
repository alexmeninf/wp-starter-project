<?php

/**
 * Class for handling what to show when clicking on setup in the menu in wp-admin
 *
 * @since 1.0.0
 */

namespace WPSynchro\Pages;

use WPSynchro\Utilities\Compatibility\MUPluginHandler;
use WPSynchro\CommonFunctions;

class AdminSetup
{
    private $show_update_settings_notice = false;
    private $notices = [];

    /**
     *  Called from WP menu to show setup
     *  @since 1.0.0
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
     *  Handle the update of data from setup screen
     *  @since 1.0.0
     */
    private function handlePOST()
    {
        $this->show_update_settings_notice = true;

        // Save access key
        if (isset($_POST['accesskey'])) {
            $accesskey = sanitize_key($_POST['accesskey']);
        } else {
            $accesskey = '';
        }

        if (strlen($accesskey) > 30) {
            update_option('wpsynchro_accesskey', $accesskey, false);
        }

        // Save methods allowed
        $pull_allowed = (isset($_POST['allow_pull']) ? true : false);
        $push_allowed = (isset($_POST['allow_push']) ? true : false);
        $methodsallowed = new \stdClass();
        $methodsallowed->pull = $pull_allowed;
        $methodsallowed->push = $push_allowed;
        update_option('wpsynchro_allowed_methods', $methodsallowed, false);

        // MU plugin enabled
        $mu_plugin_enable = (isset($_POST['enable_muplugin']) ? true : false);
        $muplugin_handler = new MUPluginHandler();

        if ($mu_plugin_enable) {
            $enable_result = $muplugin_handler->enablePlugin();
            if (is_bool($enable_result) && $enable_result == true) {
                update_option('wpsynchro_muplugin_enabled', 'yes', true);
            } else {
                $this->notices = array_merge($this->notices, $enable_result);
            }
        } else {
            $delete_result = $muplugin_handler->disablePlugin();
            if (is_bool($delete_result) && $delete_result == true) {
                delete_option('wpsynchro_muplugin_enabled');
            } else {
                $this->notices = array_merge($this->notices, $delete_result);
            }
        }

        $slow_hosting_optimize = (isset($_POST['slow_hosting_optimize']) ? true : false);
        if ($slow_hosting_optimize) {
            update_option('wpsynchro_slow_hosting_optimize', 'yes', true);
        } else {
            delete_option('wpsynchro_slow_hosting_optimize');
        }

        // Basic auth
        $basic_auth_username = (isset($_POST['basic_auth_username']) ? $_POST['basic_auth_username'] : '');
        $basic_auth_password = (isset($_POST['basic_auth_password']) ? $_POST['basic_auth_password'] : '');
        if (strlen($basic_auth_username) > 0 && strlen($basic_auth_password) > 0) {
            $basic_auth_arr = [$basic_auth_username, $basic_auth_password];
            update_option('wpsynchro_setup_basic_auth', $basic_auth_arr, false);
        } else {
            delete_option('wpsynchro_setup_basic_auth');
        }
    }

    /**
     *  Show WP Synchro setup screen
     *  @since 1.0.0
     */
    private function handleGET()
    {
        $accesskey = get_option('wpsynchro_accesskey');
        $methodsallowed = get_option('wpsynchro_allowed_methods');
        if (!$methodsallowed) {
            $methodsallowed = new \stdClass();
            $methodsallowed->pull = false;
            $methodsallowed->push = false;
        }

        $enable_muplugin = get_option('wpsynchro_muplugin_enabled');
        if ($enable_muplugin && strlen($enable_muplugin) > 0) {
            $enable_muplugin = true;
        } else {
            $enable_muplugin = false;
        }

        $enable_slow_hosting_optimize = get_option('wpsynchro_slow_hosting_optimize');
        if ($enable_slow_hosting_optimize && strlen($enable_slow_hosting_optimize) > 0) {
            $enable_slow_hosting_optimize = true;
        } else {
            $enable_slow_hosting_optimize = false;
        }

        $basic_auth = get_option('wpsynchro_setup_basic_auth');
        if ($basic_auth && count($basic_auth) > 0) {
            $basic_auth_username = $basic_auth[0];
            $basic_auth_password = $basic_auth[1];
        } else {
            $basic_auth_username = '';
            $basic_auth_password = '';
        }

        $commonfunctions = new CommonFunctions();

?>

        <div class="wrap wpsynchro-setup wpsynchro">
            <h2 class="pagetitle"><img src="<?= $commonfunctions->getAssetUrl("icon.png") ?>" width="35" height="35" />WP Synchro <?= WPSYNCHRO_VERSION ?> <?php echo (\WPSynchro\CommonFunctions::isPremiumVersion() ? 'PRO' : 'FREE'); ?> - <?php _e('Setup', 'wpsynchro'); ?></h2>

            <?php
            if ($this->show_update_settings_notice) {
                if (count($this->notices) > 0) {
            ?>
                    <div class="notice notice-error wpsynchro-notice">
                        <?php
                        foreach ($this->notices as $notice) {
                            echo '<p>' . $notice . '</p>';
                        } ?>
                    </div>
                <?php
                } else {
                ?>
                    <div class="notice notice-success wpsynchro-notice">
                        <p><?php _e('WP Synchro settings are now updated', 'wpsynchro'); ?></p>
                    </div>
            <?php
                }
            } ?>

            <p><?php _e('The general configuration of WP Synchro.', 'wpsynchro'); ?></p>

            <form id="wpsynchro-setup-form" method="POST">
                <div class="sectionheader"><span class="dashicons dashicons-admin-tools"></span> <?php _e('Configure settings', 'wpsynchro'); ?></div>
                <table class="">
                    <tr>
                        <td><label for="name"><?php _e('Access key', 'wpsynchro'); ?></label> <span title="<?= __('Configure the access key used for accessing this installation from remote. Treat the access key like a password and keep it safe from others.', 'wpsynchro') ?>" class="dashicons dashicons-editor-help"></span></td>
                        <td>
                            <input type="text" name="accesskey" id="wp_synchro_accesskey" value="<?php echo $accesskey; ?>" class="regular-text ltr" readonly> <button id="generate_new_access_key" class="wpsynchrobutton"><?php _e('Generate new access key', 'wpsynchro'); ?></button>
                        </td>
                    </tr>
                    <tr>
                        <td><?php _e('Allowed methods', 'wpsynchro'); ?></td>
                        <td>
                            <label><input type="checkbox" name="allow_pull" id="allow_pull" <?php echo ($methodsallowed->pull ? ' checked ' : ''); ?> /> <?php _e('Allow pull - Allow this site to be downloaded', 'wpsynchro'); ?></label><br>
                            <label><input type="checkbox" name="allow_push" id="allow_push" <?php echo ($methodsallowed->push ? ' checked ' : ''); ?> /> <?php _e('Allow push - Allow this site to be overwritten', 'wpsynchro'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <td><?php _e('Optimize compatibility', 'wpsynchro'); ?></td>
                        <td>
                            <label><input type="checkbox" name="enable_muplugin" id="enable_muplugin" <?php echo ($enable_muplugin ? ' checked ' : ''); ?>> <?php _e('Enable MU Plugin to optimize WP Synchro requests (recommended)', 'wpsynchro'); ?> <?= (is_multisite() ? __('(Network wide)', 'wpsynchro') : '')  ?></label><br>
                        </td>
                    </tr>
                    <tr>
                        <td><?php _e('Slow hosting', 'wpsynchro'); ?> <span title="<?= __('Timeouts may happen if the two servers are geographically far apart or under heavy load. This can be enabled to better handle this and allow for large timeout margins.', 'wpsynchro') ?>" class="dashicons dashicons-editor-help"></span></td>
                        <td>
                            <label><input type="checkbox" name="slow_hosting_optimize" id="slow_hosting_optimize" <?php echo ($enable_slow_hosting_optimize ? ' checked ' : ''); ?> /> <?php _e('Optimize for slow hosting or slow connections between servers', 'wpsynchro'); ?></label>
                        </td>
                    </tr>

                    <tr>
                        <td><?php _e('Basic authentication', 'wpsynchro'); ?> <span title="<?= __('If this site is protected by Basic Authentication access protection, you should fill out the username and password here. WP Synchro will use it to call local services for all synchronizations.', 'wpsynchro') ?>" class="dashicons dashicons-editor-help"></span></td>
                        <td>
                            <input type="text" name="basic_auth_username" value="<?= $basic_auth_username ?>" autocomplete="off" data-lpignore="true" placeholder="<?php _e('username', 'wpsynchro'); ?>">
                            <input type="password" name="basic_auth_password" value="<?= $basic_auth_password ?>" autocomplete="off" data-lpignore="true" placeholder="<?php _e('password', 'wpsynchro'); ?>">
                        </td>
                    </tr>

                </table>
                <p><input type="submit" value="<?php _e('Save settings', 'wpsynchro'); ?>" /></p>

            </form>

        </div>
<?php
    }
}
