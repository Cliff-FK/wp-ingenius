<?php

/**
 * Auloader calsses
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Utils;

/**
 * Autoloader calss, dont user Duplicator library here
 */
final class Autoloader extends AbstractAutoloader
{
    const VENDOR_PATH = DUPLICATOR____PATH . '/vendor-prefixed/';

    /**
     * Register autoloader function
     *
     * @return void
     */
    public static function register()
    {
        spl_autoload_register([self::class, 'load']);
        self::loadFiles();
    }

    /**
     * Load class
     *
     * @param string $className class name
     *
     * @return void
     */
    public static function load($className)
    {
        if (strpos($className, self::ROOT_NAMESPACE) === 0) {
            if (($filepath = self::getAddonFile($className)) === false) {
                foreach (self::getNamespacesMapping() as $namespace => $mappedPath) {
                    if (strpos($className, (string) $namespace) !== 0) {
                        continue;
                    }

                    $filepath = self::getFilenameFromClass($className, $namespace, $mappedPath);
                    if (file_exists($filepath)) {
                        include $filepath;
                        return;
                    }
                }
            } else {
                if (file_exists($filepath)) {
                    include $filepath;
                    return;
                }
            }
        } elseif (strpos($className, self::ROOT_VENDOR) === 0) {
            foreach (self::getNamespacesVendorMapping() as $namespace => $mappedPath) {
                if (strpos($className, (string) $namespace) !== 0) {
                    continue;
                }

                $filepath = self::getFilenameFromClass($className, $namespace, $mappedPath);
                if (file_exists($filepath)) {
                    include $filepath;
                    return;
                }
            }
        } else {
            // @todo remove legacy logic in autoloading when duplicator is fully converted.
            $legacyMappging = self::customLegacyMapping();
            $legacyClass    = strtolower(ltrim($className, '\\'));
            if (array_key_exists($legacyClass, $legacyMappging)) {
                if (file_exists($legacyMappging[$legacyClass])) {
                    include $legacyMappging[$legacyClass];
                    return;
                }
            }
        }
    }

    /**
     * Load necessary files
     *
     * @return void
     */
    private static function loadFiles()
    {
        foreach (
            [
                '/ralouphie/getallheaders/src/getallheaders.php',
                '/symfony/polyfill-mbstring/bootstrap.php',
                '/symfony/polyfill-php80/bootstrap.php',
                '/guzzlehttp/guzzle/src/functions_include.php',
            ] as $file
        ) {
            require_once self::VENDOR_PATH . $file;
        }
    }

    /**
     * Return namespace mapping
     *
     * @return string[]
     */
    protected static function getNamespacesMapping()
    {
        // the order is important, it is necessary to insert the longest namespaces first
        return [
            self::ROOT_INSTALLER_NAMESPACE => DUPLICATOR____PATH . '/installer/dup-installer/src/',
            self::ROOT_NAMESPACE           => DUPLICATOR____PATH . '/src/',
        ];
    }

    /**
     * Return namespace mapping
     *
     * @return string[]
     */
    protected static function getNamespacesVendorMapping()
    {
        return [
            self::ROOT_VENDOR . 'Cron'                        => self::VENDOR_PATH . 'dragonmantank/cron-expression/src/Cron',
            self::ROOT_VENDOR . 'WpOrg\\Requests'             => self::VENDOR_PATH . 'rmccue/requests/src',
            self::ROOT_VENDOR . 'Amk\\JsonSerialize'          => self::VENDOR_PATH . 'andreamk/jsonserialize/src/',
            self::ROOT_VENDOR . 'ParagonIE\\ConstantTime'     => self::VENDOR_PATH . 'paragonie/constant_time_encoding/src/',
            self::ROOT_VENDOR . 'phpseclib3'                  => self::VENDOR_PATH . 'phpseclib/phpseclib/phpseclib/',
            self::ROOT_VENDOR . 'ForceUTF8'                   => self::VENDOR_PATH . 'neitanod/forceutf8/src/ForceUTF8/',
            self::ROOT_VENDOR . 'Symfony\\Polyfill\\Mbstring' => self::VENDOR_PATH . 'symfony/polyfill-mbstring',
            self::ROOT_VENDOR . 'Symfony\\Polyfill\\Php80'    => self::VENDOR_PATH . 'symfony/polyfill-php80',
            self::ROOT_VENDOR . 'Psr\\Http\\Message'          => self::VENDOR_PATH . 'psr/http-message/src',
            self::ROOT_VENDOR . 'Psr\\Http\\Client'           => self::VENDOR_PATH . 'psr/http-client/src',
            self::ROOT_VENDOR . 'Psr\\Log'                    => self::VENDOR_PATH . 'psr/log/Psr/Log',
            self::ROOT_VENDOR . 'Psr\\Cache'                  => self::VENDOR_PATH . 'psr/cache/src',
            self::ROOT_VENDOR . 'GuzzleHttp\\Promise'         => self::VENDOR_PATH . 'guzzlehttp/promises/src',
            self::ROOT_VENDOR . 'GuzzleHttp\\Psr7'            => self::VENDOR_PATH . 'guzzlehttp/psr7/src',
            self::ROOT_VENDOR . 'GuzzleHttp'                  => self::VENDOR_PATH . 'guzzlehttp/guzzle/src',
        ];
    }

