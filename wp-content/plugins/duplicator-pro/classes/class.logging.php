<?php

/**
 * Used to create Backup and application trace logs
 *
 * Package logs: Consist of a separate log file for each Backup created
 * Trace logs:   Created only when tracing is enabled see Settings > General
 *               One trace log is created and when it hits a threshold a
 *               second one is made
 *
 * Standard: PSR-2
 *
 * @link http://www.php-fig.org/psr/psr-2
 *
 * @package    DUP_PRO
 * @subpackage classes
 * @copyright  (c) 2017, Snapcreek LLC
 * @license    https://opensource.org/licenses/GPL-3.0 GNU Public License
 * @since      3.0.0
 */

defined('ABSPATH') || defined('DUPXABSPATH') || exit;

use Duplicator\Libs\Snap\SnapLog;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Libs\Snap\SnapWP;
use Duplicator\Utils\Logging\TraceLogMng;

class DUP_PRO_Log
{
    /** @var ?resource The file handle used to write to the Backup log file */
    private static $logFileHandle;

    /**
     * Is tracing enabled
     *
     * @return bool
     */
    public static function isTraceEnabled()
    {
        static $traceEnabled = null;
        if (is_null($traceEnabled)) {
            $traceEnabled = (bool) get_option('duplicator_pro_trace_log_enabled', false);
            if ($traceEnabled) {
                TraceLogMng::getInstance(); // The init create the trace file if it doesn't exist
            }
        }
        return $traceEnabled;
    }

    /**
     * Open a log file connection for writing to the Backup log file
     *
     * @param string $nameHash The Name of the log file to create
     *
     * @return bool
     */
    public static function open($nameHash): bool
    {
        if (strlen($nameHash) == 0) {
            throw new Exception("A name value is required to open a file log.");
        }
        self::close();
        if ((self::$logFileHandle = @fopen(DUPLICATOR_PRO_SSDIR_PATH . "/{$nameHash}_log.txt", "a+")) === false) {
            self::$logFileHandle = null;
            return false;
        } else {
            // By initializing the error_handler on opening the log, I am sure that when a Backup is processed, the handler is active.
            DUP_PRO_Handler::init_error_handler();
            return true;
        }
    }

    /**
     * Close the Backup log file connection if is opened
     *
     * @return bool Returns TRUE on success or FALSE on failure.
     */
    public static function close()
    {
        $result = true;
        if (!is_null(self::$logFileHandle)) {
            $result              = @fclose(self::$logFileHandle);
            self::$logFileHandle = null;
        } else {
            $result = true;
        }
        return $result;
    }

    /**
     *  General information send to the Backup log if opened
     *
     *  @param string $msg The message to log
     *
     *  @return void
     */
    public static function info($msg)
    {
        if (!is_null(self::$logFileHandle)) {
            @fwrite(self::$logFileHandle, $msg . "\n");
        }
    }

    /**
     * Info exception
     *
     * @param Exception|Error $e   The exception to trace
     * @param string          $msg Addtional message
     *
     * @return void
     */
    public static function infoException($e, $msg = '')
    {
        $log = '';
        if (strlen($msg) > 0) {
            $log = $msg . "\n";
        }
        $log .= SnapLog::getTextException($e);
        self::info($msg);
    }

    /**
     * Print_r to the Backup log if opened
     *
     * @param mixed  $val  The value to print_r
     * @param string $name The name of the value
     *
     * @return void
     */
    public static function print_r_info($val, $name = '')
    {
        $msg  = empty($name) ? '' : 'VALUE ' . $name . ': ';
        $msg .= print_r($val, true);
        self::info($msg);
    }

    /**
     * General information send to the Backup log and trace log
     *
     * @param string $msg                       The message to log
     * @param bool   $audit                     Add the trace message to the PHP error log
     *                                          additional constraints are required
     * @param string $calling_function_override Override the calling function name
     * @param bool   $force_trace               Force the trace to be written to the trace log
     *
     * @return void
     */
    public static function infoTrace($msg, $audit = true, $calling_function_override = null, $force_trace = false)
    {
        self::info($msg);
        self::trace($msg, $audit, $calling_function_override, $force_trace, 1);
    }

    /**
     * Info trace exception
     *
     * @param Exception|Error $e   The exception to trace
     * @param string          $msg Addtional message
     *
     * @return void
     */
    public static function infoTraceException($e, $msg = '')
    {
        self::infoException($e, $msg);
        self::traceException($e, $msg);
    }

