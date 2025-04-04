<?php

/**
 * WP-config params descriptions
 *
 * @category  Duplicator
 * @package   Installer
 * @author    Snapcreek <admin@snapcreek.com>
 * @copyright 2011-2021  Snapcreek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

namespace Duplicator\Installer\Core\Params\Descriptors;

use Duplicator\Installer\Core\Params\PrmMng;
use Duplicator\Installer\Core\Params\Items\ParamItem;
use Duplicator\Installer\Core\Params\Items\ParamForm;
use Duplicator\Installer\Core\Params\Items\ParamOption;
use Duplicator\Installer\Core\Params\Items\ParamFormWpConfig;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Installer\Addons\ProBase\License;
use Duplicator\Libs\Snap\SnapDB;
use DUPX_ArchiveConfig;
use Duplicator\Installer\Core\InstState;
use Duplicator\Libs\Snap\SnapURL;

/**
 * class where all parameters are initialized. Used by the param manager
 */
final class ParamDescWpConfig implements DescriptorInterface
{
    const NOTICE_ID_WP_CONF_PARAM_PATHS_EMPTY      = 'wp_conf_param_paths_empty_to_validate';
    const NOTICE_ID_WP_CONF_FORCE_SSL_ADMIN        = 'wp_conf_disabled_force_ssl_admin';
    const NOTICE_ID_WP_CONF_PARAM_DOMAINS_MODIFIED = 'wp_conf_param_domains_empty_to_validate';

