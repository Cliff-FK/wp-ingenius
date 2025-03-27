<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Addons\WpCliAddon;

use DUP_PRO_Log;
use DUP_PRO_Package;
use DUP_PRO_PackageStatus;
use Duplicator\Ajax\ServicesPackage;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Models\Storages\StoragesUtil;
use Exception;
use WP_CLI;

class BackupBuild
{
    /**
     * Process schedules by cron
     *
     * @param DUP_PRO_Package $package Package to process
     *
     * @return void
     */
    public static function process(DUP_PRO_Package $package)
    {
        StoragesUtil::getDefaultStorage()->initStorageDirectory(true);

        if (SnapUtil::isIniValChangeable('memory_limit')) {
            @ini_set('memory_limit', -1);
        }

        $start_time = time();
        DUP_PRO_Log::trace("PACKAGE $package->ID:PROCESSING");

        if ($package->Status < DUP_PRO_PackageStatus::AFTER_SCAN) {
            // Scan step built into package build - used by schedules - NOT manual build where scan is done in web service.
            DUP_PRO_Log::trace("PACKAGE $package->ID:SCANNING");
            //After scanner runs.  Save FilterInfo (unreadable, warnings, globals etc)
            if (!$package->Archive->scanFiles(true)) {
                while ($package->Archive->scanFiles() !== true) {
                    DUP_PRO_Log::trace("CONTINUE SCANNING");
                }
            }
            $scan_report = $package->createScanReport();
            $package->set_status(DUP_PRO_PackageStatus::AFTER_SCAN);

            $end_time  = time();
            $scan_time = $end_time - $start_time;
            DUP_PRO_Log::trace("SCAN TIME=$scan_time seconds");
            WP_CLI::debug("Scan result\n" . json_encode($scan_report, JSON_PRETTY_PRINT));
            if ($scan_report['Status'] > ServicesPackage::EXEC_STATUS_PASS) {
                if (empty($scan_report['Message'])) {
                    $scan_report['Message'] = 'Scan failed';
                }
                throw new Exception("Scan failed, Status: {$scan_report['Status']}, Message: {$scan_report['Message']}");
            } else {
                WP_CLI::success("Scan success");
            }
        } elseif ($package->Status < DUP_PRO_PackageStatus::COPIEDPACKAGE) {
            DUP_PRO_Log::trace("PACKAGE $package->ID:BUILDING");
            $package->run_build(false);
            $end_time   = time();
            $build_time = $end_time - $start_time;
            DUP_PRO_Log::trace("BUILD TIME=$build_time seconds");
            if ($package->build_progress->hasCompleted()) {
                if ($package->build_progress->failed) {
                    throw new Exception("Build failed");
                }
            }
        } elseif ($package->Status < DUP_PRO_PackageStatus::COMPLETE) {
            DUP_PRO_Log::trace("PACKAGE $package->ID:STORAGE PROCESSING");
            $package->set_status(DUP_PRO_PackageStatus::STORAGE_PROCESSING);
            $package->process_storages();
            $end_time   = time();
            $build_time = $end_time - $start_time;
            DUP_PRO_Log::trace("STORAGE CHUNK PROCESSING TIME=$build_time seconds");
            if ($package->Status == DUP_PRO_PackageStatus::COMPLETE) {
                DUP_PRO_Log::trace("PACKAGE $package->ID COMPLETE");
            } elseif ($package->Status == DUP_PRO_PackageStatus::ERROR) {
                DUP_PRO_Log::trace("PACKAGE $package->ID IN ERROR STATE");
            }

            $packageCompleteStatuses = [
                DUP_PRO_PackageStatus::COMPLETE,
                DUP_PRO_PackageStatus::ERROR,
            ];
            if (in_array($package->Status, $packageCompleteStatuses)) {
                $info  = "\n";
                $info .= "********************************************************************************\n";
                $info .= "********************************************************************************\n";
                $info .= "DUPLICATOR PRO PACKAGE CREATION OR MANUAL STORAGE TRANSFER END: " . @date("Y-m-d H:i:s") . "\n";
                $info .= "NOTICE: Do NOT post to public sites or forums \n";
                $info .= "********************************************************************************\n";
                $info .= "********************************************************************************\n";
                DUP_PRO_Log::infoTrace($info);
            }

            if ($package->Status == DUP_PRO_PackageStatus::ERROR) {
                throw new Exception("Storage failed");
            }
        }
    }
}
