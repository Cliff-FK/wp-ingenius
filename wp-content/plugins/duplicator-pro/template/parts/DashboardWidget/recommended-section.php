<?php

/**
 * Duplicator Backup row in table Backups list
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Core\CapMng;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var array{name: string,slug: string,more: string,pro: array{file: string}} $plugin
 */
$plugin = $tplData['plugin'];

/** @var string */
$installUrl = $tplData['installUrl'];
$moreUrl    = $plugin['more'] . '?' . http_build_query([
    'utm_medium'   => 'link',
    'utm_source'   => 'duplicatorplugin',
    'utm_campaign' => 'duplicatordashboardwidget',
]);

?>
<div class="dup-section-recommended">
    <hr>
    <div class="dup-flex-content" >
        <div>
            <span class="dup-recommended-label">
                <?php esc_html_e('Recommended Plugin:', 'duplicator-pro'); ?>
            </span>
            <b><?php echo esc_html($plugin['name']); ?></b>
            -
            <span class="action-links">
                <?php if (CapMng::can('install_plugins', false) && CapMng::can('activate_plugins', false)) { ?>
                    <a href="<?php echo esc_url($installUrl); ?>"><?php esc_html_e('Install', 'duplicator-pro'); ?></a>
                <?php } ?>
                <a href="<?php echo esc_url($moreUrl); ?>" target="_blank" ><?php
                    esc_html_e('Learn More', 'duplicator-pro');
                ?></a>
            </span>
        </div>
        <div>
            <button type="button" id="dup-dash-widget-section-recommended" title="<?php esc_html_e('Dismiss recommended plugin', 'duplicator-pro'); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
    </div>
</div>