    /**
     * Init params
     *
     * @param ParamItem[]|ParamForm[] $params params list
     *
     * @return void
     */
    public static function init(&$params)
    {
        $archiveConfig = \DUPX_ArchiveConfig::getInstance();

        $params[PrmMng::PARAM_WP_CONF_DISALLOW_FILE_EDIT] = new ParamFormWpConfig(
            PrmMng::PARAM_WP_CONF_DISALLOW_FILE_EDIT,
            ParamForm::TYPE_BOOL,
            ParamForm::FORM_TYPE_CHECKBOX,
            [
                'default' => $archiveConfig->getDefineArrayValue(
                    'DISALLOW_FILE_EDIT'
                ),
            ],
            [
                'label'         => 'DISALLOW_FILE_EDIT:',
                'checkboxLabel' => 'Disable the Plugin/Theme Editor',
            ]
        );

        $params[PrmMng::PARAM_WP_CONF_DISALLOW_FILE_MODS] = new ParamFormWpConfig(
            PrmMng::PARAM_WP_CONF_DISALLOW_FILE_MODS,
            ParamForm::TYPE_BOOL,
            ParamForm::FORM_TYPE_CHECKBOX,
            [
                'default' => $archiveConfig->getDefineArrayValue(
                    'DISALLOW_FILE_MODS',
                    [
                        'value'      => false,
                        'inWpConfig' => false,
                    ]
                ),
            ],
            [
                'label'         => 'DISALLOW_FILE_MODS:',
                'checkboxLabel' => 'This will block users being able to use the plugin and theme installation/update ' .
                    'functionality from the WordPress admin area',
            ]
        );

        $params[PrmMng::PARAM_WP_CONF_AUTOSAVE_INTERVAL] = new ParamFormWpConfig(
            PrmMng::PARAM_WP_CONF_AUTOSAVE_INTERVAL,
            ParamForm::TYPE_INT,
            ParamForm::FORM_TYPE_NUMBER,
            [ // ITEM ATTRIBUTES
                'default' => $archiveConfig->getDefineArrayValue(
                    'AUTOSAVE_INTERVAL',
                    [
                        'value'      => 60,
                        'inWpConfig' => false,
                    ]
                ),
            ],
            [ // FORM ATTRIBUTES
                'label'          => 'AUTOSAVE_INTERVAL:',
                'subNote'        => 'Auto-save interval in seconds (default:60)',
                'min'            => 5,
                'step'           => 1,
                'wrapperClasses' => ['small'],
                'postfix'        => [
                    'type'  => 'label',
                    'label' => 'Sec.',
                ],
            ]
        );

        $params[PrmMng::PARAM_WP_CONF_WP_POST_REVISIONS] = new ParamFormWpConfig(
            PrmMng::PARAM_WP_CONF_WP_POST_REVISIONS,
            ParamForm::TYPE_INT,
            ParamForm::FORM_TYPE_NUMBER,
            [ // ITEM ATTRIBUTES
                'default'          => $archiveConfig->getDefineArrayValue(
                    'WP_POST_REVISIONS',
                    [
                        'value'      => true,
                        'inWpConfig' => false,
                    ]
                ),
                'sanitizeCallback' => function ($value) {
                    //convert bool on int
                    if ($value === true) {
                        $value = PHP_INT_MAX;
                    }
                    if ($value === false) {
                        $value = 0;
                    }
                    return $value;
                },
            ],
            [ // FORM ATTRIBUTES
                'label'          => 'WP_POST_REVISIONS:',
                'subNote'        => 'Number of article revisions. Select 0 to disable revisions. Disable the field to enable revisions.',
                'min'            => 0,
                'step'           => 1,
                'wrapperClasses' => ['small'],
            ]
        );

        $params[PrmMng::PARAM_WP_CONF_FORCE_SSL_ADMIN] = new ParamFormWpConfig(
            PrmMng::PARAM_WP_CONF_FORCE_SSL_ADMIN,
            ParamForm::TYPE_BOOL,
            ParamForm::FORM_TYPE_CHECKBOX,
            [
                'default' => self::getDefaultForceSSLAdminConfig(),
            ],
            [
                'label'         => 'FORCE_SSL_ADMIN:',
                'checkboxLabel' => 'Enforce Admin SSL',
            ]
        );

        $params[PrmMng::PARAM_WP_CONF_AUTOMATIC_UPDATER_DISABLED] = new ParamFormWpConfig(
            PrmMng::PARAM_WP_CONF_AUTOMATIC_UPDATER_DISABLED,
            ParamForm::TYPE_BOOL,
            ParamForm::FORM_TYPE_CHECKBOX,
            [
                'default' => $archiveConfig->getDefineArrayValue(
                    'AUTOMATIC_UPDATER_DISABLED',
                    [
                        'value'      => false,
                        'inWpConfig' => false,
                    ]
                ),
            ],
            [
                'label'         => 'AUTOMATIC_UPDATER_DISABLED:',
                'checkboxLabel' => 'Disable automatic updater',
            ]
        );

        $autoUpdateValue = $archiveConfig->getWpConfigDefineValue('WP_AUTO_UPDATE_CORE');
        if (is_bool($autoUpdateValue)) {
            $autoUpdateValue = ($autoUpdateValue ? 'true' : 'false');
        }
        $params[PrmMng::PARAM_WP_CONF_WP_AUTO_UPDATE_CORE] = new ParamFormWpConfig(
            PrmMng::PARAM_WP_CONF_WP_AUTO_UPDATE_CORE,
            ParamForm::TYPE_STRING,
            ParamForm::FORM_TYPE_SELECT,
            [
                'default'      => [
                    'value'      => $autoUpdateValue,
                    'inWpConfig' => $archiveConfig->inWpConfigDefine('WP_AUTO_UPDATE_CORE'),
                ],
                'acceptValues' => [
                    '',
                    'false',
                    'true',
                    'minor',
                ],
            ],
            [
                'label'   => 'WP_AUTO_UPDATE_CORE:',
                'options' => [
                    new ParamOption('minor', 'Enable only core minor updates - Default'),
                    new ParamOption('false', 'Disable all core updates'),
                    new ParamOption('true', 'Enable all core updates'),
                ],
            ]
        );

        $params[PrmMng::PARAM_WP_CONF_IMAGE_EDIT_OVERWRITE] = new ParamFormWpConfig(
            PrmMng::PARAM_WP_CONF_IMAGE_EDIT_OVERWRITE,
            ParamForm::TYPE_BOOL,
            ParamForm::FORM_TYPE_CHECKBOX,
            [
                'default' => $archiveConfig->getDefineArrayValue(
                    'IMAGE_EDIT_OVERWRITE',
                    [
                        'value'      => true,
                        'inWpConfig' => false,
                    ]
                ),
            ],
            [
                'label'         => 'IMAGE_EDIT_OVERWRITE:',
                'checkboxLabel' => 'Create only one set of image edits',
            ]
        );

        $params[PrmMng::PARAM_WP_CONF_WP_CACHE] = new ParamFormWpConfig(
            PrmMng::PARAM_WP_CONF_WP_CACHE,
            ParamForm::TYPE_BOOL,
            ParamForm::FORM_TYPE_CHECKBOX,
            [
                'default' => $archiveConfig->getDefineArrayValue('WP_CACHE'),
            ],
            [
                'label'         => 'WP_CACHE:',
                'checkboxLabel' => 'Keep Enabled',
            ]
        );

        $params[PrmMng::PARAM_WP_CONF_WPCACHEHOME] = new ParamFormWpConfig(
            PrmMng::PARAM_WP_CONF_WPCACHEHOME,
            ParamForm::TYPE_STRING,
            ParamForm::FORM_TYPE_TEXT,
            [ // ITEM ATTRIBUTES
                'default'          => $archiveConfig->getDefineArrayValue("WPCACHEHOME"),
                'sanitizeCallback' => function ($value) {
                    $result = SnapUtil::sanitizeNSCharsNewlineTrim($value);
                    // WPCACHEHOME want final slash
                    return SnapIO::safePathTrailingslashit($result);
                },
            ],
            [ // FORM ATTRIBUTES
                'label'   => 'WPCACHEHOME:',
                'subNote' => 'This define is not part of the WordPress core but is a define used by WP Super Cache.',
            ]
        );

        $params[PrmMng::PARAM_WP_CONF_WP_TEMP_DIR] = new ParamFormWpConfig(
            PrmMng::PARAM_WP_CONF_WP_TEMP_DIR,
            ParamForm::TYPE_STRING,
            ParamForm::FORM_TYPE_TEXT,
            [ // ITEM ATTRIBUTES
                'default'          => $archiveConfig->getDefineArrayValue("WP_TEMP_DIR"),
                'sanitizeCallback' => [
                    ParamsDescriptors::class,
                    'sanitizePath',
                ],
            ],
            ['label' => 'WP_TEMP_DIR:']
        );

        $params[PrmMng::PARAM_WP_CONF_WP_DEBUG] = new ParamFormWpConfig(
            PrmMng::PARAM_WP_CONF_WP_DEBUG,
            ParamForm::TYPE_BOOL,
            ParamForm::FORM_TYPE_CHECKBOX,
            [
                'default' => $archiveConfig->getDefineArrayValue('WP_DEBUG'),
            ],
            [
                'label'         => 'WP_DEBUG:',
                'checkboxLabel' => 'Display errors and warnings',
            ]
        );

        $debugLogValue = $archiveConfig->getWpConfigDefineValue('WP_DEBUG_LOG');
        if (is_string($debugLogValue)) {
            $debugLogValue = !empty($debugLogValue);
        }
        $params[PrmMng::PARAM_WP_CONF_WP_DEBUG_LOG] = new ParamFormWpConfig(
            PrmMng::PARAM_WP_CONF_WP_DEBUG_LOG,
            ParamForm::TYPE_BOOL,
            ParamForm::FORM_TYPE_CHECKBOX,
            [
                'default' => [
                    'value'      => $debugLogValue,
                    'inWpConfig' => $archiveConfig->inWpConfigDefine('WP_DEBUG_LOG'),
                ],
            ],
            [
                'label'         => 'WP_DEBUG_LOG:',
                'checkboxLabel' => 'Log errors and warnings',
            ]
        );

        $params[PrmMng::PARAM_WP_CONF_WP_DISABLE_FATAL_ERROR_HANDLER] = new ParamFormWpConfig(
            PrmMng::PARAM_WP_CONF_WP_DISABLE_FATAL_ERROR_HANDLER,
            ParamForm::TYPE_BOOL,
            ParamForm::FORM_TYPE_CHECKBOX,
            [
                'default' => $archiveConfig->getDefineArrayValue('WP_DISABLE_FATAL_ERROR_HANDLER'),
            ],
            [
                'label'         => 'WP_DISABLE_FATAL_ERROR_HANDLER:',
                'checkboxLabel' => 'Disable fatal error handler',
                'status'        => version_compare($archiveConfig->version_wp, '5.2.0', '<') ? ParamForm::STATUS_SKIP : ParamForm::STATUS_ENABLED,
            ]
        );

        $params[PrmMng::PARAM_WP_CONF_WP_DEBUG_DISPLAY] = new ParamFormWpConfig(
            PrmMng::PARAM_WP_CONF_WP_DEBUG_DISPLAY,
            ParamForm::TYPE_BOOL,
            ParamForm::FORM_TYPE_CHECKBOX,
            [
                'default' => $archiveConfig->getDefineArrayValue('WP_DEBUG_DISPLAY'),
            ],
            [
                'label'         => 'WP_DEBUG_DISPLAY:',
                'checkboxLabel' => 'Display errors and warnings',
            ]
        );

        $params[PrmMng::PARAM_WP_CONF_SCRIPT_DEBUG] = new ParamFormWpConfig(
            PrmMng::PARAM_WP_CONF_SCRIPT_DEBUG,
            ParamForm::TYPE_BOOL,
            ParamForm::FORM_TYPE_CHECKBOX,
            [
                'default' => $archiveConfig->getDefineArrayValue('SCRIPT_DEBUG'),
            ],
            [
                'label'         => 'SCRIPT_DEBUG:',
                'checkboxLabel' => 'JavaScript or CSS errors',
            ]
        );

        $params[PrmMng::PARAM_WP_CONF_CONCATENATE_SCRIPTS] = new ParamFormWpConfig(
            PrmMng::PARAM_WP_CONF_CONCATENATE_SCRIPTS,
            ParamForm::TYPE_BOOL,
            ParamForm::FORM_TYPE_CHECKBOX,
            [
                'default' => $archiveConfig->getDefineArrayValue('CONCATENATE_SCRIPTS', [
                    'value'      => false,
                    'inWpConfig' => false,
                ]),
            ],
            [
                'label'         => 'CONCATENATE_SCRIPTS:',
                'checkboxLabel' => 'Concatenate all JavaScript files into one URL',
            ]
        );

        $params[PrmMng::PARAM_WP_CONF_SAVEQUERIES] = new ParamFormWpConfig(
            PrmMng::PARAM_WP_CONF_SAVEQUERIES,
            ParamForm::TYPE_BOOL,
            ParamForm::FORM_TYPE_CHECKBOX,
            [
                'default' => $archiveConfig->getDefineArrayValue('SAVEQUERIES'),
            ],
            [
                'label'         => 'SAVEQUERIES:',
                'checkboxLabel' => 'Save database queries in an array ($wpdb->queries)',
            ]
        );

        $params[PrmMng::PARAM_WP_CONF_ALTERNATE_WP_CRON] = new ParamFormWpConfig(
            PrmMng::PARAM_WP_CONF_ALTERNATE_WP_CRON,
            ParamForm::TYPE_BOOL,
            ParamForm::FORM_TYPE_CHECKBOX,
            [
                'default' => $archiveConfig->getDefineArrayValue('ALTERNATE_WP_CRON', [
                    'value'      => false,
                    'inWpConfig' => false,
                ]),
            ],
            [
                'label'         => 'ALTERNATE_WP_CRON:',
                'checkboxLabel' => 'Use an alternative Cron with WP',
            ]
        );

        $params[PrmMng::PARAM_WP_CONF_DISABLE_WP_CRON] = new ParamFormWpConfig(
            PrmMng::PARAM_WP_CONF_DISABLE_WP_CRON,
            ParamForm::TYPE_BOOL,
            ParamForm::FORM_TYPE_CHECKBOX,
            [
                'default' => $archiveConfig->getDefineArrayValue('DISABLE_WP_CRON', [
                    'value'      => false,
                    'inWpConfig' => false,
                ]),
            ],
            [
                'label'         => 'DISABLE_WP_CRON:',
                'checkboxLabel' => 'Disable cron entirely',
            ]
        );

        $params[PrmMng::PARAM_WP_CONF_WP_CRON_LOCK_TIMEOUT] = new ParamFormWpConfig(
            PrmMng::PARAM_WP_CONF_WP_CRON_LOCK_TIMEOUT,
            ParamForm::TYPE_INT,
            ParamForm::FORM_TYPE_NUMBER,
            [
                'default'   => $archiveConfig->getDefineArrayValue('WP_CRON_LOCK_TIMEOUT', [
                    'value'      => 60,
                    'inWpConfig' => false,
                ]),
                'min_range' => 1,
            ],
            [
                'min'            => 1,
                'step'           => 1,
                'label'          => 'WP_CRON_LOCK_TIMEOUT:',
                'wrapperClasses' => ['small'],
                'subNote'        => 'Cron process cannot run more than once every WP_CRON_LOCK_TIMEOUT seconds',
            ]
        );

        $params[PrmMng::PARAM_WP_CONF_EMPTY_TRASH_DAYS] = new ParamFormWpConfig(
            PrmMng::PARAM_WP_CONF_EMPTY_TRASH_DAYS,
            ParamForm::TYPE_INT,
            ParamForm::FORM_TYPE_NUMBER,
            [
                'default'   => $archiveConfig->getDefineArrayValue('EMPTY_TRASH_DAYS', [
                    'value'      => 30,
                    'inWpConfig' => false,
                ]),
                'min_range' => 0,
            ],
            [
                'min'            => 0,
                'step'           => 1,
                'label'          => 'EMPTY_TRASH_DAYS:',
                'wrapperClasses' => ['small'],
                'subNote'        => 'How many days deleted post should be kept in trash before being deleted permanently',
            ]
        );

        $params[PrmMng::PARAM_WP_CONF_COOKIE_DOMAIN] = new ParamFormWpConfig(
            PrmMng::PARAM_WP_CONF_COOKIE_DOMAIN,
            ParamForm::TYPE_STRING,
            ParamForm::FORM_TYPE_TEXT,
            [ // ITEM ATTRIBUTES
                'default'          => $archiveConfig->getDefineArrayValue("COOKIE_DOMAIN"),
                'sanitizeCallback' => [
                    SnapUtil::class,
                    'sanitizeNSCharsNewlineTrim',
                ],
            ],
            [ // FORM ATTRIBUTES
                'label'   => 'COOKIE_DOMAIN:',
                'subNote' => 'Set <a href="http://www.askapache.com/htaccess/apache-speed-subdomains.html" target="_blank">' .
                    'different domain</a> for cookies.subdomain.example.com',
            ]
        );

        $params[PrmMng::PARAM_WP_CONF_WP_MEMORY_LIMIT] = new ParamFormWpConfig(
            PrmMng::PARAM_WP_CONF_WP_MEMORY_LIMIT,
            ParamForm::TYPE_STRING,
            ParamForm::FORM_TYPE_TEXT,
            [ // ITEM ATTRIBUTES
                'default'          => $archiveConfig->getDefineArrayValue('WP_MEMORY_LIMIT'),
                'sanitizeCallback' => [
                    SnapUtil::class,
                    'sanitizeNSCharsNewlineTrim',
                ],
                'validateRegex'    => ParamItem::VALIDATE_REGEX_AZ_NUMBER,
            ],
            [ // FORM ATTRIBUTES
                'label'          => 'WP_MEMORY_LIMIT:',
                'wrapperClasses' => ['small'],
                'subNote'        => 'PHP memory limit (default:30M; Multisite default:64M)',
            ]
        );

        $params[PrmMng::PARAM_WP_CONF_WP_MAX_MEMORY_LIMIT] = new ParamFormWpConfig(
            PrmMng::PARAM_WP_CONF_WP_MAX_MEMORY_LIMIT,
            ParamForm::TYPE_STRING,
            ParamForm::FORM_TYPE_TEXT,
            [ // ITEM ATTRIBUTES
                'default'          => $archiveConfig->getDefineArrayValue('WP_MAX_MEMORY_LIMIT'),
                'sanitizeCallback' => [
                    SnapUtil::class,
                    'sanitizeNSCharsNewlineTrim',
                ],
                'validateRegex'    => ParamItem::VALIDATE_REGEX_AZ_NUMBER,
            ],
            [ // FORM ATTRIBUTES
                'label'          => 'WP_MAX_MEMORY_LIMIT:',
                'wrapperClasses' => ['small'],
                'subNote'        => 'Wordpress admin maximum memory limit (default:256M)',
            ]
        );

        $params[PrmMng::PARAM_WP_CONF_MYSQL_CLIENT_FLAGS] = new ParamFormWpConfig(
            PrmMng::PARAM_WP_CONF_MYSQL_CLIENT_FLAGS,
            ParamForm::TYPE_ARRAY_INT,
            ParamForm::FORM_TYPE_SELECT,
            [ // ITEM ATTRIBUTES
                'default' => self::getMysqlClientFlagsDefaultVals(),
            ],
            [ // FORM ATTRIBUTES
                'label'    => 'MYSQL_CLIENT_FLAGS:',
                'options'  => self::getMysqlClientFlagsOptions(),
                'multiple' => true,
            ]
        );
    }

