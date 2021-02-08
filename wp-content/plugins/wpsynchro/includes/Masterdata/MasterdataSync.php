<?php
namespace WPSynchro\Masterdata;

/**
 * Class for handling the masterdata of the sync
 *
 * @since 1.0.0
 */
class MasterdataSync
{

    // Base data
    public $starttime = 0;
    public $installation = null;
    public $job = null;
    public $remote_wpdb = null;
    public $logger = null;
    public $timer = null;

    /**
     *  Constructor
     */
    public function __construct()
    {
    }

    /**
     *  Handle masterdata step
     *  @since 1.0.3
     */
    public function runMasterdataStep(&$installation, &$job)
    {

        // Start timer
        global $wpsynchro_container;
        $this->timer = $wpsynchro_container->get("class.SyncTimerList");
        $masterdata_timer = $this->timer->startTimer("masterdata", "overall", "timer");

        $this->installation = &$installation;
        $this->job = &$job;

        $this->logger = $wpsynchro_container->get("class.Logger");
        $this->logger->log("INFO", "Getting masterdata from source and target with remaining time:" . $this->timer->getRemainingSyncTime());

        // Figure out what data is needed
        $data_to_retrieve = array();
        $data_to_retrieve[] = "dbdetails";
        $data_to_retrieve[] = "filedetails";

        // Retrieve data
        $metadata_results = array();
        $metadata_results['from'] = $this->retrieveMasterdata('from', $data_to_retrieve);
        $metadata_results['to'] = $this->retrieveMasterdata('to', $data_to_retrieve);

        foreach ($metadata_results as $prefix => $masterdata_content) {
            if (in_array("dbdetails", $data_to_retrieve)) {
                if (!isset($masterdata_content->dbdetails) || $masterdata_content->dbdetails == null) {
                    $errormsg = sprintf(__("Did not retrieve correct database masterdata from target '%s' - See log file", "wpsynchro"), $prefix);
                    $this->job->errors[] = $errormsg;
                    $this->logger->log("CRITICAL", $errormsg, $metadata_results);
                    return;
                }
            }

            // Process and map the data to Job object
            $this->handleMasterdataMapping($prefix, $masterdata_content);
        }

        // Handle system search replaces depending on setup
        $this->findSystemSearchReplaces();

        if (count($this->job->errors) == 0) {
            // Check that plugin versions are identical on both sides, otherwise raise error
            if ($this->job->from_plugin_version != $this->job->to_plugin_version) {
                $this->job->errors[] = sprintf(__("WP Synchro plugin versions do not match on both sides. One runs version %s and other runs %s. Make sure they use same version to prevent problems caused by different versions of plugin.", "wpsynchro"), $this->job->from_plugin_version, $this->job->to_plugin_version);
            }

            // Check that prefix are the same or issue warning
            if ($this->installation->sync_database && !$this->installation->db_table_prefix_change && $this->job->from_wpdb_prefix != $this->job->to_wpdb_prefix) {
                $prefix_warning = sprintf(__("Database table prefixes are different on the source and target site. Source uses '%s' and target uses '%s'. Table prefix migration is not enabled in the installation configuration. This is just a warning, as the synchronization can complete, but the tables will not be used by the target site. Recommended action is to turn on the table prefix migration in the installation configuration.", "wpsynchro"), $this->job->from_wpdb_prefix, $this->job->to_wpdb_prefix);
                $this->job->warnings[] = $prefix_warning;
                $this->logger->log("WARNING", $prefix_warning);
            }

            // Check licensing
            if (\WPSynchro\CommonFunctions::isPremiumVersion() && count($this->job->errors) === 0) {
                global $wpsynchro_container;
                $licensing = $wpsynchro_container->get("class.Licensing");
                $licens_sync_result = $licensing->verifyLicenseForSynchronization($this->job->from_client_home_url, $this->job->to_client_home_url);

                if ($licens_sync_result->state === false) {
                    $this->job->errors[] = array_merge($this->job->errors, $licens_sync_result->errors);
                }
            }
        }

        $this->logger->log("INFO", "Completed masterdata on: " . $this->timer->endTimer($masterdata_timer) . " seconds");

        if (count($this->job->errors) === 0) {
            $this->job->masterdata_completed = true;
        }
    }

