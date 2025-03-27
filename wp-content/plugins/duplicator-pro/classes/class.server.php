<?php

defined("ABSPATH") or die("");

use Duplicator\Addons\DropboxAddon\Models\DropboxStorage;
use Duplicator\Addons\ProBase\License\License;
use Duplicator\Addons\ProBase\LicensingController;
use Duplicator\Core\MigrationMng;
use Duplicator\Libs\Shell\Shell;
use Duplicator\Libs\Snap\FunctionalityCheck;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Models\DynamicGlobalEntity;
use Duplicator\Models\Storages\StoragesUtil;

/**
 * Class used to get server info
 *
 * @package Duplicator\classes
 */
class DUP_PRO_Server
{
    /**
     * Is URL Fopen enabled
     *
     * @return bool
     */
    public static function isURLFopenEnabled()
    {
        $val = ini_get('allow_url_fopen');
        return ($val == true);
    }

    /**
     * MySQL escape test
     *
     * @return bool
     */
    public static function mysqlEscapeIsOk()
    {
        $escape_test_string     = chr(0) . chr(26) . "\r\n'\"\\";
        $escape_expected_result = "\"\\0\Z\\r\\n\\'\\\"\\\\\"";
        $escape_actual_result   = DUP_PRO_DB::escValueToQueryString($escape_test_string);
        $result                 = $escape_expected_result === $escape_actual_result;
        if (!$result) {
            $msg = "mysqli_real_escape_string test results\n" .
                "Expected escape result: " . $escape_expected_result . "\n" .
                "Actual escape result: " . $escape_actual_result;
            DUP_PRO_Log::trace($msg);
        }
        return $result;
    }

    /**
     * Gets string representation of outbound IP address
     *
     * @return bool|string Outbound IP Address or false on error
     */
    public static function getOutboundIP()
    {
        $context = stream_context_create([
            'http' =>
            ['timeout' => 15],
        ]);

        $outboundIP = @file_get_contents('https://checkip.amazonaws.com', false, $context);

        if ($outboundIP !== false) {
            // Make sure it's a properly formatted IP address
            if (preg_match('/^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/', $outboundIP) !== 1) {
                $outboundIP = false;
            }
        }

        return $outboundIP !== false ? trim($outboundIP) : false;
    }

    /**
     * Gets the system requirements which must pass to build a Backup
     *
     * @return array<string, mixed> An array of requirements
     */
    public static function getRequirments(): array
    {
        $dup_tests = [];
        StoragesUtil::getDefaultStorage()->initStorageDirectory(true);

        // PHP SUPPORT
        $dup_tests['PHP'] = [];
        $allRequiredPass  = FunctionalityCheck::checkList(self::getFunctionalitiesCheckList(), true, $noPassFuncs);

        foreach ($noPassFuncs as $func) {
            switch ($func->getType()) {
                case FunctionalityCheck::TYPE_FUNCTION:
                    $errorMessage = $func->getItemKey() . " function doesn't exist.";
                    break;
                case FunctionalityCheck::TYPE_CLASS:
                    $errorMessage = $func->getItemKey() . " class doesn't exist.";
                    break;
                default:
                    throw new Exception('Invalid item type');
            }
            // We will log even if non-required functionalities fail
            self::logRequirementFail('Fail', $errorMessage);
        }

        $dup_tests['PHP']['ALL'] = !in_array('Fail', $dup_tests['PHP']) && $allRequiredPass ? 'Pass' : 'Fail';

        //PERMISSIONS
        $home_path = DUP_PRO_Archive::getArchiveListPaths('home');
        if (strlen($home_path) === 0) {
            $home_path = DIRECTORY_SEPARATOR;
        }
        if (($handle_test = @opendir($home_path)) === false) {
            $dup_tests['IO']['WPROOT'] = 'Fail';
        } else {
            @closedir($handle_test);
            $dup_tests['IO']['WPROOT'] = 'Pass';
        }

        self::logRequirementFail($dup_tests['IO']['WPROOT'], $home_path . ' (home path) can\'t be opened.');

        $dup_tests['IO']['SSDIR'] = is_writeable(DUPLICATOR_PRO_SSDIR_PATH) ? 'Pass' : 'Fail';
        self::logRequirementFail($dup_tests['IO']['SSDIR'], DUPLICATOR_PRO_SSDIR_PATH . ' (DUPLICATOR_PRO_SSDIR_PATH) can\'t be writeable.');

        $dup_tests['IO']['SSTMP'] = is_writeable(DUPLICATOR_PRO_SSDIR_PATH_TMP) ? 'Pass' : 'Fail';
        self::logRequirementFail($dup_tests['IO']['SSTMP'], DUPLICATOR_PRO_SSDIR_PATH_TMP . ' (DUPLICATOR_PRO_SSDIR_PATH_TMP) can\'t be writeable.');

        $dup_tests['IO']['ALL'] = !in_array('Fail', $dup_tests['IO']) ? 'Pass' : 'Fail';

        //SERVER SUPPORT
        $db_version                    = DUP_PRO_DB::getVersion();
        $dup_tests['SRV']['MYSQL_VER'] = version_compare($db_version, '5.0', '>=') ? 'Pass' : 'Fail';
        self::logRequirementFail($dup_tests['SRV']['MYSQL_VER'], 'MySQL version ' . $db_version . ' is lower than 5.0.');

        //mysqli_real_escape_string test
        $dup_tests['SRV']['MYSQL_ESC'] = self::mysqlEscapeIsOk() ? 'Pass' : 'Fail';
        self::logRequirementFail($dup_tests['SRV']['MYSQL_ESC'], "The function mysqli_real_escape_string is not escaping strings as expected.");


        $dup_tests['SRV']['ALL'] = !in_array('Fail', $dup_tests['SRV']) ? 'Pass' : 'Fail';

        //INSTALLATION FILES
        $dup_tests['RES']['INSTALL'] = !(self::hasInstallFiles()) ? 'Pass' : 'Fail';
        self::logRequirementFail($dup_tests['RES']['INSTALL'], 'Installer file(s) are exist on the server.');

        $dup_tests['Success'] = $dup_tests['PHP']['ALL'] == 'Pass' && $dup_tests['IO']['ALL'] == 'Pass' &&
            $dup_tests['SRV']['ALL'] == 'Pass' && $dup_tests['RES']['INSTALL'] == 'Pass';

        return $dup_tests;
    }

