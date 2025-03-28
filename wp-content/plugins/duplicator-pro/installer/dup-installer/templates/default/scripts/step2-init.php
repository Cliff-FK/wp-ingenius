<?php

/**
 *
 * @package templates/default
 */

defined('ABSPATH') || defined('DUPXABSPATH') || exit;

use Duplicator\Installer\Core\Security;
use Duplicator\Installer\Core\Params\PrmMng;
use Duplicator\Installer\Utils\SecureCsrf;
use Duplicator\Libs\Snap\SnapJson;

$paramsManager = PrmMng::getInstance();

$nextStepPrams = [
    PrmMng::PARAM_CTRL_ACTION => 'ctrl-step3',
    Security::CTRL_TOKEN      => SecureCsrf::generate('ctrl-step3'),
];
?><script>
    $("#tabs").tabs({
        create: function (event, ui) {
            $("#tabs").removeClass('no-display');
        }
    });

    DUPX.beforeUnloadCheck(true);

    DUPX.runDeployment = function () {
        //Validate input data
        var formInput = $('#s2-input-form');

        DUPX.sendParamsStep2(formInput, function () {
            DUPX.startAjaxDbInstall(true, function () {
                DUPX.redirectMainInstaller('post', <?php echo SnapJson::jsonEncode($nextStepPrams); ?>);
            });
        });
    };
</script>
