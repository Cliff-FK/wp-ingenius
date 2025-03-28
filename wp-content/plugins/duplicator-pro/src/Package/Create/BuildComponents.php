<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Package\Create;

use DUP_PRO_Archive;
use DUP_PRO_Log;
use DUP_PRO_STR;
use Duplicator\Addons\ProBase\License\License;
use Duplicator\Installer\Package\PComponents;
use Duplicator\Libs\Snap\SnapWP;
use Exception;

class BuildComponents extends PComponents
{
    const COMP_ACTION_ALL    = 'all';
    const COMP_ACTION_DB     = 'database';
    const COMP_ACTION_MEDIA  = 'media';
    const COMP_ACTION_CUSTOM = 'custom';

    const COMPONENTS_ACTIONS = [
        self::COMP_ACTION_ALL,
        self::COMP_ACTION_DB,
        self::COMP_ACTION_MEDIA,
        self::COMP_ACTION_CUSTOM,
    ];

    const COMPONENTS_PLUS_ACTIONS = [
        self::COMP_ACTION_MEDIA,
        self::COMP_ACTION_CUSTOM,
    ];

    /** @var string[] */
    protected $components = [];
    /** @var array{dirs:string[],files:string[]}  */
    protected $filters = [
        'dirs'  => [],
        'files' => [],
    ];

    /**
     * Class contructor
     *
     * @param string[] $components The components to build
     */
    public function __construct($components)
    {
        $this->components = (array) $components;
        if (in_array(self::COMP_PLUGINS_ACTIVE, $this->components)) {
            $this->components = array_diff($this->components, [self::COMP_PLUGINS]);
        }

        if (in_array(self::COMP_THEMES_ACTIVE, $this->components)) {
            $this->components = array_diff($this->components, [self::COMP_THEMES]);
        }
        $this->components = array_values($this->components);

        DUP_PRO_Log::infoTrace("Components: " . implode(', ', $this->components));
        $this->generateFilters();
    }

    /**
     * Generate filter listh paths
     *
     * @return void
     */
    public function generateFilters()
    {
        $this->filters = [
            'dirs'  => [],
            'files' => [],
        ];

        if (!in_array(self::COMP_CORE, $this->components)) {
            $filters                = $this->getCoreComponentPaths();
            $this->filters['dirs']  = array_merge($this->filters['dirs'], $filters['dirs']);
            $this->filters['files'] = array_merge($this->filters['files'], $filters['files']);
        }

        if (!in_array(self::COMP_DB, $this->components)) {
            $filters                = $this->getDBComponentPaths();
            $this->filters['dirs']  = array_merge($this->filters['dirs'], $filters['dirs']);
            $this->filters['files'] = array_merge($this->filters['files'], $filters['files']);
        }

        if (in_array(self::COMP_PLUGINS_ACTIVE, $this->components)) {
            $filters                = $this->getInactivePluginsComponentPaths();
            $this->filters['dirs']  = array_merge($this->filters['dirs'], $filters['dirs']);
            $this->filters['files'] = array_merge($this->filters['files'], $filters['files']);
        } elseif (!in_array(self::COMP_PLUGINS, $this->components)) {
            $filters                = $this->getPluginsComponentPaths();
            $this->filters['dirs']  = array_merge($this->filters['dirs'], $filters['dirs']);
            $this->filters['files'] = array_merge($this->filters['files'], $filters['files']);
        }

        if (in_array(self::COMP_THEMES_ACTIVE, $this->components)) {
            $filters                = $this->getInactiveThemesComponentPaths();
            $this->filters['dirs']  = array_merge($this->filters['dirs'], $filters['dirs']);
            $this->filters['files'] = array_merge($this->filters['files'], $filters['files']);
        } elseif (!in_array(self::COMP_THEMES, $this->components)) {
            $filters                = $this->getThemesComponentPaths();
            $this->filters['dirs']  = array_merge($this->filters['dirs'], $filters['dirs']);
            $this->filters['files'] = array_merge($this->filters['files'], $filters['files']);
        }

        if (!in_array(self::COMP_UPLOADS, $this->components)) {
            $filters                = $this->getMediaComponentPaths();
            $this->filters['dirs']  = array_merge($this->filters['dirs'], $filters['dirs']);
            $this->filters['files'] = array_merge($this->filters['files'], $filters['files']);
        }

        if (!in_array(self::COMP_OTHER, $this->components)) {
            $filters                = $this->getOtherComponentPaths();
            $this->filters['dirs']  = array_merge($this->filters['dirs'], $filters['dirs']);
            $this->filters['files'] = array_merge($this->filters['files'], $filters['files']);
        }

        $this->filters['dirs']  = array_unique($this->filters['dirs']);
        $this->filters['files'] = array_unique($this->filters['files']);
    }

