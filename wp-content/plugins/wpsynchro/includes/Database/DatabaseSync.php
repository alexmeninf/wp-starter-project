<?php

namespace WPSynchro\Database;

/**
 * Class for handling database synchronization
 * @since 1.0.0
 */
class DatabaseSync
{
    // Data objects
    public $job = null;
    public $installation = null;
    public $logger = null;
    // Table prefix
    public $table_prefix = '';
    // Timers and limits
    public $timer = null;
    // PHP/MySQL limits
    public $max_allowed_packet_length = 0;
    public $max_post_request_length = 0;
    public $memory_limit = 0;
    public $max_time_per_sync = 0;
    public $max_response_length = 0;
    // Search/replaces
    public $searchreplaces = [];
    public $searchreplace_count = 0;
    // Using mysqli/mysql
    public $use_mysqli = false;

    /**
     * Constructor
     * @since 1.0.0
     */
    public function __construct()
    {
        if (function_exists('mysqli_connect')) {
            $this->use_mysqli = true;
        }

        global $wpsynchro_container;
        $this->logger = $wpsynchro_container->get('class.Logger');
    }

    /**
     * Start a synchronization chunk - Returns completion percent
     * @since 1.0.0
     */
    public function runDatabaseSync(&$installation, &$job)
    {
        // Start timer
        global $wpsynchro_container;
        $this->timer = $wpsynchro_container->get('class.SyncTimerList');

        $this->installation = &$installation;
        $this->job = &$job;

        $this->logger->log('INFO', 'Starting database synchronization loop with remaining time: ' . $this->timer->getRemainingSyncTime());

        // Get common library
        global $wpsynchro_container;
        $commonfunctions = $wpsynchro_container->get('class.CommonFunctions');
        $this->table_prefix = $commonfunctions->getDBTempTableName();

        // Prepare sync data
        $this->prepareSyncData();

        // Check preflight errors
        if (count($this->job->errors) > 0) {
            return;
        }

        // Now, do some work
        $lastrun_time = 2;
        while ($this->timer->shouldContinueWithLastrunTime($lastrun_time)) {
            $nomorework = true;
            foreach ($this->job->from_dbmasterdata as &$table) {
                if (isset($table->is_completed)) {
                    $table->rows = $table->completed_rows;
                } else {
                    // Pre processing throttling stuff
                    $this->handlePreProcessingThrottling($table);

                    // Call proper service to get/send data depending on pull/push
                    $lastrun_timer = $this->timer->startTimer('databasesync', 'while', 'lastrun');

                    if ($this->installation->type == 'pull') {
                        $result_from_remote_service = $this->retrieveDataFromRemoteService($table);
                    } elseif ($this->installation->type == 'push') {
                        $result_from_remote_service = $this->sendDataToRemoteService($table);
                    }

                    $table->completed_rows += $result_from_remote_service;
                    if ($table->completed_rows > $table->rows) {
                        $table->rows = $table->completed_rows;
                    }
                    $nomorework = false;

                    // Throttling
                    $lastrun_time = $this->timer->getElapsedTimeToNow($lastrun_timer);
                    $this->handlePostProcessingThrottling($lastrun_time);
                    $this->logger->log('DEBUG', 'Lastrun in : ' . $lastrun_time . ' seconds - rows throttle: ' . $this->job->db_rows_per_sync . ' and remaining time: ' . $this->timer->getRemainingSyncTime());
                    // Break out to test if we have time for more
                    break;
                }
            }

            // Recalculate completion and update state in job
            $this->updateCompletionStatusPercent();

            // If no more work, mark as completed
            if ($nomorework) {
                $this->job->database_completed = true;
                break;
            }

            // Save status to DB
            $this->job->save();
        }

        $this->logger->log('INFO', 'Ending database synchronization loop with remaining time: ' . $this->timer->getRemainingSyncTime() . ' seconds');
    }

