<?php
namespace WPSynchro\REST;

use WPSynchro\Transport\TransferAccessKey;

/**
 * Class for handling REST service "backupdatabase"
 * Call should already be verified by permissions callback
 *
 * @since 1.2.0
 */
class DatabaseBackup
{
    public function service($request)
    {
        global $wpsynchro_container;
        $common = $wpsynchro_container->get("class.CommonFunctions");
        $transfer = $wpsynchro_container->get("class.Transfer");
        $transfer->setEncryptionKey(TransferAccessKey::getAccessKey());
        $transfer->populateFromString($request->get_body());
        $body = $transfer->getDataObject();

        /**
         *  Extract parameters
         */
        $data_required_errors = false;

        if (isset($body->table)) {
            $table = $body->table;
        } else {
            $data_required_errors = true;
        }

        if (isset($body->filename)) {
            $filename = $body->filename;
            $filepath = $common->getLogLocation() . $filename;
        } else {
            $data_required_errors = true;
        }
        if (isset($body->memory_limit)) {
            $memory_limit = $body->memory_limit;
        } else {
            $data_required_errors = true;
        }

        if ($data_required_errors) {
            global $wpsynchro_container;
            $returnresult = $wpsynchro_container->get('class.ReturnResult');
            $returnresult->init();
            $returnresult->setHTTPStatus(400);
            return $returnresult->echoDataFromRestAndExit();
        }


        global $wpdb;
        $result = new \stdClass();
        $result->errors = array();
        $result->warnings = array();
        $result->debugs = array();
        $result->infos = array();

        // Add location to log file
        $result->infos[] = "Database backup is written to " . $filepath . " on site " . get_home_url();

        /**
         *  Get started with the export
         */
        // Calculate rows to go for
        if ($table->row_avg_bytes > 0) {
            $rows_per_run = ceil((1024 * 1024) / $table->row_avg_bytes);
        } else {
            $rows_per_run = 9900;
        }


        // Get data from server (to be send to remote)
        if (strlen($table->primary_key_column) > 0) {
            $sql_stmt = 'select * from `' . $table->name . '` where `' . $table->primary_key_column . '` > ' . $table->last_primary_key . ' order by `' . $table->primary_key_column . '`  limit ' . $rows_per_run;
        } else {
            $sql_stmt = 'select * from `' . $table->name . '` limit ' . $table->completed_rows . ',' . $rows_per_run;
        }

        // Execute and check result
        $data = $wpdb->get_results($sql_stmt);

        // Get databasesync object
        $databasesync = $wpsynchro_container->get("class.DatabaseSync");
        $databasesync->memory_limit = $memory_limit;
        $databasesync->job = new \stdClass();
        $databasesync->job->warnings = &$result->warnings;

        // Check if there is more than X backups and delete the oldest, this needs to be done before the first data is written to file - should only run once
        if (!file_exists($filepath)) {
            $dir = dirname($filepath);
            $this->deleteOldBackups($dir);
        }

        // Add create table to sql file
        if ($table->completed_rows == 0) {
            $file_append_create_table = file_put_contents($filepath, PHP_EOL . $table->create_table . ";" . PHP_EOL, FILE_APPEND);
            if ($file_append_create_table === false) {
                $result->errors[] = sprintf(__("Appending create table for database backup to %s failed.", "wpsynchro"), $filepath);
            } else {
                $result->debugs[] = "Wrote create table data for table " . $table->name;
            }
        }

        // If rows fetched less than max rows, than mark table as completed
        $rows_fetched = count($data);
        if ($rows_fetched < $rows_per_run) {
            $table->completed_rows = $table->rows;
        }

        // If any rows, get the insert sql version and insert into file
        if ($rows_fetched > 0) {
            $sql_inserts = array("SET FOREIGN_KEY_CHECKS=0");
            $sql_inserts = array_merge($sql_inserts, $databasesync->generateSQLInserts($table, $data, (200 * 1024 * 1024)));

            foreach ($sql_inserts as &$sqlinsert) {
                $sqlinsert .= ";" . PHP_EOL;
            }

            // Write to sql file
            $file_append_result = file_put_contents($filepath, $sql_inserts, FILE_APPEND);
            if ($file_append_result === false) {
                $result->errors[] = sprintf(__("Appending databasebackup to %s failed.", "wpsynchro"), $filepath);
            } else {
                $result->debugs[] = "Wrote data for table " . $table->name;
            }

            if ($table->completed_rows < $table->rows) {
                $table->completed_rows += $rows_fetched;
            }
        }

        $result->table = $table;

        global $wpsynchro_container;
        $returnresult = $wpsynchro_container->get('class.ReturnResult');
        $returnresult->init();
        $returnresult->setDataObject($result);
        return $returnresult->echoDataFromRestAndExit();
    }

    /**
     *  Delete backup files, if needed, based on the configuration
     */
    public function deleteOldBackups($path)
    {
        // Get files ending with sql
        $files = array();
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $value) {
            if ($value->isFile() && strtolower($value->getExtension()) === 'sql') {
                $files[] = array( $value->getMTime(), $value->getRealPath() );
            }
        }

        // If more than 19, aka 20 or above, delete the oldest
        if (count($files) > 19) {
            // Sort by timestamp
            usort($files, function ($a, $b) {
                return $a[0] > $b[0];
            });
            
            // Remove the last 19 we want to keep
            array_splice($files, count($files) - 19, 19);

            // Delete the rest
            foreach ($files as $file) {
                @unlink($file[1]);
            }

        }

    }
}
