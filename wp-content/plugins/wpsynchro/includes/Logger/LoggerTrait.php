<?php

/**
 *  Global logger trait - Provides file logging to all
 */

namespace WPSynchro\Logger;

trait LoggerTrait
{
    public $logger = null;

    /**
     *  Log data
     */
    public function log($level, $message, $context = "")
    {
        if ($this->logger === null) {
            global $wpsynchro_container;
            $this->logger = $wpsynchro_container->get('class.Logger');
        }
        $this->logger->log($level, $message, $context);
    }
}