    /**
     * Update params after overwrite logic
     *
     * @param ParamItem[]|ParamForm[] $params params list
     *
     * @return void
     */
    public static function updateParamsAfterOverwrite($params)
    {
        //UPDATE PATHS AUTOMATICALLY
        self::setDefaultWpConfigPathValue($params, PrmMng::PARAM_WP_CONF_WP_TEMP_DIR, 'WP_TEMP_DIR');
        self::setDefaultWpConfigPathValue($params, PrmMng::PARAM_WP_CONF_WPCACHEHOME, 'WPCACHEHOME');
        self::wpConfigPathsNotices();

        //UPDATE DOMAINS AUTOMATICALLY
        self::setDefaultWpConfigDomainValue($params, PrmMng::PARAM_WP_CONF_COOKIE_DOMAIN, "COOKIE_DOMAIN");
        self::wpConfigDomainNotices();
    }

    /**
     * Returns wp counfi default value
     *
     * @return array<string, mixed>
     */
    protected static function getMysqlClientFlagsDefaultVals()
    {
        $result = DUPX_ArchiveConfig::getInstance()->getDefineArrayValue(
            'MYSQL_CLIENT_FLAGS',
            [
                'value'      => [],
                'inWpConfig' => false,
            ]
        );

        $result['value'] = array_intersect($result['value'], SnapDB::getMysqlConnectFlagsList(false));
        return $result;
    }

