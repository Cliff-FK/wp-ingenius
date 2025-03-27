<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Libs\Index;

use Duplicator\Libs\Binary\AbstractBinaryEncodable;
use Duplicator\Libs\Binary\BinaryFormat;

/**
 * IndexHeader class.
 *
 * The index file starts with the header which has information about the current version of the index
 * and the start and end positions of each list type. Like the rest of the index, it is written in binary format.
 */
final class IndexHeader extends AbstractBinaryEncodable
{
    /** @var string */
    protected string $version = '';

    /** @var array<int, int[]> */
    protected array $positions = [];

    /** @var int[] */
    protected static array $listTypes = [];

    /**
     * Constructor
     *
     * @param string            $version   The version of the index file
     * @param array<int, int[]> $positions The start and end positions of each list type
     *
     * @return void
     */
    public function __construct(string $version = '', array $positions = [])
    {
        $this->initPositions();
        $this->version = $version;
        if (!empty($positions)) {
            $this->positions = $positions;
        }
    }

    /**
     * Initialize the position list. At this point,
     * all lists are empty to starts and ends at the header position
     *
     * @return void
     */
    protected function initPositions(): void
    {
        foreach (static::getListTypes() as $listType => $className) {
            $this->positions[$listType] = [
                self::getBinarySize(),
                self::getBinarySize(),
                0,
            ];
        }
    }

    /**
     * Updates the header with the new list positions. If an Index List for a type is not providied,
     * it will be asumed that it has not been modified.
     *
     * @param array<int, IndexListInfoInterface> $indexLists An array of IndexLists to update
     *
     * @return void
     */
    public function update(array $indexLists): void
    {
        $lastEndPos = self::getBinarySize();
        foreach ($this->positions as $listType => $positions) {
            $this->positions[$listType][0] = $lastEndPos;
            $this->positions[$listType][1] = $lastEndPos + $indexLists[$listType]->getSize();
            $this->positions[$listType][2] = $indexLists[$listType]->getCount();
            $lastEndPos                    = $this->positions[$listType][1];
        }
    }

    /**
     * Returns the version of the index file
     *
     * @return string The version
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Returns the start position for the given list type
     *
     * @param int $listType The list type
     *
     * @return int the start position
     */
    public function getListStart(int $listType): int
    {
        return $this->positions[$listType][0];
    }

    /**
     * Returns the end position for the given list type
     *
     * @param int $listType The list type
     *
     * @return int the start position
     */
    public function getListEnd(int $listType): int
    {
        return $this->positions[$listType][1];
    }

    /**
     * Returns the end position for the given list type
     *
     * @param int $listType The list type
     *
     * @return int the start position
     */
    public function getListSize(int $listType): int
    {
        return $this->positions[$listType][1] - $this->positions[$listType][0];
    }

    /**
     * Returns the number of items in the list
     *
     * @param int $listType The list type
     *
     * @return int The number of items in the list
     */
    public function getListCount(int $listType): int
    {
        return $this->positions[$listType][2];
    }

    /**
     * Resets the list positions to 0
     *
     * @return void
     */
    public function reset(): void
    {
        foreach ($this->positions as $listType => $positions) {
            $this->positions[$listType][0] = 0;
            $this->positions[$listType][1] = 0;
            $this->positions[$listType][2] = 0;
        }
    }

    /**
     * Returns the list types.
     *
     * @return int[] The list types
     */
    public static function getListTypes(): array
    {
        return static::$listTypes;
    }

    /**
     * Set the list types before creating an instance of the class.
     *
     * @param int[] $listTypes The list types
     *
     * @return void
     */
    public static function setListTypes(array $listTypes): void
    {
        static::$listTypes = $listTypes;
    }

    /**
     * Returns an object with the values of the binary data. the keys are going to match the format lables.
     *
     * @param array<int|string, mixed> $binaryData The binary data
     *
     * @return static
     */
    public static function objectFromData(array $binaryData)
    {
        $versionData  = array_slice($binaryData, 0, 3);
        $positionData = array_slice($binaryData, 3);
        $version      = implode('.', $versionData);
        $positions    = [];

        for ($i = 0; $i < count(static::getListTypes()); $i++) {
            $positions[$positionData[$i * 4]] = [
                $positionData[$i * 4 + 1],
                $positionData[$i * 4 + 2],
                $positionData[$i * 4 + 3],
            ];
        }

        return new static($version, $positions);
    }

    /**
     * Returns the values to write in binary format
     *
     * @return array<int|string, mixed>
     */
    public function getBinaryValues(): array
    {
        $result = [];
        $result = array_merge($result, explode('.', $this->version));

        foreach ($this->positions as $listType => $positions) {
            $result = array_merge($result, [$listType, $positions[0], $positions[1], $positions[2]]);
        }

        return $result;
    }

    /**
     * Get the binary format of the list items
     *
     * @return BinaryFormat[] The array of binary formats
     */
    public static function getBinaryFormats(): array
    {
        // Version 3 x C
        $formatStr = 'CCC';

        // Each list type is a C and 2 x L for start and end
        $formatStr .= str_repeat('CNNN', count(static::getListTypes()));

        return BinaryFormat::createFromFormat($formatStr);
    }


    /**
     * Returns the binary size of the header once written
     *
     * @return int
     */
    public static function getBinarySize(): int
    {
        static $size = null;
        if ($size !== null) {
            return $size;
        }

        $size = 0;
        foreach (self::getBinaryFormats() as $format) {
            if ($format->isVariableLength()) {
                throw new \Exception('Variable length format in header');
            }

            $size += $format->getSize();
        }

        return $size;
    }
}