    /**
     * Cet list of functionalities to check
     *
     * @return FunctionalityCheck[]
     */
    public static function getFunctionalitiesCheckList(): array
    {
        $global = DUP_PRO_Global_Entity::getInstance();
        $result = [];

        if ($global->getBuildMode() == DUP_PRO_Archive_Build_Mode::ZipArchive) {
            $result[] = new FunctionalityCheck(
                FunctionalityCheck::TYPE_CLASS,
                \ZipArchive::class,
                true,
                'https://www.php.net/manual/en/class.ziparchive.php',
                '<i style="font-size:12px">'
                   . '<a href="' . DUPLICATOR_PRO_DUPLICATOR_DOCS_URL . 'how-to-work-with-the-different-zip-engines" target="_blank">'
                   . esc_html__('Overview on how to enable ZipArchive', 'duplicator-pro') . '</i></a>'
            );
        }
        $result[] = new FunctionalityCheck(
            FunctionalityCheck::TYPE_FUNCTION,
            'json_encode',
            true,
            'https://www.php.net/manual/en/function.json-encode.php'
        );
        $result[] = new FunctionalityCheck(
            FunctionalityCheck::TYPE_FUNCTION,
            'token_get_all',
            true,
            'https://www.php.net/manual/en/function.token-get-all'
        );
        $result[] = new FunctionalityCheck(
            FunctionalityCheck::TYPE_FUNCTION,
            'file_get_contents',
            true,
            'https://www.php.net/manual/en/function.file-get-contents.php'
        );
        $result[] = new FunctionalityCheck(
            FunctionalityCheck::TYPE_FUNCTION,
            'file_put_contents',
            true,
            'https://www.php.net/manual/en/function.file-put-contents.php'
        );
        $result[] = new FunctionalityCheck(
            FunctionalityCheck::TYPE_FUNCTION,
            'mb_strlen',
            true,
            'https://www.php.net/manual/en/mbstring.installation.php'
        );
        $result[] = new FunctionalityCheck(
            FunctionalityCheck::TYPE_FUNCTION,
            'mb_detect_encoding',
            true,
            'https://www.php.net/manual/en/mbstring.installation.php'
        );
        $result[] = new FunctionalityCheck(
            FunctionalityCheck::TYPE_FUNCTION,
            'mb_convert_encoding',
            true,
            'https://www.php.net/manual/en/mbstring.installation.php'
        );

        return $result;
    }

    /**
     * Logs requirement fail status informative message
     *
     * @param string $testStatus   Either it is Pass or Fail
     * @param string $errorMessage Error message which should be logged
     *
     * @return void
     */
    private static function logRequirementFail($testStatus, $errorMessage)
    {
        if (empty($testStatus)) {
            throw new Exception('Exception: Empty $testStatus [File: ' . __FILE__ . ', Ln: ' . __LINE__);
        }

        if (empty($errorMessage)) {
            throw new Exception('Exception: Empty $errorMessage [File: ' . __FILE__ . ', Ln: ' . __LINE__);
        }

        $validTestStatuses = [
            'Pass',
            'Fail',
        ];

        if (!in_array($testStatus, $validTestStatuses)) {
            throw new Exception('Exception: Invalid $testStatus value: ' . $testStatus . ' [File: ' . __FILE__ . ', Ln: ' . __LINE__);
        }

        if ('Fail' == $testStatus) {
            DUP_PRO_Log::trace($errorMessage);
        }
    }

