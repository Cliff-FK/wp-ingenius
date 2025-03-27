<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Ajax;

use DUP_PRO_Archive;
use DUP_PRO_Archive_Build_Mode;
use DUP_PRO_DATE;
use DUP_PRO_Handler;
use DUP_PRO_Log;
use DUP_PRO_Package;
use DUP_PRO_Package_File_Type;
use DUP_PRO_Package_Runner;
use DUP_PRO_Package_Template_Entity;
use DUP_PRO_Package_Upload_Info;
use DUP_PRO_PackageStatus;
use DUP_PRO_Tree_files;
use DUP_PRO_U;
use DUP_PRO_Upload_Status;
use DUP_PRO_ZipArchive_Mode;
use Duplicator\Addons\ProBase\License\License;
use Duplicator\Core\CapMng;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\Storages\StoragesUtil;
use Duplicator\Utils\LockUtil;
use Error;
use Exception;
use stdClass;

class ServicesPackage extends AbstractAjaxService
{
    const EXEC_STATUS_PASS = 1;
    /**
     * @deprecated Never used
     */
    const EXEC_STATUS_WARN = 2;

    const EXEC_STATUS_FAIL             = 3;
    const EXEC_STATUS_MORE_TO_SCAN     = 4;
    const EXEC_STATUS_SCHEDULE_RUNNING = 5;

    /**
     * Init ajax calls
     *
     * @return void
     */
    public function init()
    {
        $this->addAjaxCall('wp_ajax_duplicator_pro_process_worker', 'processWorker');
        $this->addAjaxCall('wp_ajax_nopriv_duplicator_pro_process_worker', 'processWorker');

        $this->addAjaxCall('wp_ajax_duplicator_pro_download_package_file', 'downloadPackageFile');
        $this->addAjaxCall('wp_ajax_nopriv_duplicator_pro_download_package_file', 'downloadPackageFile');

        if (!License::can(License::CAPABILITY_PRO_BASE)) {
            return;
        }
        $this->addAjaxCall('wp_ajax_duplicator_add_quick_filters', 'addQuickFilters');
        $this->addAjaxCall('wp_ajax_duplicator_pro_package_scan', 'packageScan');
        $this->addAjaxCall('wp_ajax_duplicator_pro_package_delete', 'packageDelete');
        $this->addAjaxCall('wp_ajax_duplicator_pro_reset_packages', 'resetPackages');
        $this->addAjaxCall('wp_ajax_duplicator_pro_get_package_statii', 'packageStatii');
        $this->addAjaxCall('wp_ajax_duplicator_pro_package_stop_build', 'stopBuild');
        $this->addAjaxCall('wp_ajax_duplicator_pro_manual_transfer_storage', 'manualTransferStorage');
        $this->addAjaxCall('wp_ajax_duplicator_pro_packages_details_transfer_get_package_vm', 'detailsTransferGetPackageVM');
        $this->addAjaxCall('wp_ajax_duplicator_pro_get_folder_children', 'getFolderChildren');
        $this->addAjaxCall("wp_ajax_duplicator_get_remote_restore_download_options", "remoteRestoreDownloadOptions");
    }

