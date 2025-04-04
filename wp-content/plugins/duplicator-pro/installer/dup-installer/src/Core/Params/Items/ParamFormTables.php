<?php

/**
 * @package   Duplicator\Installer
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Installer\Core\Params\Items;

use Duplicator\Installer\Utils\Log\Log;
use Duplicator\Libs\Snap\SnapUtil;

/**
 * this class handles the entire block selection block.
 */
class ParamFormTables extends ParamForm
{
    const TYPE_ARRAY_TABLES          = 'arraytbl';
    const FORM_TYPE_TABLES_SELECT    = 'tablessel';
    const TABLE_ITEM_POSTFIX         = '_item';
    const TABLE_NAME_POSTFIX_TNAME   = '_tname';
    const TABLE_NAME_POSTFIX_EXTRACT = '_extract';
    const TABLE_NAME_POSTFIX_REPLACE = '_replace';

    /**
     * Class constructor
     *
     * @param string               $name     param identifier
     * @param string               $type     Enum: TYPE_STRING | TYPE_ARRAY_STRING | ...
     * @param string               $formType FORM_TYPE_HIDDEN | FORM_TYPE_TEXT | ...
     * @param array<string, mixed> $attr     list of attributes
     * @param array<string, mixed> $formAttr list of form attributes
     */
    public function __construct($name, $type, $formType, array $attr = [], array $formAttr = [])
    {
        if ($type != self::TYPE_ARRAY_TABLES) {
            throw new \Exception('the type must be ' . self::TYPE_ARRAY_TABLES);
        }

        if ($formType != self::FORM_TYPE_TABLES_SELECT) {
            throw new \Exception('the form type must be ' . self::FORM_TYPE_TABLES_SELECT);
        }
        parent::__construct($name, $type, $formType, $attr, $formAttr);
    }

    /**
     * Render HTML
     *
     * @return void
     */
    protected function htmlItem()
    {
        if ($this->formType == self::FORM_TYPE_TABLES_SELECT) {
            $this->tablesSelectHtml();
        } else {
            parent::htmlItem();
        }
    }

    /**
     * Render tables selector HTML
     *
     * @return void
     */
    protected function tablesSelectHtml()
    {
        $tables = \DUPX_DB_Tables::getInstance();
        $value  = $this->getInputValue();
        ?>
        <table id="plugins_list_table_selector" class="list_table_selector list-import-upt-tables">
            <thead>
                <tr>
                    <td class="name"></td>
                    <td class="info toggle-all">
                        Toggle All
                    </td>
                    <td class="action">
                        <span title="Check all Extract" class="checkbox-switch">
                            <input class="select-all-import" checked type="checkbox" >
                            <span class="slider"></span>
                        </span>
                    </td>
                    <td class="action">
                        <span title="Check all Replace" class="checkbox-switch">
                            <input class="select-all-replace" checked type="checkbox">
                            <span class="slider"></span>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th class="name">Original&nbsp;Name</th>
                    <th class="info">New&nbsp;Name</th>
                    <th class="action">Import</th>
                    <th class="action">Update</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $index = 0;
                foreach ($value as $name => $tableVals) {
                    $this->tableHtmlItem($tableVals, $tables->getTableObjByName($name), $index);
                    $index++;
                }
                ?>
            </tbody>
            <tfoot>
                <tr>
                    <th class="name">Original&nbsp;Name</th>
                    <th class="info">New&nbsp;Name</th>
                    <th class="action">Import</th>
                    <th class="action">Update</th>
                </tr>
            </tfoot>
        </table>
        <?php
    }

    /**
     * Renter tables items selector
     *
     * @param array<string, array{name: string, extract: bool, replace: bool}> $vals     form values
     * @param \DUPX_DB_Table_item                                              $tableOjb table object
     * @param integer                                                          $index    infex of current item
     *
     * @return void
     */
    protected function tableHtmlItem($vals, \DUPX_DB_Table_item $tableOjb, $index)
    {
        $itemClasses          = [
            'table-item',
            $this->getFormItemId() . self::TABLE_ITEM_POSTFIX,
        ];
        $hiddenNameAttrs      = [
            'id'    => $this->getFormItemId() . self::TABLE_NAME_POSTFIX_TNAME . '_' . $index,
            'type'  => 'hidden',
            'name'  => $this->getName() . '[]',
            'class' => $this->getFormItemId() . self::TABLE_NAME_POSTFIX_TNAME,
            'value' => $tableOjb->getOriginalName(),
        ];
        $extractCheckboxAttrs = [
            'id'    => $this->getFormItemId() . self::TABLE_NAME_POSTFIX_EXTRACT . '_' . $index,
            'name'  => $this->getName() . self::TABLE_NAME_POSTFIX_EXTRACT . '[]',
            'class' => $this->getFormItemId() . self::TABLE_NAME_POSTFIX_EXTRACT,
            'value' => 1,
        ];
        $replaceCheckboxAttrs = [
            'id'    => $this->getFormItemId() . self::TABLE_NAME_POSTFIX_REPLACE . '_' . $index,
            'name'  => $this->getName() . self::TABLE_NAME_POSTFIX_REPLACE . '[]',
            'class' => $this->getFormItemId() . self::TABLE_NAME_POSTFIX_REPLACE,
            'value' => 1,
        ];

        if ($tableOjb->canBeExctracted()) {
            if ($vals['extract']) {
                $extractCheckboxAttrs['checked'] = '';
            }

            if ($vals['replace']) {
                $replaceCheckboxAttrs['checked'] = '';
            }
        } else {
            $itemClasses[]                    = 'no-display';
            $extractCheckboxAttrs['disabled'] = '';
            $replaceCheckboxAttrs['disabled'] = '';
        }

        if ($this->isDisabled() || $this->isReadonly()) {
            $extractCheckboxAttrs['disabled'] = '';
            $replaceCheckboxAttrs['disabled'] = '';

            $skipSendValue = true;
        } else {
            $skipSendValue = false;
        }
        ?>
        <tr class="<?php echo implode(' ', $itemClasses); ?>" >
            <td class="name" >
                <span class="table-name" ><?php echo \DUPX_U::esc_html($tableOjb->getOriginalName()); ?></span><br>
                Rows: <b><?php echo $tableOjb->getRows(); ?></b> Size: <b><?php echo $tableOjb->getSize(true); ?></b>
            </td>
            <td class="info" >
                <span class="table-name" ><b><?php echo \DUPX_U::esc_html($tableOjb->getNewName()); ?></b></span><br>
                &nbsp;
            </td>
            <td class="action extract" >
                <?php
                if (!$skipSendValue) {
                    // if is disabled or readonly don't senta tables nme so params isn't updated
                    ?>
                    <input <?php echo \DUPX_U_Html::arrayAttrToHtml($hiddenNameAttrs); ?> >
                    <?php
                }
                \DUPX_U_Html::checkboxSwitch(
                    $extractCheckboxAttrs,
                    ['title' => 'Extract in database']
                );
        ?>            
            </td>
            <td class="action replace" >
                <?php
                \DUPX_U_Html::checkboxSwitch(
                    $replaceCheckboxAttrs,
                    ['title' => 'Apply replace engine at URLs and paths in database']
                );
                ?> 
            </td>
        </tr>
        <?php
    }

