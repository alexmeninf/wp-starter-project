<?php

/**
 * Class for handling the finalization of the sync
 * @since 1.0.0
 */

namespace WPSynchro\Finalize;

use WPSynchro\Files\SyncList;
use WPSynchro\Database\DatabaseFinalize;

class FinalizeSync
{

    // Base data
    private $job = null;
    private $installation = null;
    public $timer = null;

    /**
     *  Run finalize method
     *  @since 1.0.0
     */
    public function runFinalize(&$installation, &$job)
    {
        // Timer
        global $wpsynchro_container;
        $this->timer = $wpsynchro_container->get("class.SyncTimerList");

        $this->installation = &$installation;
        $this->job = &$job;

        $databasefinalize = new DatabaseFinalize();

        // Init logging
        global $wpsynchro_container;
        $logger = $wpsynchro_container->get("class.Logger");
        $logger->log("INFO", "Starting finalize - Remaining time: " . $this->timer->getRemainingSyncTime());

        $this->job->finalize_progress = 10;

        /**
         *  Check what we need to do
         */
        if (!$this->installation->sync_files) {
            $this->job->finalize_files_completed = true;
            $this->job->finalize_progress += 45;
        }
        if (!$this->installation->sync_database) {
            $this->job->finalize_db_completed = true;
            $this->job->finalize_progress += 45;
        }

        /**
         *  If we have errors, return
         */
        if (count($this->job->errors) > 0) {
            return;
        }

        /**
         *  Files finalize
         */
        if (!$this->job->finalize_files_completed) {
            $this->finalizefiles();
            if ($this->job->finalize_files_completed) {
                $this->job->finalize_progress += 45;
            }
            return;
        }

        /**
         *  DB finalize
         */
        if (!$this->job->finalize_db_completed) {
            $databasefinalize->finalize($this->installation, $this->job);
            $this->job->finalize_progress += floor(45 * $databasefinalize->getPercentCompletedForDatabaseFinalize());
            return;
        }

        $this->job->finalize_progress = 100;

        $logger->log("INFO", "Completed finalize - remaining time: " . $this->timer->getRemainingSyncTime());

        if ($this->job->finalize_files_completed && $this->job->finalize_db_completed) {

            // Execute last actions on target
            $logger->log("INFO", "Execute last actions on target - remaining time: " . $this->timer->getRemainingSyncTime());
            $this->finalizeActionsOnTarget();
            $logger->log("INFO", "Completed last actions on target - remaining time: " . $this->timer->getRemainingSyncTime());

            // Update progress
            $this->job->finalize_progress = 100;
            $this->job->finalize_completed = true;
            $this->job->finalize_progress_description = "";

            // Update option with counted success times
            $success_count = get_site_option("wpsynchro_success_count", 0);
            $success_count++;
            update_site_option("wpsynchro_success_count", $success_count);
        }
    }


    /**
     *  Finalize files
     *  @since 1.0.3
     */
    private function finalizefiles()
    {

        global $wpsynchro_container;

        $sync_list = new SyncList();
        $sync_list->init($this->installation, $this->job);

        $finalize_files_handler = $wpsynchro_container->get("class.FinalizeFiles");
        $finalize_files_handler->init($sync_list, $this->installation, $this->job);

        $finalize_files_handler->finalizeFiles();
    }

    /**
     *  Finalize actions on target
     *  @since 1.6.0
     */
    private function finalizeActionsOnTarget()
    {

        $actions_to_run = ["cleartransfertoken"];
        if ($this->installation->clear_cache_on_success === true) {
            array_unshift($actions_to_run, "clearcaches");
        }

        $url = $this->job->to_rest_base_url . "wpsynchro/v1/executeaction/";

        // Get remote transfer object
        global $wpsynchro_container;
        $remotetransport = $wpsynchro_container->get('class.RemoteTransfer');
        $remotetransport->init();
        $remotetransport->setUrl($url);
        $remotetransport->setDataObject($actions_to_run);
        $action_result = $remotetransport->remotePOST();

        if (!$action_result->isSuccess()) {
            $this->job->warnings[] = __("Some finalize actions failed to run - This can be cache clearing error or other error from target site. This normally does not impact the synchronization that much and therefore just a warning.", "wpsynchro");
        }
    }
}