    /**
     * Prepare and fetch data for sync
     * @since 1.0.0
     */
    private function prepareSyncData()
    {
        // Determine max time per sync
        $this->max_time_per_sync = ceil($this->timer->getSyncMaxExecutionTime() / 5);
        if ($this->max_time_per_sync > 10) {
            $this->max_time_per_sync = 10;
        }

        /**
         *  Check the search/replace's
         */

        // Get search replaces from installation
        $this->searchreplaces = $this->installation->searchreplaces;

        // Add system search/replaces
        $this->searchreplaces = array_merge($this->searchreplaces, $this->job->db_system_search_replaces);

        // Ignore all search/replaces - Only used for testing purposes
        if ($this->installation->ignore_all_search_replaces) {
            $this->searchreplaces = [];
        }

        $this->searchreplace_count = count($this->searchreplaces);
        $this->logger->log('DEBUG', 'Search/replaces:', $this->searchreplaces);

        // Remove tables from dbdata, if not all tables should be synced
        if ($this->installation->include_all_database_tables === false) {
            $onlyinclude = $this->installation->only_include_database_table_names;
            $newdbdata = [];
            foreach ($this->job->from_dbmasterdata as $table) {
                if (in_array($table->name, $onlyinclude)) {
                    $newdbdata[] = $table;
                }
            }
            $this->job->from_dbmasterdata = $newdbdata;
        }

        // Set max length limits for POST requests and Max allowed packet to MySQL - Determined from the smallest on the clients - And subtract 1000 bytes for safety distance
        $this->max_allowed_packet_length = min($this->job->from_max_allowed_packet_size, $this->job->to_max_allowed_packet_size);
        $this->max_post_request_length = min($this->job->from_max_post_size, $this->job->to_max_post_size) * 0.9;
        $this->memory_limit = (min($this->job->from_memory_limit, $this->job->to_memory_limit) - memory_get_peak_usage()) * 0.7;

        // Set max allowed packet to smallest of all these numbers
        $this->max_allowed_packet_length = min($this->max_allowed_packet_length, $this->max_post_request_length, $this->memory_limit) * 0.8;

        // Check if first run
        if (!$this->job->db_first_run_setup) {
            $this->createTablesOnRemoteDatabase();
            $this->job->db_first_run_setup = true;
        }
    }

    /**
     *  Handle pre processing throttling of rows based on time per sync
     *  @since 1.0.0
     */
    private function handlePreProcessingThrottling($table)
    {
        // If table is different than last time this ran
        if ($table->name != $this->job->db_throttle_table) {
            $this->job->db_throttle_table = $table->name;
            $this->job->db_rows_per_sync = $this->job->db_rows_per_sync_default;

            // Check if table rows will get to big, so we have to start lower
            if (($this->job->db_rows_per_sync * $table->row_avg_bytes) > $this->job->db_response_size_wanted_default) {
                $this->job->db_rows_per_sync = floor($this->job->db_response_size_wanted_default / $table->row_avg_bytes);
                if ($this->job->db_rows_per_sync < 10) {
                    $this->job->db_rows_per_sync = 10;
                }
            }

            // Check if new table has blobs, so lets start with a lower rows per sync, because they can be big
            if (count($table->column_types->binary) > 0) {
                $this->job->db_rows_per_sync = 20;
            }

            $this->logger->log('INFO', 'New table is started: ' . sanitize_text_field($table->name) . ' and setting new default rows per sync: ' . $this->job->db_rows_per_sync);
        }
    }

