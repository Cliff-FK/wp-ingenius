<?php

/**
 * Class that collects the functions of initial checks on the requirements to run the plugin
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Installer\Core\Addons;

use Duplicator\Installer\Core\Hooks\HooksMng;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Installer\Utils\Log\Log;

final class InstAddonsManager
{
    /** @var ?self */
    private static $instance;
    /** @var InstAbstractAddonCore[] */
    private $addons = [];
    /** @var InstAbstractAddonCore[] */
    private $enabledAddons = [];

    /**
     *
     * @return self
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Inizialize addons
     */
    private function __construct()
    {
        $this->addons = self::getAddonListFromFolder();
    }

    /**
     * inizialize all abaiblae addons
     *
     * @return void
     */
    public function inizializeAddons()
    {
        foreach ($this->addons as $addon) {
            if ($addon->canEnable() && $addon->hasDependencies()) {
                $this->enabledAddons[] = $addon->getSlug();
                $addon->init();
                Log::info('ADDON ' . $addon->getAddonFile() . ' ENABLED', Log::LV_DETAILED);
            } else {
                Log::info('CAN\'T ENABLE ADDON ' . $addon->getSlug());
            }
        }
        HooksMng::getInstance()->doAction('duplicator_addons_loaded');
    }

    /**
     *
     * @return string[]
     */
    public function getAvailableAddons(): array
    {
        $result = [];
        foreach ($this->addons as $addon) {
            $result[] = $addon->getSlug();
        }

        return $result;
    }

    /**
     *
     * @return InstAbstractAddonCore[]
     */
    public function getEnabledAddons()
    {
        return $this->enabledAddons;
    }

    /**
     * return addons folder
     *
     * @return string
     */
    public static function getAddonsPath()
    {
        return DUPX_INIT . '/addons';
    }

    /**
     *
     * @return InstAbstractAddonCore[]
     */
    private static function getAddonListFromFolder(): array
    {
        $addonList = [];

        $checkDir = SnapIO::trailingslashit(self::getAddonsPath());

        if (!is_dir($checkDir)) {
            return [];
        }

        if (($dh = opendir($checkDir)) == false) {
            return [];
        }

        while (($elem = readdir($dh)) !== false) {
            if ($elem === '.' || $elem === '..') {
                continue;
            }

            $fullPath       = $checkDir . $elem;
            $addonMainFile  = false;
            $addonMainClass = '';

            if (!is_dir($fullPath)) {
                continue;
            }

            if (($addonDh = opendir($fullPath)) == false) {
                continue;
            }

            while (($addonElem = readdir($addonDh)) !== false) {
                if ($addonElem === '.' || $addonElem === '..') {
                    continue;
                }
                $info = pathinfo($fullPath . '/' . $addonElem);

                if (strcasecmp($elem, $info['filename']) === 0) {
                    $addonMainFile  = $checkDir . $elem . '/' . $addonElem;
                    $addonMainClass = '\\Duplicator\\Installer\\Addons\\' . $info['filename'] . '\\' . $info['filename'];
                    break;
                }
            }

            if (empty($addonMainFile)) {
                continue;
            }

            try {
                if (!is_subclass_of($addonMainClass, \Duplicator\Installer\Core\Addons\InstAbstractAddonCore::class)) {
                    continue;
                }
            } catch (\Exception $e) {
                Log::info('Addon file ' . $addonMainFile . ' exists but not countain addon main core class, Exception: ' . $e->getMessage());
                continue;
            } catch (\Error $e) {
                Log::info('Addon file ' . $addonMainFile . ' exists but generate an error, Exception: ' . $e->getMessage());
                continue;
            }

            $addonObj                        = $addonMainClass::getInstance();
            $addonList[$addonObj->getSlug()] = $addonObj;
        }
        closedir($dh);

        return $addonList;
    }
}
