<?php

register_activation_hook( ADDEFEND_PLUGIN_PHP_FILE, 'addefend_insert_rules');
register_deactivation_hook( ADDEFEND_PLUGIN_PHP_FILE, 'addefend_remove_rules');


function addefend_insert_rules() {
    $content_proxy = get_option( 'addefend_content_proxy' );
    if ($content_proxy == 'apache') {
        $htaccess = ABSPATH.'.htaccess';
        addefend_log('ADDEFEND -- TRACE : htaccess file path '.$htaccess);
        $proxy_url = get_option( 'addefend_proxy_URL' );
        $image_dir = get_option( 'addefend_image_dir', '/wp-content' );

        $lines = array();
        $lines[] = '<IfModule mod_rewrite.c>';
        $lines[] = 'RewriteEngine On';
        $lines[] = 'RewriteBase /';
        $lines[] = 'RewriteCond %{REQUEST_URI} ^' . $image_dir;
        $lines[] = 'RewriteCond %{REQUEST_FILENAME} !-f';
        $lines[] = 'RewriteCond %{REQUEST_FILENAME} !-d';
        $lines[] = 'RewriteRule ^(.*)$ ' . $proxy_url . '/$1 [P,L]';
        $lines[] = '</IfModule>';

        // Check the validity of the proxy URL
        if (preg_match('/.*:\/\/.*\..*\/.*\/.*\/.*\/.*/', $proxy_url)) {
            addefend_log('ADDEFEND -- INFO : using '.$proxy_url.' as the proxy URL');
            $addefend_content_proxy = addefend_insert_with_markers($htaccess, ADDEFEND_HTACCESS_MARKER, $lines);
            if ($addefend_content_proxy) {
                addefend_log('ADDEFEND -- TRACE : Rewrite rules inserted successfully');
            } else {
                addefend_log('ADDEFEND -- ERROR : Rewrite rules could not be inserted');
            }
        }
    }
}

function addefend_remove_rules() {
    // Clean Apache-Config
    $htaccess = ABSPATH.".htaccess";
    insert_with_markers($htaccess, ADDEFEND_HTACCESS_MARKER, '');
}
