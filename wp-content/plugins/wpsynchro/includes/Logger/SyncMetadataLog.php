<?php
namespace WPSynchro\Logger;

use WPSynchro\Job;

/**
 * Class for handling logging data on sync for use in logs menu (not the logger-logger, but just a log...  :) )
 *
 * @since 1.0.5
 */
class SyncMetadataLog
{
    public function __construct()
    {
    }

    /**
     *  Start a synchronization entry in the log
     *  @since 1.0.5
     */
    public function startSynchronization($job_id, $installation_id, $description)
    {

        // Get logs
        $synclog = $this->getAllLogs();

        // Create the new one
        $newsync = new \stdClass();
        $newsync->start_time = current_time('timestamp');
        $newsync->state = 'started';
        $newsync->description = $description;
        $newsync->job_id = $job_id;
        $newsync->installation_id = $installation_id;

        // If list is above 19 (20+), remove one
        if (count($synclog) > 19) {
            $synclog = array_reverse($synclog);
            $this->removeSingleLogs(array_splice($synclog, 19, 9999));
            $synclog = array_reverse($synclog);
        }

        $synclog[] = $newsync;

        update_option("wpsynchro_sync_logs", $synclog, 'no');
    }

    /**
     *  Mark a synchronization entry as completed
     *  @since 1.0.5
     */
    public function stopSynchronization($job_id, $installation_id)
    {
        $synclog = $this->getAllLogs();
        foreach ($synclog as &$log) {
            if ($log->job_id === $job_id && $log->installation_id === $installation_id) {
                $log->state = "completed";
                update_option("wpsynchro_sync_logs", $synclog, 'no');
                return true;
            }
        }
        return false;
    }

    /**
     *  Mark a synchronization entry as failed
     *  @since 1.6.0
     */
    public function setSynchronizationToFailed($job_id, $installation_id)
    {
        $synclog = $this->getAllLogs();
        foreach ($synclog as &$log) {
            if ($log->job_id === $job_id && $log->installation_id === $installation_id) {
                $log->state = "failed";
                update_option("wpsynchro_sync_logs", $synclog, 'no');
                return true;
            }
        }
        return false;
    }


    /**
     *  Retrieve all log entries
     *  @since 1.0.5
     */
    public function getAllLogs()
    {
        $synclog = get_option("wpsynchro_sync_logs");
        if (!is_array($synclog)) {
            $synclog = array();
        }

        return $synclog;
    }

    /**
     *  Remove list of single logs
     *  @since 1.5.0
     */
    public function removeSingleLogs($logs)
    {
        global $wpsynchro_container;
        $common = $wpsynchro_container->get("class.CommonFunctions");
        $log_dir = $common->getLogLocation();

        foreach ($logs as $log) {
            // Remove data in db
            $option_to_delete = Job::getJobWPOptionName($log->installation_id, $log->job_id);
            delete_option($option_to_delete);

            // Remove associated files
            @unlink($log_dir . $common->getLogFilename($log->job_id));
            @unlink($log_dir . "database_backup_" . $log->job_id . ".sql");
        }
    }

    /**
     *  Remove all log entries
     *  @since 1.5.0
     */
    public function removeAllLogs()
    {
        // Get all current logs
        $logs = $this->getAllLogs();

        // Remove all log files from wpsynchro dir
        global $wpsynchro_container;
        $common = $wpsynchro_container->get("class.CommonFunctions");
        $log_dir = $common->getLogLocation();

        // Clean files *.log, *.sql and *.txt
        @array_map('unlink', glob("$log_dir*.sql"));
        @array_map('unlink', glob("$log_dir*.log"));
        @array_map('unlink', glob("$log_dir*.txt"));

        $options_to_delete = array();
        foreach ($logs as $log) {
            $options_to_delete[] = 'wpsynchro_' . $log->installation_id . '_' . $log->job_id;

            if (count($options_to_delete) > 30) {
                // @codeCoverageIgnoreStart
                $this->deleteLogEntriesInDatabase($options_to_delete);
                $options_to_delete = array();
                // @codeCoverageIgnoreEnd
            }
        }
        if (count($options_to_delete) > 0) {
            $this->deleteLogEntriesInDatabase($options_to_delete);
            $options_to_delete = array();
        }

        update_option("wpsynchro_sync_logs", array(), 'no');
    }

    /**
     *  Delete log entries in database
     *  @since 1.5.0
     */
    public function deleteLogEntriesInDatabase($log_options_to_delete)
    {
        global $wpdb;

        foreach ($log_options_to_delete as $log_option) {
            delete_option($log_option);
        }
    }
}
