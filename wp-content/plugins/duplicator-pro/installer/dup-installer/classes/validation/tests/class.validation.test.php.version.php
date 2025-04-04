<?php

/**
 * Validation object
 *
 * Standard: PSR-2
 *
 * @link http://www.php-fig.org/psr/psr-2 Full Documentation
 *
 * @package SC\DUPX\U
 */

use Duplicator\Installer\Core\InstState;

defined('ABSPATH') || defined('DUPXABSPATH') || exit;

class DUPX_Validation_test_php_version extends DUPX_Validation_abstract_item
{
    protected function runTest(): int
    {
        $archiveConfig = DUPX_ArchiveConfig::getInstance();
        if ($archiveConfig->isDBOnly()) {
            return self::LV_GOOD;
        }

        // compare only major version ex 5 and 7 not 5.6 and 5.5
        if (intval($archiveConfig->version_php) === intval(phpversion())) {
            return self::LV_GOOD;
        } elseif (InstState::isImportFromBackendMode()) {
            return self::LV_HARD_WARNING;
        } else {
            return self::LV_SOFT_WARNING;
        }
    }

    public function getTitle(): string
    {
        return 'PHP Version Mismatch';
    }

    protected function swarnContent()
    {
        return dupxTplRender('parts/validation/tests/php-version', [
            'fromPhp' => DUPX_ArchiveConfig::getInstance()->version_php,
            'toPhp'   => phpversion(),
            'isOk'    => false,
        ], false);
    }

    protected function hwarnContent()
    {
        return dupxTplRender('parts/validation/tests/php-version', [
            'fromPhp' => DUPX_ArchiveConfig::getInstance()->version_php,
            'toPhp'   => phpversion(),
            'isOk'    => false,
        ], false);
    }

    protected function goodContent()
    {
        return dupxTplRender('parts/validation/tests/php-version', [
            'fromPhp' => DUPX_ArchiveConfig::getInstance()->version_php,
            'toPhp'   => phpversion(),
            'isOk'    => true,
        ], false);
    }
}