    /**
     *  Handle post processing throttling of rows based on time per sync
     *
     *  @since 1.0.0
     */
    private function handlePostProcessingThrottling($lastrun_time)
    {
        // Check if we are too close to max memory (aka handling too large datasets and risking outofmemory) - One time thing per run
        $current_peak = memory_get_peak_usage();
        static $has_backed_off = false;
        if (!$has_backed_off && $current_peak > $this->memory_limit) {
            // Back off a bit
            $has_backed_off = true;
            $new_row_limit = floor($this->job->db_rows_per_sync * 0.70);
            $this->logger->log('WARNING', 'Hit memory peak - Current peak: ' . $current_peak . ' and memory limit: ' . $this->memory_limit . ' - Backing off from: ' . $this->job->db_rows_per_sync . ' rows to: ' . $new_row_limit . ' rows');
            $this->job->db_rows_per_sync = $new_row_limit;
            return;
        }

        // Check that last return response size in bytes does not exceed the max limit
        if ($this->job->db_last_response_length > 0 && $this->job->db_last_response_length > $this->job->db_response_size_wanted_max) {
            // Back off
            $this->job->db_rows_per_sync = intval($this->job->db_rows_per_sync * 0.80);
            return;
        }

        // Throttle rows per sync
        if ($lastrun_time < $this->max_time_per_sync) {
            // Scale up
            $this->job->db_rows_per_sync = ceil($this->job->db_rows_per_sync * 1.05);
        } else {
            // Back off
            $this->job->db_rows_per_sync = ceil($this->job->db_rows_per_sync * 0.90);
        }
    }

    /**
     *  Send data to remote REST service (used for push)
     *  @since 1.0.0
     */
    private function sendDataToRemoteService(&$table)
    {
        global $wpdb;
        if ($this->installation == null) {
            return 0;
        }

        // Get data from server (to be send to remote)
        if (strlen($table->primary_key_column) > 0) {
            $sql_stmt = 'select * from `' . $table->name . '` where `' . $table->primary_key_column . '` > ' . $table->last_primary_key . ' order by `' . $table->primary_key_column . '`  limit ' . intval($this->job->db_rows_per_sync);
        } else {
            $sql_stmt = 'select * from `' . $table->name . '` limit ' . $table->completed_rows . ',' . intval($this->job->db_rows_per_sync);
        }

        $data = $wpdb->get_results($sql_stmt);
        $this->logger->log('DEBUG', 'Getting data from local DB with SQL query: ' . $sql_stmt);

        $rows_fetched = count($data);

        // If rows fetched less than max rows, than mark table as completed
        if ($rows_fetched < $this->job->db_rows_per_sync) {
            $this->logger->log('INFO', 'Marking table: ' . $table->name . ' as completed');
            $table->is_completed = true;
        }

        // Generate SQL queries from data
        $sql_inserts = [];
        if ($rows_fetched > 0) {
            $sql_inserts = $this->generateSQLInserts($table, $data, $this->max_allowed_packet_length);
        } else {
            return 0;
        }

        // Create POST request to remote
        foreach ($sql_inserts as $sql_insert) {
            $body = new \stdClass();
            $body->sql_inserts = $sql_insert;
            $body->type = $this->installation->type;
            $this->callRemoteClientDBService($body, 'to');
            // Check for error
            if (count($this->job->errors) > 0) {
                return;
            }
        }

        return $rows_fetched;
    }

    /**
     *  Call service for executing sql queries
     *  @since 1.0.0
     */
    public function callRemoteClientDBService(&$body, $to_or_from = 'to')
    {
        // Start timer
        global $wpsynchro_container;
        $this->timer = $wpsynchro_container->get('class.SyncTimerList');

        if (($body->type == 'finalize' && $this->installation->type == 'pull') || ($body->type == 'pull' && $to_or_from == 'to')) {
            $url = $this->job->to_rest_base_url . 'wpsynchro/v1/clientsyncdatabase/';
        } elseif (($body->type == 'finalize' && $this->installation->type == 'push') || ($body->type == 'push' && $to_or_from == 'to')) {
            $url = $this->job->to_rest_base_url . 'wpsynchro/v1/clientsyncdatabase/';
        } elseif ($body->type == 'pull' && $to_or_from == 'from') {
            $url = $this->job->from_rest_base_url . 'wpsynchro/v1/clientsyncdatabase/';
        } elseif ($body->type == 'push' && $to_or_from == 'from') {
            $url = $this->job->from_rest_base_url . 'wpsynchro/v1/clientsyncdatabase/';
        }

        // Get remote transfer object
        $remotetransport = $wpsynchro_container->get('class.RemoteTransfer');
        $remotetransport->init();
        $remotetransport->setUrl($url);
        $remotetransport->setDataObject($body);
        $database_result = $remotetransport->remotePOST();

        if ($database_result->isSuccess()) {
            $result_body = $database_result->getBody();
            $this->job->db_last_response_length = $database_result->getBodyLength();
            $this->logger->log('DEBUG', "Got a proper response from 'clientsyncdatabase' with response length: " . $this->job->db_last_response_length);

            // Check for returning data
            if (isset($result_body->data)) {
                return $result_body->data;
            }
        } else {
            $this->job->errors[] = __('Database synchronization failed with error, which means we can not continue the synchronization.', 'wpsynchro');
        }
    }