    /**
     * Gets the system checks which are not required
     *
     * @param DUP_PRO_Package $package The Backup to check
     *
     * @return array<string,mixed> An array of system checks
     */
    public static function getChecks($package): array
    {
        $global = DUP_PRO_Global_Entity::getInstance();
        $checks = [];

        //-----------------------------
        //PHP SETTINGS
        $testWebSrv = false;
        if (defined('WP_CLI') && WP_CLI) {
            $testWebSrv = true;
        } else {
            $serverSoftware = SnapUtil::sanitizeTextInput(INPUT_SERVER, 'SERVER_SOFTWARE', '');
            if (strlen($serverSoftware) > 0) {
                foreach ($GLOBALS['DUPLICATOR_PRO_SERVER_LIST'] as $value) {
                    if (stristr($serverSoftware, (string) $value)) {
                        $testWebSrv = true;
                        break;
                    }
                }
            }
        }
        self::logCheckFalse($testWebSrv, 'Any out of server software (' . implode(', ', $GLOBALS['DUPLICATOR_PRO_SERVER_LIST']) . ') doesn\'t exist.');

        $testOpenBaseDir = ini_get("open_basedir");
        $testOpenBaseDir = empty($testOpenBaseDir);
        self::logCheckFalse($testOpenBaseDir, 'open_basedir is enabled.');

        $max_execution_time = ini_get("max_execution_time");
        $testMaxExecTime    = ($max_execution_time > DUPLICATOR_PRO_SCAN_TIMEOUT) || (strcmp($max_execution_time, 'Off') == 0 || $max_execution_time == 0);

        if (strcmp($max_execution_time, 'Off') == 0) {
            $max_execution_time_error_message = 'max_execution_time should not be' . $max_execution_time;
        } else {
            $max_execution_time_error_message = 'max_execution_time (' . $max_execution_time . ') should not be lower than the DUPLICATOR_PRO_SCAN_TIMEOUT' . DUPLICATOR_PRO_SCAN_TIMEOUT;
        }
        self::logCheckFalse($testMaxExecTime, $max_execution_time_error_message);

        $testDropbox = true;
        if ($package->contains_storage_type(DropboxStorage::getSType())) {
            $testDropbox = function_exists('openssl_csr_new');
            self::logCheckFalse($testDropbox, 'openssl_csr_new function doesn\'t exist and Backup storage have Dropbox storage.');
        }
        $testMySqlConnect = function_exists('mysqli_connect');
        self::logCheckFalse($testMySqlConnect, 'mysqli_connect function doesn\'t exist.');

        $testURLFopen = self::isURLFopenEnabled();
        self::logCheckFalse($testURLFopen, 'URL Fopen isn\'t enabled.');

        $testCURL = SnapUtil::isCurlEnabled();
        self::logCheckFalse($testCURL, 'curl_init function doesn\'t exist.');

        $test64Bit = (bool) strstr(SnapUtil::getArchitectureString(), '64') ;
        self::logCheckFalse($test64Bit, 'This servers PHP architecture is NOT 64-bit.  Backups over 2GB are not possible.');

        $testMemory = self::hasEnoughMemory();
        self::logCheckFalse($testCURL, 'memory_limit is less than DUPLICATOR_PRO_MIN_MEMORY_LIMIT: ' . DUPLICATOR_PRO_MIN_MEMORY_LIMIT);

        $checks['SRV']['Brand'] = DUP_PRO_Package::is_active_brand_prepared();
        $checks['SRV']['HOST']  = DUP_PRO_Custom_Host_Manager::getInstance()->getActiveHostings();

        $checks['SRV']['PHP']['websrv']        = $testWebSrv;
        $checks['SRV']['PHP']['openbase']      = $testOpenBaseDir;
        $checks['SRV']['PHP']['maxtime']       = $testMaxExecTime;
        $checks['SRV']['PHP']['openssl']       = $testDropbox;
        $checks['SRV']['PHP']['mysqli']        = $testMySqlConnect;
        $checks['SRV']['PHP']['allowurlfopen'] = $testURLFopen;
        $checks['SRV']['PHP']['curlavailable'] = $testCURL;
        $checks['SRV']['PHP']['arch64bit']     = $test64Bit;
        $checks['SRV']['PHP']['minMemory']     = $testMemory;
        $checks['SRV']['PHP']['version']       = true; // now the plugin is activated only if the minimum version is valid, so this check is always true

        if ($package->contains_storage_type(DropboxStorage::getSType())) {
            $dropbox_transfer_test = true;
            $transferMode          = DynamicGlobalEntity::getInstance()->getVal('dropbox_transfer_mode');
            if ($transferMode == DUP_PRO_Dropbox_Transfer_Mode::cURL) {
                $dropbox_transfer_test = $testCURL;
                self::logCheckFalse($dropbox_transfer_test, 'Dropbox transfer mode is CURL and curl_init function doesn\'t exist.');
            } elseif ($transferMode == DUP_PRO_Dropbox_Transfer_Mode::FOpen_URL) {
                $dropbox_transfer_test = $testURLFopen;
                self::logCheckFalse($dropbox_transfer_test, 'Dropbox transfer mode is Fopen URL and Fopen URL is not enabled.');
            }
            $checks['SRV']['PHP']['ALL'] = ($testWebSrv && $testOpenBaseDir && $testMaxExecTime && $testDropbox && $testMySqlConnect && $test64Bit &&
                $testMemory && $dropbox_transfer_test && $checks['SRV']['Brand']['LogoImageExists']);
        } else {
            $checks['SRV']['PHP']['ALL'] = ($testWebSrv && $testOpenBaseDir && $testMaxExecTime && $testMySqlConnect && $test64Bit &&
                $testMemory && $checks['SRV']['Brand']['LogoImageExists']);
        }

        //-----------------------------
        //WORDPRESS SETTINGS

        //Core dir and files logic
        $testHasWpCoreFiltered = !$package->Archive->hasWpCoreFolderFiltered();

        $testIsMultisite = is_multisite();

        $checks['SRV']['WP']['version'] = true; // This check is always true because the plugin is activated only if the minimum version is valid
        $checks['SRV']['WP']['core']    = $testHasWpCoreFiltered;
        // $checks['SRV']['WP']['cache'] = $testCache;
        $checks['SRV']['WP']['ismu']     = $testIsMultisite;
        $checks['SRV']['WP']['ismuplus'] = License::can(License::CAPABILITY_MULTISITE_PLUS);

        if ($testIsMultisite) {
            $checks['SRV']['WP']['ALL'] = ($testHasWpCoreFiltered && $checks['SRV']['WP']['ismuplus']);
            self::logCheckFalse($checks['SRV']['WP']['ismuplus'], 'WP is multi-site setup and licence type is not Business Gold.');
        } else {
            $checks['SRV']['WP']['ALL'] = ($testHasWpCoreFiltered);
        }

        return $checks;
    }

