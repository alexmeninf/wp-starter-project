<?php

namespace WPSynchro;

/**
 * Class for common functions
 *
 * @since 1.0.0
 */
class CommonFunctions
{

    /**
     * Get DB temp table prefix
     * @since 1.0.0
     */
    public function getDBTempTableName()
    {
        return 'wpsyntmp_';
    }

    /**
     * Create log location
     * @since 1.0.0
     */
    public function createLogLocation()
    {
        $logdir = $this->getLogLocation();
        if (!file_exists($logdir)) {
            mkdir($logdir, 0750, true);
        }
        $htaccess_file = trailingslashit($logdir) . ".htaccess";
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "order deny,allow" . PHP_EOL . "deny from all";
            file_put_contents($htaccess_file, $htaccess_content);
        }
        $indexphp_file = trailingslashit($logdir) . "index.php";
        if (!file_exists($indexphp_file)) {
            $indexphp_content = "<?php " . PHP_EOL . "// silence is golden";
            file_put_contents($indexphp_file, $indexphp_content);
        }
    }

    /**
     * Get log location
     * @since 1.0.0
     */
    public function getLogLocation()
    {
        return wp_upload_dir()['basedir'] . "/wpsynchro/";
    }

    /**
     * Get log filename
     * @since 1.0.0
     */
    public function getLogFilename($job_id)
    {
        return "runsync_" . $job_id . ".txt";
    }

    /**
     * Verify php/mysql/wp compatability
     * @since 1.0.0
     */
    public function checkEnvCompatability()
    {
        $errors = [];

        // Check PHP version 
        $required_php_version = "5.6";
        if (version_compare(PHP_VERSION, $required_php_version, '<')) {
            // @codeCoverageIgnoreStart
            $errors[] = sprintf(__("WP Synchro requires PHP version %s or higher - Please update your PHP", "wpsynchro"), $required_php_version);
            // @codeCoverageIgnoreEnd
        }

        // Check MySQL version
        global $wpdb;
        $required_mysql_version = "5.5";
        $mysqlversion = $wpdb->get_var("SELECT VERSION()");
        if (version_compare($mysqlversion, $required_mysql_version, '<')) {
            // @codeCoverageIgnoreStart
            $errors[] = sprintf(__("WP Synchro requires MySQL version %s or higher - Please update your MySQL", "wpsynchro"), $required_mysql_version);
            // @codeCoverageIgnoreEnd
        }

        // Check WP version
        global $wp_version;
        $required_wp_version = "4.9";
        if (version_compare($wp_version, $required_wp_version, '<')) {
            // @codeCoverageIgnoreStart
            $errors[] = sprintf(__("WP Synchro requires WordPress version %s or higher - Please update your WordPress", "wpsynchro"), $required_wp_version);
            // @codeCoverageIgnoreEnd
        }

        return $errors;
    }

    /**
     *  Converts a php.ini settings like 500M to convert to bytes     
     *  @since 1.0.0
     */
    public function convertPHPSizeToBytes($sSize)
    {

        $sSuffix = strtoupper(substr($sSize, -1));
        if (!in_array($sSuffix, array('P', 'T', 'G', 'M', 'K'))) {
            return (float) $sSize;
        }
        $iValue = substr($sSize, 0, -1);
        switch ($sSuffix) {
            case 'P':
                $iValue *= 1024;
                // Fallthrough intended
            case 'T':
                $iValue *= 1024;
                // Fallthrough intended
            case 'G':
                $iValue *= 1024;
                // Fallthrough intended
            case 'M':
                $iValue *= 1024;
                // Fallthrough intended
            case 'K':
                $iValue *= 1024;
                break;
        }
        return (float) $iValue;
    }

    /**
     *  Path fix with convert to forward slash
     *  @since 1.0.3
     */
    public function fixPath($path)
    {
        $path = str_replace("/\\", "/", $path);
        $path = str_replace("\\/", "/", $path);
        $path = str_replace("\\\\", "/", $path);
        $path = str_replace("\\", "/", $path);
        return $path;
    }

    /**
     *  Get asset full url
     *  @since 1.0.3
     */
    public function getAssetUrl($asset)
    {
        static $manifest = null;
        if ($manifest === null) {
            $manifest = json_decode(file_get_contents(WPSYNCHRO_PLUGIN_DIR . '/dist/manifest.json'));
        }

        if (isset($manifest->$asset)) {
            return untrailingslashit(WPSYNCHRO_PLUGIN_URL) . $manifest->$asset;
        } else {
            return "";
        }
    }

    /**
     *  Get and output template file
     *  @since 1.2.0
     */
    public function getTemplateFile($template_filename)
    {
        ob_start();
        include("Templates/" . $template_filename . ".php");
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }

    /**
     *  Get PHP max_execution_time
     *  @since 1.4.0
     */
    public function getPHPMaxExecutionTime()
    {
        $max_execution_time = intval(ini_get('max_execution_time'));
        if ($max_execution_time > 30) {
            $max_execution_time = 30;
        }
        if ($max_execution_time < 1) {
            $max_execution_time = 30;
        }
        return $max_execution_time;
    }

    /**
     *   Check if premium version
     *   @since 1.0.5
     */
    public static function isPremiumVersion()
    {
        static $is_premium = null;

        if ($is_premium === null) {
            // Check if premium version
            if (file_exists(WPSYNCHRO_PLUGIN_DIR . '/.premium')) {
                $is_premium = true;
            } else {
                $is_premium = false;
            }
        }

        return $is_premium;
    }
}