    /**
     *  Retrieve data from remote REST service (used for pull)
     *  @since 1.0.0
     */
    private function retrieveDataFromRemoteService(&$table)
    {
        global $wpdb;

        if ($this->installation == null) {
            return 0;
        }

        $body = new \stdClass();
        $body->table = $table->name;
        $body->last_primary_key = $table->last_primary_key;
        $body->primary_key_column = $table->primary_key_column;
        $body->binary_columns = $table->column_types->binary;
        $body->completed_rows = $table->completed_rows;
        $body->max_rows = $this->job->db_rows_per_sync;
        $body->type = $this->installation->type;

        // Call remote service
        $this->logger->log('DEBUG', 'Getting data from remote DB with data: ' . json_encode($body));
        $remote_result = $this->callRemoteClientDBService($body, 'from');

        // Check for errors
        if (count($this->job->errors) > 0) {
            return 0;
        }

        if (is_array($remote_result)) {
            $rows_fetched = count($remote_result);
        } else {
            $rows_fetched = 0;
        }

        if ($rows_fetched < $this->job->db_rows_per_sync) {
            $this->logger->log('INFO', 'Marking table: ' . $table->name . ' as completed');
            $table->is_completed = true;
        }

        // Insert statements
        if ($rows_fetched > 0) {
            $sql_inserts = $this->generateSQLInserts($table, $remote_result, $this->max_allowed_packet_length);
            $wpdb->query('SET FOREIGN_KEY_CHECKS=0;');
            foreach ($sql_inserts as &$sql_insert) {
                $wpdb->query($sql_insert);
                $wpdb->flush();
            }
        }

        $this->logger->log('DEBUG', 'Inserted ' . $rows_fetched . ' rows into target database');

        return $rows_fetched;
    }