    /**
     * Logs checks false informative message
     *
     * @param boolean $check        Either it is true or false
     * @param string  $errorMessage Error message which should be logged when check is false
     *
     * @return void
     */
    private static function logCheckFalse($check, $errorMessage)
    {
        if (!is_bool($check)) {
            throw new Exception('Exception: Not boolean $check [File: ' . __FILE__ . ', Ln: ' . __LINE__);
        }

        if (empty($errorMessage)) {
            throw new Exception('Exception: Empty $errorMessage [File: ' . __FILE__ . ', Ln: ' . __LINE__);
        }

        if (false === $check) {
            DUP_PRO_Log::trace($errorMessage);
        }
    }

    /**
     * Return true if memory_limit is >= 256 MB, otherwise false
     *
     * @return bool
     */
    public static function hasEnoughMemory(): bool
    {
        // In case we can't get the ini value, assume it's ok
        if (($memory_limit = @ini_get('memory_limit')) === false || $memory_limit <= 0) {
            return true;
        }

        if (SnapUtil::convertToBytes($memory_limit) >= SnapUtil::convertToBytes(DUPLICATOR_PRO_MIN_MEMORY_LIMIT)) {
            return true;
        }

        return false;
    }

    /**
     * Check to see if duplicator installation files are present
     *
     * @return bool   True if any installation files are found
     */
    public static function hasInstallFiles()
    {
        $fileToRemove = MigrationMng::checkInstallerFilesList();
        return count($fileToRemove) > 0;
    }

