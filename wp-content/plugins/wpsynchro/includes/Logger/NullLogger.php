<?php
namespace WPSynchro\Logger;

/**
 * NULL logger
 */
class NullLogger implements Logger
{
    public function log($level, $message, $context = "")
    {
    }
}
