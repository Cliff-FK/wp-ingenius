<?php

namespace Duplicator\Libs\Index;

interface IndexListInfoInterface
{
    /**
     * Returns the number of items in the list
     *
     * @return int The number of items in the list
     */
    public function getCount(): int;

    /**
     * Gets the size of the index list
     *
     * @return int Size of the index list in bytes
     */
    public function getSize(): int;
}
