<?php

namespace Duplicator\Models;

use Duplicator\Core\Models\AbstractEntitySingleton;
use Exception;

class DynamicGlobalEntity extends AbstractEntitySingleton
{
    /**
     * @var array<string,scalar> Entity data
     */
    protected $data = [];

    /**
     * Class constructor
     */
    protected function __construct()
    {
    }

    /**
     * Retrieve the value of a key
     *
     * @param string      $key     Option name
     * @param null|scalar $default Default value to return if the key doesn't exist
     *
     * @return null|scalar
     */
    public function getVal(string $key, $default = null)
    {
        return isset($this->data[$key]) ? $this->data[$key] : $default;
    }

    /**
     * Get a value as integer
     *
     * @param string $key     Option name
     * @param int    $default Default value to return if the key doesn't exist
     *
     * @return int
     */
    public function getValInt($key, $default = 0): int
    {
        $value = $this->getVal($key, $default);
        return (int) $value;
    }

    /**
     * Set option value
     *
     * @param string      $key   Option name
     * @param null|scalar $value Option value
     * @param bool        $save  Save on DB
     *
     * @return bool
     */
    public function setVal(string $key, $value = null, bool $save = false): bool
    {
        if (strlen($key) == 0) {
            throw new Exception('Invalid key');
        }
        $this->data[$key] = $value;
        return ($save ? $this->save() : true);
    }

    /**
     * Set an integer value
     *
     * @param string $key   Option name
     * @param int    $value Option value
     * @param bool   $save  If true the entity is saved
     *
     * @return void
     */
    public function setValInt(string $key, int $value = 0, bool $save = false)
    {
        $this->setVal($key, (int) $value, $save);
    }

    /**
     * Delete option value
     *
     * @param string $key  Option name
     * @param bool   $save Save on DB
     *
     * @return bool
     */
    public function removeVal(string $key, bool $save = false): bool
    {
        if (!isset($this->data[$key])) {
            return true;
        }

        unset($this->data[$key]);
        return ($save ? $this->save() : true);
    }

    /**
     * @return string
     */
    public static function getType(): string
    {
        return 'Dynamic_Entity';
    }
}
