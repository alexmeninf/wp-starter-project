<?php

namespace WPSynchro;

/**
 * Class for handling an instance of a synchronization (aka. one pull or one push)
 *
 * @since 1.0.0
 */
class Job
{

    public $id = '';
    public $installation_id = null;
    // Run lock
    public $run_lock = false;
    public $run_lock_timer = 0;
    public $run_lock_problem_time = 0;
    // Errors and warnings
    public $errors = array();
    public $warnings = array();
    // Triggers
    public $request_full_timeframe = false;

    /**
     *  Progress
     */
    public $initiation_completed = false;
    public $masterdata_completed = false;
    public $masterdata_progress = 0;
    public $database_backup_completed = false;
    public $database_backup_progress = 0;
    public $database_backup_progress_description = "";
    public $database_completed = false;
    public $database_progress = 0;
    public $database_progress_description = "";
    public $files_progress = 0;
    public $files_progress_description = "";
    public $finalize_completed = false;
    public $finalize_progress = 0;
    public $finalize_progress_description = "";
    public $is_completed = false;
    public $first_time_setup_done = false;

    /**
     *  Initiate step
     */
    public $local_transfer_token = "";
    public $remote_transfer_token = "";

    /**
     *  Data from step: Masterdata - Variables created in constructor for both to and from masterdata
     */
    public $masterdata_template = array(
        'token' => null,    // From initiate
        'accesskey' => "",  // Used in encryption
        'dbmasterdata' => null,
        'client_home_url' => null,
        'rest_base_url' => null,
        'wpdb_prefix' => null,
        'wp_options_table' => null,
        'wp_users_table' => null,
        'wp_usermeta_table' => null,
        'max_allowed_packet_size' => 0,
        'max_post_size' => 0,
        'memory_limit' => 0,
        'sql_version' => "",
        'plugin_version' => "",
        'is_multisite' => false,
        'is_multisite_main_site' => false,
        'main_blog_id' => "",
        'current_blog_id' => "",
        'blogs' => [],
        'default_super_admin_id' => 0,
        'default_super_admin_username' => "",
        'defined_uploads_location' => "",
        'files_above_webroot_dir' => "",
        'files_home_dir' => "",
        'files_wp_content_dir' => "",
        'files_wp_dir' => "",
        'files_plugins_dir' => "",
        'files_themes_dir' => "",
        'files_uploads_dir' => "",
        'files_plugin_list' => array(),
        'files_theme_list' => array(),
        'debug' => array(),
    );

    /**
     *  Data from step: database backup
     */
    public $db_backup_tables = [];

    /**
     *  Data from step: Database
     */
    public $db_first_run_setup = false;
    public $db_rows_per_sync = 500;
    public $db_rows_per_sync_default = 500;                 // 500 rows as default
    public $db_response_size_wanted_default = 1000000;       // 500 kb as default
    public $db_response_size_wanted_max = 5000000;          // Can max scale to 5mb, to prevent all sorts of trouble with memory and other stuff
    public $db_throttle_table = "";
    public $db_last_response_length = 0;
    public $db_system_search_replaces = [];

    /**
     *  Data from step: Files
     */
    public $files_sections = array();
    // System generated search/replaces, excludes, deletes
    public $files_system_search_replaces = array();
    public $files_system_deletes = [];
    // Counters
    public $files_needs_transfer = 0;                   // Count of files that need trnasfer
    public $files_needs_transfer_size = 0;              // Total size of files that needs to be transferred
    public $files_needs_delete = 0;                     // Files that need to be deleted during finalize
    // Sync list init
    public $files_sync_list_initialized = false;        // Is sync list initialized
    // Population
    public $files_population_sections_validated = false; // Is file sections validated
    public $files_population_source = false;            // Is source files populated
    public $files_population_target = false;            // Is target files populated
    public $files_population_source_count = 0;          // Count of files found on source to this point (can increase)
    public $files_population_target_count = 0;          // Count of files found on target to this point (can increase)
    public $files_population_source_excludes = [];      // Excludes on source
    public $files_population_target_excludes = [];      // Excludes on target
    // Transfer
    public $files_transfer_completed_counter = 0;       // Number of files transferred
    public $files_transfer_completed_size = 0;          // Size of files transferred
    // Stages completed
    public $files_all_sections_populated = false;       // Make a list of files from source that is included in sync
    public $files_all_sections_path_handled = false;    // Determine which files to transfer and which to delete
    public $files_all_completed = false;                // All files have been transferred and awaiting finalize deletes

    /**
     *   Finalize data
     */
    public $finalize_files_paths_reduced = false;
    public $finalize_files_completed = false;

    // Database finalize initialized - Figuring out which queries we need to run
    public $finalize_db_initialized = false;
    public $finalize_db_sql_queries = [];
    public $finalize_db_sql_queries_count = 0;
    public $finalize_db_table_to_expect_on_target = [];

    // Database excess tables
    public $finalize_db_excess_tables_initialized = false;
    public $finalize_db_excess_table_queries = [];
    public $finalize_db_excess_table_queries_count = 0;

    public $finalize_db_completed = false;

    public $finalize_success_messages_frontend = [];

    /**
     *  Constructor
     *  @since 1.6.0
     */
    public function __construct()
    {
        // Initialize masterdata fields on object based on template, for both to and from
        foreach ($this->masterdata_template as $fieldname => $defaultvalue) {
            $to_field =  'to_' . $fieldname;
            $from_field =  'from_' . $fieldname;
            $this->$to_field = $defaultvalue;
            $this->$from_field = $defaultvalue;
        }
    }


    /**
     *  Load data from DB
     *  @since 1.0.0
     */
    public function load($installation_id, $job_id)
    {
        $this->id = $job_id;
        $this->installation_id = $installation_id;

        $job = get_option(self::getJobWPOptionName($installation_id, $job_id), false);

        if ($job !== false) {
            foreach ($job as $property => &$value) {
                $this->$property = &$value;
            }
            return true;
        }
        return false;
    }

    /**
     *  Save job to DB
     *  @since 1.0.0
     */
    public function save()
    {
        update_option(self::getJobWPOptionName($this->installation_id, $this->id), $this, false);
    }

    /**
     * Return the WP option name used for job's
     * @since 1.0.0
     */
    public static function getJobWPOptionName($installation_id, $job_id)
    {
        return 'wpsynchro_' . $installation_id . '_' . $job_id;
    }
}