    /**
     * Returns the list of options of the mysql real connect flags
     *
     * @return ParamOption[]
     */
    protected static function getMysqlClientFlagsOptions(): array
    {
        $result = [];
        foreach (SnapDB::getMysqlConnectFlagsList() as $flag) {
            $result[] = new ParamOption(constant($flag), $flag);
        }
        return $result;
    }

    /**
     * Tries to replace the old path with the new path for the given wp config define.
     * If that's not possible returns a notice to the user.
     *
     * @param ParamItem[] $params      params list
     * @param string      $paramKey    param key
     * @param string      $wpConfigKey wp config key
     *
     * @return void
     */
    protected static function setDefaultWpConfigPathValue(&$params, $paramKey, $wpConfigKey)
    {
        if (!self::wpConfigNeedsUpdate($params, $paramKey, $wpConfigKey)) {
            return;
        }

        $oldMainPath = $params[PrmMng::PARAM_PATH_OLD]->getValue();
        $newMainPath = $params[PrmMng::PARAM_PATH_NEW]->getValue();
        $wpConfigVal = \DUPX_ArchiveConfig::getInstance()->getDefineArrayValue($wpConfigKey);

        // TRY TO CHANGE THE VALUE OR RESET
        if (($wpConfigVal['value'] = \DUPX_ArchiveConfig::getNewSubString($oldMainPath, $newMainPath, $wpConfigVal['value'])) === false) {
            $wpConfigVal['inWpConfig'] = false;
            $wpConfigVal['value']      = '';

            \DUPX_NOTICE_MANAGER::getInstance()->addNextStepNotice([
                'shortMsg'    => 'WP CONFIG custom paths disabled.',
                'level'       => \DUPX_NOTICE_ITEM::NOTICE,
                'longMsg'     => "The " . $params[$paramKey]->getLabel() . " path could not be set programmatically and has been disabled<br>\n",
                'longMsgMode' => \DUPX_NOTICE_ITEM::MSG_MODE_HTML,
            ], \DUPX_NOTICE_MANAGER::ADD_UNIQUE_APPEND, self::NOTICE_ID_WP_CONF_PARAM_PATHS_EMPTY);
        }

        $params[$paramKey]->setValue($wpConfigVal);
    }

