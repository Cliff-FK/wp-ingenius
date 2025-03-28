<?php

/**
 * Dup archive expander
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Installer\Core\Deploy\DupArchive;

use DupArchiveStateBase;
use Duplicator\Installer\Core\Security;
use Duplicator\Installer\Utils\Log\Log;
use Duplicator\Libs\DupArchive\DupArchiveEngine;
use Duplicator\Libs\DupArchive\Processors\DupArchiveFileProcessor;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapUtil;
use DUPX_ArchiveConfig;
use DUPX_Package;
use Exception;
use stdClass;

class Daws
{
    const DEFAULT_WORKER_TIME = 18;

    /** @var ?resource */
    protected $lock_handle;
    /** @var ?callable */
    protected $failureCallback;
    /** @var string */
    protected $lockFile = '';
    /** @var string */
    protected $cancelFile = '';

    /**
     * Class contruct
     */
    public function __construct()
    {
        DawsLogger::init();
        date_default_timezone_set('UTC'); // Some machines don’t have this set so just do it here.
        DupArchiveEngine::init(new DawsLogger(), '');
        $this->lockFile   = DUPX_INIT . '/dup-installer-dawslock__' . DUPX_Package::getPackageHash() . '.bin';
        $this->cancelFile = DUPX_INIT . '/dup-installer-dawscancel__' . DUPX_Package::getPackageHash() . '.bin';
    }

    /**
     * Failure callback
     *
     * @param callable $callback callback
     *
     * @return void
     */
    public function setFailureCallBack($callback)
    {
        if (is_callable($callback)) {
            $this->failureCallback = $callback;
        }
    }

    /**
     * Extract dup archvie
     *
     * @param array<string, mixed> $params dup archvie params
     *
     * @return stdClass
     */
    public function processRequest($params)
    {
        $retVal       = new stdClass();
        $retVal->pass = false;
        $action       = $params['action'];

        $archiveConfig = DUPX_ArchiveConfig::getInstance();
        if (!DupArchiveFileProcessor::setNewFilePathCallback([$archiveConfig, 'destFileFromArchiveName'])) {
            Log::info('ERROR: CAN\'T SET THE PATH SE CALLBACK FUNCTION');
        } else {
            Log::info('PATH SE CALLBACK FUNCTION OK ', Log::LV_DEBUG);
        }

        $throttleDelayInMs = SnapUtil::getArrayValue($params, 'throttle_delay', false, 0);
        $expandState       = null;

        if ($action == 'start_expand') {
            Log::info('DAWN START EXPAND');

            DawsExpandState::purgeStatefile();
            SnapIO::rm($this->cancelFile);
            $archiveFilepath          = SnapUtil::getArrayValue($params, 'archive_filepath');
            $restoreDirectory         = SnapUtil::getArrayValue($params, 'restore_directory');
            $workerTime               = SnapUtil::getArrayValue($params, 'worker_time', false, self::DEFAULT_WORKER_TIME);
            $filteredDirectories      = SnapUtil::getArrayValue($params, 'filtered_directories', false, []);
            $excludedDirWithoutChilds = SnapUtil::getArrayValue($params, 'excludedDirWithoutChilds', false, []);
            $filteredFiles            = SnapUtil::getArrayValue($params, 'filtered_files', false, []);
            $fileRenames              = SnapUtil::getArrayValue($params, 'fileRenames', false, []);
            $fileModeOverride         = SnapUtil::getArrayValue($params, 'file_mode_override', false, 0644);
            $includedFiles            = SnapUtil::getArrayValue($params, 'includedFiles', false, []);
            $directoryModeOverride    = SnapUtil::getArrayValue($params, 'dir_mode_override', false, 0755);
            $keepFileTime             = SnapUtil::getArrayValue($params, 'keep_file_time', false, false);

            $archveHeader                          = DupArchiveEngine::getArchiveHeader(
                $archiveFilepath,
                Security::getInstance()->getArchivePassword()
            );
            $expandState                           = new DawsExpandState($archveHeader);
            $expandState->archivePath              = $archiveFilepath;
            $expandState->working                  = true;
            $expandState->timeSliceInSecs          = $workerTime;
            $expandState->basePath                 = $restoreDirectory;
            $expandState->filteredDirectories      = $filteredDirectories;
            $expandState->excludedDirWithoutChilds = $excludedDirWithoutChilds;
            $expandState->includedFiles            = $includedFiles;
            $expandState->filteredFiles            = $filteredFiles;
            $expandState->fileRenames              = $fileRenames;
            $expandState->fileModeOverride         = $fileModeOverride;
            $expandState->directoryModeOverride    = $directoryModeOverride;
            $expandState->throttleDelayInUs        = 1000 * $throttleDelayInMs;
            $expandState->keepFileTime             = $keepFileTime;
            $expandState->save();

            $action = 'expand';
        } else {
            Log::info('DAWN CONTINUE EXPAND');
            $expandState = DawsExpandState::getFromFile();
        }

        if ($action == 'expand') {
            $this->lock_handle = SnapIO::fopen($this->lockFile, 'c+');
            SnapIO::flock($this->lock_handle, LOCK_EX);

            if ($expandState->working) {
                DupArchiveEngine::expandArchive($expandState);
            }

            if (!$expandState->working) {
                $deltaTime = time() - $expandState->startTimestamp;
                Log::info("DAWN EXPAND DONE, SECONDS: " . $deltaTime, Log::LV_DETAILED);

                if (count($expandState->failures) > 0) {
                    Log::info('DAWN EXPAND ERRORS DETECTED');

                    foreach ($expandState->failures as $failure) {
                        Log::info("{$failure->subject}:{$failure->description}");
                        if (is_callable($this->failureCallback)) {
                            call_user_func($this->failureCallback, $failure);
                        }
                    }
                }
            } else {
                Log::info("DAWN EXPAND CONTINUE", Log::LV_DETAILED);
            }

            SnapIO::flock($this->lock_handle, LOCK_UN);

            $retVal->pass   = true;
            $retVal->status = $this->getStatus($expandState);
        } elseif ($action == 'get_status') {
            $retVal->pass   = true;
            $retVal->status = $this->getStatus($expandState);
        } elseif ($action == 'cancel') {
            if (!SnapIO::touch($this->cancelFile)) {
                throw new Exception("Couldn't update time on " . $this->cancelFile);
            }
            $retVal->pass = true;
        } else {
            throw new Exception('Unknown command.');
        }
        session_write_close();

        return $retVal;
    }

    /**
     * Get dup archive status
     *
     * @param DawsExpandState $state dup archive state
     *
     * @return stdClass
     */
    private function getStatus(DawsExpandState $state)
    {
        $ret_val                 = new stdClass();
        $ret_val->archive_offset = $state->archiveOffset;
        $ret_val->archive_size   = @filesize($state->archivePath);
        $ret_val->failures       = $state->failures;
        $ret_val->file_index     = $state->fileWriteCount;
        $ret_val->is_done        = !$state->working;
        $ret_val->timestamp      = time();

        return $ret_val;
    }
}
