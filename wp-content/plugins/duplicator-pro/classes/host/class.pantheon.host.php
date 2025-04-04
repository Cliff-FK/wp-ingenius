<?php

/**
 * godaddy custom hosting class
 *
 * Standard: PSR-2
 *
 * @package SC\DUPX\HOST
 * @link    http://www.php-fig.org/psr/psr-2/
 */

defined('ABSPATH') || defined('DUPXABSPATH') || exit;

class DUP_PRO_Pantheon_Host implements DUP_PRO_Host_interface
{
    public static function getIdentifier(): string
    {
        return DUP_PRO_Custom_Host_Manager::HOST_PANTHEON;
    }

    public function isHosting()
    {
        return apply_filters('duplicator_pro_pantheon_host_check', file_exists(WPMU_PLUGIN_DIR . '/pantheon.php'));
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
        // disable wp engine plugins
        $data['fd_plugins'] = [
            'value' => ['pantheon.php'],
        ];

        // generare new wp-config.php file
        $data['wp_config'] = [
            'value'      => 'new',
            'formStatus' => 'st_infoonly',
        ];

        return $data;
    }
}
