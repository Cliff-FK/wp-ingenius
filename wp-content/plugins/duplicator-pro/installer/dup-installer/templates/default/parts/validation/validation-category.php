<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

defined('ABSPATH') || defined('DUPXABSPATH') || exit;

/**
 * Variables
 *
 * @var string $category
 * @var string $title
 */

$vManager = DUPX_Validation_manager::getInstance();
$tests    = $vManager->getTestsCategory($category);
?>
<div class="category-wrapper" >
    <div class="header" >
        <div class="category-title" >
            <?php echo DUPX_U::esc_html($title); ?>
            <span class="status-badge right <?php echo $vManager->getCagegoryBadge($category); ?>"></span>
        </div>
    </div>
    <div class="category-content" >
        <?php
        foreach ($tests as $test) {
            dupxTplRender('parts/validation/validation-test', ['test' => $test]);
        }
        ?>
    </div>
</div>
