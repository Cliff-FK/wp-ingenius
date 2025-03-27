<?php

/**
 * @package Duplicator
 */

defined("ABSPATH") or die("");

use Duplicator\Addons\ProBase\License\License;
use Duplicator\Controllers\SchedulePageController;
use Duplicator\Controllers\ToolsPageController;
use Duplicator\Core\Controllers\ControllersManager;

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 * @var DUP_PRO_Schedule_Entity $schedule
 * @var bool $blur
 */

$blur     = $tplData['blur'];
$schedule = $tplData['schedule'];

$templatesPageUrl = ToolsPageController::getInstance()->getMenuLink(ToolsPageController::L2_SLUG_TEMPLATE);

$frequency_note = __(
    'If you have a large site, it\'s recommended you schedule backups during lower traffic periods. 
    If you\'re on a shared host then be aware that running multiple schedules too close together 
    (i.e. every 10 minutes) may alert your host to a spike in system resource usage.  
    Be sure that your schedules do not overlap and give them plenty of time to run.',
    'duplicator-pro'
);

$editTemplateUrl = ControllersManager::getMenuLink(
    ControllersManager::TOOLS_SUBMENU_SLUG,
    ToolsPageController::L2_SLUG_TEMPLATE,
    null,
    [ControllersManager::QUERY_STRING_INNER_PAGE => 'edit']
);

$min_frequency      = 0;
$max_frequency      = (
    License::can(License::CAPABILITY_SHEDULE_HOURLY) ?
    DUP_PRO_Schedule_Entity::REPEAT_HOURLY :
    DUP_PRO_Schedule_Entity::REPEAT_MONTHLY
);
$frequencyUpgradMsg = sprintf(
    __(
        'Hourly frequency isn\'t available at the <b>%1$s</b> license level.',
        'duplicator-pro'
    ),
    License::getLicenseToString()
) .
' <b>' .
sprintf(
    _x(
        'To enable this option %1$supgrade%2$s the License.',
        '%1$s and %2$s represents the opening and closing HTML tags for an anchor or link',
        'duplicator-pro'
    ),
    '<a href="' . esc_url(License::getUpsellURL()) . '" target="_blank">',
    '</a>'
) .
'</b>';
?>
<form 
    id="dup-schedule-form" 
    class="dup-monitored-form <?php echo ($blur ? 'dup-mock-blur' : ''); ?>"
    action="<?php echo esc_url(ControllersManager::getCurrentLink()); ?>" 
    method="post" 
    data-parsley-ui-enabled="true"
