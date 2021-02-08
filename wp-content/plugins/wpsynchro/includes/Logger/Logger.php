<?php

namespace WPSynchro\Logger;

interface Logger
{
    public function log($level, $message, $context = "");
}