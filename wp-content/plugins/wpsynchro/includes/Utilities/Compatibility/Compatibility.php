<?php
namespace WPSynchro\Utilities\Compatibility;

/**
 * Class for handling compatibility
 *
 * @since 1.1.0 
 * 
 * BEWARE: This is referenced from MU plugin, so handle that if moving it or changing filename etc.
 */
class Compatibility
{

    private $accepted_plugins_list = array("wpsynchro/wpsynchro.php");

    public function __construct()
    {
        if ($this->checkURL()) {
            $this->init();
        }
    }

    /**
     *  Check if it is a url we want to handle
     *  @since 1.1.0
     */
    public function checkURL()
    {
        $request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (strpos($request_uri, "wp-json/wpsynchro") > -1) {
            return true;
        } else {
            return false;
        }
    }

    /**
     *  Hook into WP filters to change plugins and themes
     *  @since 1.1.0
     */
    public function init()
    {

        add_filter('option_active_plugins', array($this, 'handlePlugins'));
        add_filter('site_option_active_sitewide_plugins', array($this, 'handlePlugins'));
        add_filter('stylesheet_directory', array($this, 'handleTheme'));
        add_filter('template_directory', array($this, 'handleTheme'));
    }

    /**
     *  Make sure only WP Synchro is loaded
     *  @since 1.1.0
     */
    public function handlePlugins($plugins)
    {
        if (!is_array($plugins) || count($plugins) == 0) {
            return $plugins;
        }

        foreach ($plugins as $key => $plugin) {
            if (!in_array($plugin, $this->accepted_plugins_list)) {
                unset($plugins[$key]);
            }
        }
        return $plugins;
    }

    /**
     *  Make sure a empty theme is loaded
     *  @since 1.1.0
     */
    public function handleTheme()
    {
        $compat_theme_root = trailingslashit(dirname(__FILE__)) . "wpsynchro_compat_theme/";
        return $compat_theme_root;
    }
}
