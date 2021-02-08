<?php
namespace WPSynchro\REST;

/**
 * Class for handling REST service "downloadlog"
 * Call should already be verified by permissions callback
 *
 * @since 1.0.0
 */
class DownloadLog
{

    public function service($request)
    {

        if (!isset($request['job_id']) || strlen($request['job_id']) == 0) {
            $result = new \StdClass();
            return new \WP_REST_Response($result, 400);
        }
        $jobid = $request['job_id'];

        if (!isset($request['inst_id']) || strlen($request['inst_id']) == 0) {
            $result = new \StdClass();
            return new \WP_REST_Response($result, 400);
        }
        $instid = $request['inst_id'];

        global $wpsynchro_container;
        $common = $wpsynchro_container->get('class.CommonFunctions');
        $inst_factory = $wpsynchro_container->get('class.InstallationFactory');

        $logpath = $common->getLogLocation();
        $filename = $common->getLogFilename($jobid);

        if (file_exists($logpath . $filename)) {

            $logcontents = "";

            // Intro
            $logcontents .= "Beware: Do not share this file with other people than WP Synchro support - It contains data that can compromise your site." . PHP_EOL . PHP_EOL;

            // Log data
            $logcontents .= file_get_contents($logpath . $filename);
            $job_obj = get_option("wpsynchro_" . $instid . "_" . $jobid, "");
            $inst_obj = $inst_factory->retrieveInstallation($instid);

            // Installation object
            $logcontents .= PHP_EOL . "Installation object:" . PHP_EOL;
            $logcontents .= print_r($inst_obj, true);

            // Job object
            $logcontents .= PHP_EOL . "Job object:" . PHP_EOL;
            $logcontents .= print_r($job_obj, true);

            $zipfilename = "wpsynchro_log_" . $jobid . ".zip";

            http_response_code(200);    // IIS fails if this is not here
            header("Pragma: public");
            header("Expires: 0");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Cache-Control: public");
            header("Content-Description: File Transfer");
            header("Content-Type: application/zip");
            header("Content-Disposition: attachment; filename=" . $zipfilename);

            $zipfile = tempnam($common->getLogLocation(), "zip");
            $zip = new \ZipArchive();
            $zip->open($zipfile, \ZipArchive::OVERWRITE);
            $zip->addFromString($filename, $logcontents);
            $zip->close();

            readfile($zipfile);
            unlink($zipfile);

            exit();
        } else {
            return new \WP_REST_Response("", 400);
        }
    }
}
