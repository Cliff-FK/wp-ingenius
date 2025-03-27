<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Addons\WpCliAddon;

use DUP_PRO_Archive_Build_Mode;
use DUP_PRO_Global_Entity;
use DUP_PRO_Log;
use DUP_PRO_Package;
use DUP_PRO_Package_File_Type;
use DUP_PRO_Package_Template_Entity;
use DUP_PRO_PackageStatus;
use DUP_PRO_PackageType;
use DUP_PRO_Server;
use DUP_PRO_ZipArchive_Mode;
use Duplicator\Libs\Snap\SnapWP;
use Duplicator\Models\Storages\StoragesUtil;
use Duplicator\Utils\LockUtil;
use Error;
use Exception;
use WP_CLI;

class DuplicatorCli
{
    /**
     * DuplicatorCli constructor.
     */
    public function __construct()
    {
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('duplicator', self::class);
        }
    }

    /**
     * Duplicator Info
     *
     * @return void
     */
    public function info()
    {
        WP_CLI::line('Duplicator WP-CLI Addon');
        WP_CLI::line('Duplicator Version: ' . DUPLICATOR_PRO_VERSION);
        WP_CLI::line('PHP Version: ' . PHP_VERSION);
        global $wp_version;
        WP_CLI::line('WordPress Version: ' . $wp_version);
    }

    /**
     * Build Duplicator Backup
     *
     * Options
     *
     * --template=<ID>
     *      The template id to use, if not specified the default template will be used
     *
     * --dir=<path>
     *      The directory to copy the backup to, if not specified the backup will not be copied
     *
     * --delete
     *      Delete the package after copying
     *
     * --phpsqldump
     *      If true use phpdump instead of mysqldump, default false and use mysqldump (Not implemented yet)
     *
     * --phpzip
     *      If true use php zip instead shell zip, default false and use shell zip
     *
     * --duparchive
     *      If true use dup archive engine, default false and use shell zip
     *
     * @param scalar[]             $args      Command arguments
     * @param array<string,scalar> $assocArgs Command options
     *
     * @return void
     */
    public function build($args, $assocArgs = [])
    {
        try {
            $failed = false;
            WP_CLI::debug("Excecute Backup Build");
            DUP_PRO_Log::trace('WP-CLI: Backup Build Command');
            $assocArgs = wp_parse_args(
                $assocArgs,
                [
                    'phpsqldump' => false, // Not implemented yet
                    'phpzip'     => false, // If true use php zip instead shell zip, default false and use shell zip
                    'duparchive' => false, // If true use dup archive engine, default false and use shell zip
                    'template'   => 0, // The template id to use, if not specified the default template will be used
                    'dir'        => '', // The directory to copy the backup to, if not specified the backup will not be copied
                    'delete'     => false, // Delete the package after copying
                ]
            );

            $tempalteId = $assocArgs['template'];

            if (!is_writable(DUPLICATOR_PRO_SSDIR_PATH_TMP)) {
                throw new Exception('Current user does not have permission to write to the Duplicator temporary directory');
            }

            if (strlen($assocArgs['dir']) > 0 && !is_dir($assocArgs['dir'])) {
                throw new Exception('The directory specified does not exist');
            }

            if (strlen($assocArgs['dir']) > 0 && !is_writable($assocArgs['dir'])) {
                throw new Exception('The directory specified is not writable');
            }

            if ($tempalteId == 0) {
                if (($template = DUP_PRO_Package_Template_Entity::get_default_template()) == null) {
                    throw new Exception('No default template found');
                }
            } else {
                if (($template = DUP_PRO_Package_Template_Entity::getById($tempalteId)) == null) {
                    throw new Exception("Template {$tempalteId} not found");
                }
            }

            $homePath = SnapWP::getHomePath();
            if (!is_dir($homePath) || chdir($homePath) == false) {
                throw new Exception("Failed to change directory to {$homePath}");
            }

            if (!LockUtil::lockProcess()) {
                DUP_PRO_Log::trace("File locked so skipping");
                throw new Exception("Another cron already running so skipping");
            }

            $global = DUP_PRO_Global_Entity::getInstance();
            if ($assocArgs['phpzip']) {
                $archiveMode = DUP_PRO_Archive_Build_Mode::ZipArchive;
            } elseif ($assocArgs['duparchive']) {
                $archiveMode = DUP_PRO_Archive_Build_Mode::DupArchive;
            } else {
                $archiveMode = DUP_PRO_Archive_Build_Mode::Shell_Exec;
            }
            $global->setArchiveMode($archiveMode, DUP_PRO_ZipArchive_Mode::SingleThread, true, true);
            $global->setDbMode('mysql');

            $package = new DUP_PRO_Package(
                DUP_PRO_PackageType::MANUAL,
                [StoragesUtil::getDefaultStorageId()],
                $template,
                null
            );
            $package->save();
            $package->set_temporary_package();
            WP_CLI::success("Building Backup[{$package->ID}] {$package->getName()} ...");

            DUP_PRO_Log::trace('WP-CLI: Backup Build Command: Start build');
            WP_CLI::debug("Run Process");

            do {
                BackupBuild::process($package);
                WP_CLI::debug("Run build end Package status " . $package->Status);

                if ($package->Status < DUP_PRO_PackageStatus::PRE_PROCESS) {
                    throw new Exception('Package status error: ' . $package->Status);
                }
            } while ($package->Status < DUP_PRO_PackageStatus::COMPLETE);
            $package->save();

            if (strlen($assocArgs['dir']) > 0) {
                $backupFile    = $package->getLocalPackageFilePath(DUP_PRO_Package_File_Type::Archive);
                $installerFile = $package->getLocalPackageFilePath(DUP_PRO_Package_File_Type::Installer);
                $targetDir     = $assocArgs['dir'];

                if (!file_exists($backupFile)) {
                    WP_CLI::warning("Backup file not found: {$backupFile}");
                    $failed = true;
                } else {
                    $backupFileTarget = $targetDir . '/' . basename($backupFile);
                    if (!copy($backupFile, $backupFileTarget)) {
                        WP_CLI::warning("Failed to copy backup file to {$backupFileTarget}");
                        $failed = true;
                    }
                }

                if (!file_exists($installerFile)) {
                    WP_CLI::warning("Installer file not found: {$installerFile}");
                    $failed = true;
                } else {
                    $installerFileTarget = $targetDir . '/' . basename($installerFile);
                    if (!copy($installerFile, $installerFileTarget)) {
                        WP_CLI::warning("Failed to copy installer file to {$installerFileTarget}");
                        $failed = true;
                    }
                }

                if (!$failed) {
                    WP_CLI::success("Backup files copied to {$targetDir}");
                }
            }

            if ($assocArgs['delete']) {
                $package->delete();
                WP_CLI::success("Backup {$package->getName()} Deleted");
            }
            WP_CLI::success("Backup {$package->getName()} Build Completed");
        } catch (Exception | Error $e) {
            WP_CLI::warning($e->getMessage());
            $failed = true;
        } finally {
            LockUtil::unlockProcess();
            DUP_PRO_Log::trace('WP-CLI: Backup Build Command end');
            DUP_PRO_Log::close();
        }

        if ($failed) {
            WP_CLI::error("Command Backup Build Failed");
        } else {
            WP_CLI::success("Command Backup Build Completed");
        }
    }

    /**
     * Duplicator Full Cleanup
     * Remove all Duplicator backup files and temporary files
     *
     * @return void
     */
    public function cleanup()
    {
        try {
            // first last package id
            $ids = DUP_PRO_Package::get_ids_by_status();
            foreach ($ids as $id) {
                $package = DUP_PRO_Package::get_by_id($id);
                WP_CLI::line("Delete Backup[{$package->ID}] {$package->getName()}");
                // A smooth deletion is not performed because it is a forced reset.
                DUP_PRO_Package::force_delete($id);
            }



            foreach (DUP_PRO_Server::getOrphanedPackageFiles() as $filepath) {
                if (is_writable($filepath)) {
                    WP_CLI::line("Delete Orphaned Backup File: {$filepath}");
                    unlink($filepath);
                } else {
                    WP_CLI::warning("Failed to delete Orphaned Backup File: {$filepath}");
                }
            }

            DUP_PRO_Package::tmp_cleanup(true);
        } catch (Exception | Error $e) {
            WP_CLI::warning($e->getMessage());
        } finally {
            DUP_PRO_Log::trace('WP-CLI: Backup Build Command end');
            DUP_PRO_Log::close();
        }

        WP_CLI::success("Build Cleanup Completed");
    }
}