    /**
     * Removed all reserved installer files names
     *
     * @return never
     */
    public function addQuickFilters()
    {
        DUP_PRO_Handler::init_error_handler();
        check_ajax_referer('duplicator_add_quick_filters', 'nonce');
        $inputData = filter_input_array(INPUT_POST, [
            'dir_paths'  => [
                'filter'  => FILTER_DEFAULT,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => ['default' => ''],
            ],
            'file_paths' => [
                'filter'  => FILTER_DEFAULT,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => ['default' => ''],
            ],
        ]);
        $result    = [
            'success'      => false,
            'message'      => '',
            'filter-dirs'  => '',
            'filter-files' => '',
            'filter-names' => '',
        ];
        try {
            // CONTROLLER LOGIC
            // Need to update both the template and the temporary Backup because:
            // 1) We need to preserve preferences of this build for future manual builds - the manual template is used for this.
            // 2) Temporary Backup is used during this build - keeps all the settings/storage information.
            // Will be inserted into the Backup table after they ok the scan results.
            $template  = DUP_PRO_Package_Template_Entity::get_manual_template();
            $dirPaths  = DUP_PRO_Archive::parseDirectoryFilter(SnapUtil::sanitizeNSChars($inputData['dir_paths']));
            $filePaths = DUP_PRO_Archive::parseFileFilter(SnapUtil::sanitizeNSChars($inputData['file_paths']));

            // If we are adding a new filter & we have filters disabled, clear out the old filters.
            if (!$template->archive_filter_on && (strlen($dirPaths) > 0 || strlen($filePaths) > 0)) {
                $template->archive_filter_dirs  = '';
                $template->archive_filter_files = '';
            }

            if (strlen($dirPaths) > 0) {
                $template->archive_filter_dirs .= strlen($template->archive_filter_dirs) > 0 ? ';' . $dirPaths : $dirPaths;
            }

            if (strlen($filePaths) > 0) {
                $template->archive_filter_files .= strlen($template->archive_filter_files) > 0 ? ';' . $filePaths : $filePaths;
            }

            if (!$template->archive_filter_on) {
                $template->archive_filter_exts = '';
            }

            $template->archive_filter_on    = 1;
            $template->archive_filter_names = true;
            $template->save();

            $temporary_package                       = DUP_PRO_Package::get_temporary_package();
            $temporary_package->Status               = DUP_PRO_PackageStatus::PRE_PROCESS;
            $temporary_package->Archive->FilterDirs  = $template->archive_filter_dirs;
            $temporary_package->Archive->FilterFiles = $template->archive_filter_files;
            $temporary_package->Archive->FilterOn    = true;
            $temporary_package->Archive->FilterNames = $template->archive_filter_names;
            $temporary_package->set_temporary_package();

            $result['success']      = true;
            $result['filter-dirs']  = $temporary_package->Archive->FilterDirs;
            $result['filter-files'] = $temporary_package->Archive->FilterFiles;
            $result['filter-names'] = $temporary_package->Archive->FilterNames;
        } catch (Exception $exc) {
            $result['success'] = false;
            $result['message'] = $exc->getMessage();
        }

        wp_send_json($result);
    }