    /**
     * Returns a list of files path to filter
     *
     * @return string[]
     */
    public function getFiltersFiles()
    {
        return $this->filters['files'];
    }

    /**
     * Returns a list of dirs path to filter
     *
     * @return string[]
     */
    public function getFiltersDirs()
    {
        return $this->filters['dirs'];
    }

    /**
     * Returns core directories and files of the WP install
     *
     * @return array{dirs: string[], files: string[]}
     */
    private function getCoreComponentPaths(): array
    {
        $paths         = [
            'dirs'  => [],
            'files' => [],
        ];
        $absRootPaths  = SnapWP::getWpCoreFilesListInFolder();
        $paths['dirs'] = array_map(fn($item): string => DUP_PRO_Archive::getArchiveListPaths('abs') . '/' . $item, $absRootPaths['dirs']) ;

        $paths['files'] = array_map(fn($item): string => DUP_PRO_Archive::getArchiveListPaths('abs') . '/' . $item, $absRootPaths['files']) ;

        foreach (SnapWP::getWPContentCoreDirs() as $dir) {
            $paths['dirs'][] = DUP_PRO_Archive::getArchiveListPaths('wpcontent') . '/' . $dir;
        }
        return $paths;
    }

    /**
     * Returns database directories and files of the WP install
     *
     * @return array{dirs: string[], files: string[]}
     */
    private function getDBComponentPaths()
    {
        return [
            'dirs'  => [],
            'files' => [],
        ];
    }


    /**
     * Returns directories and file list of the Plugin component which includes all non-core files and folders
     * in the wp-content folder and the plugins and mu-plugins folders which can be outside of wp-content
     *
     * @return array{dirs: string[], files: string[]}
     */
    private function getPluginsComponentPaths(): array
    {
        $paths         = [
            'dirs'  => [
                DUP_PRO_Archive::getArchiveListPaths('plugins'),
                DUP_PRO_Archive::getArchiveListPaths('muplugins'),
            ],
            'files' => [],
        ];
        $excludePaths  = [
            DUP_PRO_Archive::getArchiveListPaths('themes'),
            DUP_PRO_Archive::getArchiveListPaths('uploads'),
        ];
        $wpContentPath = DUP_PRO_Archive::getArchiveListPaths('wpcontent');
        foreach (scandir($wpContentPath) as $basename) {
            $tmpPath = $wpContentPath . '/' . $basename;
            if (
                $basename === '.'
                || $basename === '..'
                || in_array($tmpPath, $excludePaths)
                || in_array($basename, SnapWP::getWPContentCoreDirs())
            ) {
                continue;
            }

            if (is_dir($tmpPath)) {
                $paths['dirs'][] = $tmpPath;
            } else {
                $paths['files'][] = $tmpPath;
            }
        }

        return $paths;
    }

    /**
     * Directory and file list path of inactive plugins
     *
     * @return array{dirs: string[], files: string[]}
     */
    private function getInactivePluginsComponentPaths(): array
    {
        $paths = [
            'dirs'  => [],
            'files' => [],
        ];

        foreach (SnapWP::getPluginsInfo(SnapWP::PLUGIN_INFO_INACTIVE) as $slug => $info) {
            if (DUP_PRO_STR::contains($slug, '/')) {
                $paths['dirs'][] = DUP_PRO_Archive::getArchiveListPaths('plugins') . '/' . dirname($slug);
            } else {
                $paths['files'][] = DUP_PRO_Archive::getArchiveListPaths('plugins') . '/' . $slug;
            }
        }
        DUP_PRO_Log::traceObject("Inactive plugins:", $paths);

        return $paths;
    }

    /**
     * Directory and file list path of themes
     *
     * @return array{dirs: string[], files: string[]}
     */
    private function getThemesComponentPaths()
    {
        return [
            'dirs'  => [DUP_PRO_Archive::getArchiveListPaths('themes')],
            'files' => [],
        ];
    }

    /**
     * Directory and file list path of inactive themes
     *
     * @return array{dirs: string[], files: string[]}
     */
    private function getInactiveThemesComponentPaths(): array
    {
        $inactiveThemes = [
            'dirs'  => [],
            'files' => [],
        ];
        foreach (SnapWP::getThemesInfo() as $info) {
            if ($info['isActive'] === true || (is_array($info['isActive']) && !empty($info['isActive']))) {
                continue;
            }

            $inactiveThemes['dirs'][] = DUP_PRO_Archive::getArchiveListPaths('themes') . '/' . $info['slug'];
        }

        return $inactiveThemes;
    }

    /**
     * Returns the paths related to the media Backup component
     *
     * @return array{dirs: string[], files: string[]}
     */
    private function getMediaComponentPaths()
    {
        return [
            'dirs'  => [DUP_PRO_Archive::getArchiveListPaths('uploads')],
            'files' => [],
        ];
    }

