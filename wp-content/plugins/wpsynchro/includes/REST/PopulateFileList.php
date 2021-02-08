<?php

/**
 * Class for handling REST service "PopulateFileList" - Returns file lists
 * Call should already be verified by permissions callback
 * @since 1.0.3
 */

namespace WPSynchro\REST;

use WPSynchro\Utilities\DatabaseTables;
use WPSynchro\Files\PopulateFileListFilterIterator;
use WPSynchro\Files\PopulateFileListState;
use WPSynchro\Transport\TransferAccessKey;
use WPSynchro\Files\FileHelperFunctions;
use WPSynchro\Transport\TransferFile;
use WPSynchro\Utilities\ErrorHandler\CustomPHPErrorHandler;
use WPSynchro\CommonFunctions;

class PopulateFileList
{
    // ID (generated)
    public $id;
    //
    public $exclusions;
    public $file_list = array();
    public $file_excludes_in_webroot = array();
    public $state = null;
    public $timer = null;
    public $basepath = null;
    // Debugs
    public $debugs = [];
    // Dependencies
    public $errorhandler = null;
    public $common = null;

    public function __construct()
    {
        // Generate a ID to be used for logging and such
        $this->id = uniqid();

        // Start custom error handler
        $this->errorhandler = new CustomPHPErrorHandler();
        $this->errorhandler->addErrorHandler();
        $this->errorhandler->instant_callable = [$this, "addLog"];

        // Init timer
        global $wpsynchro_container;
        $this->timer = $wpsynchro_container->get("class.SyncTimerList");
        $this->timer->init();

        // Common functions
        $this->common = new CommonFunctions();
    }

    public function service($request)
    {
        global $wpsynchro_container;
        // Get transfer object, so we can get data
        $transfer = $wpsynchro_container->get("class.Transfer");
        $transfer->setEncryptionKey(TransferAccessKey::getAccessKey());
        $transfer->populateFromString($request->get_body());
        $body = $transfer->getDataObject();

        // Extract parameters
        $section = $body->section;
        $type = $body->type;
        $allotted_time = $body->allotted_time * 0.9;
        $jobid = $body->requestid;
        $this->exclusions = $body->exclusions;

        // Initialize and get current state
        $already_running = $this->initPopulate($jobid, $section->id);
        if (!$already_running || $this->state->state == 'completed') {
            // If it is already running or completed, we just return
            global $wpsynchro_container;
            $returnresult = $wpsynchro_container->get('class.ReturnResult');
            $returnresult->init();
            $returnresult->setDataObject(null);
            return $returnresult->echoDataFromRestAndExit();
        }

        // Get some helpers
        $this->timer->addOtherSyncTimeLimit($allotted_time);
        $this->file_excludes_in_webroot = FileHelperFunctions::getWPFilesInWebrootToExclude();

        // Do some setup
        if ($type == "source") {
            $this->basepath = $section->source_basepath;
        } else {
            $this->basepath = $section->target_basepath;
        }

        $section->temp_locations_in_basepath = (array) $section->temp_locations_in_basepath;

        // If we are just getting started, set the root entries in database
        if ($this->state->state == 'start') {
            $this->handleStart($section);
        }

        // From this point on, we are in running mode, meaning doing expansion of dirs until no more time
        $this->handleRunning();

        // Update state in db
        $this->state->in_progress = false;
        update_option("wpsynchro_filepopulation_current", $this->state, false);

        global $wpsynchro_container;
        $returnresult = $wpsynchro_container->get('class.ReturnResult');
        $returnresult->init();
        $returnresult->setDataObject(null);

        // Restore error handler
        $this->errorhandler->removeErrorHandler();

        // Add debugs of this run to db
        $this->addLogs("DEBUG", $this->debugs);

        return $returnresult->echoDataFromRestAndExit();
    }

    /**
     * Get status on populate and initialize if needed
     * @since 1.6.0
     */
    public function initPopulate($job_id, $section_id)
    {
        // Figure out where we are in the process
        $this->state = get_option('wpsynchro_filepopulation_current');

        // Needed for compatibility with pre 1.6.0
        if (is_object($this->state) && get_class($this->state) !== PopulateFileListState::class) {
            $this->state = null;
        }

        // Check if we are currently running,  with the right job and section
        if (is_object($this->state) && $this->state->jobid == $job_id && $this->state->section_id == $section_id) {
            $this->debugs[] = "File population state exist with section_id: " . $section_id . " - Checking if it is in progress or if we should run";
            if ($this->state->in_progress) {
                // Return that we should not run
                $this->debugs[] = "Section id: " . $section_id . " is in progress - Aborting";
                return false;
            } else {
                // Set it to running
                $this->debugs[] = "Section id: " . $section_id . " is not running";
                if ($this->state->state !== "completed") {
                    $this->debugs[] = "Section id: " . $section_id . " is not running and not completed, so we are starting";
                    $this->state->in_progress = true;
                    update_option("wpsynchro_filepopulation_current", $this->state, false);
                }
            }
        } else {
            // Does not exist or has wrong jobid or section_id, so create new and clear database
            $this->debugs[] = "Section id: " . $section_id . " has no known state, so we start from scratch";
            $this->state = new PopulateFileListState();
            $this->state->jobid = $job_id;
            $this->state->section_id = $section_id;
            $this->state->in_progress = true;
            update_option("wpsynchro_filepopulation_current", $this->state, false);
            update_option("wpsynchro_filepopulation_current_download_id", 0, false);
            update_option("wpsynchro_filepopulation_problems", [], false);

            // Delete/create database table
            if (!DatabaseTables::createFilePopulationTable()) {
                $this->addLog("ERROR", __("Could not create database table needed for file population on the source site - This is normally because database user does not have access to create tables on the database.", "wpsynchro"));
            }
        }

        return true;
    }