    /**
     * Called for the Backup log when an error is detected and no further processing should occur
     *
     * @param string $msg    The message to log
     * @param string $detail Additional details to help resolve the issue if possible
     * @param bool   $die    Issue a die command when finished logging
     *
     * @return void
     */
    public static function error($msg, $detail = '', $die = true)
    {
        if ($detail == '') {
            $detail = '(no detail)';
        }
        // phpcs:ignore PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.Changed
        $source   = self::getStack(debug_backtrace());
        $err_msg  = "\n\n====================================================================\n";
        $err_msg .= "!RUNTIME ERROR!\n";
        $err_msg .= "---------------------------------------------------------------------\n";
        $err_msg .= "MESSAGE:\n{$msg}\n";
        $err_msg .= "DETAILS:\n{$detail}\n";
        $err_msg .= "---------------------------------------------------------------------\n";
        $err_msg .= "TRACE:\n{$source}";
        $err_msg .= "====================================================================\n\n";
        self::infoTrace($err_msg);
        if ($die) {
            self::close();
            // Output to browser
            $browser_msg  = "RUNTIME ERROR:<br/>An error has occured. Please try again!<br/>";
            $browser_msg .= "See the duplicator log file for full details: Duplicator Pro &gt; Tools &gt; Logging<br/><br/>";
            $browser_msg .= "MESSAGE:<br/> {$msg} <br/><br/>";
            $browser_msg .= "DETAILS: {$detail} <br/>";
            die(wp_kses($browser_msg, ['br' => []]));
        }
    }

    /**
     * The current stack trace of a PHP call
     *
     * @param array<array<string, mixed>> $stacktrace The current debug stack
     *
     * @return string A log friend stack-trace view of info
     */
    public static function getStack($stacktrace)
    {
        $output = "";
        $i      = 1;
        foreach ($stacktrace as $node) {
            $file_output     = isset($node['file']) ? basename($node['file']) : '';
            $function_output = isset($node['function']) ? basename($node['function']) : '';
            $line_output     = isset($node['line']) ? basename($node['line']) : '';
            $output         .= "$i. " . $file_output . " : " . $function_output . " (" . $line_output . ")\n";
            $i++;
        }

        return $output;
    }

    /**
     * Deletes the trace log and backup trace log files
     *
     * @return boolean true on success of deletion of trace log otherwise returns false
     */
    public static function deleteTraceLog(): bool
    {
        return TraceLogMng::getInstance()->deleteAllTraceFiles();
    }

    /**
     * Gets the active trace file path
     *
     * @return string   Returns the full path to the active trace file (i.e. dup-pro_hash.txt)
     */
    public static function getTraceFilepath()
    {
        return TraceLogMng::getInstance()->getCurrentFilepath();
    }

    /**
     * Gets the active trace file URL path
     *
     * @return string   Returns the URL to the active trace file
     */
    public static function getTraceURL()
    {
        return TraceLogMng::getInstance()->getCurrentURL();
    }

    /**
     * Gets the current file size of the active trace file
     *
     * @return string   Returns a human readable file size of the active trace file
     */
    public static function getTraceStatus()
    {
        $size = TraceLogMng::getInstance()->getTraceFilesSize();
        return ($size == 0 ? __('No Log', 'duplicator-pro') : DUP_PRO_U::byteSize($size));
    }