>
    <?php $tplMng->render('admin_pages/schedules/parts/edit_toolbar'); ?>
    <?php $tplData['actions'][SchedulePageController::ACTION_EDIT_SAVE]->getActionNonceFileds(); ?>
    <input type="hidden" name="schedule_id" value="<?php echo esc_attr($schedule->getId()); ?>">

    <!-- ===============================
    SETTINGS -->
    <table class="form-table">
        <tr valign="top">
            <th scope="row"><label><?php esc_html_e('Schedule Name', 'duplicator-pro'); ?></label></th>
            <td>
                <input 
                    type="text" 
                    id="schedule-name" 
                    class="width-medium"
                    name="name" 
                    value="<?php echo esc_attr($schedule->name); ?>" 
                    required data-parsley-group="standard" 
                    autocomplete="off"
                >
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <label><?php esc_html_e('Backup Template', 'duplicator-pro'); ?></label>
            </th>
            <td>
                <div class="schedule-template display-flex">
                    <div>
                        <select id="schedule-template-selector" name="template_id" class="width-medium margin-bottom-0" required>
                            <?php
                            $templates = DUP_PRO_Package_Template_Entity::getAllWithoutManualMode();
                            if (count($templates) == 0) {
                                $no_templates = __('No Templates Found', 'duplicator-pro');
                                printf('<option value="">%1$s</option>', esc_html($no_templates));
                            } else {
                                echo "<option value='' selected='true'>" . esc_html__("&lt;Choose A Template&gt;", 'duplicator-pro') . "</option>";
                                foreach ($templates as $template) {
                                    ?>
                                    <option 
                                        <?php selected($schedule->template_id, $template->getId()); ?> 
                                        value="<?php echo (int) $template->getId(); ?>"
                                    >
                                        <?php echo esc_html($template->name); ?>
                                    </option>
                                    <?php
                                }
                            }
                            ?>
                        </select>  
                        <div class="margin-bottom-1" > 
                            <small>
                                <a href="<?php echo esc_url($templatesPageUrl); ?>" target="edit-template">
                                    [<?php esc_attr_e("Show All Templates", 'duplicator-pro') ?>]
                                </a>
                            </small>
                        </div>
                    </div>
                    <div>
                        <a 
                            id="schedule-template-edit-btn" 
                            href="javascript:void(0)" 
                            onclick="DupPro.Schedule.EditTemplate()" 
                            style="display:none" 
                            class="pack-temp-btns button hollow secondary small margin-bottom-0" 
                            title="<?php esc_attr_e("Edit Selected Template", 'duplicator-pro') ?>"
                        >
                            <i class="far fa-edit"></i>
                        </a>
                        <a 
                            id="schedule-template-add-btn" 
                            href="<?php echo esc_url($editTemplateUrl); ?>" 
                            class="pack-temp-btns button hollow secondary small margin-bottom-0" 
                            title="<?php esc_attr_e("Add New Template", 'duplicator-pro') ?>" 
                            target="edit-template"
                        >
                            <i class="far fa-plus-square"></i>
                        </a>
                        <a 
                            id="schedule-template-sync-btn" 
                            href="javascript:window.location.reload()" 
                            class="pack-temp-btns button hollow secondary small margin-bottom-0" 
                            title="<?php esc_attr_e("Refresh Template List", 'duplicator-pro') ?>"
                        >
                            <i class="fas fa-sync-alt"></i>
                        </a>
                        &nbsp;
                        <i 
                            class="fa-solid fa-question-circle fa-sm dark-gray-color" 
                            data-tooltip-title="<?php esc_attr_e("Template Details", 'duplicator-pro'); ?>" 
                            data-tooltip="<?php
                            esc_attr_e(
                                'The template specifies which files and database tables should be included in the archive.<br/><br/>
                                Choose from an existing template or create a new one by clicking the "Add New Template" button. 
                                To edit a template, select it and then click the "Edit Selected Template" button.',
                                'duplicator-pro'
                            );
                            ?>">
                        </i>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <th scope="row"><label><?php esc_html_e('Storage', 'duplicator-pro'); ?></label></th>
            <td>
                <!-- ===============================
                STORAGE -->
                <?php $tplMng->render(
                    'parts/storage/select_list',
                    [
                        'selectedStorageIds' => $schedule->storage_ids,
                        'showAddNew'         => false,
                        'recoveryPointMsg'   => true,
                    ]
                ); ?>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php esc_html_e("Repeats", 'duplicator-pro'); ?></label></th>
            <td>
                <select 
                    id="change-mode" 
                    name="repeat_type" 
                    class="width-small margin-0"
                    onchange="DupPro.Schedule.ChangeMode()" 
                    data-parsley-range='<?php printf('[%1$s, %2$s]', (int) $min_frequency, (int) $max_frequency); ?>' 
                    data-parsley-error-message="<?php echo esc_attr($frequencyUpgradMsg); ?>"
                >
                    <option 
                        value="<?php echo (int) DUP_PRO_Schedule_Entity::REPEAT_HOURLY; ?>"
                        <?php selected($schedule->repeat_type, DUP_PRO_Schedule_Entity::REPEAT_HOURLY) ?> 
                    >
                        <?php esc_html_e("Hourly", 'duplicator-pro'); ?>
                    </option>
                    <option 
                        value="<?php echo (int) DUP_PRO_Schedule_Entity::REPEAT_DAILY; ?>"
                        <?php selected($schedule->repeat_type, DUP_PRO_Schedule_Entity::REPEAT_DAILY) ?> 
                    >
                        <?php esc_html_e("Daily", 'duplicator-pro'); ?>
                    </option>
                    <option 
                        value="<?php echo (int) DUP_PRO_Schedule_Entity::REPEAT_WEEKLY; ?>"
                        <?php selected($schedule->repeat_type, DUP_PRO_Schedule_Entity::REPEAT_WEEKLY) ?> 
                    >
                        <?php esc_html_e("Weekly", 'duplicator-pro'); ?>
                    </option>
                    <option 
                        value="<?php echo (int) DUP_PRO_Schedule_Entity::REPEAT_MONTHLY; ?>"
                        <?php selected($schedule->repeat_type, DUP_PRO_Schedule_Entity::REPEAT_MONTHLY) ?> 
                    >
                        <?php esc_html_e("Monthly", 'duplicator-pro'); ?>
                    </option>
                </select>
            </td>
        </tr>
        <tr>
            <th></th>
            <td style="padding-top:0px; padding-bottom:10px;">
                <!-- ===============================
            DAILY -->
                <div id="repeat-hourly-area" class="repeater-area">
                    <?php
                    esc_html_e('Every', 'duplicator-pro');
                    $hour_intervals = [
                        1,
                        2,
                        4,
                        6,
                        12,
                    ];
                    ?>

                    <select name="_run_every_hours" class="width-tiny inline-block margin-0" data-parsley-ui-enabled="false">
                        <?php foreach ($hour_intervals as $hour_interval) { ?>
                            <option <?php selected($hour_interval, (int) $schedule->run_every); ?> value="<?php echo (int) $hour_interval; ?>">
                                <?php echo (int) $hour_interval; ?>
                            </option>
                        <?php } ?>
                    </select>
                    <?php
                        esc_html_e('hours', 'duplicator-pro');
                        $tipContent = __('Backup will build every x hours starting at 00:00.', 'duplicator-pro') . '<br/><br/>' . $frequency_note;
                    ?>
                    <i 
                        class="fa-solid fa-question-circle fa-sm dark-gray-color" 
                        data-tooltip-title="<?php esc_attr_e("Frequency Note", 'duplicator-pro'); ?>" 
                        data-tooltip="<?php echo esc_attr($tipContent); ?>"
                    >
                    </i>
                    <br />
                </div>

                <!-- ===============================
            DAILY -->
                <div id="repeat-daily-area" class="repeater-area">
                    <?php esc_html_e('Every', 'duplicator-pro'); ?>
                    <select name="_run_every_days" class="width-tiny inline-block margin-0" data-parsley-ui-enabled="false">
                        <?php for ($i = 1; $i < 30; $i++) { ?>
                            <option <?php selected($i, (int) $schedule->run_every); ?> value="<?php echo (int) $i; ?>">
                                <?php echo (int) $i; ?>
                            </option>
                        <?php } ?>
                    </select>
                    <?php esc_html_e('days', 'duplicator-pro'); ?>
                    <i 
                        class="fa-solid fa-question-circle fa-sm dark-gray-color" 
                        data-tooltip-title="<?php esc_attr_e("Frequency Note", 'duplicator-pro'); ?>" 
                        data-tooltip="<?php echo esc_attr($frequency_note) ?>"
                    >
                    </i>
                    <br />
                </div>

                <!-- ===============================
                WEEKLY -->
                <div id="repeat-weekly-area" class="repeater-area">
                    <!-- RSR Cron does not support counting by week - just days and months so removing (for now?)-->
                    <div class="weekday-div">
                        <input 
                            <?php checked($schedule->is_day_set('mon')); ?> 
                            value="mon" name="weekday[]" 
                            type="checkbox" 
                            id="repeat-weekly-mon"
                            data-parsley-group="weekly" required data-parsley-class-handler="#repeat-weekly-area"
                            data-parsley-error-message="<?php esc_attr_e('At least one day must be checked.', 'duplicator-pro'); ?>"
                            data-parsley-no-focus data-parsley-errors-container="#weekday-errors" 
                        >
                        <label for="repeat-weekly-mon"><?php esc_html_e('Monday', 'duplicator-pro'); ?></label>
                    </div>
                    <div class="weekday-div">
                        <input <?php checked($schedule->is_day_set('tue')); ?> value="tue" name="weekday[]" type="checkbox" id="repeat-weekly-tue" />
                        <label for="repeat-weekly-tue"><?php esc_html_e('Tuesday', 'duplicator-pro'); ?></label>
                    </div>
                    <div class="weekday-div">
                        <input <?php checked($schedule->is_day_set('wed')); ?> value="wed" name="weekday[]" type="checkbox" id="repeat-weekly-wed" />
                        <label for="repeat-weekly-wed"><?php esc_html_e('Wednesday', 'duplicator-pro'); ?></label>
                    </div>
                    <div class="weekday-div">
                        <input <?php checked($schedule->is_day_set('thu')); ?> value="thu" name="weekday[]" type="checkbox" id="repeat-weekly-thu" />
                        <label for="repeat-weekly-thu"><?php esc_html_e('Thursday', 'duplicator-pro'); ?></label>
                    </div>
                    <div class="weekday-div" style="clear:both">
                        <input <?php checked($schedule->is_day_set('fri')); ?> value="fri" name="weekday[]" type="checkbox" id="repeat-weekly-fri" />
                        <label for="repeat-weekly-fri"><?php esc_html_e('Friday', 'duplicator-pro'); ?></label>
                    </div>
                    <div class="weekday-div">
                        <input <?php checked($schedule->is_day_set('sat')); ?> value="sat" name="weekday[]" type="checkbox" id="repeat-weekly-sat" />
                        <label for="repeat-weekly-sat"><?php esc_html_e('Saturday', 'duplicator-pro'); ?></label>
                    </div>
                    <div class="weekday-div">
                        <input <?php checked($schedule->is_day_set('sun')); ?> value="sun" name="weekday[]" type="checkbox" id="repeat-weekly-sun" />
                        <label for="repeat-weekly-sun"><?php esc_html_e('Sunday', 'duplicator-pro'); ?></label>
                    </div>
                </div>
                <div style="padding-top:3px; clear:both;" id="weekday-errors"></div>

                <!-- ===============================
                MONTHLY -->
                <div id="repeat-monthly-area" class="repeater-area">
                    <?php esc_html_e('Day', 'duplicator-pro'); ?>&nbsp;
                    <select name="day_of_month" class="width-tiny inline-block margin-0" >
                        <?php for ($i = 1; $i <= 31; $i++) { ?>
                            <option <?php selected($i, $schedule->day_of_month); ?> value="<?php echo (int) $i; ?>">
                                <?php echo (int) $i; ?>
                            </option>
                        <?php } ?>
                    </select>&nbsp;
                    <?php esc_html_e('of every', 'duplicator-pro'); ?>&nbsp;
                    <select name="_run_every_months" data-parsley-ui-enabled="false" class="width-tiny inline-block margin-0" >
                        <?php for ($i = 1; $i <= 12; $i++) { ?>
                            <option <?php selected($i, $schedule->run_every); ?> value="<?php echo (int) $i; ?>">
                                <?php echo (int) $i; ?>
                            </option>
                        <?php } ?>
                    </select>&nbsp;
                    <?php esc_html_e('month(s)', 'duplicator-pro'); ?>
                </div>
            </td>
        </tr>

        <tr valign="top" id="start-time-row">
            <th scope="row"><label><?php esc_html_e('Start Time', 'duplicator-pro'); ?></label></th>
            <td>
                <select name="_start_time" class="width-small inline-displa margin-0" >
                    <?php
                    $start_hour = $schedule->get_start_time_piece(0);
                    $start_min  = $schedule->get_start_time_piece(1);
                    $mins       = 0;

                    // Add setting to use 24 hour vs AM/PM
                    // the interval for hours is '1'
                    for ($hours = 0; $hours < 24; $hours++) {
                        ?>
                        <option <?php selected($hours, $start_hour); ?> value="<?php echo (int) $hours; ?>">
                            <?php printf('%02d:%02d', (int) $hours, (int) $mins); ?>
                        </option>
                    <?php } ?>
                </select>

                <i class="dpro-edit-info">
                    <?php esc_html_e("Current Server Time Stamp is", 'duplicator-pro'); ?>&nbsp;
                    <?php echo esc_html(date_i18n('Y-m-d H:i:s')); ?>
                </i>
                <div class="margin-bottom-1"></div>
            </td>
        </tr>
        <tr>
            <td>
            </td>
            <td>
                <p class="description margin-bottom-1 width-xlarge" >
                    <?php
                    printf(
                        esc_html_x(
                            '%1$sNote:%2$s Schedules require web site traffic in order to start a build. 
                            If you set a start time of 06:00 daily but do not get any traffic till 10:00 then the build will not start until 10:00. 
                            If you have low traffic consider setting up a cron job to periodically hit your site.',
                            '%1$s and %2$s represent opening and closing bold tags',
                            'duplicator-pro'
                        ),
                        '<b>',
                        '</b>'
                    );
                    ?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php esc_html_e('Recovery Status', 'duplicator-pro'); ?></label></th>
            <td class="dup-recovery-template">
                <div class="margin-bottom-1" >
                    <?php
                    if (($template = $schedule->getTemplate()) !== false) {
                        $schedule->recoveableHtmlInfo();
                    } else {
                        esc_html_e('Unavailable', 'duplicator-pro');
                        ?>
                        <i class="fa-solid fa-question-circle fa-sm dark-gray-color"
                        data-tooltip-title="<?php esc_attr_e("Recovery Status", 'duplicator-pro'); ?>"
                        data-tooltip="<?php esc_attr_e('Status is unavailable. Please save the schedule to view recovery status', 'duplicator-pro');
                        ?>"></i>
                    <?php } ?>
                </div>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="schedule-active"><?php esc_html_e('Activated', 'duplicator-pro'); ?></label></th>
            <td>
                <input name="_active" id="schedule-active" type="checkbox" <?php checked($schedule->active); ?> class="margin-0" >
                <label for="schedule-active"><?php esc_html_e('Enable This Schedule', 'duplicator-pro'); ?></label><br />
                <i class="dpro-edit-info"> <?php esc_html_e('When checked this schedule will run', 'duplicator-pro'); ?></i>
            </td>
        </tr>
    </table><br />
    <button 
        id="dup-pro-save-schedule" 
        class="button primary small" 
        type="submit"
    >
        <?php esc_html_e('Save Schedule', 'duplicator-pro'); ?>
    </button>
