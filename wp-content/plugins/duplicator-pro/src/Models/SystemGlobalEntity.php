<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Models;

use Duplicator\Core\Models\AbstractEntitySingleton;
use Duplicator\Models\RecommendedFix;
use VendorDuplicator\Amk\JsonSerialize\JsonSerialize;

/**
 * System global entity
 * DUP_PRO_System_Global_Entity
 */
class SystemGlobalEntity extends AbstractEntitySingleton
{
    /** @var RecommendedFix[] */
    public $recommended_fixes = [];
    /** @var bool */
    public $schedule_failed = false;
    /** @var int */
    public $package_check_ts = 0;

    /**
     * Class constructor
     */
    protected function __construct()
    {
    }

    /**
     * Will be called, automatically, when Serialize
     *
     * @return array<string,mixed>
     */
    public function __serialize() // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.NewMagicMethods.__serializeFound
    {
        return JsonSerialize::serializeToData($this, JsonSerialize::JSON_SKIP_MAGIC_METHODS | JsonSerialize::JSON_SKIP_CLASS_NAME);
    }

    /**
     * Called after json decode
     *
     * @return void
     */
    public function __wakeup()
    {
        foreach ($this->recommended_fixes as $index => $data) {
            $this->recommended_fixes[$index] = RecommendedFix::objectFromData($data); // @phpstan-ignore-line
        }
    }

    /**
     * Return entity type identifier
     *
     * @return string
     */
    public static function getType(): string
    {
        return 'DUP_PRO_System_Global_Entity';
    }

    /**
     * Add recommended quick fix
     *
     * @param string  $error_text          error text
     * @param string  $fix_text            text to add
     * @param mixed[] $javascript_callback javascript callback
     *
     * @return bool True on success, or false on error.
     */
    public function addQuickFix($error_text, $fix_text, $javascript_callback)
    {
        if ($this->fixExists($fix_text) === false) {
            $id                        = str_shuffle(substr(uniqid('', true), 0, 14) . mt_rand(0, 1000000));
            $fix                       = new RecommendedFix();
            $fix->recommended_fix_type = RecommendedFix::TYPE_FIX;
            $fix->error_text           = $error_text;
            $fix->parameter1           = $fix_text;
            $fix->parameter2           = $javascript_callback;
            $fix->id                   = $id;
            array_push($this->recommended_fixes, $fix);
            return $this->save();
        }

        return true;
    }

    /**
     * Add recommended text
     *
     * @param string $error_text error text
     * @param string $fix_text   text to add
     *
     * @return bool True on success, or false on error.
     */
    public function addTextFix($error_text, $fix_text)
    {
        if ($this->fixExists($fix_text) === false) {
            $fix                       = new RecommendedFix();
            $fix->recommended_fix_type = RecommendedFix::TYPE_TEXT;
            $fix->error_text           = $error_text;
            $fix->parameter1           = $fix_text;
            array_push($this->recommended_fixes, $fix);
            return $this->save();
        }

        return true;
    }

    /**
     * Remove quick fix by id
     *
     * @param string $id fix id
     *
     * @return bool true on success, false on failure
     */
    public function removeFixById($id)
    {
        foreach ($this->recommended_fixes as $key => $fix) {
            if ($fix->id === $id) {
                unset($this->recommended_fixes[$key]);
                $this->recommended_fixes = array_values($this->recommended_fixes);
                return $this->save();
            }
        }
        return true;
    }

    /**
     * Search recommended fix by content
     *
     * @param string $search search string
     *
     * @return bool true found, false not found
     */
    private function fixExists($search): bool
    {
        foreach ($this->recommended_fixes as $fix) {
            if (strcmp($fix->parameter1, $search) == 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Clear recommended fixes
     *
     * @return bool true on success, false on failure
     */
    public function clearFixes()
    {
        $this->recommended_fixes = [];
        return $this->save();
    }
}