    /**
     * Adds a message to the active trace log
     *
     * @param string $message                   The message to add to the active trace
     * @param bool   $audit                     Add the trace message to the PHP error log
     *                                          additional constraints are required
     * @param string $calling_function_override Override the calling function name
     * @param bool   $force_trace               Force the trace to be written to the trace log
     * @param int    $backTraceBack             The number of backtrace calls to go back
     *
     * @return void
     */
    public static function trace($message, $audit = true, $calling_function_override = null, $force_trace = false, $backTraceBack = 0)
    {
        if (self::isTraceEnabled() || $force_trace) {
            $send_trace_to_error_log = (bool) get_option('duplicator_pro_send_trace_to_error_log', false);

            $remotePort  = SnapUtil::sanitizeIntInput(INPUT_SERVER, 'REMOTE_PORT', -1);
            $remotePort  = $remotePort > 0 ? $remotePort : '';
            $requestTime = SnapUtil::sanitizeIntInput(INPUT_SERVER, 'REQUEST_TIME', -1);
            $requestTime = $requestTime > 0 ? $requestTime : '';
            $remoteAddr  = SnapUtil::sanitizeTextInput(INPUT_SERVER, 'REMOTE_ADDR', '');
            $unique_id   = sprintf("%08x", abs(crc32($remoteAddr . $requestTime . $remotePort)));

            if ($calling_function_override == null) {
                $calling_function = SnapUtil::getCallingFunctionName($backTraceBack);
            } else {
                $calling_function = $calling_function_override;
            }

            if (is_object($message)) {
                $ov      = get_object_vars($message);
                $message = print_r($ov, true);
            } elseif (is_array($message)) {
                $message = print_r($message, true);
            }

            $logging_message = "[{$unique_id}] {$calling_function} {$message}";

            // Write to error log if warranted - if either it's a non audit(error) or tracing has been piped to the error log
            if (($audit == false) || ($send_trace_to_error_log) || ($force_trace) && WP_DEBUG && WP_DEBUG_LOG) {
                $formatted_time = date('d-m H:i:s', time() + SnapWP::getGMTOffset());
                SnapLog::phpErr($formatted_time . ' ' . $logging_message);
            }

            // Everything goes to the plugin log, whether it's part of Backup generation or not.
            TraceLogMng::getInstance()->write($logging_message);
        }
    }

    /**
     * Trace exception
     *
     * @param Exception|Error $e   The exception to trace
     * @param string          $msg Addtional message
     *
     * @return void
     */
    public static function traceException($e, $msg = '')
    {
        $log = '';
        if (strlen($msg) > 0) {
            $log = $msg . "\n";
        }
        $log .= SnapLog::getTextException($e);
        self::trace($log);
    }

    /**
     * Prints a variable to the trace log
     *
     * @param mixed  $val                       The value to print_r
     * @param string $name                      The name of the value
     * @param bool   $audit                     Add the trace message to the PHP error log
     *                                          additional constraints are required
     * @param string $calling_function_override Override the calling function name
     * @param bool   $force_trace               Force the trace to be written to the trace log
     *
     * @return void
     */
    public static function print_r_trace($val, $name = '', $audit = true, $calling_function_override = null, $force_trace = false)
    {
        $msg  = empty($name) ? '' : 'VALUE ' . $name . ': ';
        $msg .= print_r($val, true);
        if ($calling_function_override == null) {
            $calling_function = SnapUtil::getCallingFunctionName();
        } else {
            $calling_function = $calling_function_override;
        }

        self::trace($msg, $audit, $calling_function, $force_trace, 1);
    }

    /**
     * Adds a message to the active trace log with ***ERROR*** prepended
     *
     * @param string $message The error message to add to the active trace
     *
     * @return void
     */
    public static function traceError($message)
    {
        SnapUtil::errorLog("***ERROR*** $message");
        self::infoTrace("***ERROR*** $message", false, SnapUtil::getCallingFunctionName());
    }

    /**
     * Adds a message followed by an object dump to the message trace
     *
     * @param string $message The message to add to the active trace
     * @param mixed  $object  Generic data
     *
     * @return void
     */
    public static function traceObject($message, $object)
    {
        $calling = SnapUtil::getCallingFunctionName();
        self::trace($message . "\n", true, $calling);
        self::trace(SnapLog::v2str($object), true, $calling);
    }

    /**
     * Does the trace file exists
     *
     * @return bool Returns true if an active trace file exists
     */
    public static function traceFileExists()
    {
        $file_path = DUP_PRO_Log::getTraceFilepath();
        return file_exists($file_path);
    }
}

class DUP_PRO_Handler
{
    const MODE_OFF = 0;
    // don't write in log
    const MODE_LOG = 1;
    // write errors in log file
    const MODE_VAR = 2;
    // put php errors in $varModeLog static var
    const SHUTDOWN_TIMEOUT = 'tm';

    /** @var array<string,mixed> */
    private static $shutdownReturns = ['tm' => 'timeout'];
    /** @var int */
    private static $handlerMode = self::MODE_LOG;
    /** @var bool print code reference and errno at end of php error line  [CODE:10|FILE:test.php|LINE:100] */
    private static $codeReference = true;
    /** @var bool print prefix in php error line [PHP ERR][WARN] MSG: ..... */
    private static $errPrefix = true;
    /** @var string php errors in MODE_VAR */
    private static $varModeLog = '';