    /**
     *  Generate sql inserts, queued together inside max_packet_allowed gathered from metadata and setup in preparesyncdata method
     *  @since 1.0.0
     */
    public function generateSQLInserts(&$table, &$rows, $max_packet_length)
    {
        $insert_buffer = '';
        $insert_buffer_length = 0;
        $insert_count = 0;
        $insert_count_max = 998;    // Max 1000 inserts per statement, limit in mysql (minus a few such as foreign key check)
        $last_primary_key = 0;
        $inserts_array = [];

        $sql_insert_prefix = function ($temp_tablename, $col_and_val) {
            $cols = array_keys($col_and_val);

            $insert_buffer = 'INSERT INTO `' . $temp_tablename . '` (`' . implode('`,`', $cols) . '`) VALUES ';
            return $insert_buffer;
        };

        foreach ($rows as $row) {
            // If beginning of new buffer
            $col_and_val = get_object_vars($row);

            if ($insert_buffer == '') {
                $insert_buffer = $sql_insert_prefix($table->temp_name, $col_and_val);
                $insert_buffer_length = strlen($insert_buffer);
            }

            $temp_insert_add = '(';
            $error_during_column_handling = false;
            foreach ($col_and_val as $col => $val) {
                if ($col == $table->primary_key_column) {
                    $last_primary_key = $val;
                }

                // Handle NULL values
                if (is_null($val)) {
                    $temp_insert_add .= 'NULL,';
                } elseif ($table->column_types->isString($col)) {
                    // Handle string values
                    if ($col != 'guid') {
                        $val = $this->handleSearchReplace($val);
                    }
                    $temp_insert_add .= "'" . $this->escape($val) . "',";
                } elseif ($table->column_types->isNumeric($col)) {
                    // Handle numeric values
                    if (strpos($val, 'e') > -1 || strpos($val, 'E') > -1) {
                        $temp_insert_add .= "'" . $this->escape($val) . "',";
                    } else {
                        $temp_insert_add .= $this->escape($val) . ',';
                    }
                } elseif ($table->column_types->isBinary($col)) {
                    // Handle binary values
                    $available_memory = $this->memory_limit - memory_get_usage();
                    $val_length = strlen($val);
                    $expected_length = $val_length * 2;
                    if ($expected_length > $available_memory) {
                        $warningsmsg = sprintf(__('Large row with binary column ignored from table: %s - Size of value: %d - Increase memory limit on server', 'wpsynchro'), $table->name, $val_length);
                        $this->logger->log('WARNING', $warningsmsg);
                        $this->job->warnings[] = $warningsmsg;
                        $error_during_column_handling = true;
                        break;
                    } else {
                        if (strlen($val) > 0) {
                            $temp_insert_add .= '0x' . bin2hex($val) . ',';
                        } else {
                            $temp_insert_add .= 'NULL,';
                        }
                    }
                } elseif ($table->column_types->isBit($col)) {
                    // Handle bit values
                    $temp_insert_add .= "b'" . decbin($val) . "',";
                }
            }

            if ($error_during_column_handling) {
                continue;
            }

            $temp_insert_add = trim($temp_insert_add, ', ') . '),';
            $tmp_insert_add_length = strlen($temp_insert_add);

            if ($tmp_insert_add_length > $max_packet_length) {
                $warningsmsg = sprintf(__('Large row ignored from table: %s - Size: %d - This happens when a table row is larger than your system limits allows. These limits are a combination of max SQL packet size, memory limitsand PHP max_post_size on both ends of the synchronization.', 'wpsynchro'), $table->name, $tmp_insert_add_length);
                $this->logger->log('WARNING', $warningsmsg);
                $this->job->warnings[] = $warningsmsg;
                continue;
            }

            if ((($insert_buffer_length + $tmp_insert_add_length) < $max_packet_length) && $insert_count < $insert_count_max) {
                $insert_buffer .= $temp_insert_add;
                $insert_buffer_length += $tmp_insert_add_length;
                $insert_count++;
            } else {
                // Save sql to array
                $insert_buffer = trim($insert_buffer, ', ');
                $inserts_array[] = $insert_buffer;
                // Start from beginning
                $insert_buffer = $sql_insert_prefix($table->temp_name, $col_and_val);
                $insert_buffer .= $temp_insert_add;
                $insert_buffer_length = strlen($insert_buffer);
                $insert_count = 1;
            }
        }
        if (strlen($insert_buffer) > 0 && $insert_count > 0) {
            $insert_buffer = trim($insert_buffer, ', ');
            $inserts_array[] = $insert_buffer;
        }

        $table->last_primary_key = $last_primary_key;

        return $inserts_array;
    }

    /**
     * Handle SQL escape
     * @since 1.0.0
     */
    private function escape($data)
    {
        global $wpdb;

        if ($this->use_mysqli) {
            $escaped = mysqli_real_escape_string($wpdb->__get('dbh'), $data);
        } else {
            // @codeCoverageIgnoreStart
            $escaped = mysql_real_escape_string($data, $wpdb->__get('dbh'));
            // @codeCoverageIgnoreEnd
        }

        return $escaped;
    }

