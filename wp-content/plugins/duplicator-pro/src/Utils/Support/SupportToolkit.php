<?php

namespace Duplicator\Utils\Support;

use DUP_PRO_Log;
use DUP_PRO_Package;
use DUP_PRO_Server;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Utils\Logging\TraceLogMng;
use Duplicator\Utils\ZipArchiveExtended;
use Exception;

class SupportToolkit
{
    const SUPPORT_TOOLKIT_BACKUP_NUMBER = 10;
    const SUPPORT_TOOLKIT_PREFIX        = 'duplicator_support_toolkit_';

    /**
     * Returns true if the diagnostic data can be downloaded
     *
     * @return bool true if diagnostic info can be downloaded
     */
    public static function isAvailable()
    {
        return ZipArchiveExtended::isPhpZipAvailable();
    }

    /**
     * Returns an anchor tag with the diagnostic data label and download URL
     * If the diagnostic data can not be downloaded, it returns a string with Backup trace and debug log download instructions
     *
     * @param string[] $fallbackLinks Which logs to include in case the diagnostic data is not available
     *                                Possible values: 'package', 'trace', 'debug'
     *
     * @return string
     */
    public static function getDiagnosticInfoLinks($fallbackLinks = [])
    {
        if (self::isAvailable()) {
            return sprintf(
                _x(
                    '%1$sdiagnostic data file%2$s',
                    '1: opening anchor tag, 2: closing anchor tag',
                    'duplicator-pro'
                ),
                '<a href="' . esc_url(self::getSupportToolkitDownloadUrl()) . '">',
                '</a>'
            );
        }

        $fallbackLinks = !empty($fallbackLinks) ? $fallbackLinks : [
            'package',
            'trace',
            'debug',
        ];

        $links = [];
        foreach ($fallbackLinks as $link) {
            switch ($link) {
                case 'package':
                    $links[] = sprintf(
                        _x(
                            '%1$spackage%2$s',
                            '1: opening anchor tag, 2: closing anchor tag',
                            'duplicator-pro'
                        ),
                        '<a href="' . esc_url(DUPLICATOR_PRO_DUPLICATOR_DOCS_URL . 'how-do-i-read-the-package-build-log/') . '" target="_blank">',
                        '</a>'
                    );
                    break;
                case 'trace':
                    $links[] = sprintf(
                        _x(
                            '%1$strace%2$s',
                            '1: opening anchor tag, 2: closing anchor tag',
                            'duplicator-pro'
                        ),
                        '<a href="' . esc_url(DUPLICATOR_PRO_DUPLICATOR_DOCS_URL . 'how-do-i-read-the-package-trace-log/') . '" target="_blank">',
                        '</a>'
                    );
                    break;
                case 'debug':
                    $links[] = __('debug', 'duplicator-pro');
                    break;
            }
        }

        if (count($links) === 1) {
            return sprintf(
                _x(
                    '%1$s log',
                    '1: log file label (backup, trace, debug)',
                    'duplicator-pro'
                ),
                $links[0]
            );
        }

        if (count($links) === 2) {
            return sprintf(
                _x(
                    '%1$s and %2$s logs',
                    '1: first log label, 2: second log label (backup, trace, debug)',
                    'duplicator-pro'
                ),
                $links[0],
                $links[1]
            );
        }

        return sprintf(
            _x(
                '%1$s, %2$s and %3$s logs',
                '1: first log, 2: second log, 3: third log (backup, trace, debug)',
                'duplicator-pro'
            ),
            $links[0],
            $links[1],
            $links[2]
        );
    }

    /**
     * Returns the diagnostic data download URL if available,
     * empty string otherwise.
     *
     * @return string
     */
    public static function getSupportToolkitDownloadUrl()
    {
        if (!self::isAvailable()) {
            return '';
        }

        return admin_url('admin-ajax.php') . '?' . http_build_query([
            'action' => 'duplicator_download_support_toolkit',
            'nonce'  => wp_create_nonce('duplicator_download_support_toolkit'),
        ]);
    }

    /**
     * Generates a support toolkit zip file
     *
     * @return string The path to the generated zip file
     */
    public static function getToolkit()
    {
        $tempZipFilePath = DUPLICATOR_PRO_SSDIR_PATH_TMP . '/' .
            self::SUPPORT_TOOLKIT_PREFIX . date(DUP_PRO_Package::PACKAGE_HASH_DATE_FORMAT) . '_' .
            SnapUtil::generatePassword(16, false, false) . '.zip';
        $zip             = new ZipArchiveExtended($tempZipFilePath);
        if ($zip->open() === false) {
            throw new Exception(__('Failed to create zip file', 'duplicator-pro'));
        }

        // Trace log
        foreach (TraceLogMng::getInstance()->getTraceFiles() as $traceFile) {
            $zip->addFile($traceFile, basename($traceFile)); // don't check errors
        }

        // Debug log (if it exists)
        if (WP_DEBUG_LOG !== false) {
            if (is_bool(WP_DEBUG_LOG)) {
                $zip->addFile(
                    trailingslashit(wp_normalize_path(realpath(WP_CONTENT_DIR))) . 'debug.log',
                    '',
                    10 * MB_IN_BYTES
                );
            } elseif (is_string(WP_DEBUG_LOG) && strlen(WP_DEBUG_LOG) > 0) {
                //The path can be relative too so resolve via safepath
                $zip->addFile(
                    SnapIO::safePath(WP_DEBUG_LOG, true),
                    '',
                    10 * MB_IN_BYTES
                );
            }
        }

        //phpinfo (as html)
        $zip->addFileFromString('phpinfo.html', self::getPhpInfo());

        //custom server settings info (as html)
        $zip->addFileFromString('serverinfo.txt', self::getPlainServerSettings());

        //Last 10 backup build logs
        DUP_PRO_Package::by_status_callback(
            function (DUP_PRO_Package $package) use ($zip): void {
                $zip->addFile($package->get_safe_log_filepath());
            },
            [],
            self::SUPPORT_TOOLKIT_BACKUP_NUMBER,
            0,
            '`id` DESC'
        );

        return $tempZipFilePath;
    }

    /**
     * Returns the contents of the "Server Settings" section in "Tools" > "General" in plain text format
     *
     * @return string
     */
    private static function getPlainServerSettings()
    {
        $result = '';

        foreach (DUP_PRO_Server::getServerSettingsData() as $section) {
            $result .= $section['title'] . "\n";
            $result .= str_repeat('=', 50) . "\n";
            foreach ($section['settings'] as $data) {
                $result .= str_pad($data['logLabel'], 20, ' ', STR_PAD_RIGHT) . ' ' . $data['value'] . "\n";
            }
            $result .= "\n\n";
        }

        return $result;
    }

    /**
     * Returns the output of phpinfo as a string
     *
     * @return string
     */
    private static function getPhpInfo()
    {
        ob_start();
        SnapUtil::phpinfo();
        $phpInfo = ob_get_clean();

        return $phpInfo === false ? '' : $phpInfo;
    }
}
