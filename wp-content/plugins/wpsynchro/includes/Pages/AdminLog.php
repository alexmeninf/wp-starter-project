<?php

/**
 * Class for handling what to show when clicking on log in the menu in wp-admin
 * @since 1.0.0
 */

namespace WPSynchro\Pages;

use WPSynchro\CommonFunctions;
use WPSynchro\Logger\SyncMetadataLog;

class AdminLog
{

    /**
     *  Called from WP menu to show setup
     *  @since 1.0.0
     */
    public function render()
    {
        // If showing log
        if (isset($_REQUEST['showlog']) && isset($_REQUEST['inst'])) {
            $job_id = sanitize_key($_REQUEST['showlog']);
            $inst_id = sanitize_key($_REQUEST['inst']);
            $this->showLog($job_id, $inst_id);
            return;
        }

        // Remove all logs
        if (isset($_REQUEST['removelogs']) && $_REQUEST['removelogs'] == 1) {
            $metalog = new SyncMetadataLog();
            $metalog->removeAllLogs();
            echo "<script>window.location='" . menu_page_url('wpsynchro_log', false) . "';</script>";
            return;
        }

        $removelogs_url = add_query_arg('removelogs', 1, menu_page_url('wpsynchro_log', false));
        $commonfunctions = new CommonFunctions();

        // Get data
        $metadatalog = new SyncMetadataLog();
        $data = $metadatalog->getAllLogs();
        $data = array_reverse($data);

        // Data for JS
        $data_for_js = [
            "logData" => $data,
            "removeAllLogs" => $removelogs_url,
            "showLogUrl" => menu_page_url('wpsynchro_log', false),
            "downloadLogUrl" => rest_url("wpsynchro/v1/downloadlog/?_wpnonce=" . wp_create_nonce('wp_rest')),
        ];
        wp_localize_script('wpsynchro_admin_js', 'wpsynchro_logs_data', $data_for_js);

        $translation_for_js = [
            "pageTitle" => __('Logs', 'wpsynchro'),
            "introText" => __('See your last synchronizations and the result of them. Here you can also download the log file from the synchronization.', 'wpsynchro'),
            "deleteLogsButton" => __('Delete all logs', 'wpsynchro'),
            "tableColumnDate" => __('Synchronization date', 'wpsynchro'),
            "tableColumnStatus" => __('Status', 'wpsynchro'),
            "tableColumnDescription" => __('Description', 'wpsynchro'),
            "tableColumnLogfile" => __('Logfile', 'wpsynchro'),
            "noLogsText" => __('No synchronization are done yet.', 'wpsynchro'),
            "actionShowLog" => __('Show log', 'wpsynchro'),
            "actionDownloadLog" => __('Download log', 'wpsynchro'),
        ];
        wp_localize_script('wpsynchro_admin_js', 'wpsynchro_logs_translations', $translation_for_js);

        // Print content
        echo '<div id="wpsynchro-log" class="wpsynchro"><page-logs></page-logs></div>';
    }

    /**
     *  Show the log file for job
     *  @since 1.0.5
     */
    public function showLog($job_id, $inst_id)
    {
        // Check if file exist        
        $common = new CommonFunctions();  
        global $wpsynchro_container;
        $inst_factory = $wpsynchro_container->get('class.InstallationFactory');

        $logpath = $common->getLogLocation();
        $filename = $common->getLogFilename($job_id);

        $job_obj = get_option("wpsynchro_" . $inst_id . "_" . $job_id, "");
        $inst_obj = $inst_factory->retrieveInstallation($inst_id);


        if (file_exists($logpath . $filename)) {
            $logcontents = file_get_contents($logpath . $filename);

            echo "<h1>Log file for jobid " . $job_id . "</h1> ";
            echo "<div><h3>Beware: Do not share this file with other people than WP Synchro support - It contains data that can compromise your site.</h3></div>";

            echo '<pre>';
            echo $logcontents;
            echo '</pre>';

            echo '<h3>Installation object:</h3>';
            echo '<pre>';
            print_r($inst_obj);
            echo '</pre>';

            echo '<h3>Job object:</h3>';
            echo '<pre>';
            print_r($job_obj);
            echo '</pre>';
        }
    }
}