    /**
     * Handle running phase of section population
     * @since 1.4.0
     */
    public function handleRunning()
    {
        $this->debugs[] = "Start running";

        // Get dirs that need expanding
        global $wpdb;

        // Run until we have run 3 seconds and then do not start new expand's
        while ($this->timer->getElapsedOverallTimer() < 10) {
            $dir_to_expand = $wpdb->get_row("select * from " . $wpdb->prefix . "wpsynchro_file_population_list where is_expanded=0 and is_dir=1 order by id limit 1");
            if ($dir_to_expand == null) {
                // No more dirs to expand, so we are complete
                $this->debugs[] = "Got no more dirs to expand, so setting section to completed";
                $this->state->state = "completed";
                return;
            }
            $this->debugs[] = "Expanding dir: " . $this->basepath . $dir_to_expand->source_file;

            // Set current item in state, so we can continue from it later
            $resume = false;
            if ($this->state->current_dir_state->id == 0) {
                $this->state->current_dir_state->id = $dir_to_expand->id;
            } else {
                $resume = true;
            }

            // Get files/dirs in dir
            $filter_iterator = $this->getPathIterator($this->basepath . $dir_to_expand->source_file);
            if ($filter_iterator === false) {
                return;
            }
            $fileobj_list = array();
            $is_completed = true;
            $check_timeout_timer = microtime(true);
            foreach ($filter_iterator as $fileinfo) {
                if ($fileinfo->isDot()) {
                    continue;
                }

                // Check if we should continue last session
                if ($resume) {
                    $path = $fileinfo->getPathname();
                    if ($path == $this->state->current_dir_state->current_path) {
                        $resume = false;
                    }
                    continue;
                }

                // Get pathname and set it in state, so we can continue, if we have to break out
                $pathname = $fileinfo->getPathname();
                $this->state->current_dir_state->current_path = $pathname;

                // Get data on this file
                $path = $this->common->fixPath($pathname);
                $fileobj_list[] = $this->getFileObject($path, ($fileinfo->isDir() ? 0 : $fileinfo->getSize()), $fileinfo->isDir());
                $this->state->files_found++;

                // Update database for every X files, so status can pick it up
                if ($this->state->files_found % 1000 == 0) {
                    update_option("wpsynchro_filepopulation_current", $this->state, false);
                }

                // check that file list does not contain more than X files - If so, break out, so we can write to db
                if (count($fileobj_list) > 990) {
                    $is_completed = false;
                    break;
                }

                // check every 2 seconds, if we need to break out because of time, to make sure we can also write to db before going back
                if ((microtime(true) - $check_timeout_timer) > 2) {
                    // Check if we have less than X seconds back and abort if so, completing whatever state we got so far
                    if ($this->timer->getRemainingSyncTime() < 10) {
                        $is_completed = false;
                        break;
                    }
                    $check_timeout_timer = microtime(true);
                }
            }

            // If completed, mark the dir as expanded
            if ($is_completed) {
                $this->setPathExpanded($dir_to_expand->id);
            }

            // Insert data to db
            $this->insertPathsToDB($fileobj_list);

            // Reset current dir, to prevent it continuing
            if ($is_completed) {
                $this->state->resetCurrentDirState();
            }
        }
    }

    /**
     * Get file/dir iterator on single dir
     * @since 1.4.0
     */
    public function getPathIterator($path)
    {
        try {
            $dir_iterator = new \DirectoryIterator($path);
            $filter_iterator = new PopulateFileListFilterIterator($dir_iterator);
            $filter_iterator::$FILTERS = $this->exclusions;
            $filter_iterator::$common = $this->common;
            $filter_iterator::$file_excludes = $this->file_excludes_in_webroot;
            return $filter_iterator;
        } catch (\Throwable $e) { // For PHP 7
            $this->addLog("ERROR", "Error during file population on " . get_home_url() . ": Can't read path: " . $path . ". Reason can be that path does not exist anymore or a permission issue.");
        } catch (\Exception $e) { // For PHP 5
            $this->addLog("ERROR", "Error during file population on " . get_home_url() . ": Can't read path: " . $path . ". Reason can be that path does not exist anymore or a permission issue.");
        }

        return false;
    }

