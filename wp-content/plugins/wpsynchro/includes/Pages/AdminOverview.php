<?php

/**
 * Class for handling what to show when clicking on the menu in wp-admin
 * @since 1.0.0
 */

namespace WPSynchro\Pages;

use WPSynchro\InstallationFactory;

class AdminOverview
{

    public $installation_factory;

    public function __construct()
    {
        $this->installation_factory = new InstallationFactory();
    }

    public static function render()
    {

        $instance = new self;
        $instance->handleGET();
    }

    private function handleGET()
    {
        // Check php/wp/mysql versions
        global $wpsynchro_container;
        $commonfunctions = $wpsynchro_container->get('class.CommonFunctions');
        $compat_errors = $commonfunctions->checkEnvCompatability();

        // Review notification
        $success_count = get_site_option("wpsynchro_success_count", 0);
        $request_review_dismissed = get_site_option("wpsynchro_dismiss_review_request", false);
        $show_review_notification = $success_count >= 10 && !$request_review_dismissed;
        $review_notification_text = sprintf(
            __("You have used WP Synchro %d times now - We hope you are enjoying it and have saved some time and troubles.<br>
            We try really hard to give you a high quality tool for WordPress site migrations.<br>
            If you enjoy using WP Synchro, we would appreciate your review on 
            <a href='https://wordpress.org/support/plugin/wpsynchro/reviews/?rate=5#new-post' target='_blank'>WordPress plugin repository</a>.<br>
            Thank you for the help.", "wpsynchro"),
            $success_count
        );


        // Check for delete
        if (isset($_GET['delete'])) {
            $delete = $_GET['delete'];
        } else {
            $delete = "";
        }

        // If delete
        if (strlen($delete) > 0) {
            global $wpsynchro_container;
            $inst_factory = $wpsynchro_container->get('class.InstallationFactory');
            $inst_factory->deleteInstallation($delete);
        }

        // Check for duplicate
        if (isset($_GET['duplicate'])) {
            $duplicate = $_GET['duplicate'];
        } else {
            $duplicate = "";
        }

        // If duplicate
        if (strlen($duplicate) > 0) {
            global $wpsynchro_container;
            $inst_factory = $wpsynchro_container->get('class.InstallationFactory');
            $inst_factory->duplicateInstallation($duplicate);
        }

        // Check if healthcheck should be run
        $run_healthcheck = false;
        if (\WPSynchro\CommonFunctions::isPremiumVersion()) {
            $licensing = $wpsynchro_container->get("class.Licensing");
            if ($licensing->hasProblemWithLicensing()) {
                $run_healthcheck = true;
            }
        }
        if (!$run_healthcheck) {
            $healthcheck_last_success = intval(get_site_option("wpsynchro_healthcheck_timestamp", 0));
            $seconds_in_week = 604800; // 604800 is one week       
            if (($healthcheck_last_success + $seconds_in_week) < time()) {
                $run_healthcheck = true;
            }
        }

        // Installation data      
        $data = $this->installation_factory->getAllInstallations();
        usort($data, function ($a, $b) {
            return strcmp($a->name, $b->name);
        });

        // Cards
        $card_content = "";
        if (!\WPSynchro\CommonFunctions::isPremiumVersion()) {
            $card_content .= $commonfunctions->getTemplateFile("card-pro-version");
        }
        $card_content .= $commonfunctions->getTemplateFile("card-mailinglist");
        $card_content .= $commonfunctions->getTemplateFile("card-facebook");

        // Data for JS
        $data_for_js = [
            "isPro" => \WPSynchro\CommonFunctions::isPremiumVersion(),
            "pageUrl" => menu_page_url('wpsynchro_overview', false),
            "runSyncUrl" => menu_page_url('wpsynchro_run', false),
            "AddEditUrl" => menu_page_url('wpsynchro_addedit', false),
            "compatErrors" => $compat_errors,
            "showReviewNotification" => $show_review_notification,
            "runHealthcheck" => $run_healthcheck,
            "reviewNotificationDismissUrl" => add_query_arg(array('wpsynchro_dismiss_review_request' => 1), admin_url()),
            "addInstallationUrl" => menu_page_url('wpsynchro_addedit', false),
            "installationData" => $data,
        ];
        wp_localize_script('wpsynchro_admin_js', 'wpsynchro_overview_data', $data_for_js);

        $translation_for_js = [
            "reviewNotificationText" => $review_notification_text,
            "pageTitle" => __('Overview', 'wpsynchro'),
            "reviewNotificationRateButton" => __('Rate WP Synchro on WordPress.org', 'wpsynchro'),
            "reviewNotificationDismissButton" => __('Dismiss forever', 'wpsynchro'),
            "addInstallationButton" => __('Add installation', 'wpsynchro'),
            "tableColumnName" => __('Name', 'wpsynchro'),
            "tableColumnType" => __('Type', 'wpsynchro'),
            "tableColumnDescription" => __('Description', 'wpsynchro'),
            "tableColumnActions" => __('Actions', 'wpsynchro'),
            "canRunText" => __('Run now', 'wpsynchro'),
            "canNotRunElementTitle" => __('Installation can not be run - See description', 'wpsynchro'),
            "actionDuplicateText" => __('Duplicate', 'wpsynchro'),
            "actionScheduleText" => __('Schedule', 'wpsynchro'),
            "actionDeleteText" => __('Delete', 'wpsynchro'),
            "actionDeleteConfirmText" => __('Are you sure you want to delete this?', 'wpsynchro'),
            "cardContent" => $card_content,
            "noInstallationsText" => __('Get started by adding a new installation...', 'wpsynchro'),
            "scheduleHeaderText" => __("Scheduling a synchronization", "wpsynchro"),
            "scheduleText1" => sprintf(__("To schedule a job to run at a certain time or with a certain interval, you need to have %sWP CLI%s installed.", "wpsynchro"), "<a href='https://wp-cli.org/' target='_blank'>", "</a>"),
            "scheduleText2" => __("With WP CLI installed, you can run this synchronization", "wpsynchro"),
            "scheduleText3" => __("with this command", "wpsynchro"),
            "scheduleText4" => __("Or if you want it in quiet mode, with no output", "wpsynchro"),
            "scheduleText5" => __("You can add this command to cron and run it exactly how you want it.", "wpsynchro"),

        ];
        wp_localize_script('wpsynchro_admin_js', 'wpsynchro_overview_translations', $translation_for_js);

        // Print content
        echo '<div id="wpsynchro-overview" class="wpsynchro"><page-overview></page-overview></div>';
    }
}