    /**
     *  DUPLICATOR_PRO_PACKAGE_SCAN
     *
     *  @example to test: /wp-admin/admin-ajax.php?action=duplicator_pro_package_scan
     *
     *  @return void
     */
    public function packageScan()
    {
        AjaxWrapper::json(
            [
                self::class,
                'packageScanCallback',
            ],
            'duplicator_pro_package_scan',
            SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'nonce'),
            CapMng::CAP_CREATE
        );
    }

    /**
     *  DUPLICATOR_PRO_PACKAGE_SCAN
     *
     *  @example to test: /wp-admin/admin-ajax.php?action=duplicator_pro_package_scan
     *
     *  @return array<string, mixed>
     */
    public static function packageScanCallback()
    {
        DUP_PRO_Handler::init_error_handler();
        try {
            // Keep the locking file opening and closing just to avoid adding even more complexity
            if (!LockUtil::lockProcess()) {
                // File is already locked indicating schedule is running
                DUP_PRO_Log::trace("Already locked when attempting manual build - schedule running");

                return ['Status' => self::EXEC_STATUS_SCHEDULE_RUNNING];
            }

            @set_time_limit(0);
            StoragesUtil::getDefaultStorage()->initStorageDirectory(true);

            $report      = [];
            $package     = DUP_PRO_Package::get_temporary_package();
            $package->ID = null;

            $firstChunk = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'firstChunk', false);
            DUP_PRO_Log::trace('First Chunk: ' . ($firstChunk ? 'true' : 'false'));
            if ($firstChunk) {
                DUP_PRO_Log::trace('First Scan Chunk');
                $fileScanDone = $package->Archive->scanFiles(true);
                DUP_PRO_Package::set_temporary_package_member('Status', DUP_PRO_PackageStatus::SCANNING);
            } else {
                DUP_PRO_Log::trace('Continuing Scan');
                $fileScanDone = $package->Archive->scanFiles();
            }

            $package->set_temporary_package();

            if ($fileScanDone) {
                $report           = $package->createScanReport();
                $report['Status'] = self::EXEC_STATUS_PASS;

                $package->set_temporary_package();

                DUP_PRO_Package::set_temporary_package_member('ScanFile', $package->ScanFile);
                DUP_PRO_Package::tmp_cleanup();
                DUP_PRO_Package::set_temporary_package_member('Status', DUP_PRO_PackageStatus::AFTER_SCAN);
            } else {
                DUP_PRO_Log::trace('Scan not done yet');
                $report = ['Status' => self::EXEC_STATUS_MORE_TO_SCAN];
            }

            LockUtil::unlockProcess();
        } catch (Exception $ex) {
            return [
                'Status'  =>  self::EXEC_STATUS_FAIL,
                'Message' => sprintf(__("Exception occurred. Exception message: %s", 'duplicator-pro'), $ex->getMessage()),
                'File'    => $ex->getFile(),
                'Line'    => $ex->getLine(),
                'Trace'   => $ex->getTrace(),
            ];
        } catch (Error $ex) {
            return [
                'Status'  =>  self::EXEC_STATUS_FAIL,
                'Message' =>  sprintf(
                    esc_html__("Fatal Error occurred. Error message: %1\$s<br>\nTrace: %2\$s", 'duplicator-pro'),
                    $ex->getMessage(),
                    $ex->getTraceAsString()
                ),
                'File'    => $ex->getFile(),
                'Line'    => $ex->getLine(),
                'Trace'   => $ex->getTrace(),
            ];
        }

        return $report;
    }

    /**
     * Hook ajax wp_ajax_duplicator_pro_package_delete
     * Deletes the files and database record entries
     *
     * @return void
     */
    public function packageDelete()
    {
        AjaxWrapper::json(
            [
                self::class,
                'packageDeleteCallback',
            ],
            'duplicator_pro_package_delete',
            SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'nonce'),
            CapMng::CAP_CREATE
        );
    }

    /**
     * Hook ajax wp_ajax_duplicator_pro_package_delete
     * Deletes the files and database record entries
     *
     * @return array<string, mixed>
     */
    public static function packageDeleteCallback()
    {
        $deletedCount = 0;

        $inputData     = filter_input_array(INPUT_POST, [
            'package_ids' => [
                'filter'  => FILTER_VALIDATE_INT,
                'flags'   => FILTER_REQUIRE_ARRAY,
                'options' => ['default' => false],
            ],
        ]);
        $packageIDList = $inputData['package_ids'];

        if (empty($packageIDList) || in_array(false, $packageIDList)) {
            throw new Exception(__("Invalid request.", 'duplicator-pro'));
        }

        DUP_PRO_Log::traceObject("Starting deletion of Backups by ids: ", $packageIDList);
        foreach ($packageIDList as $id) {
            if ($package = DUP_PRO_Package::get_by_id($id)) {
                if ($package->delete()) {
                    $deletedCount++;
                }
            } else {
                throw new Exception("Invalid Backup ID.");
            }
        }

        return [
            'ids'     => $packageIDList,
            'removed' => $deletedCount,
        ];
    }

    /**
     * Hook ajax wp_ajax_duplicator_pro_reset_packages
     *
     * @return never
     */
    public function resetPackages()
    {
        ob_start();
        try {
            DUP_PRO_Handler::init_error_handler();

            $error  = false;
            $result = [
                'data'    => ['status' => null],
                'html'    => '',
                'message' => '',
            ];

            $nonce = SnapUtil::sanitizeTextInput(INPUT_POST, 'nonce');
            if (!wp_verify_nonce($nonce, 'duplicator_pro_reset_packages')) {
                DUP_PRO_Log::trace('Security issue');
                throw new Exception('Security issue');
            }
            CapMng::can(CapMng::CAP_SETTINGS);

            // first last Backup id
            $ids = DUP_PRO_Package::get_ids_by_status(
                [
                    [
                        'op'     => '<',
                        'status' => DUP_PRO_PackageStatus::COMPLETE,
                    ],
                ],
                0,
                0,
                '`id` DESC'
            );
            foreach ($ids as $id) {
                // A smooth deletion is not performed because it is a forced reset.
                DUP_PRO_Package::force_delete($id);
            }
        } catch (Exception $e) {
            $error             = true;
            $result['message'] = $e->getMessage();
        }

        $result['html'] = ob_get_clean();
        if ($error) {
            wp_send_json_error($result);
        } else {
            wp_send_json_success($result);
        }
    }

    /**
     * Hook ajax wp_ajax_duplicator_pro_get_package_statii
     *
     * @return void
     */
    public function packageStatii()
    {
        AjaxWrapper::json(
            [
                self::class,
                'packageStatiiCallback',
            ],
            'duplicator_pro_get_package_statii',
            SnapUtil::sanitizeTextInput(INPUT_POST, 'nonce'),
            CapMng::CAP_BASIC
        );
    }

    /**
     * Hook ajax wp_ajax_duplicator_pro_get_package_statii
     *
     * @return array<mixed>
     */
    public static function packageStatiiCallback(): array
    {
        $limit     = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'limit', 0);
        $offset    = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'offset', 0);
        $packageId = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'packageId', -1);

        if ($packageId > 0) {
            if (($package = DUP_PRO_Package::get_by_id($packageId)) === false) {
                throw new Exception(__('Couldn\'t get Backup.', 'duplicator-pro'));
            }

            return [self::getPackageStatusInfo($package)];
        }

        $resultData = [];
        DUP_PRO_Package::by_status_callback(
            function (DUP_PRO_Package $package) use (&$resultData): void {
                $resultData[] = self::getPackageStatusInfo($package);
            },
            [],
            $limit,
            $offset,
            '`id` DESC'
        );

        return $resultData;
    }

    /**
     * Returns the Backup status info
     *
     * @param DUP_PRO_Package $package The Backup
     *
     * @return array<string, mixed> The status data
     */
    protected static function getPackageStatusInfo(DUP_PRO_Package $package): array
    {
        $status                         = [];
        $status['ID']                   = $package->ID;
        $status['status']               = self::getAdjustedPackageStatus($package);
        $status['status_progress']      = $package->get_status_progress();
        $status['size']                 = $package->get_display_size();
        $status['status_progress_text'] = '';

        if ($status['status'] < DUP_PRO_PackageStatus::COMPLETE) {
            $active_storage = $package->get_active_storage();
            if ($active_storage !== false) {
                $isDownload                     = $package->isDownloadInProgress();
                $status['status_progress_text'] = $active_storage->getActionText($isDownload);
            } else {
                $status['status_progress_text'] = '';
            }
        }

        return $status;
    }

    /**
     * Get the Backup status
     *
     * @param DUP_PRO_Package $package The Backup to get the status for
     *
     * @return int|float
     */
    private static function getAdjustedPackageStatus(DUP_PRO_Package $package)
    {
        if ($package->is_cancel_pending()) {
            return DUP_PRO_PackageStatus::PENDING_CANCEL;
        }

        $estimated_progress = ($package->build_progress->current_build_mode == DUP_PRO_Archive_Build_Mode::Shell_Exec) ||
            ($package->ziparchive_mode == DUP_PRO_ZipArchive_Mode::SingleThread);

        if (($package->Status == DUP_PRO_PackageStatus::ARCSTART) && $estimated_progress) {
            // Amount of time passing before we give them a 1%
            $time_per_percent       = 11;
            $thread_age             = time() - $package->build_progress->thread_start_time;
            $total_percentage_delta = DUP_PRO_PackageStatus::ARCDONE - DUP_PRO_PackageStatus::ARCSTART;

            if ($thread_age > ($total_percentage_delta * $time_per_percent)) {
                // It's maxed out so just give them the done condition for the rest of the time
                return DUP_PRO_PackageStatus::ARCDONE;
            } else {
                $percentage_delta = (int) ($thread_age / $time_per_percent);

                return DUP_PRO_PackageStatus::ARCSTART + $percentage_delta;
            }
        } else {
            return $package->Status;
        }
    }

    /**
     * Hook ajax wp_ajax_duplicator_pro_package_stop_build
     *
     * @return void
     */
    public function stopBuild()
    {
        AjaxWrapper::json(
            [
                self::class,
                'stopBuildCallback',
            ],
            'duplicator_pro_package_stop_build',
            SnapUtil::sanitizeTextInput(INPUT_POST, 'nonce'),
            CapMng::CAP_CREATE
        );
    }

    /**
     * Stop build callback
     *
     * @return array<string, mixed>
     */
    public static function stopBuildCallback(): array
    {
        $inputData = filter_input_array(INPUT_POST, [
            'package_id'  => [
                'filter'  => FILTER_VALIDATE_INT,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => ['default' => false],
            ],
            'stop_active' => [
                'filter'  => FILTER_VALIDATE_BOOLEAN,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => ['default' => false],
            ],
        ]);

        $packageId  = $inputData['package_id'];
        $stopActive = $inputData['stop_active'];

        try {
            $package = null;
            if ($stopActive) {
                DUP_PRO_Log::trace("Web service stop build of $packageId");
                $package = DUP_PRO_Package::get_next_active_package();
            } elseif ($packageId != false) {
                DUP_PRO_Log::trace("Web service stop build of $packageId");
                $package = DUP_PRO_Package::get_by_id($packageId);
            }

            if ($package == null && $stopActive !== true) {
                DUP_PRO_Log::trace(
                    "Could not find Backup so attempting hard delete. 
                    Old files may end up sticking around although chances are there isnt much if we couldnt nicely cancel it."
                );
                $result = DUP_PRO_Package::force_delete($packageId);

                if (!$result) {
                    throw new Exception('Hard delete failure');
                }

                return [
                    'success' => true,
                    'message' => 'Hard delete success',
                ];
            } else {
                DUP_PRO_Log::trace("set $package->ID for cancel");
                $package->set_for_cancel();
            }
        } catch (Exception $ex) {
            DUP_PRO_Log::trace($ex->getMessage());
            throw $ex;
        }

        return ['success' => true];
    }

    /**
     * Hook ajax process worker
     *
     * @return never
     */
    public function processWorker()
    {
        DUP_PRO_Handler::init_error_handler();
        DUP_PRO_U::checkAjax();
        header("HTTP/1.1 200 OK");

        DUP_PRO_Log::trace("Process worker request");
        DUP_PRO_Package_Runner::process();
        DUP_PRO_Log::trace("Exiting process worker request");

        echo 'ok';
        exit();
    }

    /**
     * Hook ajax wp_ajax_duplicator_pro_download_package_file
     *
     * @return never
     */
    public function downloadPackageFile()
    {
        DUP_PRO_Handler::init_error_handler();
        $inputData = filter_input_array(INPUT_GET, [
            'fileType' => [
                'filter'  => FILTER_VALIDATE_INT,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => ['default' => false],
            ],
            'hash'     => [
                'filter'  => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => ['default' => false],
            ],
            'token'    => [
                'filter'  => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => ['default' => false],
            ],
        ]);

        try {
            if (
                $inputData['token'] === false ||
                $inputData['hash'] === false ||
                $inputData["fileType"] === false ||
                DUP_PRO_Package::getLocalPackageAjaxDownloadToken($inputData['hash']) !== $inputData['token'] ||
                ($package = DUP_PRO_Package::get_by_hash($inputData['hash'])) == false
            ) {
                throw new Exception(__("Invalid request.", 'duplicator-pro'));
            }

            switch ($inputData['fileType']) {
                case DUP_PRO_Package_File_Type::Installer:
                    $filePath = $package->getLocalPackageFilePath(DUP_PRO_Package_File_Type::Installer);
                    $fileName = $package->Installer->getDownloadName();
                    break;
                case DUP_PRO_Package_File_Type::Archive:
                    $filePath = $package->getLocalPackageFilePath(DUP_PRO_Package_File_Type::Archive);
                    $fileName = basename($filePath);
                    break;
                case DUP_PRO_Package_File_Type::Log:
                    $filePath = $package->getLocalPackageFilePath(DUP_PRO_Package_File_Type::Log);
                    $fileName = basename($filePath);
                    break;
                default:
                    throw new Exception(__("File type not supported.", 'duplicator-pro'));
            }

            if ($filePath == false) {
                throw new Exception(__("File don\'t exists", 'duplicator-pro'));
            }

            \Duplicator\Libs\Snap\SnapIO::serveFileForDownload($filePath, $fileName, DUPLICATOR_PRO_BUFFER_DOWNLOAD_SIZE);
        } catch (Exception $ex) {
            DUP_PRO_Log::trace('Unable to download Backup file: ' . $ex->getMessage());
            wp_die(esc_html($ex->getMessage()));
        }
    }

    /**
     * Hook ajax transfer data
     *
     * @return void
     */
    public function detailsTransferGetPackageVM()
    {
        AjaxWrapper::json(
            [
                self::class,
                'detailsTransferGetPackageVMCallback',
            ],
            'duplicator_pro_packages_details_transfer_get_package_vm',
            SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'nonce'),
            CapMng::CAP_CREATE
        );
    }

    /**
     * Hook ajax handler for packages_details_transfer_get_package_vm
     * Retrieve view model for the Packages/Details/Transfer screen
     * active_package_id: true/false
     * percent_text: Percent through the current transfer
     * text: Text to display
     * transfer_logs: array of transfer request vms (start, stop, status, message)
     *
     * @return array<string, mixed>
     */
    public static function detailsTransferGetPackageVMCallback()
    {
        $inputData = filter_input_array(INPUT_POST, [
            'package_id' => [
                'filter'  => FILTER_VALIDATE_INT,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => ['default' => false],
            ],
        ]);

        $package_id = $inputData['package_id'];
        if (!$package_id) {
            throw new Exception(__("Invalid request.", 'duplicator-pro'));
        }

        if (!CapMng::can(CapMng::CAP_STORAGE, false)) {
            throw new Exception('Security issue.');
        }

        $package = DUP_PRO_Package::get_by_id($package_id);
        if (!$package) {
            $msg = sprintf(__('Could not get Backup by ID %s', 'duplicator-pro'), $package_id);
            throw new Exception($msg);
        }

        $vm = new stdClass();

        /* -- First populate the transfer log information -- */

        // If this is the Backup being requested include the transfer details
        $vm->transfer_logs = [];

        $active_upload_info = null;

        $storages = AbstractStorageEntity::getAll();

        foreach ($package->upload_infos as &$upload_info) {
            if ($upload_info->getStorageId() === StoragesUtil::getDefaultStorageId()) {
                continue;
            }

            $status      = $upload_info->get_status();
            $status_text = $upload_info->get_status_text();

            $transfer_log = new stdClass();

            if ($upload_info->get_started_timestamp() == null) {
                $transfer_log->started = __('N/A', 'duplicator-pro');
            } else {
                $transfer_log->started = DUP_PRO_DATE::getLocalTimeFromGMTTicks($upload_info->get_started_timestamp());
            }

            if ($upload_info->get_stopped_timestamp() == null) {
                $transfer_log->stopped = __('N/A', 'duplicator-pro');
            } else {
                $transfer_log->stopped = DUP_PRO_DATE::getLocalTimeFromGMTTicks($upload_info->get_stopped_timestamp());
            }

            $transfer_log->status_text = $status_text;
            $transfer_log->message     = $upload_info->get_status_message();

            $transfer_log->storage_type_text = __('Unknown', 'duplicator-pro');
            foreach ($storages as $storage) {
                if ($storage->getId() == $upload_info->getStorageId()) {
                    $transfer_log->storage_type_text = $storage->getStypeName();
                    // break;
                }
            }

            array_unshift($vm->transfer_logs, $transfer_log);

            if ($status == DUP_PRO_Upload_Status::Running) {
                if ($active_upload_info != null) {
                    DUP_PRO_Log::trace("More than one upload info is running at the same time for Backup {$package->ID}");
                }

                $active_upload_info = &$upload_info;
            }
        }

        /* -- Now populate the activa Backup information -- */
        $active_package = DUP_PRO_Package::get_next_active_package();

        if ($active_package == null) {
            // No active Backup
            $vm->active_package_id = -1;
            $vm->text              = __('No Backup is building.', 'duplicator-pro');
        } else {
            $vm->active_package_id = $active_package->ID;

            if ($active_package->ID == $package_id) {
                if ($active_upload_info != null) {
                    $vm->percent_text = "{$active_upload_info->progress}%";
                    $vm->text         = $active_upload_info->get_status_message();
                } else {
                    // We see this condition at the beginning and end of the transfer so throw up a generic message
                    $vm->percent_text = "";
                    $vm->text         = __("Synchronizing with server...", 'duplicator-pro');
                }
            } else {
                $vm->text = __("Another Backup is presently running.", 'duplicator-pro');
            }

            if ($active_package->is_cancel_pending()) {
                // If it's getting cancelled override the normal text
                $vm->text = __("Cancellation pending...", 'duplicator-pro');
            }
        }

        return [
            'success' => true,
            'vm'      => $vm,
        ];
    }

    /**
     * Hook ajax manual transfer storage
     *
     * @return void
     */
    public function manualTransferStorage()
    {
        AjaxWrapper::json(
            [
                self::class,
                'manualTransferStorageCallback',
            ],
            'duplicator_pro_manual_transfer_storage',
            SnapUtil::sanitizeTextInput(INPUT_POST, 'nonce'),
            CapMng::CAP_CREATE
        );
    }

    /**
     * Manual transfer storage callback
     *
     * @return array<string, mixed>
     */
    public static function manualTransferStorageCallback()
    {
        $isValid   = true;
        $inputData = filter_input_array(INPUT_POST, [
            'package_id'  => [
                'filter'  => FILTER_VALIDATE_INT,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => ['default' => false],
            ],
            'storage_ids' => [
                'filter'  => FILTER_VALIDATE_INT,
                'flags'   => FILTER_REQUIRE_ARRAY,
                'options' => ['default' => false],
            ],
            'download'    => [
                'filter'  => FILTER_VALIDATE_BOOLEAN,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => ['default' => false],
            ],
        ]);

        $package_id  = $inputData['package_id'];
        $storage_ids = $inputData['storage_ids'];
        $isDownload  = $inputData['download'];
        $isValid     = $package_id !== false && $storage_ids !== false;

        try {
            if (!$isValid) {
                throw new Exception(__("Invalid request.", 'duplicator-pro'));
            }

            DUP_PRO_Log::trace("Test if Backup is Running");
            if (DUP_PRO_Package::isPackageRunning()) {
                DUP_PRO_Log::trace("Package running.");
                $msg = sprintf(__('Trying to queue a transfer for Backup %d but a Backup is already active!', 'duplicator-pro'), $package_id);
                throw new Exception($msg);
            } else {
                DUP_PRO_Log::trace("Package not running.");
            }

            $package = DUP_PRO_Package::get_by_id($package_id);
            DUP_PRO_Log::open($package->getNameHash());

            if (!$package) {
                throw new Exception(sprintf(esc_html__('Could not find Backup ID %d!', 'duplicator-pro'), $package_id));
            }

            if (empty($storage_ids)) {
                throw new Exception("Please select a storage.");
            }

            $info  = "\n";
            $info .= "********************************************************************************\n";
            $info .= "********************************************************************************\n";
            $info .= "PACKAGE MANUAL TRANSFER REQUESTED: " . @date("Y-m-d H:i:s") . "\n";
            $info .= "********************************************************************************\n";
            $info .= "********************************************************************************\n\n";
            DUP_PRO_Log::infoTrace($info);

            foreach ($storage_ids as $storage_id) {
                if (($storage = AbstractStorageEntity::getById($storage_id)) === false) {
                    throw new Exception(sprintf(__('Could not find storage ID %d!', 'duplicator-pro'), $storage_id));
                }

                DUP_PRO_Log::infoTrace(
                    'Storage adding to the Backup "' . $package->getName() .
                    ' [Package Id: ' . $package_id . ']":: Storage Id: "' . $storage_id .
                    '" Storage Name: "' . esc_html($storage->getName()) .
                    '" Storage Type: "' . esc_html($storage->getStypeName()) . '"'
                );

                $upload_info = new DUP_PRO_Package_Upload_Info($storage_id);
                $upload_info->setDownloadFromRemote($isDownload);
                array_push($package->upload_infos, $upload_info);
            }

            $package->set_status(DUP_PRO_PackageStatus::STORAGE_PROCESSING);
            $package->timer_start = DUP_PRO_U::getMicrotime();

            $package->update();
        } catch (Exception $ex) {
            DUP_PRO_Log::trace($ex->getMessage());
            throw $ex;
        }

        return ['success' => true];
    }

    /**
     * Hook ajax wp_ajax_duplicator_pro_get_folder_children
     *
     * @return never
     */
    public function getFolderChildren()
    {
        DUP_PRO_Handler::init_error_handler();
        check_ajax_referer('duplicator_pro_get_folder_children', 'nonce');

        $json      = [];
        $isValid   = true;
        $inputData = filter_input_array(INPUT_GET, [
            'folder'  => [
                'filter'  => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => ['default' => false],
            ],
            'exclude' => [
                'filter'  => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags'   => FILTER_REQUIRE_ARRAY,
                'options' => [
                    'default' => [],
                ],
            ],
        ]);
        $folder    = $inputData['folder'];
        $exclude   = $inputData['exclude'];

        if ($folder === false) {
            $isValid = false;
        }

        ob_start();
        try {
            CapMng::can(CapMng::CAP_BASIC);

            if (!$isValid) {
                throw new Exception(__('Invalid request.', 'duplicator-pro'));
            }
            if (is_dir($folder)) {
                try {
                    $Package = DUP_PRO_Package::get_temporary_package();
                } catch (Exception $e) {
                    $Package = null;
                }

                $treeObj = new DUP_PRO_Tree_files($folder, true, $exclude);
                $treeObj->uasort(['DUP_PRO_Archive', 'sortTreeByFolderWarningName']);
                if (!is_null($Package)) {
                    $treeObj->treeTraverseCallback([$Package->Archive, 'checkTreeNodesFolder']);
                }

                $jsTreeData = DUP_PRO_Archive::getJsTreeStructure($treeObj, '', false);
                $json       = $jsTreeData['children'];
            }
        } catch (Exception $e) {
            DUP_PRO_Log::trace($e->getMessage());
            $json['message'] = $e->getMessage();
        }
        ob_clean();
        wp_send_json($json);
    }

    /**
     * Show remote storage options from where the Backup can be downloaded
     *
     * @return void
     */
    public function remoteRestoreDownloadOptions()
    {
        AjaxWrapper::json(
            [
                self::class,
                'remoteRestoreDownloadOptionsCallback',
            ],
            'duplicator_get_remote_restore_download_options',
            SnapUtil::sanitizeTextInput(INPUT_POST, 'nonce'),
            [
                CapMng::CAP_BACKUP_RESTORE,
                CapMng::CAP_STORAGE,
            ]
        );
    }

    /**
     * Show remote storage options from where the Backup can be downloaded
     *
     * @return array<string,mixed>
     */
    public static function remoteRestoreDownloadOptionsCallback(): array
    {
        $result = [
            'success'       => true,
            'alreadyInUse'  => false,
            'cancelNeeded'  => false,
            'packageExists' => true,
            'message'       => '',
            'content'       => '',
        ];
        try {
            $packageId = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'packageId', -1);

            switch (SnapUtil::sanitizeStrictInput(SnapUtil::INPUT_REQUEST, 'remoteAction')) {
                case 'download':
                    $action = 'download';
                    break;
                case 'restore':
                    $action = 'restore';
                    break;
                default:
                    throw new Exception(__('Invalid action.', 'duplicator-pro'));
            }

            if ($packageId < 0 || ($package = DUP_PRO_Package::get_by_id($packageId)) === false) {
                throw new Exception(__('Invalid Backup ID.', 'duplicator-pro'));
            }

            if ($package->haveLocalStorage()) {
                throw new Exception(__('Backup already exists locally.', 'duplicator-pro'));
            }

            if (DUP_PRO_Package::isPackageRunning()) {
                $result['cancelNeeded'] = true;
                $activePackage          = DUP_PRO_Package::get_next_active_package();

                if ($activePackage !== null && $packageId === $activePackage->ID) {
                    $result['alreadyInUse'] = true;
                }

                return $result;
            }

            $storages = $package->getValidStorages(true);

            if (count($storages) === 0) {
                $result['packageExists'] = false;
                $result['message']       = __('Backup does not exist in any remote storage.', 'duplicator-pro');
                return $result;
            }

            if ($action === 'restore') {
                $template = 'admin_pages/packages/remote_download/remote_restore_options';
            } else {
                $template = 'admin_pages/packages/remote_download/remote_download_options';
            }

            $result['content'] = TplMng::getInstance()->render($template, [
                'packageId'     => $package->ID,
                'packageName'   => $package->getName(),
                'isStorageFull' => StoragesUtil::getDefaultStorage()->isFull(),
                'storages'      => $storages,
            ], false);
        } catch (Exception $ex) {
            DUP_PRO_Log::trace($ex->getMessage());
            throw $ex;
        }

        return $result;
    }
}