    /**
     * Mappgin of some legacy classes
     *
     * @return array<string, string>
     */
    protected static function customLegacyMapping()
    {
        return [
            'dup_pro_u'                       => DUPLICATOR____PATH . '/classes/utilities/class.u.php',
            'dup_pro_str'                     => DUPLICATOR____PATH . '/classes/utilities/class.u.string.php',
            'dup_pro_date'                    => DUPLICATOR____PATH . '/classes/utilities/class.u.date.php',
            'dup_pro_zip_u'                   => DUPLICATOR____PATH . '/classes/utilities/class.u.zip.php',
            'dup_pro_validator'               => DUPLICATOR____PATH . '/classes/utilities/class.u.validator.php',
            'dup_pro_tree_files'              => DUPLICATOR____PATH . '/classes/utilities/class.u.tree.files.php',
            'dup_pro_mu'                      => DUPLICATOR____PATH . '/classes/utilities/class.u.multisite.php',
            'dup_pro_global_entity'           => DUPLICATOR____PATH . '/classes/entities/class.global.entity.php',
            'dup_pro_package_template_entity' => DUPLICATOR____PATH . '/classes/entities/class.package.template.entity.php',
            'dup_pro_schedule_entity'         => DUPLICATOR____PATH . '/classes/entities/class.schedule.entity.php',
            'dup_pro_schedule_repeat_types'   => DUPLICATOR____PATH . '/classes/entities/class.schedule.entity.php',
            'dup_pro_schedule_days'           => DUPLICATOR____PATH . '/classes/entities/class.schedule.entity.php',
            'dup_pro_secure_global_entity'    => DUPLICATOR____PATH . '/classes/entities/class.secure.global.entity.php',
            'dup_pro_storage_entity'          => DUPLICATOR____PATH . '/classes/entities/class.storage.entity.php',
            'dup_pro_package_runner'          => DUPLICATOR____PATH . '/classes/package/class.pack.runner.php',
            'dup_pro_package'                 => DUPLICATOR____PATH . '/classes/package/class.pack.php',
            'dup_pro_packagetype'             => DUPLICATOR____PATH . '/classes/package/class.pack.php',
            'dup_pro_archive'                 => DUPLICATOR____PATH . '/classes/package/class.pack.archive.php',
            'dup_pro_database'                => DUPLICATOR____PATH . '/classes/package/class.pack.database.php',
            'dup_pro_installer'               => DUPLICATOR____PATH . '/classes/package/class.pack.installer.php',
            'dup_pro_custom_host_manager'     => DUPLICATOR____PATH . '/classes/host/class.custom.host.manager.php',
            'dup_pro_ui_viewstate'            => DUPLICATOR____PATH . '/classes/ui/class.ui.viewstate.php',
            'dup_pro_ui_dialog'               => DUPLICATOR____PATH . '/classes/ui/class.ui.dialog.php',
            'dup_pro_ui_messages'             => DUPLICATOR____PATH . '/classes/ui/class.ui.messages.php',
            'dup_pro_php_log'                 => DUPLICATOR____PATH . '/classes/class.php.log.php',
            'dup_pro_constants'               => DUPLICATOR____PATH . '/classes/class.constants.php',
            'dup_pro_db'                      => DUPLICATOR____PATH . '/classes/class.db.php',
            'dup_pro_log'                     => DUPLICATOR____PATH . '/classes/class.logging.php',
            'dup_pro_handler'                 => DUPLICATOR____PATH . '/classes/class.logging.php',
            'dup_pro_server'                  => DUPLICATOR____PATH . '/classes/class.server.php',
            'dup_pro_package_pagination'      => DUPLICATOR____PATH . '/classes/class.package.pagination.php',
            'dup_pro_scanvalidator'           => DUPLICATOR____PATH . '/classes/class.scan.check.php',
        ];
    }
}
