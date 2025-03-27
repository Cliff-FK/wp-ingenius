<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Libs\Index;

use Duplicator\Libs\Snap\SnapIO;
use Generator;

/**
 * FileIndexManager class.
 *
 * @extends AbstractIndexManager<FileNodeInfo>
 */
class FileIndexManager extends AbstractIndexManager
{
    public const VERSION = '0.0.1';

    /** @var int[] */
    public const LIST_TYPES = [
        self::LIST_TYPE_FILES,
        self::LIST_TYPE_DIRS,
        self::LIST_TYPE_INSTALLER,
        self::LIST_TYPE_DELETE,
    ];

    public const LIST_TYPE_FILES     = 0;
    public const LIST_TYPE_DIRS      = 1;
    public const LIST_TYPE_INSTALLER = 2;
    public const LIST_TYPE_DELETE    = 3;

    /** @var string root path for mapping */
    protected static string $rootPath = '';
    /** @var string new root path for mapping */
    protected static string $newRootPath = '';

    /**
     * Constructor
     *
     * @param string $path Path to the index file
     *
     * @return void
     */
    public function __construct($path)
    {
        parent::__construct($path, self::VERSION);
    }


    /**
     * Find the node by path
     *
     * @param int    $type The list type
     * @param string $path The path
     *
     * @return ?FileNodeInfo The node or null
     */
    public function findByPath($type, $path): ?FileNodeInfo
    {
        foreach ($this->iterate($type) as $node) {
            if ($node->getPath() === $path) {
                return $node;
            }
        }

        return null;
    }

    /**
     * Returns an array of the paths
     *
     * @param int    $type   The list type
     * @param string $prefix The prefix
     *
     * @return string[]
     */
    public function getPathArray($type, $prefix = ''): array
    {
        $pathArray = [];
        $prefix    = (strlen($prefix) > 0 ? SnapIO::trailingslashit($prefix) : '');
        foreach ($this->iterate($type) as $node) {
            $pathArray[] = $prefix . $node->getPath();
        }

        return $pathArray;
    }

    /**
     * Iterates of the paths
     *
     * @param int    $type   The list type
     * @param string $prefix The prefix
     *
     * @return Generator<int, string>
     */
    public function iteratePaths($type, $prefix = ''): Generator
    {
        $prefix = (strlen($prefix) > 0 ? SnapIO::trailingslashit($prefix) : '');
        foreach ($this->iterate($type) as $node) {
            yield $prefix . $node->getPath();
        }
    }

    /**
     * Get the list types
     *
     * @return int[] The list types
     */
    protected static function getListTypes(): array
    {
        return self::LIST_TYPES;
    }

    /**
     * Get the list item class
     *
     * @return class-string<FileNodeInfo>
     */
    protected function getItemClass(): string
    {
        return FileNodeInfo::class;
    }

    /**
     * Returns the version of the index manager class
     *
     * @return string
     */
    protected static function getVersion(): string
    {
        return self::VERSION;
    }

    /**
     * Set root path. If new root is not set, the root path will be removed on saving.
     *
     * @param string $root    Root path
     * @param string $newRoot New root path
     *
     * @return void
     */
    public static function setRootPathMap(string $root = '', string $newRoot = ''): void
    {
        self::$rootPath    = $root === '' ? '' : SnapIO::trailingslashit($root);
        self::$newRootPath = $newRoot === '' ? '' : SnapIO::trailingslashit($newRoot);
    }

    /**
     * Modify the data before writing to always save the correct relative path in the archive.
     *
     * @param array<int|string, mixed> $data The data to write
     *
     * @return array<int|string, mixed> The modified data
     */
    protected function beforeWrite(array $data): array
    {
        if (
            self::$rootPath !== '' &&
            isset($data['path']) &&
            ($relativePath = SnapIO::getRelativePath($data['path'], self::$rootPath)) !== false
        ) {
            $data['path'] = self::$newRootPath . $relativePath;
        }

        return $data;
    }
}