    /**
     * Returns an array with stats about the orphaned files
     *
     * @return string[] The full path of the orphaned file
     */
    public static function getOrphanedPackageFiles(): array
    {
        $global  = DUP_PRO_Global_Entity::getInstance();
        $orphans = [];

        $endPackagesFile = [
            'archive.daf',
            'archive.zip',
            'database.sql',
            'dirs.txt',
            'files.txt',
            'log.txt',
            'scan.json',
        ];

        $endPackagesFile[] = $global->installer_base_name;
        for ($i = 0; $i < count($endPackagesFile); $i++) {
            $endPackagesFile[$i] = preg_quote($endPackagesFile[$i], '/');
        }
        $regexMatch = '/(' . implode('|', $endPackagesFile) . ')$/';

        $numPackages = DUP_PRO_Package::count_by_status();
        $numPerPage  = 100;
        $pages       = floor($numPackages / $numPerPage) + 1;

        $skipStart = ['dup_pro'];
        for ($page = 0; $page < $pages; $page++) {
            $offset       = $page * $numPerPage;
            $pagePackages = DUP_PRO_Package::get_row_by_status([], $numPerPage, $offset);
            foreach ($pagePackages as $cPack) {
                $skipStart[] = $cPack->name . '_' . $cPack->hash;
            }
        }
        $pagePackages      = null;
        $fileTimeSkipInSec = (max(DUP_PRO_Constants::DEFAULT_MAX_PACKAGE_RUNTIME_IN_MIN, $global->max_package_runtime_in_min) + DUP_PRO_Constants::ORPAHN_CLEANUP_DELAY_MAX_PACKAGE_RUNTIME) * 60;

        if (file_exists(DUPLICATOR_PRO_SSDIR_PATH) && ($handle = opendir(DUPLICATOR_PRO_SSDIR_PATH)) !== false) {
            while (false !== ($fileName = readdir($handle))) {
                if ($fileName == '.' || $fileName == '..') {
                    continue;
                }

                $fileFullPath = DUPLICATOR_PRO_SSDIR_PATH . '/' . $fileName;

                if (is_dir($fileFullPath)) {
                    continue;
                }
                if (time() - filemtime($fileFullPath) < $fileTimeSkipInSec) {
                    // file younger than 2 hours skip for security
                    continue;
                }
                if (!preg_match($regexMatch, $fileName)) {
                    continue;
                }
                foreach ($skipStart as $skip) {
                    if (strpos($fileName, $skip) === 0) {
                        continue 2;
                    }
                }
                $orphans[] = $fileFullPath;
            }
            closedir($handle);
        }
        return $orphans;
    }

    /**
     * Returns an array with stats about the orphaned files
     *
     * @return array{size:int,count:int} The total count and file size of orphaned files
     */
    public static function getOrphanedPackageInfo(): array
    {
        $files         = self::getOrphanedPackageFiles();
        $info          = [];
        $info['size']  = 0;
        $info['count'] = 0;
        if (count($files)) {
            foreach ($files as $path) {
                $get_size = @filesize($path);
                if ($get_size > 0) {
                    $info['size'] += $get_size;
                    $info['count']++;
                }
            }
        }
        return $info;
    }

    /**
     * Get the IP of a client machine
     *
     * @return string   IP of the client machine
     */
    public static function getClientIP()
    {
        $result = '';
        if (($result = SnapUtil::sanitizeTextInput(INPUT_SERVER, 'HTTP_X_FORWARDED_FOR', '')) !== '') {
            return $result;
        }

        if (($result = SnapUtil::sanitizeTextInput(INPUT_SERVER, 'REMOTE_ADDR', '')) !== '') {
            return $result;
        }

        if (($result = SnapUtil::sanitizeTextInput(INPUT_SERVER, 'HTTP_CLIENT_IP', '')) !== '') {
            return $result;
        }

        return '';
    }

    /**
     * Get PHP memory usage
     *
     * @param bool $peak If true, returns peak memory usage
     *
     * @return string   Returns human readable memory usage.
     */
    public static function getPHPMemory($peak = false)
    {
        if ($peak) {
            $result = 'Unable to read PHP peak memory usage';
            if (function_exists('memory_get_peak_usage')) {
                $result = DUP_PRO_U::byteSize(memory_get_peak_usage(true));
            }
        } else {
            $result = 'Unable to read PHP memory usage';
            if (function_exists('memory_get_usage')) {
                $result = DUP_PRO_U::byteSize(memory_get_usage(true));
            }
        }
        return $result;
    }

    /**
     *  Gets the name of the owner of the current PHP script
     *
     * @return string The name of the owner of the current PHP script
     */
    public static function getCurrentUser()
    {
        $unreadable = 'Undetectable';
        if (function_exists('get_current_user')) {
            $user = get_current_user();
            return strlen($user) ? $user : $unreadable;
        }
        return $unreadable;
    }