    /**
     * Tries to replace the old domain with the new domain for the given wp config define.
     * If that's not possible returns a notice to the user.
     *
     * @param ParamItem[] $params      params list
     * @param string      $paramKey    param key
     * @param string      $wpConfigKey wp config key
     *
     * @return void
     */
    protected static function setDefaultWpConfigDomainValue(&$params, $paramKey, $wpConfigKey)
    {
        if (!self::wpConfigNeedsUpdate($params, $paramKey, $wpConfigKey)) {
            return;
        }

        $wpConfigVal  = \DUPX_ArchiveConfig::getInstance()->getDefineArrayValue($wpConfigKey);
        $parsedUrlNew = parse_url(PrmMng::getInstance()->getValue(PrmMng::PARAM_URL_NEW));
        $parsedUrlOld = parse_url(PrmMng::getInstance()->getValue(PrmMng::PARAM_URL_OLD));

        if ($wpConfigVal['value'] == $parsedUrlOld['host']) {
            $wpConfigVal['value'] = $parsedUrlNew['host'];
        } else {
            $wpConfigVal['inWpConfig'] = false;
            $wpConfigVal['value']      = '';

            \DUPX_NOTICE_MANAGER::getInstance()->addNextStepNotice([
                'shortMsg'    => 'WP CONFIG domains disabled.',
                'level'       => \DUPX_NOTICE_ITEM::NOTICE,
                'longMsg'     => "The " . $params[$paramKey]->getLabel() . " domain could not be set programmatically and has been disabled<br>\n",
                'longMsgMode' => \DUPX_NOTICE_ITEM::MSG_MODE_HTML,
            ], \DUPX_NOTICE_MANAGER::ADD_UNIQUE_APPEND, self::NOTICE_ID_WP_CONF_PARAM_DOMAINS_MODIFIED);
        }

        $params[$paramKey]->setValue($wpConfigVal);
    }

