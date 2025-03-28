<?php

/**
 * Urls and paths params descriptions
 *
 * @category  Duplicator
 * @package   Installer
 * @author    Snapcreek <admin@snapcreek.com>
 * @copyright 2011-2021  Snapcreek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

namespace Duplicator\Installer\Core\Params\Descriptors;

use Duplicator\Installer\Core\InstState;
use Duplicator\Installer\Core\Params\PrmMng;
use Duplicator\Installer\Core\Params\Items\ParamItem;
use Duplicator\Installer\Core\Params\Items\ParamForm;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapJson;

/**
 * class where all parameters are initialized. Used by the param manager
 */
final class ParamDescUrlsPaths implements DescriptorInterface
{
    const INVALID_PATH_EMPTY = 'can\'t be empty';
    const INVALID_URL_EMPTY  = 'can\'t be empty';

    /**
     * Init params
     *
     * @param ParamItem[]|ParamForm[] $params params list
     *
     * @return void
     */
    public static function init(&$params)
    {
        $archive_config = \DUPX_ArchiveConfig::getInstance();
        $paths          = $archive_config->getRealValue('archivePaths');

        $oldMainPath = $paths->home;
        $newMainPath = DUPX_ROOT;

        $oldHomeUrl = rtrim($archive_config->getRealValue('homeUrl'), '/');
        $newHomeUrl = rtrim(DUPX_ROOT_URL, '/');

        $oldSiteUrl      = rtrim($archive_config->getRealValue('siteUrl'), '/');
        $oldContentUrl   = rtrim($archive_config->getRealValue('contentUrl'), '/');
        $oldUploadUrl    = rtrim($archive_config->getRealValue('uploadBaseUrl'), '/');
        $oldPluginsUrl   = rtrim($archive_config->getRealValue('pluginsUrl'), '/');
        $oldMuPluginsUrl = rtrim($archive_config->getRealValue('mupluginsUrl'), '/');

        $oldWpAbsPath       = $paths->abs;
        $oldContentPath     = $paths->wpcontent;
        $oldUploadsBasePath = $paths->uploads;
        $oldPluginsPath     = $paths->plugins;
        $oldMuPluginsPath   = $paths->muplugins;

        $defValEdit = "This default value is automatically generated.\n"
            . "Change it only if you're sure you know what you're doing!";

        $params[PrmMng::PARAM_URL_OLD] = new ParamItem(
            PrmMng::PARAM_URL_OLD,
            ParamForm::TYPE_STRING,
            ['default' => $oldHomeUrl]
        );

        $params[PrmMng::PARAM_WP_ADDON_SITES_PATHS] = new ParamItem(
            PrmMng::PARAM_WP_ADDON_SITES_PATHS,
            ParamForm::TYPE_ARRAY_STRING,
            [
                'default' => [],
            ]
        );

        $newObj                        = new ParamForm(
            PrmMng::PARAM_URL_NEW,
            ParamForm::TYPE_STRING,
            ParamForm::FORM_TYPE_TEXT,
            [// ITEM ATTRIBUTES
                'default'          => $newHomeUrl,
                'sanitizeCallback' => [
                    ParamsDescriptors::class,
                    'sanitizeUrl',
                ],
                'validateCallback' => [
                    ParamsDescriptors::class,
                    'validateUrlWithScheme',
                ],
            ],
            [// FORM ATTRIBUTES
                'label'          => 'New Site URL:',
                'status'         => function (ParamForm $param) {
                    if (
                        PrmMng::getInstance()->getValue(PrmMng::PARAM_TEMPLATE) !== \DUPX_Template::TEMPLATE_ADVANCED ||
                        InstState::isRestoreBackup() ||
                        InstState::isAddSiteOnMultisite()
                    ) {
                        return ParamForm::STATUS_INFO_ONLY;
                    } else {
                        return ParamForm::STATUS_ENABLED;
                    }
                },
                'wrapperClasses' => [
                    'revalidate-on-change',
                    'cant-be-empty',
                    'requires-db-hide',
                ],
                'subNote'        => function (ParamForm $param) {
                    $archive_config = \DUPX_ArchiveConfig::getInstance();
                    $oldHomeUrl     = rtrim($archive_config->getRealValue('homeUrl'), '/');
                    $subsiteId      = PrmMng::getInstance()->getValue(PrmMng::PARAM_SUBSITE_ID);
                    if (
                        InstState::isInstType(
                            [InstState::TYPE_STANDALONE]
                        ) &&
                        $subsiteId > 0
                    ) {
                        $subsiteObj = $archive_config->getSubsiteObjById($subsiteId);
                        $oldHomeUrl = $subsiteObj->fullHomeUrl ?? $oldHomeUrl;
                    }
                    return 'Old value: <b>' . \DUPX_U::esc_html($oldHomeUrl) . '</b>';
                },
                'postfix'        => [
                    'type'      => 'button',
                    'label'     => 'get',
                    'btnAction' => 'DUPX.getNewUrlByDomObj(this);',
                ],
            ]
        );
        $params[PrmMng::PARAM_URL_NEW] = $newObj;
        $urlNewInputId                 =  $newObj->getFormItemId();

        $params[PrmMng::PARAM_PATH_OLD] = new ParamItem(
            PrmMng::PARAM_PATH_OLD,
            ParamForm::TYPE_STRING,
            ['default' => $oldMainPath]
        );

        $newObj = new ParamForm(
            PrmMng::PARAM_PATH_NEW,
            ParamForm::TYPE_STRING,
            ParamForm::FORM_TYPE_TEXT,
            [// ITEM ATTRIBUTES
                'default'          => $newMainPath,
                'sanitizeCallback' => [
                    ParamsDescriptors::class,
                    'sanitizePath',
                ],
                'validateCallback' => function ($value, ParamItem $paramObj) {
                    if (strlen($value) == 0) {
                        $paramObj->setInvalidMessage('The new path can\'t be empty.');
                        return false;
                    }

                    // if home path is root path is necessary do a trailingslashit
                    $realPath = SnapIO::safePathTrailingslashit($value);
                    if (!is_dir($realPath)) {
                        $paramObj->setInvalidMessage(
                            'The new path must be an existing folder on the server.<br>' .
                            'It is not possible to continue the installation without first creating the folder <br>' .
                            '<b>' . $value . '</b>'
                        );
                        return false;
                    }

                    // don't check the return of chmod, if fail the installer must continue
                    SnapIO::chmod($realPath, 'u+rwx');
                    return true;
                },
            ],
            [// FORM ATTRIBUTES
                'label'          => 'New Path:',
                'status'         => function (ParamForm $param) {
                    if (
                        PrmMng::getInstance()->getValue(PrmMng::PARAM_TEMPLATE) !== \DUPX_Template::TEMPLATE_ADVANCED ||
                        InstState::isRestoreBackup() ||
                        InstState::isAddSiteOnMultisite()
                    ) {
                        return ParamForm::STATUS_INFO_ONLY;
                    } else {
                        return ParamForm::STATUS_ENABLED;
                    }
                },
                'subNote'        => 'Old value: <b>' . \DUPX_U::esc_html($oldMainPath) . '</b>',
                'wrapperClasses' => [
                    'revalidate-on-change',
                    'cant-be-empty',
                    'requires-db-hide',
                ],
            ]
        );

        $params[PrmMng::PARAM_PATH_NEW] = $newObj;
        $pathNewInputId                 =  $newObj->getFormItemId();

        $params[PrmMng::PARAM_SITE_URL_OLD] = new ParamItem(
            PrmMng::PARAM_SITE_URL_OLD,
            ParamForm::TYPE_STRING,
            ['default' => $oldSiteUrl]
        );

        $wrapClasses    = [
            'revalidate-on-change',
            'cant-be-empty',
            'auto-updatable',
            'autoupdate-enabled',
        ];
        $postfixElement = [
            'type'      => 'button',
            'label'     => 'Auto',
            'btnAction' => 'DUPX.autoUpdateToggle(this, ' . SnapJson::jsonEncode($defValEdit) . ');',
        ];

        $params[PrmMng::PARAM_SITE_URL] = new ParamForm(
            PrmMng::PARAM_SITE_URL,
            ParamForm::TYPE_STRING,
            ParamForm::FORM_TYPE_TEXT,
            [// ITEM ATTRIBUTES
                'default'          => '',
                'sanitizeCallback' => [
                    ParamsDescriptors::class,
                    'sanitizeUrl',
                ],
                'validateCallback' => [
                    ParamsDescriptors::class,
                    'validateUrlWithScheme',
                ],
            ],
            [// FORM ATTRIBUTES
                'label'          => 'WP core URL:',
                'status'         => [
                    self::class,
                    'statusFormOtherPathsUrls',
                ],
                'postfix'        => $postfixElement,
                'subNote'        => 'Old value: <b>' . \DUPX_U::esc_html($oldSiteUrl) . '</b>',
                'wrapperClasses' => $wrapClasses,
                'wrapperAttr'    => ['data-auto-update-from-input' => $urlNewInputId],
            ]
        );

        $params[PrmMng::PARAM_PATH_CONTENT_OLD] = new ParamItem(
            PrmMng::PARAM_PATH_CONTENT_OLD,
            ParamForm::TYPE_STRING,
            ['default' => $oldContentPath]
        );

        $params[PrmMng::PARAM_PATH_CONTENT_NEW] = new ParamForm(
            PrmMng::PARAM_PATH_CONTENT_NEW,
            ParamForm::TYPE_STRING,
            ParamForm::FORM_TYPE_TEXT,
            [// ITEM ATTRIBUTES
                'default'          => '',
                'sanitizeCallback' => [
                    ParamsDescriptors::class,
                    'sanitizePath',
                ],
                'validateCallback' => [
                    ParamsDescriptors::class,
                    'validatePath',
                ],
            ],
            [// FORM ATTRIBUTES
                'label'          => 'WP-content path:',
                'status'         => [
                    self::class,
                    'statusFormOtherPathsUrls',
                ],
                'postfix'        => $postfixElement,
                'subNote'        => 'Old value: <b>' . \DUPX_U::esc_html($oldContentPath) . '</b>',
                'wrapperClasses' => $wrapClasses,
                'wrapperAttr'    => ['data-auto-update-from-input' => $pathNewInputId],
            ]
        );

        $params[PrmMng::PARAM_PATH_WP_CORE_OLD] = new ParamItem(
            PrmMng::PARAM_PATH_WP_CORE_OLD,
            ParamForm::TYPE_STRING,
            ['default' => $oldWpAbsPath]
        );

        $params[PrmMng::PARAM_PATH_WP_CORE_NEW] = new ParamForm(
            PrmMng::PARAM_PATH_WP_CORE_NEW,
            ParamForm::TYPE_STRING,
            ParamForm::FORM_TYPE_TEXT,
            [// ITEM ATTRIBUTES
                'default'          => '',
                'sanitizeCallback' => [
                    ParamsDescriptors::class,
                    'sanitizePath',
                ],
                'validateCallback' => function ($value, ParamItem $paramObj) {
                    $homePath = PrmMng::getInstance()->getValue(PrmMng::PARAM_PATH_NEW);

                    if (!SnapIO::isChildPath($value, $homePath)) {
                        $paramObj->setInvalidMessage(
                            'ABSPATH have to be a equal or a child of HOMEPATH' .
                            '<pre>' .
                            'ABSPATH : ' . $value . '<br>' .
                            'HOMEPATH: ' . $homePath . '<br>' .
                            '</pre>'
                        );
                        return false;
                    }

                    return true;
                },
            ],
            [// FORM ATTRIBUTES
                'label'          => 'WP core path:',
                'status'         => [
                    self::class,
                    'statusFormOtherPathsUrls',
                ],
                'postfix'        => $postfixElement,
                'subNote'        => 'Old value: <b>' . \DUPX_U::esc_html($oldWpAbsPath) . '</b>',
                'wrapperClasses' => $wrapClasses,
                'wrapperAttr'    => ['data-auto-update-from-input' => $pathNewInputId],
            ]
        );

        $params[PrmMng::PARAM_PATH_UPLOADS_OLD] = new ParamItem(
            PrmMng::PARAM_PATH_UPLOADS_OLD,
            ParamForm::TYPE_STRING,
            ['default' => $oldUploadsBasePath]
        );

        $params[PrmMng::PARAM_PATH_UPLOADS_NEW] = new ParamForm(
            PrmMng::PARAM_PATH_UPLOADS_NEW,
            ParamForm::TYPE_STRING,
            ParamForm::FORM_TYPE_TEXT,
            [// ITEM ATTRIBUTES
                'default'          => '',
                'sanitizeCallback' => [
                    ParamsDescriptors::class,
                    'sanitizePath',
                ],
                'validateCallback' => function ($value, ParamItem $paramObj) {
                    $paramsManager = PrmMng::getInstance();

                    $result = (
                        SnapIO::isChildPath($value, $paramsManager->getValue(PrmMng::PARAM_PATH_NEW), false, false) ||
                        SnapIO::isChildPath($value, $paramsManager->getValue(PrmMng::PARAM_PATH_CONTENT_NEW), false, false)
                    );

                    if ($result == false) {
                        $paramObj->setInvalidMessage('Upload path have to be a child of wp-content path');
                    }

                    return $result;
                },
            ],
            [// FORM ATTRIBUTES
                'label'          => 'Uploads path:',
                'status'         => [
                    self::class,
                    'statusFormOtherPathsUrls',
                ],
                'postfix'        => $postfixElement,
                'subNote'        => 'Old value: <b>' . \DUPX_U::esc_html($oldUploadsBasePath) . '</b>',
                'wrapperClasses' => $wrapClasses,
                'wrapperAttr'    => ['data-auto-update-from-input' => $pathNewInputId],
            ]
        );

        $params[PrmMng::PARAM_URL_CONTENT_OLD] = new ParamItem(
            PrmMng::PARAM_URL_CONTENT_OLD,
            ParamForm::TYPE_STRING,
            ['default' => $oldContentUrl]
        );

        $params[PrmMng::PARAM_URL_CONTENT_NEW] = new ParamForm(
            PrmMng::PARAM_URL_CONTENT_NEW,
            ParamForm::TYPE_STRING,
            ParamForm::FORM_TYPE_TEXT,
            [// ITEM ATTRIBUTES
                'default'          => '',
                'sanitizeCallback' => [
                    ParamsDescriptors::class,
                    'sanitizeUrl',
                ],
                'validateCallback' => [
                    ParamsDescriptors::class,
                    'validateUrlWithScheme',
                ],
            ],
            [// FORM ATTRIBUTES
                'label'          => 'WP-content URL:',
                'status'         => [
                    self::class,
                    'statusFormOtherPathsUrls',
                ],
                'postfix'        => $postfixElement,
                'subNote'        => 'Old value: <b>' . \DUPX_U::esc_html($oldContentUrl) . '</b>',
                'wrapperClasses' => $wrapClasses,
                'wrapperAttr'    => ['data-auto-update-from-input' => $urlNewInputId],
            ]
        );

        $params[PrmMng::PARAM_URL_UPLOADS_OLD] = new ParamItem(
            PrmMng::PARAM_URL_UPLOADS_OLD,
            ParamForm::TYPE_STRING,
            ['default' => $oldUploadUrl]
        );

        $params[PrmMng::PARAM_URL_UPLOADS_NEW] = new ParamForm(
            PrmMng::PARAM_URL_UPLOADS_NEW,
            ParamForm::TYPE_STRING,
            ParamForm::FORM_TYPE_TEXT,
            [
                'default'          => '',
                'sanitizeCallback' => [
                    ParamsDescriptors::class,
                    'sanitizeUrl',
                ],
                'validateCallback' => [
                    ParamsDescriptors::class,
                    'validateUrlWithScheme',
                ],
            ],
            [// FORM ATTRIBUTES
                'label'          => 'Uploads URL:',
                'status'         => [
                    self::class,
                    'statusFormOtherPathsUrls',
                ],
                'postfix'        => $postfixElement,
                'subNote'        => 'Old value: <b>' . \DUPX_U::esc_html($oldUploadUrl) . '</b>',
                'wrapperClasses' => $wrapClasses,
                'wrapperAttr'    => ['data-auto-update-from-input' => $urlNewInputId],
            ]
        );

        $params[PrmMng::PARAM_URL_PLUGINS_OLD] = new ParamItem(
            PrmMng::PARAM_URL_PLUGINS_OLD,
            ParamForm::TYPE_STRING,
            ['default' => $oldPluginsUrl]
        );

        $params[PrmMng::PARAM_URL_PLUGINS_NEW] = new ParamForm(
            PrmMng::PARAM_URL_PLUGINS_NEW,
            ParamForm::TYPE_STRING,
            ParamForm::FORM_TYPE_TEXT,
            [// ITEM ATTRIBUTES
                'default'          => '',
                'sanitizeCallback' => [
                    ParamsDescriptors::class,
                    'sanitizeUrl',
                ],
                'validateCallback' => [
                    ParamsDescriptors::class,
                    'validateUrlWithScheme',
                ],
            ],
            [// FORM ATTRIBUTES
                'label'          => 'Plugins URL:',
                'status'         => [
                    self::class,
                    'statusFormOtherPathsUrls',
                ],
                'postfix'        => $postfixElement,
                'subNote'        => 'Old value: <b>' . \DUPX_U::esc_html($oldPluginsUrl) . '</b>',
                'wrapperClasses' => $wrapClasses,
                'wrapperAttr'    => ['data-auto-update-from-input' => $urlNewInputId],
            ]
        );

        $params[PrmMng::PARAM_PATH_PLUGINS_OLD] = new ParamItem(
            PrmMng::PARAM_PATH_PLUGINS_OLD,
            ParamForm::TYPE_STRING,
            [
                'default'          => $oldPluginsPath,
                'sanitizeCallback' => [
                    ParamsDescriptors::class,
                    'sanitizePath',
                ],
                'validateCallback' => [
                    ParamsDescriptors::class,
                    'validatePath',
                ],
            ]
        );

        $params[PrmMng::PARAM_PATH_PLUGINS_NEW] = new ParamForm(
            PrmMng::PARAM_PATH_PLUGINS_NEW,
            ParamForm::TYPE_STRING,
            ParamForm::FORM_TYPE_TEXT,
            [// ITEM ATTRIBUTES
                'default'          => '',
                'sanitizeCallback' => [
                    ParamsDescriptors::class,
                    'sanitizePath',
                ],
                'validateCallback' => [
                    ParamsDescriptors::class,
                    'validatePath',
                ],
            ],
            [// FORM ATTRIBUTES
                'label'          => 'Plugins path:',
                'status'         => [
                    self::class,
                    'statusFormOtherPathsUrls',
                ],
                'postfix'        => $postfixElement,
                'subNote'        => 'Old value: <b>' . \DUPX_U::esc_html($oldPluginsPath) . '</b>',
                'wrapperClasses' => $wrapClasses,
                'wrapperAttr'    => ['data-auto-update-from-input' => $pathNewInputId],
            ]
        );

        $params[PrmMng::PARAM_URL_MUPLUGINS_OLD] = new ParamItem(
            PrmMng::PARAM_URL_MUPLUGINS_OLD,
            ParamForm::TYPE_STRING,
            ['default' => $oldMuPluginsUrl]
        );

        $params[PrmMng::PARAM_URL_MUPLUGINS_NEW] = new ParamForm(
            PrmMng::PARAM_URL_MUPLUGINS_NEW,
            ParamForm::TYPE_STRING,
            ParamForm::FORM_TYPE_TEXT,
            [// ITEM ATTRIBUTES
                'default'          => '',
                'sanitizeCallback' => [
                    ParamsDescriptors::class,
                    'sanitizeUrl',
                ],
                'validateCallback' => [
                    ParamsDescriptors::class,
                    'validateUrlWithScheme',
                ],
            ],
            [// FORM ATTRIBUTES
                'label'          => 'MU-plugins URL:',
                'status'         => [
                    self::class,
                    'statusFormOtherPathsUrls',
                ],
                'postfix'        => $postfixElement,
                'subNote'        => 'Old value: <b>' . \DUPX_U::esc_html($oldMuPluginsUrl) . '</b>',
                'wrapperClasses' => $wrapClasses,
                'wrapperAttr'    => ['data-auto-update-from-input' => $urlNewInputId],
            ]
        );

        $params[PrmMng::PARAM_PATH_MUPLUGINS_OLD] = new ParamItem(
            PrmMng::PARAM_PATH_MUPLUGINS_OLD,
            ParamForm::TYPE_STRING,
            ['default' => $oldMuPluginsPath]
        );

        $params[PrmMng::PARAM_PATH_MUPLUGINS_NEW] = new ParamForm(
            PrmMng::PARAM_PATH_MUPLUGINS_NEW,
            ParamForm::TYPE_STRING,
            ParamForm::FORM_TYPE_TEXT,
            [// ITEM ATTRIBUTES
                'default'          => '',
                'sanitizeCallback' => [
                    ParamsDescriptors::class,
                    'sanitizePath',
                ],
                'validateCallback' => [
                    ParamsDescriptors::class,
                    'validatePath',
                ],
            ],
            [// FORM ATTRIBUTES
                'label'          => 'MU-plugins path:',
                'status'         => [
                    self::class,
                    'statusFormOtherPathsUrls',
                ],
                'postfix'        => $postfixElement,
                'subNote'        => 'Old value: <b>' . \DUPX_U::esc_html($oldMuPluginsPath) . '</b>',
                'wrapperClasses' => $wrapClasses,
                'wrapperAttr'    => ['data-auto-update-from-input' => $pathNewInputId],
            ]
        );
    }