    /**
     * Returns the server settings data
     *
     * @return array<mixed>
     */
    public static function getServerSettingsData(): array
    {
        $serverSettings = [];

        //GENERAL SETTINGS
        $serverSettings[] = [
            'title'    => __('General', 'duplicator-pro'),
            'settings' => self::getGeneralServerSettings(),
        ];

        //WORDPRESS SETTINGS
        $serverSettings[] = [
            'title'    => __('WordPress', 'duplicator-pro'),
            'settings' => self::getWordPressServerSettings(),
        ];

        //PHP SETTINGS
        $serverSettings[] = [
            'title'    => __('PHP', 'duplicator-pro'),
            'settings' => self::getPHPServerSettings(),
        ];

        //MYSQL SETTINGS
        $serverSettings[] = [
            'title'    => __('MySQL', 'duplicator-pro'),
            'settings' => self::getMysqlServerSettings(),
        ];

        // Paths Info
        $serverSettings[] = [
            'title'    => __('Paths Info', 'duplicator-pro'),
            'settings' => self::getPathsSettings(),
        ];

        //URLs info
        $urlsSettings = [];
        foreach (DUP_PRO_Archive::getOriginalURLs() as $key => $url) {
            $urlsSettings[] = [
                'label'    => __('URL ', 'duplicator-pro') . $key,
                'logLabel' => 'URL ' . $key,
                'value'    => $url,
            ];
        }

        $serverSettings[] = [
            'title'    => __('URLs Info', 'duplicator-pro'),
            'settings' => $urlsSettings,
        ];

        //Disk Space
        $home_path          = duplicator_pro_get_home_path();
        $space              = SnapIO::diskTotalSpace($home_path);
        $space_free         = SnapIO::diskFreeSpace($home_path);
        $serverDiskSettings = [
            [
                'label'           => __('Free Space', 'duplicator-pro'),
                'logLabel'        => 'Free Space',
                'value'           => sprintf(
                    __('%1$s%% -- %2$s from %3$s', 'duplicator-pro'),
                    round($space_free / $space * 100, 2),
                    DUP_PRO_U::byteSize($space_free),
                    DUP_PRO_U::byteSize($space)
                ),
                'valueNoteBottom' => __(
                    'Note: This value is the physical servers hard-drive allocation.
                    On shared hosts check your control panel for the "TRUE" disk space quota value.',
                    'duplicator-pro'
                ),
            ],
        ];

        $serverSettings[] = [
            'title'    => __('Server Disk', 'duplicator-pro'),
            'settings' => $serverDiskSettings,
        ];

        return $serverSettings;
    }

    /**
     * Returns the geleral server settings
     *
     * @return array<mixed>
     */
    private static function getGeneralServerSettings()
    {
        $serverAddr = SnapUtil::sanitizeTextInput(INPUT_SERVER, 'SERVER_ADDR', '');
        $serverName = SnapUtil::sanitizeTextInput(INPUT_SERVER, 'SERVER_NAME', '');
        if ($serverAddr !== '') {
            $ip = $serverAddr;
        } elseif (strlen($serverName) > 0 && function_exists('gethostbyname')) {
            $ip = gethostbyname($serverName);
        } else {
            $ip = __("Can't detect", 'duplicator-pro');
        }

        $serverSoftware = SnapUtil::sanitizeTextInput(
            INPUT_SERVER,
            'SERVER_SOFTWARE',
            __('Unknown', 'duplicator-pro')
        );

        return [
            [
                'label'     => __('Duplicator Version', 'duplicator-pro'),
                'logLabel'  => 'Duplicator Version',
                'value'     => DUPLICATOR_PRO_VERSION,
                'valueNote' => sprintf(
                    _x(
                        '- %1$sCheck WordPress Updates%2$s',
                        '%1$s and %2$s are the opening and closing anchor tags',
                        'duplicator-pro'
                    ),
                    '<a href="' . esc_url(LicensingController::getForceUpgradeCheckURL()) . '">',
                    '</a>'
                ),
            ],
            [
                'label'    => __('Operating System', 'duplicator-pro'),
                'logLabel' => 'Operating System',
                'value'    => PHP_OS,
            ],
            [
                'label'     => __('Timezone', 'duplicator-pro'),
                'logLabel'  => 'Timezone',
                'value'     => function_exists('wp_timezone_string') ? wp_timezone_string() :  __('Unknown', 'duplicator-pro'),
                'valueNote' => sprintf(
                    _x(
                        'This is a %1$sWordPress Setting%2$s',
                        '%1$s and %2$s are the opening and closing anchor tags',
                        'duplicator-pro'
                    ),
                    '<a href="options-general.php">',
                    '</a>'
                ),
            ],

            [
                'label'    => __('Server Time', 'duplicator-pro'),
                'logLabel' => 'Server Time',
                'value'    => current_time('Y-m-d H:i:s'),
            ],
            [
                'label'    => __('Web Server', 'duplicator-pro'),
                'logLabel' => 'Web Server',
                'value'    => $serverSoftware,
            ],
            [
                'label'    => __('Loaded PHP INI', 'duplicator-pro'),
                'logLabel' => 'Loaded PHP INI',
                'value'    => php_ini_loaded_file(),
            ],
            [
                'label'    => __('Server IP', 'duplicator-pro'),
                'logLabel' => 'Server IP',
                'value'    => $ip,
            ],
            [
                'label'    => __('Outbound IP', 'duplicator-pro'),
                'logLabel' => 'Outbound IP',
                'value'    => self::getOutboundIP() !== false ? self::getOutboundIP() : __("Can't detect", 'duplicator-pro'),
            ],
            [
                'label'    => __('Client IP', 'duplicator-pro'),
                'logLabel' => 'Client IP',
                'value'    => self::getClientIP(),
            ],
            [
                'label'    => __('Host', 'duplicator-pro'),
                'logLabel' => 'Host',
                'value'    => parse_url(get_site_url(), PHP_URL_HOST),
            ],
            [
                'label'    => __('Duplicator Version', 'duplicator-pro'),
                'logLabel' => 'Duplicator Version',
                'value'    => DUPLICATOR_PRO_VERSION,
            ],
        ];
    }

