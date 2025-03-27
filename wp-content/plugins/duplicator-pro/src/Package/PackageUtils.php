<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Package;

use DUP_PRO_Package;
use DUP_PRO_PackageStatus;
use DUP_PRO_Log;
use Exception;
use Duplicator\Installer\Models\MigrateData;
use Duplicator\Package\Recovery\RecoveryPackage;

class PackageUtils
{
    /**
     * Update CREATED AFTER INSTALL FLAGS
     *
     * @param MigrateData $migrationData migration data
     *
     * @return void
     */
    public static function updateCreatedAfterInstallFlags(MigrateData $migrationData)
    {
        if ($migrationData->restoreBackupMode == false) {
            return;
        }

        // Refresh recovery Backup set beforw backup
        $ids = DUP_PRO_Package::dbSelect('FIND_IN_SET(\'' . DUP_PRO_Package::FLAG_DISASTER_SET . '\', `flags`)', 0, 0, '', 'ids');
        if (count($ids)) {
            RecoveryPackage::setRecoveablePackage($ids[0]);
        }

        // Update all backups with created after restore flag or created after install time
        DUP_PRO_Package::dbSelectCallback(
            function (DUP_PRO_Package $package): void {
                $package->updateMigrateAfterInstallFlag();
                $package->save();
            },
            'FIND_IN_SET(\'' . DUP_PRO_Package::FLAG_CREATED_AFTER_RESTORE . '\', `flags`) OR 
            (
                `id` > ' .  $migrationData->packageId . ' AND
                `created` < \'' . esc_sql($migrationData->installTime) . '\'
            )'
        );
    }

    /**
     * Get packages without storages
     *
     * @return int[]
     */
    public static function getPackageWithoutStorages()
    {
        $where = '`status` = ' . DUP_PRO_PackageStatus::COMPLETE .
            ' AND FIND_IN_SET(\'' . DUP_PRO_Package::FLAG_HAVE_LOCAL . '\', `flags`) = 0' .
            ' AND FIND_IN_SET(\'' . DUP_PRO_Package::FLAG_HAVE_REMOTE . '\', `flags`) = 0';
        return DUP_PRO_Package::dbSelect($where, 0, 0, '', 'ids');
    }

    /**
     * Massive delete packages without storages using direct SQL query
     *
     * @return int Number of packages deleted
     */
    public static function bulkDeletePackageWithoutStorages()
    {
        // In that case we can use direct SQL query because the backup don't have storages,so we don't need remove local files
        global $wpdb;

        $table = DUP_PRO_Package::getTableName();

        $ids   = self::getPackageWithoutStorages();
        $count = count($ids);

        if ($count == 0) {
            return 0;
        }

        $idList = implode(',', $ids);

        $query  = "DELETE FROM `{$table}` WHERE id IN ({$idList})";
        $result = $wpdb->query($query);

        if ($result === false) {
            throw new Exception("Error deleting packages without storages: " . $wpdb->last_error);
        }

        return (int) $result;
    }
}
