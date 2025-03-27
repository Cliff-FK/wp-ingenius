<?php

/*
 * Copyright 2014 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not
 * use this file except in compliance with the License. You may obtain a copy of
 * the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations under
 * the License.
 */
namespace VendorDuplicator\Google\Service\Drive;

class FileList extends \VendorDuplicator\Google\Collection
{
    protected $collection_key = 'files';
    protected $filesType = DriveFile::class;
    protected $filesDataType = 'array';
    /**
     * @var bool
     */
    public $incompleteSearch;
    /**
     * @var string
     */
    public $kind;
    /**
     * @var string
     */
    public $nextPageToken;
    /**
     * @param DriveFile[]
     */
    public function setFiles($files)
    {
        $this->files = $files;
    }
    /**
     * @return DriveFile[]
     */
    public function getFiles()
    {
        return $this->files;
    }
    /**
     * @param bool
     */
    public function setIncompleteSearch($incompleteSearch)
    {
        $this->incompleteSearch = $incompleteSearch;
    }
    /**
     * @return bool
     */
    public function getIncompleteSearch()
    {
        return $this->incompleteSearch;
    }
    /**
     * @param string
     */
    public function setKind($kind)
    {
        $this->kind = $kind;
    }
    /**
     * @return string
     */
    public function getKind()
    {
        return $this->kind;
    }
    /**
     * @param string
     */
    public function setNextPageToken($nextPageToken)
    {
        $this->nextPageToken = $nextPageToken;
    }
    /**
     * @return string
     */
    public function getNextPageToken()
    {
        return $this->nextPageToken;
    }
}
// Adding a class alias for backwards compatibility with the previous class name.
class_alias(FileList::class, 'VendorDuplicator\Google_Service_Drive_FileList');