    /**
     * Return true if wp config key need update
     *
     * @param ParamItem[] $params      params list
     * @param string      $paramKey    param key
     * @param string      $wpConfigKey wp config key
     *
     * @return bool
     */
    protected static function wpConfigNeedsUpdate(&$params, $paramKey, $wpConfigKey): bool
    {
        if (
            InstState::isRestoreBackup($params[PrmMng::PARAM_INST_TYPE]->getValue())
        ) {
            return false;
        }

        // SKIP IF PARAM IS OVERWRITTEN
        if ($params[$paramKey]->getStatus() === ParamItem::STATUS_OVERWRITE) {
            return false;
        }

        // SKIP IF EMPTY
        $wpConfigVal = \DUPX_ArchiveConfig::getInstance()->getDefineArrayValue($wpConfigKey);
        if (strlen($wpConfigVal['value']) === 0) {
            return false;
        }

        // EMPTY IF DISABLED
        if ($wpConfigVal['inWpConfig'] == false) {
            $wpConfigVal['value'] = '';
            $params[$paramKey]->setValue($wpConfigVal);
            return false;
        }

        return true;
    }

    /**
     * Set wp config paths notices
     *
     * @return void
     */
    protected static function wpConfigPathsNotices()
    {
        $noticeManager = \DUPX_NOTICE_MANAGER::getInstance();

        // PREPEND IF EXISTS
        $noticeManager->addNextStepNotice([
            'shortMsg'    => '',
            'level'       => \DUPX_NOTICE_ITEM::NOTICE,
            'longMsg'     => "It was found that the following config paths were outside of the source site's home path (" .
                \DUPX_ArchiveConfig::getInstance()->getRealValue("originalPaths")->home . "):<br><br>\n",
            'longMsgMode' => \DUPX_NOTICE_ITEM::MSG_MODE_HTML,
        ], \DUPX_NOTICE_MANAGER::ADD_UNIQUE_PREPEND_IF_EXISTS, self::NOTICE_ID_WP_CONF_PARAM_PATHS_EMPTY);

        // APPEND IF EXISTS
        $msg  = '<br>Keeping config paths that are outside of the home path may cause malfunctions, so these settings have been disabled by default,';
        $msg .= ' but you can set them manually if necessary by switching the install mode ';
        $msg .= 'to "Advanced" and at Step 3 navigating to "Options" &gt; "WP-Config File"';

        $noticeManager->addNextStepNotice([
            'shortMsg'    => '',
            'level'       => \DUPX_NOTICE_ITEM::NOTICE,
            'longMsg'     => $msg,
            'longMsgMode' => \DUPX_NOTICE_ITEM::MSG_MODE_HTML,
        ], \DUPX_NOTICE_MANAGER::ADD_UNIQUE_APPEND_IF_EXISTS, self::NOTICE_ID_WP_CONF_PARAM_PATHS_EMPTY);

        $noticeManager->saveNotices();
    }

