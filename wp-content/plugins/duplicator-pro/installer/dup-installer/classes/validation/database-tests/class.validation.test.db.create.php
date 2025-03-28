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

use Duplicator\Installer\Core\Params\PrmMng;

class DUPX_Validation_test_db_create extends DUPX_Validation_abstract_item
{
    /**
     *
     * @var bool
     */
    protected $alreadyExists = false;

    /**
     *
     * @var string
     */
    protected $errorMessage = '';

    protected function runTest(): int
    {
        if (
            DUPX_Validation_database_service::getInstance()->skipDatabaseTests() ||
            PrmMng::getInstance()->getValue(PrmMng::PARAM_DB_ACTION) !== DUPX_DBInstall::DBACTION_CREATE
        ) {
            return self::LV_SKIP;
        }

        // already exists test
        if (DUPX_Validation_database_service::getInstance()->databaseExists()) {
            $this->errorMessage  = 'Database already exists';
            $this->alreadyExists = true;
            return self::LV_FAIL;
        }

        if (DUPX_Validation_database_service::getInstance()->createDatabase($this->errorMessage) === false) {
            return self::LV_FAIL;
        }

        return self::LV_PASS;
    }

    public function getTitle(): string
    {
        return 'Create New Database';
    }

    protected function failContent()
    {
        return dupxTplRender('parts/validation/database-tests/db-create', [
            'isOk'          => false,
            'alreadyExists' => $this->alreadyExists,
            'errorMessage'  => $this->errorMessage,
            'isCpanel'      => (PrmMng::getInstance()->getValue(PrmMng::PARAM_DB_VIEW_MODE) === 'cpnl'),
            'dbname'        => PrmMng::getInstance()->getValue(PrmMng::PARAM_DB_NAME),
        ], false);
    }

    protected function passContent()
    {
        return dupxTplRender('parts/validation/database-tests/db-create', [
            'isOk'          => true,
            'alreadyExists' => $this->alreadyExists,
            'errorMessage'  => $this->errorMessage,
            'isCpanel'      => (PrmMng::getInstance()->getValue(PrmMng::PARAM_DB_VIEW_MODE) === 'cpnl'),
            'dbname'        => PrmMng::getInstance()->getValue(PrmMng::PARAM_DB_NAME),
        ], false);
    }
}
