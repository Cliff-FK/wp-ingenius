<?php

/**
 * @package Duplicator
 */

defined("ABSPATH") or die("");

use Duplicator\Core\Views\TplMng;
use Duplicator\Models\Storages\AbstractStorageEntity;

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 * @var DUP_PRO_Schedule_Entity $schedule
 */


?>
<tr>
    <td colspan="3">
        <div class="maroon">
            <?php esc_html_e('Error reading storages', 'duplicator-pro'); ?>
        </div>
    </td>
</tr>