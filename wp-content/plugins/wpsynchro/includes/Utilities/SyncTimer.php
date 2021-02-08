<?php
namespace WPSynchro\Utilities;

/**
 * Class to represent a single timer
 *
 * @since 1.3.0
 */
class SyncTimer
{

    public $desc = null;
    public $time_start = null;
    public $time_end = null;
    public $elapsed = 0;

    function __construct()
    {
        
    }

    public function setStart($time_as_float)
    {
        $this->time_start = $time_as_float;
    }

    public function startNow()
    {
        $this->time_start = microtime(true);
    }

    public function endNow()
    {
        $this->time_end = microtime(true);
        $this->elapsed = $this->time_end - $this->time_start;
        return $this->elapsed;
    }

    public function getTimeUntilNow()
    {
        return microtime(true) - $this->time_start;
    }

    public function getElapsed()
    {
        return $this->elapsed;
    }
}
