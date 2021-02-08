<?php

/**
 * Handle custom error reporting, overriding PHP or just adding it
 * @since 1.6.1
 */

namespace WPSynchro\Utilities\ErrorHandler;

class CustomPHPErrorHandler
{
    // Setup
    public $pass_errors_to_php_native_handler = true;
    // Logs
    public $errors = [];
    public $warnings = [];
    public $debugs = [];
    // Log callable, to instant log to some function
    public $instant_callable;
    // Old error reporting value
    public $old_error_reporting;


    /**
     *  Add the error handler to PHP
     *  @since 1.6.1
     */
    public function addErrorHandler()
    {
        $this->old_error_reporting = error_reporting(\E_ALL);
        ini_set("display_errors", "off");
        set_error_handler([$this, 'handleErrors'], \E_ALL);
        set_exception_handler([$this, 'handleExceptions']);
        register_shutdown_function([$this, 'handleBuiltInPHPErrors']);
    }

    /**
     *  Remove the error handler from PHP
     *  @since 1.6.1
     */
    public function removeErrorHandler()
    {
        error_reporting($this->old_error_reporting);
        ini_restore("display_errors");
        restore_error_handler();
        restore_exception_handler();
    }

    /**
     *  Handle PHP errors
     *  @since 1.6.1
     */
    public function handleErrors($err_number, $err_string, $err_file, $err_line)
    {
        $errorstring = $this->getPrettyErrorString($err_number);
        if (in_array($err_number, [\E_ERROR, \E_CORE_ERROR, \E_COMPILE_ERROR, \E_USER_ERROR])) {
            $msg = sprintf(
                __("[%s] %s - Fatal error happened, which was triggered in %s on line %d", "wpsynchro"),
                $errorstring,
                $err_string,
                $err_file,
                $err_line
            );
            $this->errors[] = $msg;
            $this->instantLog("ERROR", $msg);
        } elseif (in_array($err_number, [\E_WARNING, \E_CORE_WARNING, \E_USER_WARNING])) {
            $msg = sprintf(
                __("[%s] %s - Warning in %s on line %d", "wpsynchro"),
                $errorstring,
                $err_string,
                $err_file,
                $err_line
            );
            $this->warnings[] = $msg;
            $this->instantLog("WARNING", $msg);
        } else {
            $msg = sprintf(
                __("[%s] %s - Triggered in %s on line %d", "wpsynchro"),
                $errorstring,
                $err_string,
                $err_file,
                $err_line
            );
            $this->debugs[] = $msg;
            $this->instantLog("DEBUG", $msg);
        }

        if ($this->pass_errors_to_php_native_handler) {
            return false;   // Also let the PHP error handler do its stuff, so it will be logged in error log etc
        }
        return true;
    }

    /**
     *  Handle PHP exceptions
     *  @since 1.6.1
     */
    public function handleExceptions($exception)
    {
        $this->handleErrors(\E_USER_ERROR, $exception->getMessage(), $exception->getFile(), $exception->getLine());
    }

    /**
     *  Handle built-in PHP errors, that set_error_handler does not catch
     *  @since 1.6.1
     */
    public function handleBuiltInPHPErrors()
    {
        $last_error = error_get_last();
        if (is_array($last_error)) {
            if (in_array($last_error['type'], [\E_ERROR, \E_PARSE, \E_CORE_ERROR, \E_CORE_WARNING, \E_COMPILE_ERROR, \E_COMPILE_WARNING])) {
                $this->handleErrors($last_error['type'], $last_error['message'], $last_error['file'], $last_error['line']);
            }
        }
    }

    /**
     *  Instant log, optional attachable callable that will be called right away when it happens
     *  @since 1.6.1
     */
    public function instantLog($type, $msg)
    {
        if (!is_callable($this->instant_callable)) {
            return;
        }
        call_user_func($this->instant_callable, $type, $msg);
    }

    /**
     *  Set not to pass logs to native PHP handler also
     */
    public function setDoNotPassLogsToPHP()
    {
        $this->pass_errors_to_php_native_handler = false;
    }

    /**
     *  Get prettier version of error code
     */
    public function getPrettyErrorString($errorcode)
    {
        switch ($errorcode) {
            case E_ERROR: // 1 //
                return 'E_ERROR';
            case E_WARNING: // 2 //
                return 'E_WARNING';
            case E_PARSE: // 4 //
                return 'E_PARSE';
            case E_NOTICE: // 8 //
                return 'E_NOTICE';
            case E_CORE_ERROR: // 16 //
                return 'E_CORE_ERROR';
            case E_CORE_WARNING: // 32 //
                return 'E_CORE_WARNING';
            case E_COMPILE_ERROR: // 64 //
                return 'E_COMPILE_ERROR';
            case E_COMPILE_WARNING: // 128 //
                return 'E_COMPILE_WARNING';
            case E_USER_ERROR: // 256 //
                return 'E_USER_ERROR';
            case E_USER_WARNING: // 512 //
                return 'E_USER_WARNING';
            case E_USER_NOTICE: // 1024 //
                return 'E_USER_NOTICE';
            case E_STRICT: // 2048 //
                return 'E_STRICT';
            case E_RECOVERABLE_ERROR: // 4096 //
                return 'E_RECOVERABLE_ERROR';
            case E_DEPRECATED: // 8192 //
                return 'E_DEPRECATED';
            case E_USER_DEPRECATED: // 16384 //
                return 'E_USER_DEPRECATED';
        }
        return "UNKNOWN";
    }
}
