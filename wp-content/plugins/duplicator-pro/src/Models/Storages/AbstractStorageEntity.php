<?php

namespace Duplicator\Models\Storages;

use classObj;
use DUP_PRO_Global_Entity;
use DUP_PRO_Log;
use DUP_PRO_Package;
use DUP_PRO_Package_Upload_Info;
use DUP_PRO_Schedule_Entity;
use DUP_PRO_Storage_Entity;
use DateTime;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapURL;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Utils\Crypt\CryptBlowfish;
use Duplicator\Utils\IncrementalStatusMessage;
use Duplicator\Utils\Settings\ModelMigrateSettingsInterface;
use Duplicator\Package\Storage\StorageTransferChunkFiles;
use Error;
use Exception;
use ReflectionClass;
use VendorDuplicator\Amk\JsonSerialize\JsonSerialize;

abstract class AbstractStorageEntity extends DUP_PRO_Storage_Entity implements AbstractStorageEntityInterface, ModelMigrateSettingsInterface
{
    /** @var int */
    const BACKUP_RECORDS_REMOVE_ALL = 0;

    /** @var int */
    const BACKUP_RECORDS_REMOVE_DEFAULT = 1;

    /** @var int */
    const BACKUP_RECORDS_REMOVE_NEVER = 2;

    /** @var array<int,string> Class list registered */
    private static $storageTypes = [];