    /**
     * Returns file and directory and file path lists of the "Other" package component
     *
     * @return array{dirs: string[], files: string[]}
     */
    private function getOtherComponentPaths(): array
    {
        $paths = [
            'dirs'  => [],
            'files' => [],
        ];

        $absPath   = DUP_PRO_Archive::getArchiveListPaths('abs');
        $corePaths = SnapWP::getWpCoreFilesListInFolder();
        foreach (scandir($absPath) as $basename) {
            $tmpPath = $absPath . '/' . $basename;
            if (
                $basename === '.'
                || $basename === '..'
                || in_array($basename, $corePaths['dirs'])
                || in_array($basename, $corePaths['files'])
                || $tmpPath === DUP_PRO_Archive::getArchiveListPaths('wpcontent')
            ) {
                continue;
            }

            if (is_dir($tmpPath)) {
                $paths['dirs'][] = $tmpPath;
            } else {
                $paths['files'][] = $tmpPath;
            }
        }

        return $paths;
    }

    /**
     * Get label by compoentent
     *
     * @param string $component The component
     *
     * @return string
     */
    public static function getLabel($component): string
    {
        switch ($component) {
            case self::COMP_DB:
                return __('Database', 'duplicator-pro');
            case self::COMP_CORE:
                return __('Core', 'duplicator-pro');
            case self::COMP_PLUGINS:
                return __('Plugins', 'duplicator-pro');
            case self::COMP_PLUGINS_ACTIVE:
                return __('Only Active Plugins', 'duplicator-pro');
            case self::COMP_THEMES:
                return __('Themes', 'duplicator-pro');
            case self::COMP_THEMES_ACTIVE:
                return __('Only Active Themes', 'duplicator-pro');
            case self::COMP_UPLOADS:
                return __('Media', 'duplicator-pro');
            case self::COMP_OTHER:
                return __('Other', 'duplicator-pro');
            default:
                throw new Exception('Invalid component: ' . $component);
        }
    }

    /**
     * Get label by compoentent action
     *
     * @param string $componentAction The component action
     * @param bool   $icon            If true, the label will include an icon
     *
     * @return string
     */
    public static function getActionLabel($componentAction, $icon = false)
    {
        $result = '';
        if ($icon) {
            switch ($componentAction) {
                case self::COMP_ACTION_ALL:
                    $result .= '<i class="fa-solid fa-square-check"></i>';
                    break;
                case self::COMP_ACTION_DB:
                    $result .= '<i class="fa-solid fa-database"></i>';
                    break;
                case self::COMP_ACTION_MEDIA:
                    $result .= '<i class="fa-solid fa-images"></i>';
                    break;
                case self::COMP_ACTION_CUSTOM:
                    $result .= '<i class="fa-solid fa-gear"></i>';
                    break;
                default:
                    throw new Exception('Invalid component action' . $componentAction);
            }
            $result .= ' ';
        }

        switch ($componentAction) {
            case self::COMP_ACTION_ALL:
                $result .= __('Full Site', 'duplicator-pro');
                break;
            case self::COMP_ACTION_DB:
                $result .= __('Database Only', 'duplicator-pro');
                break;
            case self::COMP_ACTION_MEDIA:
                $result .= __('Media Only', 'duplicator-pro');
                break;
            case self::COMP_ACTION_CUSTOM:
                $result .= __('Custom', 'duplicator-pro');
                break;
            default:
                throw new Exception('Invalid component action' . $componentAction);
        }


        return $result;
    }

    /**
     * Get label by compoentent action
     *
     * @param string[] $components The active components
     *
     * @return string Enum of the component action (COMP_ACTION_*)
     */
    public static function getActionFromComponents($components): string
    {
        if (count($components) === 1) {
            switch ($components[0]) {
                case self::COMP_DB:
                    return self::COMP_ACTION_DB;
                case self::COMP_UPLOADS:
                    return self::COMP_ACTION_MEDIA;
                default:
                    return self::COMP_ACTION_CUSTOM;
            }
        } elseif (array_diff(self::COMPONENTS_DEFAULT, $components) === []) {
            return self::COMP_ACTION_ALL;
        }

        return self::COMP_ACTION_CUSTOM;
    }

    /**
     * Returns the Backup components from the input array
     *
     * @param string[] $input the input array $_POST, $_GET, etc.
     *
     * @return string[]
     */
    public static function getFromInput($input): array
    {
        $components = [];
        foreach (self::COMPONENTS as $component) {
            if (isset($input[$component])) {
                $components[] = $component;
            }
        }

        if (License::can(License::CAPABILITY_PACKAGE_COMPONENTS_PLUS)) {
            return $components;
        }

        if ($components === self::COMPONENTS_DEFAULT || (count($components) === 1 && $components[0] === self::COMP_DB)) {
            return $components;
        }

        return self::COMPONENTS_DEFAULT;
    }
}
