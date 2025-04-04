<?php

/**
 * wpengine custom hosting class
 *
 * Standard: PSR-2
 *
 * @package SC\DUPX\HOST
 * @link    http://www.php-fig.org/psr/psr-2/
 */

defined('ABSPATH') || defined('DUPXABSPATH') || exit;

class DUP_PRO_Liquidweb_Host implements DUP_PRO_Host_interface
{
    public static function getIdentifier(): string
    {
        return DUP_PRO_Custom_Host_Manager::HOST_LIQUIDWEB;
    }

    public function isHosting()
    {
        return apply_filters('duplicator_pro_liquidweb_host_check', file_exists(WPMU_PLUGIN_DIR . '/liquid-web.php'));
    }

    public function init()
    {
        add_filter('duplicator_pro_overwrite_params_data', [self::class, 'installerParams']);
    }

    /**
     * Add installer params
     *
     * @param array<string,array{formStatus?:string,value:mixed}> $data Data
     *
     * @return array<string,array{formStatus?:string,value:mixed}>
     */
    public static function installerParams($data)
    {
        $data['fd_plugins'] = [
            'value' => [
                'liquidweb_mwp.php',
                '000-liquidweb-config.php',
                'liquid-web.php',
                'lw_disable_nags.php',
            ],
        ];
        return $data;
    }
}
