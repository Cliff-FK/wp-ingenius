<?php

define('VITE_DEV', true);

add_action('wp_enqueue_scripts', function() {
    if ( defined('VITE_DEV') && VITE_DEV ) {
        wp_enqueue_script_module('mytheme', 'http://localhost:3000/assets/js/main.js', [], null, true);
    } else {
         wp_enqueue_script_module('mytototheme1', get_theme_file_uri('dist/main.js'));
    }
});
