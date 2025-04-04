<?php

/**
 *
 * @package templates/default
 */

defined('ABSPATH') || defined('DUPXABSPATH') || exit;

dupxTplRender('pages-parts/head/header-main', [
    'htmlTitle'       => 'Step <span class="step">1</span> of 2: Deployment ' .
        '<div class="sub-header">This step will extract the archive file, install & update the database.</div>',
    'showSwitchView'  => true,
    'showHeaderLinks' => false,
]);