    /**
     * Returns the WP server settings
     *
     * @return array<mixed>
     */
    private static function getWordPressServerSettings()
    {
        global $wp_version;
        $managedHosting = (DUP_PRO_Custom_Host_Manager::getInstance()->isManaged() === false) ?
            __('No managed hosting detected', 'duplicator-pro') :
            implode(', ', DUP_PRO_Custom_Host_Manager::getInstance()->getActiveHostings());

        return [
            [
                'label'    => __('WordPress Version', 'duplicator-pro'),
                'logLabel' => 'WordPress Version',
                'value'    => $wp_version,
            ],
            [
                'label'    => __('Language', 'duplicator-pro'),
                'logLabel' => 'Language',
                'value'    => get_bloginfo('language'),
            ],
            [
                'label'    => __('Charset', 'duplicator-pro'),
                'logLabel' => 'Charset',
                'value'    => get_bloginfo('charset'),
            ],
            [
                'label'    => __('Memory Limit', 'duplicator-pro'),
                'logLabel' => 'Memory Limit',
                'value'    => WP_MEMORY_LIMIT,
            ],
            [
                'label'    => __('Managed hosting', 'duplicator-pro'),
                'logLabel' => 'Managed hosting',
                'value'    => $managedHosting,
            ],
        ];
    }

    /**
     * Returns the PHP server settings
     *
     * @return array<mixed>
     */
    private static function getPHPServerSettings()
    {
        return [
            [
                'label'    => __('PHP Version', 'duplicator-pro'),
                'logLabel' => 'PHP Version',
                'value'    => phpversion(),
            ],
            [
                'label'    => __('PHP SAPI', 'duplicator-pro'),
                'logLabel' => 'PHP SAPI',
                'value'    => PHP_SAPI,
            ],
            [
                'label'    => __('User', 'duplicator-pro'),
                'logLabel' => 'User',
                'value'    => self::getCurrentUser(),
            ],
            [
                'label'     => __('Memory Limit', 'duplicator-pro'),
                'logLabel'  => 'Memory Limit',
                'labelLink' => 'http://www.php.net/manual/en/ini.core.php#ini.memory-limit',
                'value'     => @ini_get('memory_limit'),
            ],
            [
                'label'    => __('Memory In Use', 'duplicator-pro'),
                'logLabel' => 'Memory In Use',
                'value'    => size_format(memory_get_usage(true)),
            ],
            [
                'label'        => __('Max Execution Time', 'duplicator-pro'),
                'logLabel'     => 'Max Execution Time',
                'labelLink'    => 'http://www.php.net/manual/en/info.configuration.php#ini.max-execution-time',
                'value'        => @ini_get('max_execution_time'),
                'valueNote'    => sprintf(
                    _x('(default) - %1$s', '%1$s = "is dynamic" or "value is fixed" based on settings', 'duplicator-pro'),
                    set_time_limit(0) ? __('is dynamic', 'duplicator-pro') : __('value is fixed', 'duplicator-pro')
                ),
                'valueTooltip' =>
                __(
                    'If the value shows dynamic then this means its possible for PHP to run longer than the default. 
                    If the value is fixed then PHP will not be allowed to run longer than the default.',
                    'duplicator-pro'
                ),
            ],
            [
                'label'     => __('open_basedir', 'duplicator-pro'),
                'logLabel'  => 'open_basedir',
                'labelLink' => 'http://php.net/manual/en/ini.core.php#ini.open-basedir',
                'value'     => empty(@ini_get('open_basedir')) ? __('Off', 'duplicator-pro') : @ini_get('open_basedir'),
            ],
            [
                'label'     => __('Shell (shell_exec)', 'duplicator-pro'),
                'logLabel'  => 'Shell (shell_exec)',
                'labelLink' => 'http://us3.php.net/shell_exec',
                'value'     => !Shell::hasDisabledFunctions('shell_exec') ? __('Is Supported', 'duplicator-pro') : __('Not Supported', 'duplicator-pro'),
            ],
            [
                'label'     => __('Shell (popen)', 'duplicator-pro'),
                'logLabel'  => 'Shell (popen)',
                'labelLink' => 'http://us3.php.net/popen',
                'value'     => !Shell::hasDisabledFunctions('popen') ? __('Is Supported', 'duplicator-pro') : __('Not Supported', 'duplicator-pro'),
            ],
            [
                'label'     => __('Shell (exec)', 'duplicator-pro'),
                'logLabel'  => 'Shell (exec)',
                'labelLink' => 'https://www.php.net/manual/en/function.exec.php',
                'value'     => !Shell::hasDisabledFunctions('exec') ? __('Is Supported', 'duplicator-pro') : __('Not Supported', 'duplicator-pro'),
            ],
            [
                'label'    => __('Shell Exec Zip', 'duplicator-pro'),
                'logLabel' => 'Shell Exec Zip',
                'value'    => (DUP_PRO_Zip_U::getShellExecZipPath() != null) ? __('Is Supported', 'duplicator-pro') : __('Not Supported', 'duplicator-pro'),
            ],
            [
                'label'     => __('Suhosin Extension', 'duplicator-pro'),
                'logLabel'  => 'Suhosin Extension',
                'labelLink' => 'https://suhosin.org/stories/index.html',
                'value'     => Shell::isSuhosinEnabled() ? __('Enabled', 'duplicator-pro') : __('Disabled', 'duplicator-pro'),
            ],
            [
                'label'    => __('Architecture', 'duplicator-pro'),
                'logLabel' => 'Architecture',
                'value'    => SnapUtil::getArchitectureString(),
            ],
            [
                'label'    => __('Error Log File', 'duplicator-pro'),
                'logLabel' => 'Error Log File',
                'value'    => @ini_get('error_log'),
            ],
        ];
    }