    /**
     * Return statu form for paths and urls options
     *
     * @param ParamForm $param current param
     *
     * @return string
     */
    public static function statusFormOtherPathsUrls(ParamForm $param): string
    {
        if (
            PrmMng::getInstance()->getValue(PrmMng::PARAM_TEMPLATE) !== \DUPX_Template::TEMPLATE_ADVANCED ||
            InstState::isRestoreBackup() ||
            InstState::isAddSiteOnMultisite()
        ) {
            return ParamForm::STATUS_INFO_ONLY;
        } else {
            return ParamForm::STATUS_READONLY;
        }
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
        PrmMng::getInstance();

        $archive_config = \DUPX_ArchiveConfig::getInstance();
        $paths          = $archive_config->getRealValue('archivePaths');

        $oldMainPath = $paths->home;
        $newMainPath = $params[PrmMng::PARAM_PATH_NEW]->getValue();

        $oldHomeUrl = rtrim($archive_config->getRealValue('homeUrl'), '/');
        $newHomeUrl = $params[PrmMng::PARAM_URL_NEW]->getValue();

        $oldSiteUrl      = rtrim($archive_config->getRealValue('siteUrl'), '/');
        $oldContentUrl   = rtrim($archive_config->getRealValue('contentUrl'), '/');
        $oldUploadUrl    = rtrim($archive_config->getRealValue('uploadBaseUrl'), '/');
        $oldPluginsUrl   = rtrim($archive_config->getRealValue('pluginsUrl'), '/');
        $oldMuPluginsUrl = rtrim($archive_config->getRealValue('mupluginsUrl'), '/');

        // if empty value isn't overwritten
        if (strlen($params[PrmMng::PARAM_PATH_WP_CORE_NEW]->getValue()) == 0) {
            $newVal = \DUPX_ArchiveConfig::getNewSubString($oldMainPath, $newMainPath, $paths->abs);
            $params[PrmMng::PARAM_PATH_WP_CORE_NEW]->setValue($newVal);
        }

        // if empty value isn't overwritten
        if (strlen($params[PrmMng::PARAM_PATH_CONTENT_NEW]->getValue()) == 0) {
            $newVal = \DUPX_ArchiveConfig::getNewSubString($oldMainPath, $newMainPath, $paths->wpcontent);
            $params[PrmMng::PARAM_PATH_CONTENT_NEW]->setValue($newVal);
        }

        // if empty value isn't overwritten
        if (strlen($params[PrmMng::PARAM_PATH_UPLOADS_NEW]->getValue()) == 0) {
            $newVal = \DUPX_ArchiveConfig::getNewSubString($oldMainPath, $newMainPath, $paths->uploads);
            $params[PrmMng::PARAM_PATH_UPLOADS_NEW]->setValue($newVal);
        }

        // if empty value isn't overwritten
        if (strlen($params[PrmMng::PARAM_PATH_PLUGINS_NEW]->getValue()) == 0) {
            $newVal = \DUPX_ArchiveConfig::getNewSubString($oldMainPath, $newMainPath, $paths->plugins);
            $params[PrmMng::PARAM_PATH_PLUGINS_NEW]->setValue($newVal);
        }

        // if empty value isn't overwritten
        if (strlen($params[PrmMng::PARAM_PATH_MUPLUGINS_NEW]->getValue()) == 0) {
            $newVal = \DUPX_ArchiveConfig::getNewSubString($oldMainPath, $newMainPath, $paths->muplugins);
            $params[PrmMng::PARAM_PATH_MUPLUGINS_NEW]->setValue($newVal);
        }

        // if empty value isn't overwritten
        if (strlen($params[PrmMng::PARAM_SITE_URL]->getValue()) == 0) {
            $newVal = \DUPX_ArchiveConfig::getNewSubUrl($oldHomeUrl, $newHomeUrl, $oldSiteUrl);
            $params[PrmMng::PARAM_SITE_URL]->setValue($newVal);
        }

        // if empty value isn't overwritten
        if (strlen($params[PrmMng::PARAM_URL_CONTENT_NEW]->getValue()) == 0) {
            $newVal = \DUPX_ArchiveConfig::getNewSubUrl($oldHomeUrl, $newHomeUrl, $oldContentUrl);
            $params[PrmMng::PARAM_URL_CONTENT_NEW]->setValue($newVal);
        }

        // if empty value isn't overwritten
        if (strlen($params[PrmMng::PARAM_URL_UPLOADS_NEW]->getValue()) == 0) {
            $newVal = \DUPX_ArchiveConfig::getNewSubUrl($oldHomeUrl, $newHomeUrl, $oldUploadUrl);
            $params[PrmMng::PARAM_URL_UPLOADS_NEW]->setValue($newVal);
        }

        // if empty value isn't overwritten
        if (strlen($params[PrmMng::PARAM_URL_PLUGINS_NEW]->getValue()) == 0) {
            $newVal = \DUPX_ArchiveConfig::getNewSubUrl($oldHomeUrl, $newHomeUrl, $oldPluginsUrl);
            $params[PrmMng::PARAM_URL_PLUGINS_NEW]->setValue($newVal);
        }

        // if empty value isn't overwritten
        if (strlen($params[PrmMng::PARAM_URL_MUPLUGINS_NEW]->getValue()) == 0) {
            $newVal = \DUPX_ArchiveConfig::getNewSubUrl($oldHomeUrl, $newHomeUrl, $oldMuPluginsUrl);
            $params[PrmMng::PARAM_URL_MUPLUGINS_NEW]->setValue($newVal);
        }
    }
}