    /**
     * Handle search/replace in data
     * @since 1.2.0
     */
    public function handleSearchReplace($data)
    {
        // Check data type
        if (is_serialized($data)) {
            // Handle search/replace in serialized data

            preg_match_all('/s:([0-9]+):"/', $data, $m, PREG_OFFSET_CAPTURE);
            $modifications_needed = [];
            foreach ($m[0] as $i => $notused) {
                $strlen = $m[1][$i][0];
                if ($strlen == 0) {
                    // If no length, no replace needed
                    continue;
                }
                // Setup variables
                $part_start = $m[0][$i][1];
                $desc_part_length = strlen($m[0][$i][0]);
                $part_string_start = $part_start + $desc_part_length;

                // Get current string
                $actual_string = substr($data, $part_string_start, $strlen);

                // Check if any replaces needs done
                $replaces_done = false;
                $actual_string_replaced = $actual_string;
                foreach ($this->searchreplaces as $replaces) {
                    $replaces_actually_done = 0;
                    $actual_string_replaced = str_replace($replaces->from, $replaces->to, $actual_string_replaced, $replaces_actually_done);
                    if ($replaces_actually_done > 0) {
                        $replaces_done = true;
                    }
                }

                // Record changes needed
                if ($replaces_done) {
                    $actual_string_length = strlen($actual_string);
                    $actual_string_chars = strlen((string) $actual_string_length);
                    $actual_string_replaced_length = strlen($actual_string_replaced);
                    $actual_string_replaced_chars = strlen((string) $actual_string_replaced_length);

                    $modification = new \stdClass();
                    $modification->part_start = $part_start;
                    $modification->part_length = $desc_part_length + $strlen;
                    $modification->new_string = $actual_string_replaced;
                    $modification->new_string_length = $actual_string_replaced_length;
                    $modification->placement_diff = ($actual_string_replaced_length - $actual_string_length) + ($actual_string_replaced_chars - $actual_string_chars);
                    $modifications_needed[] = $modification;
                }
            }

            // Handle modifications needed
            $offset = 0;
            foreach ($modifications_needed as $mod) {
                $newstring = 's:' . $mod->new_string_length . ':"' . $mod->new_string;
                $data = substr_replace($data, $newstring, ($mod->part_start + $offset), $mod->part_length);
                // Adjust for the former changes
                $offset = $offset + $mod->placement_diff;
            }
        } else {
            // Its just plain data, so simple fixy fixy
            foreach ($this->searchreplaces as $replaces) {
                $data = str_replace($replaces->from, $replaces->to, $data);
            }
        }

        return $data;
    }

    /**
     *  Create tables on remote (and filter out temp tables)
     *  @since 1.0.0
     */
    private function createTablesOnRemoteDatabase()
    {
        global $wpdb;

        // the list of queries to setup tables
        $sql_queries = [];

        // Disable foreign key checks
        $sql_queries[] = 'SET FOREIGN_KEY_CHECKS = 0;';

        // Create the temp tables (and drop them if already exists)
        foreach ($this->job->from_dbmasterdata as &$table) {
            // If table contains prefix, just move on
            if (strpos($table->name, $this->table_prefix) === 0) {
                continue;
            }

            if (!isset($table->temp_name) || strlen($table->temp_name) == 0) {
                $table->temp_name = $this->table_prefix . uniqid();
            }

            $create_table = str_replace('`' . $table->name . '`', '`' . $table->temp_name . '`', $table->create_table);

            // Go through every table name, so see if table is referenced in create statement - Could be a innodb constraint or whatever
            foreach ($this->job->from_dbmasterdata as &$inside_table) {
                if ($inside_table->name == $table->name) {
                    // Ignore if it is the same table
                    continue;
                }

                // Check if the create statement contains the name of inside-table
                if (strpos($table->create_table, '`' . $inside_table->name . '`') > -1) {
                    // If not yet given a temp name, set that first
                    if (!isset($inside_table->temp_name) || strlen($inside_table->temp_name) == 0) {
                        $inside_table->temp_name = $this->table_prefix . uniqid();
                    }
                    // Replace in create statement, so inside tables new temp name is there instead
                    $create_table = str_replace('`' . $inside_table->name . '`', '`' . $inside_table->temp_name . '`', $create_table);
                }
            }

            // Adapt create statement according to MySQL version
            $sql_queries[] = $this->adaptCreateStatement($create_table, $this->job->to_sql_version);
        }

        if ($this->installation->type == 'pull') {
            // Execute the sql queries
            foreach ($sql_queries as $sql_query) {
                $wpdb->query($sql_query);
            }
        } elseif ($this->installation->type == 'push') {
            // if push, then always call remote service for sql create tables

            $body = new \stdClass();
            $body->sql_inserts = $sql_queries;
            $body->type = $this->installation->type;

            $this->callRemoteClientDBService($body, 'to');
        }
    }

