<?php

/**
 * Class for handling REST service "PopulateFileListStatus" - Get status of file population
 * Call should already be verified by permissions callback
 * @since 1.6.0
 */

namespace WPSynchro\REST;

use WPSynchro\Transport\TransferAccessKey;
use WPSynchro\Files\PopulateFileListState;
use WPSynchro\Transport\TransferFile;

class PopulateFileListStatus
{

    public $state = null;
    public $basepath = null;
    // Result to be send back
    public $result = null;
    // last downloaded id
    public $last_download_id = 0;

    public function __construct()
    {
        // Initialize return data
        $this->result = new \stdClass();
    }

    public function service($request)
    {
        // Get transfer object, so we can get data
        global $wpsynchro_container;
        $transfer = $wpsynchro_container->get("class.Transfer");
        $transfer->setEncryptionKey(TransferAccessKey::getAccessKey());
        $transfer->populateFromString($request->get_body());
        $body = $transfer->getDataObject();

        // Extract parameters
        $section = $body->section;
        $type = $body->type;
        $jobid = $body->requestid;

        // Do some setup
        if ($type == "source") {
            $this->basepath = $section->source_basepath;
        } else {
            $this->basepath = $section->target_basepath;
        }

        // Figure out where we are in the process
        $this->state = get_option('wpsynchro_filepopulation_current');

        // Add debugs and errors to result
        $this->setErrorsAndDebugs();

        // Make sure we are checking with the right job and section
        if (is_object($this->state) && $this->state->jobid == $jobid && $this->state->section_id == $section->id) {
            // We are the right place, so return the state
            $this->result->state = $this->state;
            // Get any errors

            // If it is completed, return file list
            $this->last_download_id  = intval(get_option("wpsynchro_filepopulation_current_download_id"));
            $this->result->filelist = $this->getFileList(990, $this->last_download_id);
            update_option("wpsynchro_filepopulation_current_download_id", $this->last_download_id, false);
        }

        global $wpsynchro_container;
        $returnresult = $wpsynchro_container->get('class.ReturnResult');
        $returnresult->init();
        $returnresult->setDataObject($this->result);
        return $returnresult->echoDataFromRestAndExit();
    }

    /**
     * Get file list on completion, in parts
     * @since 1.4.0
     */
    public function getFileList($max_count = 990, $id_offset)
    {
        global $wpdb;
        $filelist = array();
        $filelist_sqlresult = $wpdb->get_results($wpdb->prepare("select * from " . $wpdb->prefix . "wpsynchro_file_population_list where id > %d order by id limit %d", $id_offset, $max_count));

        if ($filelist_sqlresult) {
            foreach ($filelist_sqlresult as $filedir) {
                $file = new TransferFile();
                $file->source_file = str_replace($this->basepath, "", $filedir->source_file);
                $file->size = $filedir->size;
                $file->is_dir = $filedir->is_dir == 0 ? false : true;
                $file->hash = $filedir->hash;

                if ($filedir->id > $this->last_download_id) {
                    $this->last_download_id = $filedir->id;
                }

                $filelist[] = $file;
            }
        }

        return $filelist;
    }

    /**
     * Get errors and debug from file population and add to result
     * @since 1.6.1
     */
    public function setErrorsAndDebugs()
    {
        // Get any errors from db
        $logs = get_option("wpsynchro_filepopulation_problems");
        if (!is_array($logs)) {
            $logs = [];
        }

        if (isset($logs["ERROR"]) && count($logs["ERROR"]) > 0) {
            $this->result->errors = $logs["ERROR"];
        }

        if (isset($logs["DEBUG"]) && count($logs["DEBUG"]) > 0) {
            $this->result->debugs = $logs["DEBUG"];
        }

        // Reset logs
        update_option("wpsynchro_filepopulation_problems", [], false);
    }
}
