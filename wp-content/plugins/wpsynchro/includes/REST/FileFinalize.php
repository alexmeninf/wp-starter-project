<?php
namespace WPSynchro\REST;

use WPSynchro\Transport\TransferAccessKey;
use WPSynchro\Files\FileHelperFunctions;

/**
 * Class for handling REST service "FileFinalize"
 * Call should already be verified by permissions callback
 *
 * @since 1.0.0
 */
class FileFinalize
{

    public function service($request)
    {

        // Init timer
        global $wpsynchro_container;
        $timer = $wpsynchro_container->get("class.SyncTimerList");
        $timer->init();

        // Transfer object
        global $wpsynchro_container;
        $common = $wpsynchro_container->get("class.CommonFunctions");
        $transfer = $wpsynchro_container->get("class.Transfer");
        $transfer->setEncryptionKey(TransferAccessKey::getAccessKey());
        $transfer->populateFromString($request->get_body());
        $body = $transfer->getDataObject();

        // Extract parameters  
        $delete = $body->delete;
        $allotted_time = $body->allotted_time;
        $timer->addOtherSyncTimeLimit($allotted_time);

        $result = new \stdClass();
        $result->success = false;
        $result->errors = array();
        $result->warnings = array();
        $result->debugs = array();

        $result->debugs[] = "Finalize REST service: Start finalize with max time: " . $timer->getRemainingSyncTime();

        // remove the old dirs/files
        foreach ($delete as $key => &$deletepath) {
            $filepath = $deletepath->target_file;

            if (!file_exists($filepath) || !is_writable($filepath)) {
                $result->debugs[] = "Finalize REST service: Could not find/change file/dir that is on delete array, so ignoring the file: " . $filepath;
                $deletepath->deleted = true;
                continue;
            }

            $deleted = false;
            if (is_file($filepath)) {
                unlink($filepath);
                $deleted = true;
            } else {
                $delete_result = FileHelperFunctions::removeDirectory($filepath, $timer);
                $result->debugs[] = "Finalize REST service: Starting deleting: " . $filepath;
                if ($delete_result === false) {
                    // Delete did not complete within timeframe
                    $result->debugs[] = "Finalize REST service: Could not complete delete within max time for: " . $filepath;
                } else {
                    $deleted = true;
                }
            }
            if ($deleted) {
                $result->debugs[] = "Finalize REST service:  Deleted " . $filepath;
                $deletepath->deleted = true;
            }

            if (!$timer->shouldContinueWithLastrunTime(3)) {
                $result->debugs[] = "Finalize REST service: File/dir needs to abort due to max execution time";
                break;
            }
        }

        // When all is deleted, we have completed
        $result->debugs[] = "Finalize REST service: File/dir deleted completed";
        $result->delete = $delete;

        global $wpsynchro_container;
        $returnresult = $wpsynchro_container->get('class.ReturnResult');
        $returnresult->init();
        $returnresult->setDataObject($result);
        return $returnresult->echoDataFromRestAndExit();
    }
}