    /**
     *  Masterdata mapping
     *  @since 1.5.0
     */
    public function handleMasterdataMapping($prefix, $masterdata_content)
    {
        /**
         *  Base mappings
         */
        if (isset($masterdata_content->base)) {
            $mappings = array(
                "_client_home_url" => "client_home_url",
                "_rest_base_url" => "rest_base_url",
                "_wpdb_prefix" => "wpdb_prefix",
                "_wp_options_table" => "wp_options_table",                
                "_wp_users_table" => "wp_users_table",
                "_wp_usermeta_table" => "wp_usermeta_table",
                "_max_allowed_packet_size" => "max_allowed_packet_size",
                "_max_post_size" => "max_post_size",
                "_memory_limit" => "memory_limit",
                "_sql_version" => "sql_version",
                "_plugin_version" => "plugin_version",
            );

            foreach ($mappings as $job_key => $masterdata_key) {
                if (!isset($masterdata_content->base->$masterdata_key)) {
                    continue;
                }
                $tmp_var = $prefix . $job_key;
                $this->job->$tmp_var = $masterdata_content->base->$masterdata_key;
            }
        }

        /**
         *  Multisite mapping
         */
        if (isset($masterdata_content->multisite)) {
            $key = $prefix . '_is_multisite';
            $this->job->$key = $masterdata_content->multisite->is_multisite;
            $key = $prefix . '_is_multisite_main_site';
            $this->job->$key = $masterdata_content->multisite->is_main_site;     
            $key = $prefix . '_defined_uploads_location';
            $this->job->$key = $masterdata_content->multisite->defined_uploads_location;

            if ( $masterdata_content->multisite->is_multisite) {
                $key = $prefix . '_main_blog_id';
                $this->job->$key = $masterdata_content->multisite->main_blog_id;
                $key = $prefix . '_current_blog_id';
                $this->job->$key = $masterdata_content->multisite->current_blog_id;
                $key = $prefix . '_blogs';
                $this->job->$key = $masterdata_content->multisite->blogs;      
                $key = $prefix . '_default_super_admin_id';
                $this->job->$key = $masterdata_content->multisite->default_super_admin_id;  
                $key = $prefix . '_default_super_admin_username';
                $this->job->$key = $masterdata_content->multisite->default_super_admin_username;  
            }            
        }


        /**
         *  DB details mapping
         */
        if (isset($masterdata_content->dbdetails)) {
            $tmp_var = $prefix . '_dbmasterdata';
            $this->job->$tmp_var = $masterdata_content->dbdetails;
        }

        /**
         *  Files data mapping
         */
        if (isset($masterdata_content->files)) {
            $mappings = array(
                "_files_above_webroot_dir" => "files_above_webroot_dir",
                "_files_home_dir" => "files_home_dir",
                "_files_wp_content_dir" => "files_wp_content_dir",
                "_files_wp_dir" => "files_wp_dir",
                "_files_uploads_dir" => "files_uploads_dir",
                "_files_plugins_dir" => "files_plugins_dir",
                "_files_themes_dir" => "files_themes_dir",
                "_files_plugin_list" => "files_plugin_list",
                "_files_theme_list" => "files_theme_list",
                "_files_uploads_dir" => "files_uploads_dir",
            );

            foreach ($mappings as $job_key => $masterdata_key) {
                if (!isset($masterdata_content->files->$masterdata_key)) {
                    continue;
                }
                $tmp_var = $prefix . $job_key;
                $this->job->$tmp_var = $masterdata_content->files->$masterdata_key;
            }
        }

        /**
         *  Debug data mapping
         */
        if (isset($masterdata_content->debug)) {
            $tmp_var = $prefix . '_debug';
            $this->job->$tmp_var = $masterdata_content->debug;
        }
    }