</form>

<script>
    jQuery(document).ready(function ($) {
        DupPro.Schedule.ChangeMode = function () {
            var mode = $("#change-mode option:selected").val();
            var animate = 400;
            $('#repeat-hourly-area, #repeat-daily-area, #repeat-weekly-area, #repeat-monthly-area').hide();
            n = $("#repeat-weekly-area input:checked").length;

            if (n == 0) {
                // Hack so parsely will ignore weekly if it isnt selected
                $('#repeat-weekly-mon').prop("checked", true);
            }

            switch (mode) {
                case "0":
                    $('#repeat-daily-area').show(animate);
                    $('#start-time-row').show(animate);
                    break;
                case "1":
                    $('#repeat-weekly-area').show(animate);
                    $('#start-time-row').show(animate);
                    break;
                case "2":
                    $('#repeat-monthly-area').show(animate);
                    $('#start-time-row').show(animate);
                    break;
                case "3":
                    $('#repeat-hourly-area').show(animate);
                    $('#start-time-row').hide(animate);
                    break;

            }
        }
        
        DupPro.Schedule.EditTemplate = function () {
            var templateID = $('#schedule-template-selector').val();
            var url = <?php echo wp_json_encode($editTemplateUrl); ?> + '&package_template_id=' + templateID;
            window.open(url, 'edit-template');
        };

        DupPro.Schedule.ToggleTemplateEditBtn = function () {
            $('#schedule-template-edit-btn, #schedule-template-add-btn, #schedule-template-sync-btn').hide();
            if ($("#schedule-template-selector").val() > 0) {
                $('#schedule-template-edit-btn').show();
            } else {
                $('#schedule-template-add-btn, #schedule-template-sync-btn').show();
            }
        }

        //INIT
        $('#dup-schedule-form').parsley({
            excluded: ':disabled'
        });

        $("#repeat-daily-date, #repeat-daily-on-date").datepicker({
            showOn: "both",
            buttonText: "<i class='fa fa-calendar'></i>"
        });
        DupPro.Schedule.ChangeMode();
        jQuery('#schedule-name').focus().select();
        DupPro.Schedule.ToggleTemplateEditBtn();
        $("#schedule-template-selector").change(function () {
            DupPro.Schedule.ToggleTemplateEditBtn()
        });
        
    });
</script>