    /**
     * This function only initializes the error handler the first time it is called
     *
     * @return void
     */
    public static function init_error_handler()
    {
        static $initialized = null;
        if ($initialized === null) {
            @set_error_handler([self::class, 'error']);
            @register_shutdown_function([self::class, 'shutdown']);
            $initialized = true;
        }
    }

    /**
     * Error handler
     *
     * @param integer $errno   Error level
     * @param string  $errstr  Error message
     * @param string  $errfile Error file
     * @param integer $errline Error line
     *
     * @return bool
     */
    public static function error($errno, $errstr, $errfile, $errline): bool
    {
        switch (self::$handlerMode) {
            case self::MODE_OFF:
                if ($errno == E_ERROR) {
                    $log_message = self::getMessage($errno, $errstr, $errfile, $errline);
                    DUP_PRO_Log::error($log_message);
                }

                break;
            case self::MODE_VAR:
                self::$varModeLog .= self::getMessage($errno, $errstr, $errfile, $errline) . "\n";
                break;
            case self::MODE_LOG:
            default:
                switch ($errno) {
                    case E_ERROR:
                        $log_message = self::getMessage($errno, $errstr, $errfile, $errline);
                        DUP_PRO_Log::error($log_message);
                        break;
                    case E_NOTICE:
                    case E_WARNING:
                    default:
                        $log_message = self::getMessage($errno, $errstr, $errfile, $errline);
                        DUP_PRO_Log::infoTrace($log_message);
                        break;
                }
        }
        return true;
    }

    /**
     * Get error message
     *
     * @param integer $errno   Error level
     * @param string  $errstr  Error message
     * @param string  $errfile Error file
     * @param integer $errline Error line
     *
     * @return string
     */
    private static function getMessage($errno, $errstr, $errfile, $errline)
    {
        $result = '';
        if (self::$errPrefix) {
            $result = '[PHP ERR]';
            switch ($errno) {
                case E_ERROR:
                    $result .= '[FATAL]';
                    break;
                case E_WARNING:
                    $result .= '[WARN]';
                    break;
                case E_NOTICE:
                    $result .= '[NOTICE]';
                    break;
                default:
                    $result .= '[ISSUE]';
                    break;
            }
            $result .= ' MSG:';
        }

        $result .= $errstr;
        if (self::$codeReference) {
            $result .= ' [CODE:' . $errno . '|FILE:' . $errfile . '|LINE:' . $errline . ']';
        }

        return $result;
    }

    /**
     * if setMode is called without params set as default
     *
     * @param int  $mode          ENUM self::MODE_*
     * @param bool $errPrefix     print prefix in php error line [PHP ERR][WARN] MSG: .....
     * @param bool $codeReference print code reference and errno at end of php error line  [CODE:10|FILE:test.php|LINE:100]
     *
     * @return void
     */
    public static function setMode($mode = self::MODE_LOG, $errPrefix = true, $codeReference = true)
    {
        switch ($mode) {
            case self::MODE_OFF:
            case self::MODE_VAR:
                self::$handlerMode = $mode;

                break;
            case self::MODE_LOG:
            default:
                self::$handlerMode = self::MODE_LOG;
        }

        self::$varModeLog    = '';
        self::$errPrefix     = $errPrefix;
        self::$codeReference = $codeReference;
    }

    /**
     *
     * @return string return var log string in MODE_VAR
     */
    public static function getVarLog()
    {
        return self::$varModeLog;
    }

    /**
     *
     * @return string return var log string in MODE_VAR and clean var
     */
    public static function getVarLogClean()
    {
        $result           = self::$varModeLog;
        self::$varModeLog = '';
        return $result;
    }

    /**
     *
     * @param string $status timeout
     * @param string $str    string
     *
     * @return void
     */
    public static function setShutdownReturn($status, $str)
    {
        self::$shutdownReturns[$status] = $str;
    }

    /**
     * Shutdown handler
     *
     * @return void
     */
    public static function shutdown()
    {
        if (($error = error_get_last())) {
            if (preg_match('/^Maximum execution time (?:.+) exceeded$/i', $error['message'])) {
                echo esc_html(self::$shutdownReturns[self::SHUTDOWN_TIMEOUT]);
            }
            self::error($error['type'], $error['message'], $error['file'], $error['line']);
        }
    }
}