    /**
     *  Change create statements according to MySQL version
     *  @since 1.0.0
     */
    public function adaptCreateStatement($create, $to_db_version)
    {
        // Change name to random in all constraints, if there, to prevent trouble with existing
        $create = preg_replace_callback("/CONSTRAINT\s`(\w+)`/", function () {
            return 'CONSTRAINT `' . uniqid() . '`';
        }, $create);

        // Change charset and collation to recommended for the target
        $charset = "utf8mb4";
        $collate = "utf8mb4_unicode_520_ci";
        if (version_compare($to_db_version, '5.5', '>=') && version_compare($to_db_version, '5.6', '<')) {  // MySQL
            $charset = "utf8mb4";
            $collate = "utf8mb4_unicode_ci";
        } elseif (version_compare($to_db_version, '10.1', '>=') && version_compare($to_db_version, '10.2', '<')) {   // MariaDB
            $charset = "utf8mb4";
            $collate = "utf8mb4_unicode_ci";
        }

        // Change the charset and collate on entire table
        $create = preg_replace("/CHARSET=(\w+)/", "CHARSET={$charset}", $create);
        // If collate is set, replace it and if not set, add it
        if (stripos($create, "COLLATE=") === false) {
            $create = trim($create, " ;");
            $create .= " COLLATE={$collate}";
        } else {
            // Replace it
            $create = preg_replace("/COLLATE=(\w+)/", "COLLATE={$collate}", $create);
        }

        // Change any charset and collate on columns
        $create = preg_replace("/CHARACTER SET (\w+)/", "", $create);
        $create = preg_replace("/COLLATE (\w+)/", "", $create);

        return $create;
    }

    /**
     *  Calculate completion percent
     *  @since 1.0.0
     */
    private function updateCompletionStatusPercent()
    {
        if (!isset($this->job->from_dbmasterdata)) {
            return;
        }

        $totalrows = 0;
        $completedrows = 0;
        $percent_completed = 0;
        // Data sizes
        $total_data_size = 0;

        foreach ($this->job->from_dbmasterdata as $table) {
            if (isset($table->rows)) {
                $temp_rows = $table->rows;
            } else {
                $temp_rows = 0;
            }
            if (isset($table->completed_rows)) {
                $temp_completedrows = $table->completed_rows;
            } else {
                $temp_completedrows = 0;
            }
            $totalrows += $temp_rows;
            $completedrows += $temp_completedrows;
            $total_data_size += $table->data_total_bytes;
        }

        if ($totalrows > 0) {
            $percent_completed = floor(($completedrows / $totalrows) * 100);
        } else {
            $percent_completed = 100;
        }
        // :)
        if ($percent_completed > 100) {
            $percent_completed = 100;
        }

        $this->job->database_progress = $percent_completed;

        // Update status description
        $current_number = $total_data_size * ($percent_completed / 100);
        $total_number = $total_data_size;
        $one_mb = 1012 * 1024;

        if ($total_number < $one_mb) {
            $total_number = number_format($total_number / 1024, 0, ',', '.') . 'kB';
            $current_number = number_format($current_number / 1024, 0, ',', '.') . 'kB';
        } else {
            $total_number = number_format($total_number / $one_mb, 1, ',', '.') . 'MB';
            $current_number = number_format($current_number / $one_mb, 1, ',', '.') . 'MB';
        }

        $completed_desc_rows = number_format($completedrows, 0, ',', '.');
        $total_desc_rows = number_format($totalrows, 0, ',', '.');

        if ($this->job->database_progress < 100) {
            $database_progress_description = sprintf(__('Data: %s / %s - Rows: %s / %s', 'wpsynchro'), $current_number, $total_number, $completed_desc_rows, $total_desc_rows);
        } else {
            $database_progress_description = '';
        }

        $this->logger->log('INFO', 'Database progress update: ' . $database_progress_description);
        $this->job->database_progress_description = $database_progress_description;
    }
}
