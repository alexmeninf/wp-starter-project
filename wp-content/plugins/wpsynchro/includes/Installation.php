<?php

namespace WPSynchro;

/**
 * Class for handling a "sync installation"
 * @since 1.0.0
 */
class Installation
{
    public $id = '';
    public $name = '';
    // Type
    public $type = '';
    // From
    public $site_url = '';
    public $access_key = '';
    // Connection options
    public $connection_type = "direct";
    public $basic_auth_username = "";
    public $basic_auth_password = "";
    // General settings
    public $verify_ssl = true;
    public $clear_cache_on_success = true;
    public $success_notification_email_list = "";
    public $error_notification_email_list = "";
    // Data to sync
    public $sync_preset = "all";
    public $sync_database = false;
    public $sync_files = false;
    /*
     * Database
     */
    public $db_make_backup = true;
    public $db_table_prefix_change = true;
    public $db_preserve_activeplugins = true;
    // Exclusions DB
    public $include_all_database_tables = true;
    public $only_include_database_table_names = [];
    // Search / replaces in db
    public $searchreplaces = [];
    public $ignore_all_search_replaces = false;

    /*
     *  Files
     */
    public $file_locations = [];
    public $files_exclude_files_match = "node_modules";


    /*
     * Errors
     */
    public $validate_errors = [];

    /**
     *  Generated content
     */
    public $description = null;
    public $can_run = false;

    // Constants
    const SYNC_TYPES = ['pull', 'push'];
    const CONNECTION_TYPES = ['direct', 'basicauth'];
    const SYNC_PRESETS = ['all', 'db_all', 'file_all', 'none'];

    public function __construct()
    {
    }

    /**
     *  Prepare generated data on object
     *  @since 1.6.0
     */
    public function prepareGeneratedData()
    {
        $this->getOverviewDescription();
        $this->can_run = $this->canRun();
    }

    /**
     *  Get text to show on overview for this installation
     *  @since 1.0.0
     */
    public function getOverviewDescription()
    {
        $desc = __("Synchronize", "wpsynchro") . " ";
        // Type
        if ($this->type == 'push') {
            $desc .= sprintf(__("from <b>this installation</b> to <b>%s</b> ", "wpsynchro"), $this->site_url) . " ";
        } else {
            $desc .= sprintf(__("<b>from %s</b> to <b>this installation</b>", "wpsynchro"), $this->site_url) . " ";
        }

        if (!$this->verify_ssl) {
            $desc .= "<br> - " . __("Self-signed and non-valid SSL certificates allowed", "wpsynchro");
        }

        if ($this->sync_preset == 'all') {
            $desc .= "<br> - " . __("Synchronize entire site (database and files)", "wpsynchro");
        } elseif ($this->sync_preset == 'db_all') {
            $desc .= "<br> - " . __("Synchronize entire database", "wpsynchro");
        } elseif ($this->sync_preset == 'file_all') {
            $desc .= "<br> - " . __("Synchronize all files", "wpsynchro");
        } elseif ($this->sync_preset == 'none') {
            if (!$this->sync_database && !$this->sync_files) {
                $desc .= "<br> - " . __("Custom synchronization - But no data chosen for sync", "wpsynchro");
            } else {
                $desc .= "<br> - " . __("Custom synchronization", "wpsynchro");
            }
        }

        if ($this->sync_database && $this->sync_preset == 'none' && $this->db_make_backup) {
            $desc .= "<br> - ";
            if ($this->include_all_database_tables) {
                $desc .= __("Database backup: All database tables will be exported", "wpsynchro");
            } else {
                $desc .= sprintf(__("Database backup: Will backup %d selected tables. ", "wpsynchro"), count($this->only_include_database_table_names));
            }
        }

        if ($this->sync_database && $this->sync_preset == 'none') {
            $desc .= "<br> - ";
            if ($this->include_all_database_tables) {
                $desc .= __("Database: All database tables will be migrated", "wpsynchro");
            } else {
                $desc .= sprintf(__("Database: Will migrate %d selected tables. ", "wpsynchro"), count($this->only_include_database_table_names));
            }
        }

        if ($this->sync_files && $this->sync_preset == 'none') {
            if (count($this->file_locations) > 0) {
                if (count($this->file_locations) == 1) {
                    $desc .= "<br> - " . __("Files: One location will be migrated", "wpsynchro");
                } else {
                    $desc .= "<br> - " . sprintf(__("Files: %d locations will be migrated", "wpsynchro"), count($this->file_locations));
                }
            } else {
                $desc .= "<br> - " . __("Files: No locations chosen for synchronization", "wpsynchro");
            }
        }

        // check for errors
        $errors = $this->checkErrors();
        if (count($errors) > 0) {
            $desc .= "<br><br>";
            foreach ($errors as $error) {
                $desc .= "<b style='color:red;'>" . $error . "</b><br>";
            }
        }

        $this->description = $desc;
        return $desc;
    }

