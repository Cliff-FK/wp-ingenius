<?php

/**
 * Utility class for zipping up content
 *
 * Standard: PSR-2
 *
 * @link http://www.php-fig.org/psr/psr-2
 *
 * @package    DUP_PRO
 * @subpackage classes/utilities
 * @copyright  (c) 2017, Snapcreek LLC
 * @license    https://opensource.org/licenses/GPL-3.0 GNU Public License
 * @since      3.3.0
 */

use Duplicator\Libs\Shell\Shell;

/**
 * Helper class for reporting problems with zipping
 *
 * @see DUP_PRO_Zip_U
 */
class DUP_PRO_Problem_Fix
{
    /** @var string The detected problem */
    public $problem = '';
    /** @var string A recommended fix for the problem */
    public $fix = '';
}

class DUP_PRO_Zip_U
{
    /**
     * Gets an array of possible ZipArchive problems on the server
     *
     * @return string[]
     */
    private static function getPossibleZipPaths()
    {
        return [
            '/usr/bin/zip',
            '/opt/local/bin/zip', // RSR TODO put back in when we support shellexec on windows,
            //'C:/Program\ Files\ (x86)/GnuWin32/bin/zip.exe');
            '/opt/bin/zip',
            '/bin/zip',
            '/usr/local/bin/zip',
            '/usr/sfw/bin/zip',
            '/usr/xdg4/bin/zip',
        ];
    }

    /**
     * Gets an array of possible ShellExec Zip problems on the server
     *
     * @return DUP_PRO_Problem_Fix[]
     */
    public static function getShellExecZipProblems(): array
    {
        $problem_fixes = [];
        if (!self::getShellExecZipPath()) {
            $filepath       = null;
            $possible_paths = self::getPossibleZipPaths();
            foreach ($possible_paths as $path) {
                if (file_exists($path)) {
                    $filepath = $path;
                    break;
                }
            }

            if ($filepath == null) {
                $problem_fix          = new DUP_PRO_Problem_Fix();
                $problem_fix->problem = __('Zip executable not present', 'duplicator-pro');
                $problem_fix->fix     = __('Install the zip executable and make it accessible to PHP.', 'duplicator-pro');
                $problem_fixes[]      = $problem_fix;
            }

            if (Shell::isSuhosinEnabled()) {
                $fixDisabled = __(
                    'Remove any of the following from the disable_functions or suhosin.executor.func.blacklist setting in the php.ini files: %1$s',
                    'duplicator-pro'
                );
            } else {
                $fixDisabled = __(
                    'Remove any of the following from the disable_functions setting in the php.ini files: %1$s',
                    'duplicator-pro'
                );
            }

            //Function disabled at server level
            if (Shell::hasDisabledFunctions(['escapeshellarg', 'escapeshellcmd', 'extension_loaded'])) {
                $problem_fix          = new DUP_PRO_Problem_Fix();
                $problem_fix->problem = __('Required functions disabled in the php.ini.', 'duplicator-pro');
                $problem_fix->fix     = sprintf($fixDisabled, 'escapeshellarg, escapeshellcmd, extension_loaded.');
                $problem_fixes[]      = $problem_fix;
            }

            if (Shell::hasDisabledFunctions(['popen', 'pclose', 'exec', 'shell_exec'])) {
                $problem_fix          = new DUP_PRO_Problem_Fix();
                $problem_fix->problem = __('Required functions disabled in the php.ini.', 'duplicator-pro');
                $problem_fix->fix     = sprintf($fixDisabled, 'popen, pclose or exec or shell_exec.');
                $problem_fixes[]      = $problem_fix;
            }
        }

        return $problem_fixes;
    }

    /**
     * Get the path to the zip program executable on the server
     * If wordpress have multiple scan path shell zip archive is disabled
     *
     * @return null|string   Returns the path to the zip program or null if isn't available
     */
    public static function getShellExecZipPath()
    {
        $filepath = null;
        if (apply_filters('duplicator_pro_is_shellzip_available', Shell::test(Shell::AVAILABLE_COMMANDS))) {
            $scanPath = DUP_PRO_Archive::getScanPaths();
            if (count($scanPath) > 1) {
                return null;
            }

            $shellOutput = Shell::runCommand('hash zip 2>&1', Shell::AVAILABLE_COMMANDS);
            if ($shellOutput !== false && $shellOutput->isEmpty()) {
                $filepath = 'zip';
            } else {
                $possible_paths = self::getPossibleZipPaths();
                foreach ($possible_paths as $path) {
                    if (file_exists($path)) {
                        $filepath = $path;
                        break;
                    }
                }
            }
        }

        return $filepath;
    }

    /**
     * custom shell arg escape sequence
     *
     * @param string $arg argument to escape
     *
     * @return string
     */
    public static function customShellArgEscapeSequence($arg)
    {
        return str_replace([' ', '-'], ['\ ', '\-'], $arg);
    }
}