    /**
     *  Retrieve masterdata
     *  @since 1.0.0
     */
    public function retrieveMasterdata($to_or_from = 'from', $slugs_to_retrieve = array())
    {
        global $wpsynchro_container;
        $logger = $wpsynchro_container->get("class.Logger");

        // Get webservice url
        if (($this->installation->type == 'pull' && $to_or_from == 'to') || ($this->installation->type == 'push' && $to_or_from == 'from')) {    
            $inst_factory = $wpsynchro_container->get("class.InstallationFactory");
            $installation = $inst_factory->getLocalInstallation();
        } elseif (($this->installation->type == 'pull' && $to_or_from == 'from') || ($this->installation->type == 'push' && $to_or_from == 'to')) {
            $installation = $this->installation;
        }

        // Get masterdata retrival object
        $retrieval = new MasterdataRetrieval($installation);
        $retrieval->setDataToRetrieve($slugs_to_retrieve);

        $logger->log("DEBUG", "Calling masterdata service on: " . $installation->site_url . " with intent to user as '" . $to_or_from . "'");
        $result = $retrieval->getMasterdata();

        // Check for errors
        if ($result) {     
            return $retrieval->data;
        } else {
            $errormsg = sprintf(__("Could not retrieve masterdata from target '%s', which means we can not continue the synchronization.", "wpsynchro"), $installation->site_url);
            $this->job->errors[] = $errormsg;
            $this->logger->log("CRITICAL", $errormsg);
            return [];
        }
    }

    /**
     *  Find needed system search/replaces
     *  @since 1.6.0
     */
    public function findSystemSearchReplaces()
    {
      
         // Check for multisite and if we need any search/replaces
         if ($this->job->from_is_multisite || $this->job->to_is_multisite) {
        
            // Exclude WP Synchro on these locations also
            $this->job->files_population_source_excludes[] = $this->job->from_files_uploads_dir . "/wpsynchro";
            $this->job->files_population_target_excludes[] = $this->job->to_files_uploads_dir . "/wpsynchro";

            // If both are multisites - So either main<>main or subsite<>subsite
            if ( $this->job->from_is_multisite && $this->job->to_is_multisite) {

                


            }

            // Multisite subsite to singlesize or other way around
            if ( ($this->job->from_is_multisite && !$this->job->to_is_multisite) || (!$this->job->from_is_multisite && $this->job->to_is_multisite)) {

                // If the source is a multisite and not the main site, exclude all other sites uploads folder
                if ($this->job->from_is_multisite && !$this->job->from_is_multisite_main_site ) {
                    $uploads_sites_folder_on_source = dirname(dirname(trailingslashit($this->job->from_files_uploads_dir))) . "/sites/";
                    foreach($this->job->from_blogs as $blog) {
                        if ( $blog->id != $this->job->from_current_blog_id) {
                            $this->job->files_population_source_excludes[] = $uploads_sites_folder_on_source . $blog->id;
                        }
                    }                              
                }                              
            }

            // If migrating from multisite subsite to single site, make sure that UPLOADS constant is defined for single site.
            if ( $this->job->from_is_multisite && !$this->job->to_is_multisite ) {
                $relative_uploads_dir = trim(str_replace($this->job->to_files_home_dir,"",$this->job->to_files_uploads_dir),"/"); 
                $define_uploads_dir = "DEFINE('UPLOADS','" . $relative_uploads_dir . "');";    
                if ( $this->job->to_defined_uploads_location != $relative_uploads_dir) {
                    $this->job->finalize_success_messages_frontend[] = sprintf(__("If you are moving uploads directory (media), you must add the following line to your wp-config.php on the site %s : <code>%s</code> - Make sure to add this line before loading WordPress in the bottom of wp-config.php","wpsynchro"), untrailingslashit($this->job->to_client_home_url), $define_uploads_dir);                
                } 
                $this->job->finalize_success_messages_frontend[] = __("All users from multisite is moved - Make sure to check and remove unwanted users on single site.","wpsynchro");
            }
            // If migrating from single site to multisite subsite
            if (!$this->job->from_is_multisite && $this->job->to_is_multisite) {
                $this->job->finalize_success_messages_frontend[] = __("Users from source single site was merged with the users on the multisite. Make sure to check that the users are as expected.","wpsynchro");
            }
            
            

        }


    }
}