    /**
     * Set wp config domain notices
     *
     * @return void
     */
    protected static function wpConfigDomainNotices()
    {
        $noticeManager = \DUPX_NOTICE_MANAGER::getInstance();

        // PREPEND IF EXISTS
        $noticeManager->addNextStepNotice([
            'shortMsg'    => '',
            'level'       => \DUPX_NOTICE_ITEM::NOTICE,
            'longMsg'     => "The following config domains were disabled:<br><br>\n",
            'longMsgMode' => \DUPX_NOTICE_ITEM::MSG_MODE_HTML,
        ], \DUPX_NOTICE_MANAGER::ADD_UNIQUE_PREPEND_IF_EXISTS, self::NOTICE_ID_WP_CONF_PARAM_DOMAINS_MODIFIED);

        // APPEND IF EXISTS
        $msg  = '<br>The plugin was unable to automatically replace the domain, so the setting has been disabled by default.';
        $msg .= ' Please review them by switching the install mode to "Advanced" and at Step 3 navigating to "Options" &gt; "WP-Config File"';

        $noticeManager->addNextStepNotice([
            'shortMsg'    => '',
            'level'       => \DUPX_NOTICE_ITEM::NOTICE,
            'longMsg'     => $msg,
            'longMsgMode' => \DUPX_NOTICE_ITEM::MSG_MODE_HTML,
        ], \DUPX_NOTICE_MANAGER::ADD_UNIQUE_APPEND_IF_EXISTS, self::NOTICE_ID_WP_CONF_PARAM_DOMAINS_MODIFIED);

        $noticeManager->saveNotices();
    }