    /**
     *  Check for errors, also taking pro/free into account
     *  @since 1.0.0
     */
    public function checkErrors()
    {
        $errors = array();
        $ispro = \WPSynchro\CommonFunctions::isPremiumVersion();

        if (!$ispro && ($this->sync_preset == "all" || $this->sync_preset == "files" || $this->sync_files == true)) {
            $errors[] = __("File migration is only available in PRO version", "wpsynchro");
        }

        if (!$ispro && ($this->sync_preset == "all" || $this->db_make_backup == true)) {
            $errors[] = __("Database backup is only available in PRO version", "wpsynchro");
        }

        return $errors;
    }

    /**
     *  Check if installation can run, taking PRO/FREE and functionalities into account
     *  @since 1.0.0
     */
    public function canRun()
    {
        $errors = $this->checkErrors();
        if (count($errors) > 0) {
            return false;
        } else {
            return true;
        }
    }

    /**
     *  Check if a preset is chosen and change the object accordingly
     *  @since 1.2.0
     */
    public function checkAndUpdateToPreset()
    {

        // Is PRO version
        $is_pro = \WPSynchro\CommonFunctions::isPremiumVersion();

        // Adjust settings to the correct ones
        if ($this->sync_preset == 'all') {
            // DB
            $this->sync_database = true;
            $this->db_make_backup = true;
            $this->db_table_prefix_change = true;
            $this->db_preserve_activeplugins = true;
            $this->include_all_database_tables = true;
            $this->only_include_database_table_names = [];
            // Files
            $this->sync_files = true;
            $this->file_locations = [];
            $this->files_exclude_files_match = "";
        } elseif ($this->sync_preset == 'db_all') {
            // DB
            $this->sync_database = true;
            $this->db_make_backup = true;
            $this->db_table_prefix_change = true;
            $this->db_preserve_activeplugins = true;
            $this->include_all_database_tables = true;
            $this->only_include_database_table_names = [];
            // Files
            $this->sync_files = false;
        } elseif ($this->sync_preset == 'file_all') {
            // DB
            $this->sync_database = false;
            $this->db_make_backup = false;
            $this->db_table_prefix_change = false;
            // Files
            $this->sync_files = true;
            $this->file_locations = [];
            $this->files_exclude_files_match = "";
        } elseif ($this->sync_preset == 'none') {
        }

        if (!$is_pro) {
            $this->db_make_backup = false;
            $this->sync_files = false;
            $this->success_notification_email_list = "";
            $this->error_notification_email_list = "";
            $this->connection_type = "direct";
            $this->basic_auth_username = "";
            $this->basic_auth_password = "";
        }
    }

    /**
     *  Map function
     */
    public static function map($obj)
    {
        $temp_installation = new self;
        foreach ($obj as $key => $value) {
            $temp_installation->$key = $value;
        }
        return $temp_installation;
    }

    /**
     *  Get success email list
     *  @since 1.6.0
     */
    public function getSuccessEmailList()
    {
        return $this->getEmailList("success");
    }

    /**
     *  Get failure email list
     *  @since 1.6.0
     */
    public function getFailureEmailList()
    {
        return $this->getEmailList("failure");
    }

    /**
     *  Add search/replace
     *  @since 1.6.0
     */
    public function getSearchReplaceObject($from, $to)
    {
        $sr = new \stdClass();
        $sr->from = $from;
        $sr->to = $to;
        return $sr;
    }

    /**
     *  Retrieve a list of emails from a field
     *  @since 1.6.0
     */
    private function getEmailList($type)
    {
        // Get data
        $data = "";
        if ($type === "success") {
            $data = $this->success_notification_email_list;
        } elseif ($type === "failure") {
            $data = $this->error_notification_email_list;
        }

        // Go through list
        $exploded_list = explode(";", $data);
        $emails = [];
        foreach ($exploded_list as $email) {
            $email = trim($email);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }
        return $emails;
    }
}
