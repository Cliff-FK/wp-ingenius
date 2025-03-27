<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

defined("ABSPATH") or die("");

use Duplicator\Addons\DropboxAddon\Models\DropboxStorage;
use Duplicator\Core\Views\TplMng;

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 * @var DUP_PRO_Package $package
 */
$package = $tplData['package'];
$global  = DUP_PRO_Global_Entity::getInstance();

global $wp_version;
?>
<!-- ================================================================
SETUP
================================================================ -->
<div class="details-title">
    <i class="fas fa-tasks fa-sm fa-fw"></i> <?php esc_html_e("Setup", 'duplicator-pro'); ?>
    <div class="dup-more-details">
        <a href="site-health.php" target="_blank" title="<?php esc_attr_e('Site Health', 'duplicator-pro'); ?>">
            <i class="fas fa-file-medical-alt"></i>
        </a>
    </div>
</div>

<!-- ======================
SYSTEM SETTINGS -->
<?php TplMng::getInstance()->render(
    'admin_pages/packages/scan/items/setup/system',
    ['hasDropbox' => $package->contains_storage_type(DropboxStorage::getSType())]
); ?>
<!-- ======================
WP SETTINGS -->
<?php TplMng::getInstance()->render(
    'admin_pages/packages/scan/items/setup/wordpress',
    ['wpVersion' => $wp_version]
); ?>

<!-- ======================
Restore only Backup -->
<?php TplMng::getInstance()->render('admin_pages/packages/scan/items/setup/restore'); ?>