    /**
     * Returns default config value for FORCE_SSL_ADMIN depending on current site's settings
     *
     * @return array<string, mixed>
     */
    protected static function getDefaultForceSSLAdminConfig()
    {
        $forceAdminSSLConfig = \DUPX_ArchiveConfig::getInstance()->getDefineArrayValue('FORCE_SSL_ADMIN');
        if (!SnapURL::isCurrentUrlSSL() && $forceAdminSSLConfig['inWpConfig'] === true) {
            $noticeMng = \DUPX_NOTICE_MANAGER::getInstance();
            $noticeMng->addFinalReportNotice(
                [
                    'shortMsg' => "FORCE_SSL_ADMIN was enabled on none SSL",
                    'level'    => \DUPX_NOTICE_ITEM::SOFT_WARNING,
                    'longMsg'  => 'It was found that FORCE_SSL_ADMIN is enabled and you are installing on a site without SSL, ' .
                        'so that config has been disabled.',
                    'sections' => 'general',
                ],
                \DUPX_NOTICE_MANAGER::ADD_UNIQUE,
                self::NOTICE_ID_WP_CONF_FORCE_SSL_ADMIN
            );
            $noticeMng->saveNotices();
            $forceAdminSSLConfig['value'] = false;
        }
        return $forceAdminSSLConfig;
    }
}
