<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Libs\Index;

use Duplicator\Libs\Binary\BinaryFormat;
use Duplicator\Libs\Binary\BinaryIO;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapUtil;
use Generator;

/**
 * Index List class
 */
class IndexList implements IndexListInfoInterface
{
    const NAME_PREFIX      = 'index_list_';
    const NAME_SUFFIX      = '.txt';
    const NAME_HASH_LENGTH = 8;

    /**
     * The prefix format for the length of the binary data
     *
     * @var string
     */
    const PREFIX_FORMAT = 'N';

    /**
     * The end-of-line flag.
     *
     * @var string
     */
    const END_OF_LINE = "\n";

    /** @var int */
    protected int $type = -1;

    /** @var string */
    protected string $path = '';

    /** @var int */
    protected int $count = 0;

    /** @var ?resource */
    protected $handle;

    /**
     * Class constructor
     *
     * @param string $dirPath The path to the dir in which the temporary index list is created
     * @param int    $type    The list type of this index list
     *
     * @return void
     */
    public function __construct(string $dirPath, int $type)
    {
        $this->type = $type;
        if (is_dir($dirPath) === false) {
            throw new \Exception('Index list directory does not exist');
        }

        $this->path = SnapIO::trailingslashit($dirPath) . self::getNewIndexName();

        if (($this->handle = fopen($this->path, 'c+b')) === false) {
            throw new \Exception('Could not open index file for writing');
        }
    }

    /**
     * Generates a new index name.
     *
     * @return string The newly generated index name.
     */
    protected static function getNewIndexName(): string
    {
        return  self::NAME_PREFIX . date('YmdHis') . '_' . SnapUtil::generatePassword(self::NAME_HASH_LENGTH, false, false) . self::NAME_SUFFIX;
    }

    /**
     * Returns the path of the single index list file
     *
     * @return string The path
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Returns the number of items in the list
     *
     * @return int The number of items in the list
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * Returns the number of items in the list
     *
     * @param int $count The number of items in the list
     *
     * @return void
     */
    public function setCount($count): void
    {
        $this->count = $count;
    }

    /**
     * Gets the size of the index list
     *
     * @return int Size of the index list in bytes
     */
    public function getSize(): int
    {
        if (($size = filesize($this->path)) === false) {
            throw new \Exception("Couldn't get the size of the index list file.");
        }

        return $size;
    }

    /**
     * Writes a node item into the list
     *
     * @param array<int|string, mixed> $data    The data to write
     * @param BinaryFormat[]           $formats The formats of the data
     *
     * @return void
     */
    public function add(array $data, array $formats): void
    {
        if (fseek($this->handle, 0, SEEK_END) !== 0) {
            throw new \Exception("Couldn't seek to the end of the index list file.");
        }

        self::writeLine($this->handle, BinaryIO::encode($formats, $data));

        $this->count++;
    }

    /**
     * Copies all the content of the list to the handle
     *
     * @param resource $toHandle Stream to copy to
     *
     * @return void
     */
    public function copyToMain($toHandle): void
    {
        if ($this->getSize() === 0) {
            return;
        }

        if (rewind($this->handle) === false) {
            throw new \Exception("Couldn't seek to start before copy to main");
        }

        if (stream_copy_to_stream($this->handle, $toHandle) === false) {
            throw new \Exception("Couldn't copy index list contents");
        }
    }

    /**
     * Copies all the content of the list to the handle
     *
     * @param resource $fromHandle Stream to copy to
     * @param int      $offset     Offset of the list
     * @param int      $length     Length of the list
     *
     * @return void
     */
    public function copyFromMain($fromHandle, $offset, $length): void
    {
        if (ftruncate($this->handle, 0) === false) {
            throw new \Exception("Couldn't truncate index list before copying into it.");
        }

        if (rewind($this->handle) === false) {
            throw new \Exception("Couldn't rewind index list before copying into it.");
        }

        if (stream_copy_to_stream($fromHandle, $this->handle, $length, $offset) === false) {
            throw new \Exception("Couldn't copy index list contents");
        }
    }

    /**
     * Resets the index list
     *
     * @return void
     */
    public function reset(): void
    {
        $this->count = 0;

        if (ftruncate($this->handle, 0) === false) {
            throw new \Exception("Couldn't truncate index list to reset.");
        }

        if (rewind($this->handle) === false) {
            throw new \Exception("Couldn't rewind index list to reset.");
        }
    }

    /**
     * Generator method to iterate over the contents of the index list.
     *
     * @param BinaryFormat[] $formats The formats of the data
     * @param int            $seek    Seek to a specific line before iterating
     *
     * @return Generator<int, array<int|string, mixed>> The generator for iteration
     */
    public function iterate($formats, $seek = -1): Generator
    {
        return self::iterateFromHandle($this->handle, $formats, $seek);
    }

    /**
     * Generator method to iterate over the contents of the index list.
     *
     * @param resource       $handle  Optional handle to iterate over. Default is the internal handle
     * @param BinaryFormat[] $formats The formats of the data
     * @param int            $seek    Seek to a specific line before iterating
     * @param int            $start   The start position for the list. Default is 0
     * @param int            $end     The end position for the list. Default is -1 (EOF)
     *
     * @return Generator<int, array<int|string, mixed>> The generator for iteration
     */
    public static function iterateFromHandle($handle, $formats, $seek = -1, $start = 0, $end = -1): Generator
    {
        if (fseek($handle, $start) === -1) {
            throw new \Exception("Couldn't seek to iterate on index list file.");
        }

        if ($seek !== -1) {
            $i = 0;
            while ($i < $seek && self::readLine($handle) !== false) {
                $i++;
            }
        }

        while (($binary = self::readLine($handle)) !== false && ($end === -1 || ftell($handle) <= $end)) {
            yield BinaryIO::decode($formats, $binary);
        }
    }

    /**
     * Reads an item line from the handle. It is expected that the pointer is at the start of an item line,
     * otherwise an exception will be thrown.
     *
     * @param resource $handle The handle to read from
     *
     * @return string|false The binary string or false if EOF
     */
    protected static function readLine($handle)
    {
        $prefixLength = BinaryFormat::getBinaryLengthMap()[self::PREFIX_FORMAT];
        if (
            ($lengtBinary = fread($handle, $prefixLength)) === false ||
            feof($handle)
        ) {
            return false;
        }

        $length = unpack(self::PREFIX_FORMAT, $lengtBinary)[1];
        if (($result = fread($handle, $length)) === false) {
            throw new \Exception("Couldn't read index list item.");
        }

        if (fread($handle, strlen(self::END_OF_LINE)) !== self::END_OF_LINE) {
            throw new \Exception("Not an index item. End of line not found.");
        }

        return $result;
    }

    /**
     * Writes a item line to the handle
     *
     * @param resource $handle The handle to write to
     * @param string   $binary The binary string to write
     *
     * @return void
     */
    protected static function writeLine($handle, $binary): void
    {
        $line  = BinaryIO::encode([(new BinaryFormat(self::PREFIX_FORMAT))->setVariableLength()], $binary);
        $line .= self::END_OF_LINE;

        if (fwrite($handle, $line) === false) {
            throw new \Exception("Couldn't write to index list.");
        }
    }

    /**
     * Class destructor
     *
     * @return void
     */
    public function __destruct()
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }

        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }
}
