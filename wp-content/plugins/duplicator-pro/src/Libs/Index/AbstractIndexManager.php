<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Libs\Index;

use Duplicator\Libs\Binary\AbstractBinaryEncodable;
use Generator;

/**
 * The index manager is a class to create, write, read the index file of duplicator.
 *
 * @template T of AbstractBinaryEncodable
 */
abstract class AbstractIndexManager
{
    /** @var string */
    protected string $path = '';

    /** @var ?resource */
    protected $handle;

    /** @var IndexHeader */
    protected $header;

    /** @var bool */
    protected bool $isOpen = false;

    /** @var array<int, IndexList> */
    protected array $indexLists = [];

    /**
     * Constructor
     *
     * @param string $path    Path to the index file
     * @param string $version The version of the index file
     *
     * @return void
     */
    public function __construct($path, $version = '')
    {
        $this->path = $path;
        IndexHeader::setListTypes(static::getListTypes());
        if (!@file_exists($path) || @filesize($path) === 0) {
            $this->header = new IndexHeader($version);
            return;
        }

        if (($binary = @fread($this->getHandle(), IndexHeader::getBinarySize())) === false) {
            throw new \Exception('Error reading index file');
        }

        $this->header = IndexHeader::fromBinary($binary);
        if ($this->header->getVersion() !== static::getVersion()) {
            throw new \Exception('Version mismatch in index file.');
        }
    }

    /**
     * Returns the handle of the index file
     *
     * @return resource
     */
    protected function getHandle()
    {
        if ($this->handle !== null) {
            return $this->handle;
        }

        if ($this->path === '') {
            throw new \Exception('Path is not set.');
        }

        if (($this->handle = @fopen($this->path, 'c+b')) === false) {
            throw new \Exception('Error opening index file.');
        }

        return $this->handle;
    }

    /**
    * Returns the version of the index manager class
    *
    * @return string
    */
    abstract protected static function getVersion(): string;

    /**
     * Returns the list types
     *
     * @return int[] The list types
     */
    abstract protected static function getListTypes(): array;

   /**
    * Returns the class name of the items the list is going to store
    *
    * @return class-string<T>
    */
    abstract protected function getItemClass(): string;

    /**
     * Adds a scan node into the list
     *
     * @param int $listType List type
     * @param T   $node     Node to add
     *
     * @return void
     */
    public function add(int $listType, AbstractBinaryEncodable $node): void
    {
        $this->open();
        $data = $this->beforeWrite($node->getBinaryValues());
        $this->indexLists[$listType]->add($data, $node->getBinaryFormats());
    }

    /**
     * Modify the data before writing
     *
     * @param array<string|int, mixed> $data The data to write
     *
     * @return array<string|int, mixed> The modified data
     */
    protected function beforeWrite(array $data)
    {
        return $data;
    }

    /**
     * Iterate of a specific list type.
     *
     * @param int $listType List type
     * @param int $seek     The number of the item to seek to
     *
     * @return Generator<int, T> The generator for iteration
     */
    public function iterate(int $listType, $seek = -1): Generator
    {
        $itemClass = $this->getItemClass();
        $formats   = $itemClass::getBinaryFormats();
        if ($this->isOpen) {
            $iterator = $this->indexLists[$listType]->iterate($formats, $seek);
        } else {
            $start    = $this->header->getListStart($listType);
            $end      = $this->header->getListEnd($listType);
            $iterator = IndexList::iterateFromHandle($this->getHandle(), $formats, $seek, $start, $end);
        }

        foreach ($iterator as $data) {
            yield $itemClass::objectFromData($data);
        }
    }

    /**
     * Returns the number of items in a specific list type
     *
     * @param int $listType List type
     *
     * @return int The number of items
     */
    public function getCount(int $listType): int
    {
        if ($this->isOpen) {
            return $this->indexLists[$listType]->getCount();
        }

        return $this->header->getListCount($listType);
    }

    /**
     * Split index file into index lists
     *
     * @return void
     */
    protected function open(): void
    {
        if ($this->isOpen) {
            return;
        }

        $header = $this->header;
        foreach (static::getListTypes() as $listType) {
            $this->indexLists[$listType] = new IndexList(dirname($this->path), $listType);
            $this->indexLists[$listType]->setCount($this->header->getListCount($listType));
            if ($header->getListSize($listType) !== 0) {
                $this->indexLists[$listType]->copyFromMain($this->getHandle(), $header->getListStart($listType), $header->getListSize($listType));
            }
        }

        $this->isOpen = true;
    }

    /**
     * Merges the index files into the main index file
     *
     * @return void
     */
    public function save(): void
    {
        if (!$this->isOpen) {
            if (!file_exists($this->path) && !touch($this->path)) {
                throw new \Exception("Couldn't create index file.");
            }

            return;
        }

        $this->truncate();
        $this->header->update($this->indexLists);
        if (@fwrite($this->getHandle(), $this->header->toBinary()) === false) {
            throw new \Exception("Couldn't write header to index file.");
        }

        foreach ($this->indexLists as $indexList) {
            $indexList->copyToMain($this->getHandle());
        }
    }

    /**
     * Reset the index manager
     *
     * @return void
     */
    public function reset(): void
    {
        $this->isOpen = false;
        $this->truncate();
        $this->header->reset();
        foreach ($this->indexLists as $indexList) {
            $indexList->reset();
        }
    }

    /**
     * Truncate the index file
     *
     * @return void
     */
    protected function truncate(): void
    {
        if (@ftruncate($this->getHandle(), 0) === false) {
            throw new \Exception("Couldn't truncate index before closing.");
        }

        if (@rewind($this->getHandle()) === false) {
            throw new \Exception("Couldn't rewind index before closing.");
        }
    }

    /**
     * Destructor
     *
     * @return void
     */
    public function __destruct()
    {
        if (is_resource($this->handle)) {
            @fclose($this->handle);
        }
    }
}
