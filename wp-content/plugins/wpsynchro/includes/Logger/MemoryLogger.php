<?php
namespace WPSynchro\Logger;

/**
 * Memory logger
 */
class MemoryLogger implements Logger
{
    public $emergencies = [];
    public $alerts = [];
    public $criticals = [];
    public $errors = [];
    public $warnings = [];
    public $notices = [];
    public $infos = [];
    public $debugs = [];
    

    public function log($level, $message, $context = "")
    {
        // Format log msg
        $date = new \DateTime();

        $formatted_msg = "[{$date->format($this->dateformat)}] [{$level}] {$message}" . PHP_EOL;

        // If context, print that on newline
        if (is_array($context) || is_object($context)) {
            $formatted_msg .= PHP_EOL . print_r($context, true) . PHP_EOL;
        }
        
        if ($level === 'EMERGENCY') {
            $this->emergencies[] = $formatted_msg;
        } elseif ($level === 'ALERT') {
            $this->alerts[] = $formatted_msg;
        } elseif ($level === 'CRITICAL') {
            $this->criticals[] = $formatted_msg;
        } elseif ($level === 'ERROR') {
            $this->errors[] = $formatted_msg;
        } elseif ($level === 'WARNING') {
            $this->warnings[] = $formatted_msg;
        } elseif ($level === 'NOTICE') {
            $this->notices[] = $formatted_msg;
        } elseif ($level === 'INFO') {
            $this->infos[] = $formatted_msg;
        } elseif ($level === 'DEBUG') {
            $this->debugs[] = $formatted_msg;
        }
    }
}