    /**
     * Handle start phase of section population
     * @since 1.4.0
     */
    public function handleStart($section)
    {
        // If we have a preset to move all files, iterate through the file location.
        if ($section->type == "sync_preset_all_files") {
            $this->debugs[] = "Start new with preset all files: " . $this->basepath;
            // If preset is set, populate locations in basepath with all content of web root
            $section->temp_locations_in_basepath = array();
            $filter_iterator = $this->getPathIterator($this->basepath);

            if ($filter_iterator === false) {
                return;
            }

            foreach ($filter_iterator as $fileinfo) {
                if ($fileinfo->isDot()) {
                    continue;
                }

                $section->temp_locations_in_basepath["/" . $fileinfo->getFilename()] = true;
            }
            $this->debugs[] = "All files preset generated list of basepaths: " . print_r($section->temp_locations_in_basepath, true);
        }

        // Check what work we need to do
        $paths = array();
        if (count($section->temp_locations_in_basepath) > 0) {
            foreach ($section->temp_locations_in_basepath as $relativepath => $whatever) {
                $paths[] = trailingslashit($this->basepath) . trim($relativepath, "/");
            }
        } else {
            $paths[] = $this->basepath;
        }

        $this->debugs[] = "All files preset list of paths: " . print_r($paths, true);

        $fileobj_list = array();
        foreach ($paths as $path) {
            $path = $this->common->fixPath($path);
            $found = true;
            if (!file_exists($path)) {
                // Try with utf8_decode
                $found = false;
                if (file_exists(utf8_decode($path))) {
                    $path = utf8_decode($path);
                    $found = true;
                }
            }
            if ($found) {
                $fileobj_list[] = $this->getFileObject($path, (is_dir($path) ? 0 : filesize($path)), is_dir($path));
                $this->debugs[] = "All files preset - Found path: " . $path;
                $this->state->files_found++;
            }
        }

        // Add filelist to database, so we can start digging in
        $this->insertPathsToDB($fileobj_list);

        // Change state to running
        $this->state->state = 'running';
    }

    /**
     * Get standard file structure from data
     * @since 1.4.0
     */
    public function getFileObject($name, $size, $is_dir, $hash = null)
    {

        $file_tmp = new TransferFile();
        $file_tmp->source_file = str_replace($this->basepath, "", $name);
        $file_tmp->size = $size;
        $file_tmp->is_dir = $is_dir;

        if ($hash == null) {
            if ($file_tmp->is_dir) {
                $file_tmp->hash = null;
            } else {
                if ($file_tmp->size == 0) {
                    $file_tmp->hash = 'd41d8cd98f00b204e9800998ecf8427e';
                } else {
                    if (file_exists($name) && is_readable($name)) {
                        $file_tmp->hash = md5_file($name);
                    } else {
                        $file_tmp->hash = "file_not_exist";
                    }
                }
            }
        } else {
            $file_tmp->hash = $hash;
        }

        return $file_tmp;
    }

    /**
     * Insert path to database
     * @since 1.4.0
     */
    public function insertPathsToDB($pathlist)
    {
        global $wpdb;
        $insert_query_part = "INSERT INTO " . $wpdb->prefix . "wpsynchro_file_population_list (source_file, hash, is_expanded, is_dir, size) VALUES ";
        $insert_value_part_arr = array();
        $insert_counter = 0;
        $insert_total_counter = 0;

        foreach ($pathlist as $path) {
            $insert_counter++;
            $insert_total_counter++;
            if ($insert_counter > 995) {
                $wpdb->query($insert_query_part . " " . implode(",", $insert_value_part_arr));
                $insert_counter = 0;
                $insert_value_part_arr = array();
            }

            // Add value part
            $insert_value_part_arr[] = $wpdb->prepare("(%s,%s,%d,%d,%d)", $path->source_file, $path->hash, ($path->is_dir ? 0 : 1), $path->is_dir, $path->size);
        }
        if ($insert_counter > 0) {
            $wpdb->query($insert_query_part . " " . implode(",", $insert_value_part_arr));
        }
    }

    /**
     * Set path as expanded in db
     * @since 1.4.0
     */
    public function setPathExpanded($id)
    {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . "wpsynchro_file_population_list",
            array(
                'is_expanded' => 1,
            ),
            array('id' => $id),
            array(
                '%d'
            ),
            array('%d')
        );
    }

    /**
     *  Add single log
     *  @since 1.6.1
     */
    public function addLog($type, $msg)
    {
        return $this->addLogs($type, [$msg]);
    }

    /**
     *  Add error/debug/other message to database, so it can be picked up by populate status
     *  @since 1.6.1
     */
    public function addLogs($type, $msgs = [])
    {
        if (empty($msgs)) {
            return;
        }

        $log = get_option("wpsynchro_filepopulation_problems");
        if (!is_array($log)) {
            $log = [];
        }

        $hostname = parse_url(get_home_url(), \PHP_URL_HOST);

        foreach ($msgs as $msg) {
            if (!isset($log[$type])) {
                $log[$type] = [];
            }
            $log[$type][] = "[FILE POPULATION " . $this->id . " " . $hostname . "] " . $msg;
        }

        update_option("wpsynchro_filepopulation_problems", $log, false);
    }
}