    /** @var string */
    protected $name = '';
    /** @var string */
    protected $notes = '';
    /** @var int */
    protected $storage_type = 0;
    /** @var array<string,scalar>  Storage configuration data */
    protected $config = [];
    /** @var bool this value is true on wakeup of old storages entities, for new storages is false*/
    protected $legacyEntity = true;
    /** @var IncrementalStatusMessage Inclemental messages system */
    protected $testLog;
    /** @var ?AbstractStorageAdapter */
    protected $adapter;

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->name         = __('New Storage', "duplicator-pro");
        $this->storage_type = static::getSType();
        $this->legacyEntity = false;
        $this->testLog      = new IncrementalStatusMessage();
        $this->config       = static::getDefaultConfig();
    }

    /**
     * Entity type
     *
     * @return string
     */
    public static function getType()
    {
        return 'DUP_PRO_Storage_Entity';
    }

    /**
     * Render the settings page for this storage.
     * Subclasses should override this method to render their own settings page.
     *
     * @return void
     */
    public static function renderGlobalOptions()
    {
    }

    /**
     * Initizalize entity from JSON
     *
     * @param string               $json           JSON string
     * @param array<string,scalar> $rowData        DB row data
     * @param ?string              $overwriteClass Overwrite class object, class must extend AbstractEntity
     *
     * @return static
     */
    protected static function getEntityFromJson($json, $rowData, $overwriteClass = null)
    {
        if ($overwriteClass === null) {
            $tmp            = JsonSerialize::unserialize($json);
            $overwriteClass = AbstractStorageEntity::getSTypePHPClass($tmp);
        }
        return parent::getEntityFromJson($json, $rowData, $overwriteClass);
    }

    /**
     * Get default config
     *
     * @return array<string,scalar>
     */
    protected static function getDefaultConfig()
    {
        return [
            'storage_folder' => self::getDefaultStorageFolder(),
            'max_packages'   => 10,
        ];
    }

    /**
     * Will be called, automatically, when Serialize
     *
     * @return array<string, mixed>
     */
    public function __serialize() // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.NewMagicMethods.__serializeFound
    {
        $data = parent::__serialize();

        if (DUP_PRO_Global_Entity::getInstance()->isEncryptionEnabled()) {
            if (($dataString = JsonSerialize::serialize($data['config'])) == false) {
                throw new Exception('Error serialize storage config');
            }
            $data['config'] = CryptBlowfish::encryptIfAvaiable($dataString, null, true);
        }

        unset($data['testLog']);
        unset($data['adapter']);
        return $data;
    }

    /**
     * Serialize
     *
     * Wakeup method.
     *
     * @return void
     */
    public function __wakeup()
    {
        parent::__wakeup();

        if (is_string($this->config)) {
            if (CryptBlowfish::isEncryptAvailable()) {
                // if si encrypted config is a string else is an array
                $config = CryptBlowfish::decryptIfAvaiable($this->config, null, true);
                $config = JsonSerialize::unserialize($config);

                $this->config = static::getDefaultConfig();
                // Update only existing keys
                foreach (array_keys($this->config) as $key) {
                    if (!isset($config[$key])) {
                        continue;
                    }
                    $this->config[$key] = $config[$key];
                }
            } else {
                $this->config = static::getDefaultConfig();
            }
        }
        $this->testLog = new IncrementalStatusMessage();
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get notes
     *
     * @return string
     */
    public function getNotes()
    {
        return $this->notes;
    }

    /**
     * Get priority, used to sort storages.
     * 100 is neutral value, 0 is the highest priority
     *
     * @return int
     */
    public static function getPriority()
    {
        return 100;
    }

    /**
     * Register storage type
     *
     * @return void
     */
    public static function registerType()
    {
        if (isset(self::$storageTypes[static::getSType()])) {
            throw new Exception("Storage type " . static::getSType() . " already registered with class " . self::$storageTypes[static::getSType()]);
        }
        self::$storageTypes[static::getSType()] = static::class;
    }

    /**
     * Get storages types
     *
     * @return int[]
     */
    final public static function getResisteredTypes()
    {
        return array_keys(self::$storageTypes);
    }

    /**
     * Get storages types sorted by priority
     *
     * @return int[]
     */
    final public static function getResisteredTypesByPriority()
    {
        $types = self::getResisteredTypes();
        usort($types, function ($a, $b) {
            $aClass = self::$storageTypes[$a];
            $bClass = self::$storageTypes[$b];
            return $aClass::getPriority() <=> $bClass::getPriority();
        });
        return $types;
    }

    /**
     * Get storage type class
     *
     * @param int|array<string,mixed> $data Storage data or storage type id
     *
     * @return class-string<AbstractStorageEntity>
     */
    final public static function getSTypePHPClass($data)
    {
        if (is_array($data)) {
            $type  = ($data['storage_type'] ?? UnknownStorage::getSType());
            $class = self::$storageTypes[$type] ?? UnknownStorage::class;
        } else {
            $type  = (int) $data;
            $class = self::$storageTypes[$type] ?? UnknownStorage::class;
            $data  = [];
        }
        return apply_filters('duplicator_pro_storage_type_class', $class, $type, $data);
    }

    /**
     * Get new storage object by type
     *
     * @param int $type Storage type
     *
     * @return self
     */
    final public static function getNewStorageByType($type)
    {
        $class = self::getSTypePHPClass($type);
        /** @var self */
        return new $class();
    }

    /**
     * Render config fields by storage type
     *
     * @param int|self $type Storage type or storage object
     * @param bool     $echo Echo or return
     *
     * @return string
     */
    final public static function renderSTypeConfigFields($type, $echo = true)
    {
        if ($type instanceof self) {
            $storage = $type;
        } else {
            $class = self::getSTypePHPClass($type);
            /** @var self */
            $storage = new $class();
        }
        return $storage->renderConfigFields($echo);
    }

    /**
     * Get storage adapter
     *
     * @return AbstractStorageAdapter
     */
    abstract protected function getAdapter();

    /**
     * Update data from http request, this method don't save data, just update object properties
     *
     * @param string $message Message
     *
     * @return bool True if success and all data is valid, false otherwise
     */
    public function updateFromHttpRequest(&$message = '')
    {
        $this->adapter = null; // Reset the adapter on update
        $this->name    = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'name', '');
        if (strlen($this->name) == 0) {
            $message = __('Storage name is required.', 'duplicator-pro');
            return false;
        }
        $this->notes = SnapUtil::sanitizeDefaultInput(SnapUtil::INPUT_REQUEST, 'notes', '');

        return true;
    }

    /**
     * Sanitize storage folder
     *
     * @param string $inputKey Input key
     * @param string $root     add,remove,none (add root, remove root, do nothing)
     *
     * @return string
     */
    protected static function getSanitizedInputFolder($inputKey, $root = 'none')
    {
        $folder = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, $inputKey, '');
        $folder = trim(stripslashes($folder));
        $folder = SnapIO::safePathUntrailingslashit($folder);
        $folder = ltrim($folder, '/\\');

        switch ($root) {
            case 'add':
                $folder = ltrim($folder, '/\\');
                $folder = '/' . $folder;
                break;
            case 'remove':
                $folder = ltrim($folder, '/\\');
                break;
            case 'none':
            default:
                break;
        }

        return $folder;
    }

    /**
     * Is type selectable, if false the storage can't be selected so can't be created new storage of this type
     *
     * @return bool
     */
    public static function isSelectable()
    {
        return true;
    }

    /**
     * If storage is default can't be deleted and the name can't be changed
     *
     * @return bool
     */
    public static function isDefault()
    {
        return false;
    }

    /**
     * Check if storage is supported
     *
     * @return bool
     */
    public static function isSupported()
    {
        return true;
    }

    /**
     * Get supported notice, displayed if storage isn't supported
     *
     * @return string html string or empty if storage is supported
     */
    public static function getNotSupportedNotice()
    {
        if (self::isSupported()) {
            return '';
        }

        $result = sprintf(
            __(
                'The Storage %s is not supported on this server.',
                'duplicator-pro'
            ),
            static::getStypeName()
        );
        return esc_html($result);
    }

    /**
     * Returns true if storage type is local
     *
     * @return bool
     */
    public static function isLocal()
    {
        return false;
    }

    /**
     * Get storage folder
     *
     * @return string
     */
    protected function getStorageFolder()
    {
        /** @var string */
        return $this->config['storage_folder'];
    }

    /**
     * Get storage location string
     *
     * @return string
     */
    abstract public function getLocationString();

    /**
     * Returns an html anchor tag of location
     *
     * @return string Returns an html anchor tag with the storage location as a hyperlink.
     *
     * @example
     * OneDrive Example return
     * <a target="_blank" href="https://1drv.ms/f/sAFrQtasdrewasyghg">https://1drv.ms/f/sAFrQtasdrewasyghg</a>
     */
    public function getHtmlLocationLink()
    {
        if ($this->isValid()) {
            return '<a href="' . esc_url($this->getLocationString()) . '" target="_blank" >' . esc_html($this->getLocationString()) . '</a>';
        } else {
            return esc_html($this->getLocationString());
        }
    }

    /**
     * Check if storage is valid
     *
     * @return bool Return true if storage is valid and ready to use, false otherwise
     */
    abstract public function isValid();

    /**
     * Return max storage Backups, 0 unlimited
     *
     * @return int<0,max>
     */
    public function getMaxPackages()
    {
        /** @var int<0,max> */
        return $this->config['max_packages'];
    }

    /**
     * Get upload chunk size in bytes
     *
     * @return int bytes size, -1 unlimited
     */
    abstract public function getUploadChunkSize();

    /**
     * Get download chunk size in bytes
     *
     * @return int bytes size, -1 unlimited
     */
    abstract public function getDownloadChunkSize();

    /**
     * Get upload chunk timeout in seconds
     *
     * @return int timeout in microseconds, 0 unlimited
     */
    public function getUploadChunkTimeout()
    {
        return 0;
    }

    /**
     * Return Backup transfer files
     *
     * @param DUP_PRO_Package $package the Backup
     *
     * @return array<string,string> return array from => to
     */
    protected function getPackageTransferFiles(DUP_PRO_Package $package)
    {
        return [
            $package->Installer->getSafeFilePath() => $package->Installer->getInstallerName(),
            $package->Archive->getSafeFilePath()   => $package->Archive->getArchiveName(),
        ];
    }

    /**
     * Copies the Backup files from the default local storage to another local storage location
     *
     * @param DUP_PRO_Package             $package     the Backup
     * @param DUP_PRO_Package_Upload_Info $upload_info the upload info
     *
     * @return void
     */
    public function copyFromDefault(DUP_PRO_Package $package, DUP_PRO_Package_Upload_Info $upload_info)
    {
        DUP_PRO_Log::infoTrace("Copyng to Storage " . $this->name . '[ID: ' . $this->id . '] type:' . $this->getStypeName());

        $storageUpload = new StorageTransferChunkFiles(
            [
                'replacements' => $this->getPackageTransferFiles($package),
                'chunkSize'    => $this->getUploadChunkSize(),
                'chunkTimeout' => $this->getUploadChunkTimeout(),
                'upload_info'  => $upload_info,
                'package'      => $package,
                'adapter'      => $this->getAdapter(),
            ],
            0,
            $this->getUploadChunkTimeout()
        );

        switch ($storageUpload->start()) {
            case StorageTransferChunkFiles::CHUNK_COMPLETE:
                DUP_PRO_Log::trace('LOCAL UPLOAD IN CHUNKS COMPLETED');
                $upload_info->copied_installer = true;
                $upload_info->copied_archive   = true;

                if ($this->config['max_packages'] > 0) {
                    DUP_PRO_Log::trace('Purge old local Backups');
                    $this->purgeOldPackages();
                }
                do_action('duplicator_pro_upload_complete', $upload_info);
                break;
            case StorageTransferChunkFiles::CHUNK_STOP:
                DUP_PRO_Log::trace('LOCAL UPLOAD IN CHUNKS NOT COMPLETED >> CONTINUE NEXT CHUNK');
                //do nothing for now
                break;
            case StorageTransferChunkFiles::CHUNK_ERROR:
            default:
                DUP_PRO_Log::infoTrace('Local upload in chunks, upload error: ' . $storageUpload->getLastErrorMessage());
                $upload_info->increaseFailureCount();
        }
        $package->update();
    }

    /**
     * Copies the Backup files from the default local storage to another local storage location
     *
     * @param DUP_PRO_Package             $package     the Backup
     * @param DUP_PRO_Package_Upload_Info $upload_info the upload info
     *
     * @return void
     */
    public function copyToDefault(DUP_PRO_Package $package, DUP_PRO_Package_Upload_Info $upload_info)
    {
        DUP_PRO_Log::infoTrace("Copyng to Storage " . $this->name . '[ID: ' . $this->id . '] type:' . $this->getStypeName());

        $archiveTmpPath   = DUPLICATOR_PRO_SSDIR_PATH_TMP . '/' . $package->Archive->getArchiveName() . '.part';
        $installerTmpPath = DUPLICATOR_PRO_SSDIR_PATH_TMP . '/' . $package->Installer->getInstallerName() . '.part';
        $replacements     = [
            $package->Installer->getInstallerName() => $installerTmpPath,
            $package->Archive->getArchiveName()     => $archiveTmpPath,
        ];

        DUP_PRO_Log::infoTrace("Download chunk size: " . $this->getDownloadChunkSize());

        $storageDownload = new StorageTransferChunkFiles(
            [
                'replacements' => $replacements,
                'chunkSize'    => $this->getDownloadChunkSize(),
                'chunkTimeout' => $this->getUploadChunkTimeout(),
                'upload_info'  => $upload_info,
                'package'      => $package,
                'adapter'      => $this->getAdapter(),
                'download'     => true,
            ],
            0,
            $this->getUploadChunkTimeout()
        );

        switch ($storageDownload->start()) {
            case StorageTransferChunkFiles::CHUNK_COMPLETE:
                DUP_PRO_Log::trace('DOWNLOAD FROM REMOTE IN CHUNKS COMPLETED');
                if (
                    $this->renamePartDownloadFiles(
                        $installerTmpPath,
                        $package->Installer->getSafeFilePath(),
                        $archiveTmpPath,
                        $package->Archive->getSafeFilePath(),
                        $upload_info
                    ) == false
                ) {
                    // No retry on file rename error
                    DUP_PRO_Log::infoTrace("Upload failed without retry [Storage Id: $this->id]");
                    $upload_info->uploadFailed();
                }
                break;
            case StorageTransferChunkFiles::CHUNK_STOP:
                DUP_PRO_Log::trace('DOWNLOAD IN CHUNKS NOT COMPLETED >> CONTINUE NEXT CHUNK');
                //do nothing for now
                break;
            case StorageTransferChunkFiles::CHUNK_ERROR:
            default:
                DUP_PRO_Log::infoTrace('Local download in chunks, download error: ' . $storageDownload->getLastErrorMessage());
                $upload_info->increaseFailureCount();
        }
        $package->update();
    }

    /**
     * Rename part download file to the final file
     *
     * @param string                      $installerTmpPath Installer part file path
     * @param string                      $installerPath    Installer file path
     * @param string                      $archiveTmpPath   Archive part file path
     * @param string                      $archivePath      Archive file path
     * @param DUP_PRO_Package_Upload_Info $uploadInfo       the upload info
     *
     * @return bool True if success, false otherwise
     */
    protected function renamePartDownloadFiles(
        $installerTmpPath,
        $installerPath,
        $archiveTmpPath,
        $archivePath,
        DUP_PRO_Package_Upload_Info $uploadInfo
    ) {
        if (
            SnapIO::rename(
                $installerTmpPath,
                $installerPath,
                true
            ) == false
        ) {
            DUP_PRO_Log::infoTrace("Failed to rename part file [{$installerTmpPath}] to installer file [{$installerPath}]");
            return false;
        } else {
            $uploadInfo->copied_installer = true;
        }

        if (
            SnapIO::rename(
                $archiveTmpPath,
                $archivePath,
                true
            ) == false
        ) {
            DUP_PRO_Log::infoTrace("Failed to rename part file [{$archiveTmpPath}] to archive file [{$archivePath}]");
            return false;
        } else {
            $uploadInfo->copied_archive = true;
        }

        return true;
    }

    /**
     * Returns true if the storage has the backup
     *
     * @param DUP_PRO_Package $package the Backup
     *
     * @return bool
     */
    public function hasPackage(DUP_PRO_Package $package)
    {
        try {
            return $this->getAdapter()->isFile($package->Archive->getArchiveName());
        } catch (Exception $e) {
            DUP_PRO_Log::traceException($e, 'Error getting storage adapter');
            return false;
        }
    }

    /**
     * Check if storage is full
     *
     * @return bool
     */
    public function isFull()
    {
        $adapter       = $this->getAdapter();
        $fullFilesList = $adapter->scanDir('', true, false);
        $packagesList  = array_filter(
            $fullFilesList,
            fn($file): bool => preg_match(DUPLICATOR_PRO_ARCHIVE_REGEX_PATTERN, $file) === 1
        );
        return ($this->config['max_packages'] > 0 && count($packagesList) >= $this->config['max_packages']);
    }

    /**
     * Purge old Backups.
     * If a backup is in the exclude list, it will be skipped, but the number of Backups to delete will not be reduced,
     * so that the total number of Backups in the storage will be equal to the max_packages setting.
     *
     * @param array<string> $exclude List of Backups to exclude from deletion
     *
     * @return false|string[] false on failure or array of deleted files of Backups
     */
    public function purgeOldPackages($exclude = [])
    {
        if ($this->config['max_packages'] <= 0) {
            return [];
        }

        DUP_PRO_Log::infoTrace("Attempting to purge old Backups at " . $this->name . '[ID: ' . $this->id . '] type: ' . $this->getSTypeName());

        $result        = [];
        $global        = DUP_PRO_Global_Entity::getInstance();
        $adapter       = $this->getAdapter();
        $fullFilesList = $adapter->scanDir('', true, false);
        $filesToPurge  = self::getPurgeFileList($fullFilesList, $this->config['max_packages'], $exclude);
        try {
            foreach ($filesToPurge as $file) {
                if (!$adapter->delete($file)) {
                    DUP_PRO_Log::infoTrace("Failed to purge backup from remote storage: " . $file);
                    continue;
                }
                $result[] = $file;

                if (preg_match(DUPLICATOR_PRO_ARCHIVE_REGEX_PATTERN, $file) !== 1) {
                    // Skip non-archive files
                    continue;
                }

                $package = DUP_PRO_Package::getByArchiveName($file);
                if ($package === null) {
                    DUP_PRO_Log::infoTrace("Won't update package storage status, package not found");
                    continue;
                }

                // Update the Backup storage status
                $package->unsetStorage(
                    $this->getId(),
                    $global->getPurgeBackupRecords() === self::BACKUP_RECORDS_REMOVE_ALL
                );
            }
        } catch (Exception $e) {
            DUP_PRO_Log::infoTraceException($e, "FAIL: purge Backup for storage " . $this->name . '[ID: ' . $this->id . '] type:' . $this->getStypeName());
            return false;
        }

        DUP_PRO_Log::infoTrace("Purge of old Backups at " . $this->name . '[ID: ' . $this->id . "] storage completed. Num packages deleted " . count($result));
        return $result;
    }

    /**
     * Returns the list of Backups files to delete based on the max_packages setting
     * If a backup is in the exclude list, it will be skipped, but the number of Backups to delete will not be reduced,
     * so that the total number of Backups in the storage will be equal to the max_packages setting.
     * Note: Don't return only archvie files, return all files that are part of a Backup
     *
     * @param string[]  $fullFileList List of all files in the storage
     * @param int       $maxBackups   Max number of Backups to keep
     * @param string [] $exclude      List of Backups to exclude from deletion
     *
     * @return string[] array of backups files to delete (archive, installer, logs etc)
     */
    public static function getPurgeFileList($fullFileList, $maxBackups, $exclude = [])
    {
        $backupList = array_filter(
            $fullFileList,
            fn($file): bool => preg_match(DUPLICATOR_PRO_ARCHIVE_REGEX_PATTERN, $file) === 1
        );

        if (count($backupList) <= $maxBackups) {
            return [];
        }

        // Calculate before exlude to get the correct number of Backups to delete
        $numToDelete = count($backupList) - $maxBackups;
        if (!empty($exclude)) {
            $backupList = array_diff($backupList, $exclude);
        }

        self::sortBackupListByDate($backupList, true);

        $archivesToDelete = array_slice($backupList, 0, $numToDelete);
        $suffixLength     = strlen('_archive.zip');

        $nameHashes = array_map(fn($archiveName): string => substr($archiveName, 0, -$suffixLength), $archivesToDelete);

        $filesToDelete = array_filter(
            $fullFileList,
            function ($file) use ($nameHashes) {
                foreach ($nameHashes as $nameHash) {
                    if (strpos($file, $nameHash) !== false) {
                        return true;
                    }
                }

                return false;
            }
        );

        return array_values($filesToDelete);
    }

    /**
     * Sorts the Backup list by date
     *
     * @param string[] $backupList List of Backups
     * @param bool     $ascending  Sort from oldest to newest
     *
     * @return void
     */
    public static function sortBackupListByDate(&$backupList, $ascending = true)
    {
        // Calculate the date string position and length
        $dateStrLen = strlen(date(DUP_PRO_Package::PACKAGE_HASH_DATE_FORMAT));
        $dateStrPos = -(strlen('_archive.zip') + $dateStrLen);

        // Sort by reverse creation time
        usort($backupList, function ($a, $b) use ($dateStrPos, $dateStrLen, $ascending) {
            $aDate = DateTime::createFromFormat(
                DUP_PRO_Package::PACKAGE_HASH_DATE_FORMAT,
                substr($a, $dateStrPos, $dateStrLen)
            )->getTimestamp();
            $bDate = DateTime::createFromFormat(
                DUP_PRO_Package::PACKAGE_HASH_DATE_FORMAT,
                substr($b, $dateStrPos, $dateStrLen)
            )->getTimestamp();

            //reverse sort
            if ($aDate == $bDate) {
                return 0;
            } elseif ($aDate < $bDate) {
                return $ascending ? -1 : 1;
            } else {
                return $ascending ? 1 : -1;
            }
        });
    }

    /**
     * List quick view
     *
     * @param bool $echo Echo or return
     *
     * @return string HTML string
     */
    public function getListQuickView($echo = true)
    {
        ob_start();
        ?>
        <div>
            <label><?php esc_html_e('Location', 'duplicator-pro') ?>:</label>
            <?php
                echo wp_kses(
                    $this->getHtmlLocationLink(),
                    [
                        'a' => [
                            'href'   => [],
                            'target' => [],
                        ],
                    ]
                );
            ?>
        </div>
        <?php
        if ($echo) {
            ob_end_flush();
            return '';
        } else {
            return (string) ob_get_clean();
        }
    }

    /**
     * List quick view
     *
     * @param bool $echo Echo or return
     *
     * @return string HTML string
     */
    public function getDeleteView($echo = true)
    {
        ob_start();
        ?>
        <div class="item">
            <span class="lbl">Name:</span><?php echo esc_html($this->getName()); ?><br>
            <span class="lbl">Type:</span>&nbsp;
            <?php
            echo wp_kses(
                $this->getStypeIcon(),
                [
                    'i'   => [
                        'class' => [],
                    ],
                    'img' => [
                        'src'   => [],
                        'alt'   => [],
                        'class' => [],
                    ],
                ]
            );
            ?>
            &nbsp;<?php echo esc_html($this->getStypeName()); ?>
        </div>
        <?php
        if ($echo) {
            ob_end_flush();
            return '';
        } else {
            return (string) ob_get_clean();
        }
    }

    /**
     * Get action key text for uploads
     *
     * @param string $key Key name (action, pending, failed, cancelled, success)
     *
     * @return string
     */
    protected function getUploadActionKeyText($key)
    {
        switch ($key) {
            case 'action':
                return sprintf(
                    __('Transferring to %1$s folder:<br/> <i>%2$s</i>', "duplicator-pro"),
                    $this->getStypeName(),
                    $this->getStorageFolder()
                );
            case 'pending':
                return sprintf(
                    __('Transfer to %1$s folder %2$s is pending', "duplicator-pro"),
                    $this->getStypeName(),
                    $this->getStorageFolder()
                );
            case 'failed':
                return sprintf(
                    __('Failed to transfer to %1$s folder %2$s', "duplicator-pro"),
                    $this->getStypeName(),
                    $this->getStorageFolder()
                );
            case 'cancelled':
                return sprintf(
                    __('Cancelled before could transfer to %1$s folder %2$s', "duplicator-pro"),
                    $this->getStypeName(),
                    $this->getStorageFolder()
                );
            case 'success':
                return sprintf(
                    __('Transferred Backup to %1$s folder %2$s', "duplicator-pro"),
                    $this->getStypeName(),
                    $this->getStorageFolder()
                );
            default:
                throw new Exception('Invalid key');
        }
    }

    /**
     * Get action key text for downloads
     *
     * @param string $key Key name (action, pending, failed, cancelled, success)
     *
     * @return string
     */
    protected function getDownloadActionKeyText($key)
    {
        switch ($key) {
            case 'action':
                return sprintf(
                    __('Downloading from %1$s folder:<br/> <i>%2$s</i>', "duplicator-pro"),
                    $this->getStypeName(),
                    $this->getStorageFolder()
                );
            case 'pending':
                return sprintf(
                    __('Download from %1$s folder %2$s is pending', "duplicator-pro"),
                    $this->getStypeName(),
                    $this->getStorageFolder()
                );
            case 'failed':
                return sprintf(
                    __('Failed to download from %1$s folder %2$s', "duplicator-pro"),
                    $this->getStypeName(),
                    $this->getStorageFolder()
                );
            case 'cancelled':
                return sprintf(
                    __('Cancelled before could download from %1$s folder %2$s', "duplicator-pro"),
                    $this->getStypeName(),
                    $this->getStorageFolder()
                );
            case 'success':
                return sprintf(
                    __('Downloaded Backup from %1$s folder %2$s', "duplicator-pro"),
                    $this->getStypeName(),
                    $this->getStorageFolder()
                );
            default:
                throw new Exception('Invalid key');
        }
    }

    /**
     * Get action text
     *
     * @param bool $isDownload Changes the text if the transfer is from storage to local
     *
     * @return string
     */
    public function getActionText($isDownload = false)
    {
        return $isDownload ? $this->getDownloadActionKeyText('action') : $this->getUploadActionKeyText('action');
    }

    /**
     * Get pending action text
     *
     * @param bool $isDownload Changes the text if the transfer is from storage to local
     *
     * @return string
     */
    public function getPendingText($isDownload = false)
    {
        return $isDownload ? $this->getDownloadActionKeyText('pending') : $this->getUploadActionKeyText('pending');
    }

    /**
     * Returns the text to display when the Backup has failed to copy to the storage location
     *
     * @param bool $isDownload Changes the text if the transfer is from storage to local
     *
     * @return string
     */
    public function getFailedText($isDownload = false)
    {
        return $isDownload ? $this->getDownloadActionKeyText('failed') : $this->getUploadActionKeyText('failed');
    }

    /**
     * Returns the text to display when the Backup has been cancelled before it could be copied to the storage location
     *
     * @param bool $isDownload Changes the text if the transfer is from storage to local
     *
     * @return string
     */
    public function getCancelledText($isDownload = false)
    {
        return $isDownload ? $this->getDownloadActionKeyText('cancelled') : $this->getUploadActionKeyText('cancelled');
    }

    /**
     * Returns the text to display when the Backup has been successfully copied to the storage location
     *
     * @param bool $isDownload Changes the text if the transfer is from storage to local
     *
     * @return string
     */
    public function getSuccessText($isDownload = false)
    {
        return $isDownload ? $this->getDownloadActionKeyText('success') : $this->getUploadActionKeyText('success');
    }

    /**
     *
     * @return string
     */
    protected static function getDefaultStorageFolder()
    {
        /** @var array<string,scalar> */
        $parsetUrl = SnapURL::parseUrl(get_home_url());
        if (is_string($parsetUrl['host']) && strlen($parsetUrl['host']) > 0) {
            $parsetUrl['host'] = preg_replace("([^\w\d\-_~,;\[\]\(\)\/\.])", '', $parsetUrl['host']);
        }
        $parsetUrl['scheme']   = false;
        $parsetUrl['port']     = false;
        $parsetUrl['query']    = false;
        $parsetUrl['fragment'] = false;
        $parsetUrl['user']     = false;
        $parsetUrl['pass']     = false;
        if (is_string($parsetUrl['path']) && strlen($parsetUrl['path']) > 0) {
            $parsetUrl['path'] = preg_replace("([^\w\d\-_~,;\[\]\(\)\/\.])", '', $parsetUrl['path']);
        }
        return ltrim(SnapURL::buildUrl($parsetUrl), '/\\');
    }

    /**
     * Render form config fields
     *
     * @param bool $echo Echo or return
     *
     * @return string
     */
    public function renderConfigFields($echo = true)
    {
        try {
            $templateData = $this->getConfigFieldsData();
        } catch (Exception | Error $e) {
            TplMng::getInstance()->render(
                'admin_pages/storages/parts/storage_error',
                ['exception' => $e]
            );
            $templateData = $this->getDefaultConfigFieldsData();
        }

        return TplMng::getInstance()->render(
            $this->getConfigFieldsTemplatePath(),
            $templateData,
            $echo
        );
    }

    /**
     * Returns the config fields template path
     *
     * @return string
     */
    abstract protected function getConfigFieldsTemplatePath();

    /**
     * Returns the config fields template data
     *
     * @return array<string, mixed>
     */
    abstract protected function getConfigFieldsData();

    /**
     * Returns the default config fields template data
     *
     * @return array<string, mixed>
     */
    abstract protected function getDefaultConfigFieldsData();

    /**
     * Render remote localtion info
     *
     * @param bool $failed        Failed upload
     * @param bool $cancelled     Cancelled upload
     * @param bool $packageExists Backup exists
     * @param bool $echo          Echo or return
     *
     * @return string
     */
    public function renderRemoteLocationInfo($failed = false, $cancelled = false, $packageExists = true, $echo = true)
    {
        return TplMng::getInstance()->render(
            'admin_pages/storages/parts/remote_localtion_info',
            [
                'failed'        => $failed,
                'cancelled'     => $cancelled,
                'packageExists' => $packageExists,
                'storage'       => $this,
            ],
            $echo
        );
    }

    /**
     * Storages test
     *
     * @param string $message Test message
     *
     * @return bool return true if success, false otherwise
     */
    public function test(&$message = '')
    {
        $this->testLog->reset();
        $message = sprintf(__('Testing %s storage...', 'duplicator-pro'), $this->getStypeName());
        $this->testLog->addMessage($message);

        if (static::isSupported() == false) {
            $message = sprintf(__('Storage %s isn\'t supported on current server', 'duplicator-pro'), $this->getStypeName());
            $this->testLog->addMessage($message);
            return false;
        }
        if ($this->isValid() == false) {
            $message = sprintf(__('Storage %s config data isn\'t valid', 'duplicator-pro'), $this->getStypeName());
            $this->testLog->addMessage($message);
            return false;
        }

        try {
            $adapter = $this->getAdapter();
        } catch (Exception $e) {
            // This exception is captured temporally until all storage has implemented its adapter.
            /** @todo remove this remove this when it is okay */
            return true;
        }
        $testFileName = 'dup_test_' . md5(uniqid((string) random_int(0, mt_getrandmax()), true)) . '.txt';

        $this->testLog->addMessage(sprintf(__('Checking if the temporary file exists "%1$s"...', 'duplicator-pro'), $testFileName));
        if ($adapter->exists($testFileName)) {
            $this->testLog->addMessage(sprintf(__(
                'File with the temporary file name already exists, please try again "%1$s"',
                'duplicator-pro'
            ), $testFileName));
            $message = __('File with the temporary file name already exists, please try again', 'duplicator-pro');
            return false;
        }

        $this->testLog->addMessage(sprintf(__('Creating temporary file "%1$s"...', 'duplicator-pro'), $testFileName));
        if (!$adapter->createFile($testFileName, 'test')) {
            $this->testLog->addMessage(
                __(
                    'There was a problem when storing the temporary file',
                    'duplicator-pro'
                )
            );
            $message = __('There was a problem storing the temporary file', 'duplicator-pro');
            return false;
        }

        $this->testLog->addMessage(sprintf(__('Checking if the temporary file exists "%1$s"...', 'duplicator-pro'), $testFileName));
        if (!$adapter->isFile($testFileName)) {
            $this->testLog->addMessage(sprintf(__(
                'The temporary file was not found "%1$s"',
                'duplicator-pro'
            ), $testFileName));
            $message = __('The temporary file was not found', 'duplicator-pro');
            return false;
        }

        $this->testLog->addMessage(sprintf(__('Deleting temporary file "%1$s"...', 'duplicator-pro'), $testFileName));
        if (!$adapter->delete($testFileName)) {
            $this->testLog->addMessage(sprintf(__(
                'There was a problem when deleting the temporary file "%1$s"',
                'duplicator-pro'
            ), $testFileName));
            $message = __('There was a problem deleting the temporary file', 'duplicator-pro');
            return false;
        }

        $this->testLog->addMessage(__('Successfully stored and deleted file', 'duplicator-pro'));
        $message = __('Successfully stored and deleted file', 'duplicator-pro');
        return true;
    }

    /**
     * Get last test messages
     *
     * @return string
     */
    public function getTestLog()
    {
        return (string) $this->testLog;
    }

    /**
     * Get copied storage from source id.
     * If destId is existing storage is accepted source id with only the same type
     *
     * @param int $sourceId Source storage id
     * @param int $targetId Target storage id, if <= 0 create new storage
     *
     * @return false|static Return false on failure or storage object with updated value
     */
    public static function getCopyStorage($sourceId, $targetId = -1)
    {
        if (($source = static::getById($sourceId)) === false) {
            return false;
        }

        if ($targetId <= 0) {
            $class = get_class($source);
            /** @var static */
            $target = new $class();
        } else {
            /** @var false|static */
            $target = static::getById($targetId);
            if ($target == false) {
                return false;
            }
            if ($source->getSType() != $target->getSType()) {
                return false;
            }
        }

        $skipProps = [
            'id',
            'testLog',
        ];

        $reflect = new ReflectionClass($source);
        foreach ($reflect->getProperties() as $prop) {
            if (in_array($prop->getName(), $skipProps)) {
                continue;
            }
            if ($prop->isStatic()) {
                continue;
            }
            $prop->setAccessible(true);
            if ($prop->getName() == 'name') {
                $newName = sprintf(__('%1$s - Copy', "duplicator-pro"), $prop->getValue($source));
                $prop->setValue($target, $newName);
            } else {
                $prop->setValue($target, $prop->getValue($source));
            }
        }

        return $target;
    }

    /**
     * Get all storages by type
     *
     * @param int $sType Storage type
     *
     * @return self[]|false return entities list of false on failure
     */
    public static function getAllBySType($sType)
    {
        return self::getAll(0, 0, null, fn(self $storage): bool => $storage->getSType() == $sType);
    }

    /**
     * To export data
     *
     * @return array<string, mixed>
     */
    public function settingsExport()
    {
        $data = JsonSerialize::serializeToData($this, JsonSerialize::JSON_SKIP_MAGIC_METHODS |  JsonSerialize::JSON_SKIP_CLASS_NAME);
        unset($data['testLog']);
        return $data;
    }

    /**
     * Update object properties from import data
     *
     * @param array<string, mixed> $data        data to import
     * @param string               $dataVersion version of data
     * @param array<string, mixed> $extraData   extra data, useful form id mapping etc.
     *
     * @return bool True if success, otherwise false
     */
    public function settingsImport($data, $dataVersion, array $extraData = [])
    {
        $skipProps = ['id'];

        $reflect = new ReflectionClass(self::class);
        $props   = $reflect->getProperties();

        foreach ($props as $prop) {
            if (in_array($prop->getName(), $skipProps)) {
                continue;
            }
            if (!isset($data[$prop->getName()])) {
                continue;
            }
            $prop->setAccessible(true);
            if (is_array($data[$prop->getName()])) {
                $value = array_merge($prop->getValue($this), $data[$prop->getName()]);
                $prop->setValue($this, $value);
            } else {
                $prop->setValue($this, $data[$prop->getName()]);
            }
        }

        return true;
    }

    /**
     * Save new storage to DB
     *
     * @return int|false The id or false on failure
     */
    protected function insert()
    {
        if (($id = parent::insert()) === false) {
            return false;
        }

        do_action('duplicator_pro_after_storage_create', $id);
        return $id;
    }

    /**
     * Delete this storage
     *
     * @return bool True on success, or false on error.
     */
    public function delete()
    {
        $id = $this->id;

        if (parent::delete() === false) {
            return false;
        }

        DUP_PRO_Package::by_status_callback(function (DUP_PRO_Package $package) use ($id): void {
            foreach ($package->upload_infos as $key => $upload_info) {
                if ($upload_info->getStorageId() == $id) {
                    DUP_PRO_Log::traceObject("deleting uploadinfo from package $package->ID", $upload_info);
                    unset($package->upload_infos[$key]);
                    $package->save();
                    break;
                }
            }
        });

        DUP_PRO_Schedule_Entity::listCallback(function (DUP_PRO_Schedule_Entity $schedule) use ($id): void {
            if (($key = array_search($id, $schedule->storage_ids)) !== false) {
                $key = (int) $key;
                //use array_splice() instead of unset() to reset keys
                array_splice($schedule->storage_ids, $key, 1);
                if (count($schedule->storage_ids) === 0) {
                    $schedule->active = false;
                }
                $schedule->save();
            }
        });

        do_action('duplicator_pro_after_storage_delete', $id);

        return true;
    }
}