    /**
     * Returns the MySQL server settings
     *
     * @return array<mixed>
     */
    public static function getMysqlServerSettings()
    {
        return [
            [
                'label'    => __('Version', 'duplicator-pro'),
                'logLabel' => 'Version',
                'value'    => DUP_PRO_DB::getVersion(),
            ],
            [
                'label'    => __('Charset', 'duplicator-pro'),
                'logLabel' => 'Charset',
                'value'    => DB_CHARSET,
            ],
            [
                'label'     => __('Wait Timeout', 'duplicator-pro'),
                'logLabel'  => 'Wait Timeout',
                'labelLink' => 'http://dev.mysql.com/doc/refman/5.0/en/server-system-variables.html#sysvar_wait_timeout',
                'value'     => DUP_PRO_DB::getVariable('wait_timeout'),
            ],
            [
                'label'     => __('Max Allowed Packets', 'duplicator-pro'),
                'logLabel'  => 'Max Allowed Packets',
                'labelLink' => 'http://dev.mysql.com/doc/refman/5.0/en/server-system-variables.html#sysvar_max_allowed_packet',
                'value'     => DUP_PRO_DB::getVariable('max_allowed_packet'),
            ],
            [
                'label'     => __('mysqldump Path', 'duplicator-pro'),
                'logLabel'  => 'mysqldump Path',
                'labelLink' => 'http://dev.mysql.com/doc/refman/5.0/en/mysqldump.html',
                'value'     => DUP_PRO_DB::getMySqlDumpPath() !== false ? DUP_PRO_DB::getMySqlDumpPath() : __('Path Not Found', 'duplicator-pro'),
            ],
        ];
    }

    /**
     * Returns the paths settings
     *
     * @return array<mixed>
     */
    public static function getPathsSettings(): array
    {
        $pathsSettings = [
            [
                'label'    => __('Target root path', 'duplicator-pro'),
                'logLabel' => 'Target root path',
                'value'    => DUP_PRO_Archive::getTargetRootPath(),
            ],
        ];

        foreach (DUP_PRO_Archive::getOriginalPaths() as $key => $origPath) {
            $pathsSettings[] = [
                'label'    => __('Original ', 'duplicator-pro') . $key,
                'logLabel' => 'Original ' . $key,
                'value'    => $origPath,
            ];
        }

        foreach (DUP_PRO_Archive::getArchiveListPaths() as $key => $archivePath) {
            $pathsSettings[] = [
                'label'    => __('Archive ', 'duplicator-pro') . $key,
                'logLabel' => 'Archive ' . $key,
                'value'    => $archivePath,
            ];
        }

        return $pathsSettings;
    }
}