    /**
     * Check if value is valid
     *
     * @param mixed $value         value
     * @param mixed $validateValue variable passed by reference. Updated to validated value in the case, the value is a valid value.
     *
     * @return bool true if is a valid value for this object
     */
    public function isValid($value, &$validateValue = null): bool
    {
        $validateValue = (array) $value;

        $availableTables = \DUPX_DB_Tables::getInstance()->getTablesNames();
        $validateTables  = array_keys($validateValue);

        // all tables in list have to exist in  available tables
        foreach ($validateValue as $table => $tableValues) {
            if (!in_array($table, $availableTables)) {
                Log::info('INVALID ' . $table . ' ISN\'T IN AVAILABLE LIST: ' . Log::v2str($availableTables));
                return false;
            }
        }

        // all tables abaliable have to exists in list
        foreach ($availableTables as $avaibleTable) {
            if (!in_array($avaibleTable, $validateTables)) {
                Log::info('AVAILABLE ' . $avaibleTable . ' ISN\'T IN PARAM LIST TABLE');
                return false;
            }
        }

        return true;
    }

    /**
     * Appli filter to value input
     *
     * @param array<string, mixed> $superObject query string values
     *
     * @return array<string, array{name: string, extract: bool, replace: bool}>
     */
    public function getValueFilter($superObject): array
    {
        $result = [];

        if (($tables = json_decode($superObject[$this->getName()])) == false) {
            throw new \Exception('Invalid json string');
        }

        foreach ($tables as $table) {
            $table = (array) $table;
            if ($table['extract'] == false) {
                // replace can't be true if extract if false
                $table['replace'] = false;
            }
            $result[$table['name']] = $table;
        }

        return $result;
    }

    /**
     * Return sanitized value
     *
     * @param mixed $value value input
     *
     * @return array<string, array{name: string, extract: bool,replace: bool}>
     */
    public function getSanitizeValue($value): array
    {
        $newValues      = (array) $value;
        $sanitizeValues = [];

        foreach ($newValues as $key => $newValue) {
            $sanitizedKey = SnapUtil::sanitizeNSCharsNewlineTrim($key);
            $newValue     = (array) $newValue;

            $sanitizedNewValue            = self::getParamItemValueFromData();
            $sanitizedNewValue['name']    = isset($newValue['name']) ? SnapUtil::sanitizeNSCharsNewlineTrim($newValue['name']) : '';
            $sanitizedNewValue['extract'] = isset($newValue['extract']) && filter_var($newValue['extract'], FILTER_VALIDATE_BOOLEAN);
            $sanitizedNewValue['replace'] = isset($newValue['replace']) && filter_var($newValue['replace'], FILTER_VALIDATE_BOOLEAN);

            $sanitizeValues[$sanitizedKey] = $sanitizedNewValue;
        }
        return $sanitizeValues;
    }

    /**
     * Get default type attributes
     *
     * @param string $type param value type
     *
     * @return array<string, mixed>
     */
    protected static function getDefaultAttrForType($type): array
    {
        $attrs = parent::getDefaultAttrForType($type);
        if ($type == self::TYPE_ARRAY_TABLES) {
            $attrs['default'] = [];
        }

        return $attrs;
    }

    /**
     * Get default form attributes
     *
     * @param string $formType form type
     *
     * @return array<string, mixed>
     */
    protected static function getDefaultAttrForFormType($formType): array
    {
        $attrs = parent::getDefaultAttrForFormType($formType);
        if ($formType == self::FORM_TYPE_TABLES_SELECT) {
            $attrs['wrapperContainerTag'] = 'div';
            $attrs['inputContainerTag']   = 'div';
        }
        return $attrs;
    }

    /**
     * Return param item from data
     *
     * @param string $name    table name
     * @param bool   $extract extract
     * @param bool   $replace replace
     *
     * @return array{name: string, extract: bool, replace: bool}
     */
    public static function getParamItemValueFromData($name = '', $extract = false, $replace = false)
    {
        return [
            'name'    => $name,
            'extract' => $extract,
            'replace' => $replace,
        ];
    }
}
