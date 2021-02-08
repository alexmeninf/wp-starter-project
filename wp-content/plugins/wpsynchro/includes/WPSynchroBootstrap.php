<?php

namespace WPSynchro;

use WPSynchro\Utilities\Upgrade\DatabaseUpgrade;
use WPSynchro\Updater\PluginUpdater;
use WPSynchro\CLI\WPCLICommand;
use WPSynchro\Utilities\Compatibility\MUPluginHandler;
use WPSynchro\Utilities\JSData\LoadJSData;

/**
 * Primary plugin class
 * Loads all the needed stuff to get the plugin off the ground and make the user a happy panda
 *
 * @since 1.0.0
 */
class WPSynchroBootstrap
{
    /**
     *  Initialize plugin, setting some defines for later use
     *  @since 1.0.0
     */
    public function __construct()
    {
        define('WPSYNCHRO_PLUGIN_DIR', WP_PLUGIN_DIR . '/wpsynchro/');
        define('WPSYNCHRO_PLUGIN_URL', trailingslashit(plugins_url('/wpsynchro')));
    }

    /**
     * Run method, that will kickstart all the needed initialization
     * @since 1.0.0
     */
    public function run()
    {
        // Initialize service controller
        $this->loadServiceController();

        // Check database need update
        if (is_admin()) {
            DatabaseUpgrade::checkDBVersion();
        }

        // Load WP CLI command, if WP CLI request
        if (defined('WP_CLI') && WP_CLI && \WPSynchro\CommonFunctions::isPremiumVersion()) {
            \WP_CLI::add_command('wpsynchro', new WPCLICommand());
        }

        // Load REST API endpoints
        $this->loadRESTApi();

        // Only load backend stuff when needed
        if (is_admin()) {
            if (\WPSynchro\CommonFunctions::isPremiumVersion()) {
                // Check licensing for wp-admin calls, and only if pro version
                global $wpsynchro_container;
                $licensing = $wpsynchro_container->get('class.Licensing');
                $licensing->verifyLicense();

                // Check for updates
                $pluginupdater = new PluginUpdater();
                $pluginupdater->checkForUpdate();
            }

            $this->loadBackendAdmin();
            $this->loadTextdomain();

            // Check if MU plugin needs update
            $muplugin_handler = new MUPluginHandler();
            $muplugin_handler->checkNeedsUpdate();
        }
    }

    /**
     *  Load service controller
     *  @since 1.0.0
     */
    private function loadServiceController()
    {
        ServiceController::init();
    }

    /**
     *  Load admin related functions (menus,etc)
     *  @since 1.0.0
     */
    private function loadBackendAdmin()
    {
        $this->addMenusToBackend();
        $this->addStylesAndScripts();
        $this->loadActions();
    }

    /**
     *  Load REST services used by WP Synchro
     *  Will be loaded always, because its the "server" part of WP Synchro
     *  @since 1.0.0
     */
    private function loadRESTApi()
    {
        $restservices = new \WPSynchro\REST\RESTServices();
        $restservices->setup();
    }

    /**
     *  Load other actions
     *  @since 1.0.3
     */
    private function loadActions()
    {
        add_action('admin_init', function () {
            $dismiss_option = filter_input(INPUT_GET, 'wpsynchro_dismiss_review_request', FILTER_SANITIZE_STRING);
            if (is_string($dismiss_option)) {
                update_site_option('wpsynchro_dismiss_review_request', true);
                wp_die();
            }
        });
    }

    /**
     *  Load text domain
     *  @since 1.0.0
     */
    private function loadTextdomain()
    {
        add_action(
            'init',
            function () {
                load_plugin_textdomain('wpsynchro', false, 'wpsynchro/languages');
            }
        );
    }

    /**
     *   Add menu to backend
     *   @since 1.0.0
     */
    private function addMenusToBackend()
    {
        add_action(
            'admin_menu',
            function () {
                add_menu_page('WP Synchro', 'WP Synchro', 'manage_options', 'wpsynchro_menu', [__NAMESPACE__ . '\\Pages\AdminOverview', 'render'], 'dashicons-update', 76);
                add_submenu_page('wpsynchro_menu', '', '', 'manage_options', 'wpsynchro_menu', '');
                add_submenu_page('wpsynchro_menu', __('Overview', 'wpsynchro'), __('Overview', 'wpsynchro'), 'manage_options', 'wpsynchro_overview', [__NAMESPACE__ . '\\Pages\AdminOverview', 'render']);
                add_submenu_page('wpsynchro_menu', __('Logs', 'wpsynchro'), __('Logs', 'wpsynchro'), 'manage_options', 'wpsynchro_log', [new \WPSynchro\Pages\AdminLog(), 'render']);
                add_submenu_page('wpsynchro_menu', __('Setup', 'wpsynchro'), __('Setup', 'wpsynchro'), 'manage_options', 'wpsynchro_setup', [__NAMESPACE__ . '\\Pages\AdminSetup', 'render']);
                add_submenu_page('wpsynchro_menu', __('Support', 'wpsynchro'), __('Support', 'wpsynchro'), 'manage_options', 'wpsynchro_support', [__NAMESPACE__ . '\\Pages\AdminSupport', 'render']);
                if (\WPSynchro\CommonFunctions::isPremiumVersion()) {
                    add_submenu_page('wpsynchro_menu', __('Licensing', 'wpsynchro'), __('Licensing', 'wpsynchro'), 'manage_options', 'wpsynchro_licensing', [__NAMESPACE__ . '\\Pages\AdminLicensing', 'render']);
                }

                // Run installation page (not in menu)
                add_submenu_page('wpsynchro_menu', '', '', 'manage_options', 'wpsynchro_run', [__NAMESPACE__ . '\\Pages\AdminRunSync', 'render']);
                // Add installation page (not in menu)
                add_submenu_page('wpsynchro_menu', '', '', 'manage_options', 'wpsynchro_addedit', [__NAMESPACE__ . '\\Pages\AdminAddEdit', 'render']);
            }
        );
    }

    /**
     *   Add CSS and JS to backend
     *   @since 1.0.0
     */
    private function addStylesAndScripts()
    {
        // Admin scripts
        add_action(
            'admin_enqueue_scripts',
            function ($hook) {
                if (strpos($hook, 'wpsynchro') > -1) {
                    global $wpsynchro_container;
                    $commonfunctions = $wpsynchro_container->get('class.CommonFunctions');

                    wp_enqueue_script('wpsynchro_admin_js', $commonfunctions->getAssetUrl('main.js'), [], WPSYNCHRO_VERSION, true);

                    // Load standard data we need
                    (new LoadJSData())->load();
                }
            }
        );

        // Admin styles
        add_action('admin_enqueue_scripts', function ($hook) {
            if (strpos($hook, 'wpsynchro') > -1) {
                global $wpsynchro_container;
                $commonfunctions = $wpsynchro_container->get('class.CommonFunctions');
                wp_enqueue_style('wpsynchro_admin_css', $commonfunctions->getAssetUrl('main.css'), [], WPSYNCHRO_VERSION);
            }
        });
    }
}
