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

defined('ABSPATH') || defined('DUPXABSPATH') || exit;

use Duplicator\Installer\Core\InstState;
use Duplicator\Installer\Core\Params\PrmMng;
use Duplicator\Installer\Models\ScanInfo;
use Duplicator\Libs\Snap\SnapIO;

class DUPX_Validation_test_disk_space extends DUPX_Validation_abstract_item
{
    /** @var int */
    private $freeSpace = 0;
    /** @var int */
    private $archiveSize = 0;
    /** @var int */
    private $extractedSize = 0;

    protected function runTest(): int
    {
        if (!function_exists('disk_free_space') || InstState::isRecoveryMode()) {
            return self::LV_SKIP;
        }

        // if home path is root path is necessary do a trailingslashit
        $realPath            = SnapIO::safePathTrailingslashit(PrmMng::getInstance()->getValue(PrmMng::PARAM_PATH_NEW));
        $this->freeSpace     = (int) @disk_free_space($realPath);
        $this->archiveSize   = DUPX_Conf_Utils::archiveExists() ? DUPX_Conf_Utils::archiveSize() : 1;
        $this->extractedSize = ScanInfo::getInstance()->getUSize();

        if ($this->freeSpace && $this->archiveSize > 0 && $this->freeSpace > ($this->extractedSize + $this->archiveSize)) {
            return self::LV_GOOD;
        } else {
            return self::LV_SOFT_WARNING;
        }
    }

    public function getTitle(): string
    {
        return 'Disk Space';
    }

    protected function swarnContent()
    {
        return dupxTplRender('parts/validation/tests/diskspace', [
            'freeSpace'     => DUPX_U::readableByteSize($this->freeSpace),
            'requiredSpace' => DUPX_U::readableByteSize($this->archiveSize + $this->extractedSize),
            'isOk'          => false,
        ], false);
    }

    protected function goodContent()
    {
        return dupxTplRender('parts/validation/tests/diskspace', [
            'freeSpace'     => DUPX_U::readableByteSize($this->freeSpace),
            'requiredSpace' => DUPX_U::readableByteSize($this->archiveSize + $this->extractedSize),
            'isOk'          => true,
        ], false);
    }
}
