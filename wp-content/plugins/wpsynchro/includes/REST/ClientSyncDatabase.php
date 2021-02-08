<?php
namespace WPSynchro\REST;

use WPSynchro\Transport\TransferAccessKey;

/**
 * Class for handling REST service "clientsyncdatabase" - Execute SQL from remote
 * Call should already be verified by permissions callback
 * @since 1.0.0
 */
class ClientSyncDatabase
{
    public function service($request)
    {
        global $wpsynchro_container;
        $transfer = $wpsynchro_container->get("class.Transfer");
        $transfer->setEncryptionKey(TransferAccessKey::getAccessKey());
        $transfer->populateFromString($request->get_body());
        $body = $transfer->getDataObject();

        global $wpdb;
        $result = new \stdClass();
        $result->errors = array();
        $result->warnings = array();
        $result->debugs = array();

        // Extract parameters
        if (isset($body->type)) {
            $type = $body->type;
        } else {
            $result->errors[] = __("Error from ClientSyncDatabase REST service - Check the log file for further information.", "wpsynchro");
            $result->debugs[] = "Error body from REST service: " . json_encode($body);
            global $wpsynchro_container;
            $returnresult = $wpsynchro_container->get('class.ReturnResult');
            $returnresult->init();
            $returnresult->setHTTPStatus(400);
            $returnresult->setDataObject($result);
            return $returnresult->echoDataFromRestAndExit();
        }

        if (isset($body->table)) {
            $table = $body->table;
        } else {
            $table = '';
        }
        if (isset($body->last_primary_key)) {
            $last_primary_key = $body->last_primary_key;
        } else {
            $last_primary_key = 0;
        }
        if (isset($body->primary_key_column)) {
            $primary_key_column = $body->primary_key_column;
        } else {
            $primary_key_column = "";
        }
        if (isset($body->binary_columns)) {
            $binary_columns = $body->binary_columns;
        } else {
            $binary_columns = array();
        }
        if (isset($body->completed_rows)) {
            $completed_rows = $body->completed_rows;
        } else {
            $completed_rows = 0;
        }
        if (isset($body->max_rows)) {
            $max_rows = $body->max_rows;
        } else {
            $max_rows = 0;
        }
        if (isset($body->sql_inserts)) {
            $sql_inserts = $body->sql_inserts;
        } else {
            $sql_inserts = array();
        }



        if ($type == "pull") {
            // Get data
            if (strlen($primary_key_column) > 0) {
                $sql_stmt = 'select * from `' . $table . '` where `' . $primary_key_column . '` > ' . $last_primary_key . ' order by `' . $primary_key_column . '`  limit ' . intval($max_rows);
            } else {
                $sql_stmt = 'select * from `' . $table . '` limit ' . $completed_rows . ',' . intval($max_rows);
            }

            $data = $wpdb->get_results($sql_stmt);

            // Setup default variables
            $result->data = $data;
        } elseif ($type == "push") {
            $wpdb->query("SET FOREIGN_KEY_CHECKS=0;");
            if (is_array($sql_inserts)) {
                foreach ($sql_inserts as $sql_insert) {
                    $result = $wpdb->query($sql_insert);
                }
            } else {
                $result = $wpdb->query($sql_inserts);
            }
        } elseif ($type == "finalize") {
            $wpdb->query("SET FOREIGN_KEY_CHECKS=0;");
            foreach ($sql_inserts as $sql_insert) {
                $result = $wpdb->query($sql_insert);
            }
        }

        global $wpsynchro_container;
        $returnresult = $wpsynchro_container->get('class.ReturnResult');
        $returnresult->init();
        $returnresult->setDataObject($result);
        return $returnresult->echoDataFromRestAndExit();
    }
}
