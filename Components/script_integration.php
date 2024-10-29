<?php

global $wpdb;
define('ADDEFEND_TABLE_NAME', $wpdb->prefix . "addefend");
define('ADDEFEND_SCRIPT_UPDATE_FREQUENCY_IN_SEC', 5*60);
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

// create the addefend table
register_activation_hook(ADDEFEND_PLUGIN_PHP_FILE, 'addefend_create_table');
function addefend_create_table() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE ".ADDEFEND_TABLE_NAME." (
      id mediumint(9) NOT NULL,
      script longtext NOT NULL,
      timestamp int NOT NULL,
      PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql);
}

register_activation_hook(ADDEFEND_PLUGIN_PHP_FILE, 'download_addefend_script');
function download_addefend_script() {
    // the new script is being stored in a dedicated table in the wordpress database
    $script_URL = get_option('addefend_script_URL');
    // API call
    $response = wp_remote_get($script_URL);
    // handling the response
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) == 404){
        if ( is_wp_error( $response ) ) {
            $error_string = $response->get_error_message();
            addefend_log('ADDEFEND -- ERROR : Script download error :' . $error_string);
        }else {
            addefend_log('ADDEFEND -- ERROR : 404 script not found');
        }
        addefend_log('ADDEFEND -- ERROR : Sticking with the old script');
    }else{
        addefend_log('ADDEFEND -- TRACE : Script is downloaded successfully');
        $new_script = wp_remote_retrieve_body($response);
        global $wpdb;
        $script_inserted = $wpdb->replace(
            ADDEFEND_TABLE_NAME,
            array(
                'id' => '1',
                'script' => $new_script,
                'timestamp' => time()
            )
        );
        if(!$script_inserted){
            addefend_log('ADDEFEND -- ERROR : script couldn\'t be saved in the database');
        } else{
            addefend_log('ADDEFEND -- TRACE : script is successfully saved in the database');
        }
    }
}

// inject the script in the footer
add_action('wp_footer', 'inject_addefend_script');
function inject_addefend_script(){
    global $wpdb;
    extract($wpdb->get_row("SELECT script AS addefend_script, timestamp AS addefend_script_timestamp FROM ".ADDEFEND_TABLE_NAME." WHERE (id = '1')", "ARRAY_A"));
    if($addefend_script && $addefend_script_timestamp){
        $addefend_script_age = time() - $addefend_script_timestamp;
        echo '<script type="text/javascript">' . $addefend_script . '</script>';
        addefend_log('ADDEFEND -- TRACE : A ' . $addefend_script_age . ' seconds old script was injected in the footer');
        if ($addefend_script_age < ADDEFEND_SCRIPT_UPDATE_FREQUENCY_IN_SEC) {
            $nextScriptUpdateDate = $addefend_script_timestamp + ADDEFEND_SCRIPT_UPDATE_FREQUENCY_IN_SEC;
            addefend_log('ADDEFEND -- TRACE : the next script update is scheduled for ' . date('l jS \of F Y h:i:s A (e)', $nextScriptUpdateDate));
        } else {
            download_addefend_script();
        }
    } else {
        addefend_log('ADDEFEND -- ERROR : Failed to retrieve the script from the database');
        download_addefend_script();
    }
}

function addefend_script_is_cached() {
    global $wpdb;
    $addefend_script = $wpdb->get_var('SELECT script FROM '.ADDEFEND_TABLE_NAME.' WHERE (id = \'1\')');
    if ($addefend_script) {
        return true;
    }
    return false;
}

// clean up the database from all addefend content
register_deactivation_hook(ADDEFEND_PLUGIN_PHP_FILE, 'addefend_cleanup');
function addefend_cleanup() {
    global $wpdb;
    $wpdb->delete(ADDEFEND_TABLE_NAME, array('id' => '1'));
}
