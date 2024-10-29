<?php

add_action( 'admin_menu', 'addefend_menu' );
function addefend_menu() {
    add_options_page( 'AdDefend Integration Settings', 'AdDefend integration', 'manage_options', 'AdDefend', 'addefend_options' );
}

register_uninstall_hook( ADDEFEND_PLUGIN_PHP_FILE, 'addefend_delete_options' );
function addefend_delete_options() {
    delete_option('addefend_script_URL');
    delete_option('addefend_proxy_URL');
    delete_option('addefend_image_dir');
    delete_option('addefend_content_proxy');
}

function addefend_options() {
    if ( !current_user_can( 'manage_options' ) )  {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }

    $contentProxy = get_option( 'addefend_content_proxy', 'apache');
    $scriptUrl = get_option( 'addefend_script_URL' , '' );
    $proxyUrl = get_option( 'addefend_proxy_URL' , '' );
    $testUrl = get_option( 'addefend_test_URL' , '' );
    $imageDir = get_option( 'addefend_image_dir' , ADDEFEND_DEFAULT_IMAGE_DIRECTORY );

    if (isset($_POST[ 'submitted' ]) && $_POST[ 'submitted' ] == 'config_form') {
        check_admin_referer('submit_addefend_integration_parameters');
        $nonce = $_POST['_wpnonce'];
        if ( ! wp_verify_nonce( $nonce, 'submit_addefend_integration_parameters' ) ) {
            exit; // Get out of here, the nonce is rotten!
        }

        $contentProxy = $_POST['selected_content_proxy'];
        $scriptUrl = esc_url($_POST['script_URL']);
        $proxyUrl = esc_url($_POST['proxy_URL']);
        $testUrl = $_POST['test_URL'];
        $imageDir = $_POST['image_dir'];

        // always save the new input
        update_option("addefend_content_proxy", $contentProxy);
        update_option('addefend_script_URL', $scriptUrl);
        update_option('addefend_proxy_URL', $proxyUrl);
        update_option('addefend_test_URL', $testUrl);
        update_option('addefend_image_dir', $imageDir);

        $addefend_script_is_cached = addefend_script_is_cached();
        $validScriptUrl = wp_remote_retrieve_response_code(wp_remote_get($scriptUrl)) == 200;
        if ($validScriptUrl) {
            if (!$addefend_script_is_cached) {
                download_addefend_script();
            }
        } else {
            if ($addefend_script_is_cached) {
                addefend_cleanup();
            }
            echo '<div class="error"><p><strong>Script URL is invalid</strong></p></div>';
        }

        $htaccess = ABSPATH . '.htaccess';
        $addefend_rules = addefend_extract_from_markers($htaccess, ADDEFEND_HTACCESS_MARKER );
        $addefend_rules_added = sizeof($addefend_rules) > 5;
        $validProxyUrl = preg_match('/.*:\/\/.*\..*\/.*\/.*\/.*\/.*/', $proxyUrl);
        if ($validProxyUrl) {
            if ($contentProxy === 'apache') {
                if (!$addefend_rules_added) {
                    addefend_insert_rules();
                }
            } elseif ($addefend_rules_added) {
                addefend_remove_rules();
            }
            echo '<div class="updated"><p><strong>Settings saved for: ' . $contentProxy . '</strong></p></div>';
        } else {
            if ($addefend_rules_added) {
                addefend_remove_rules();
            }
            echo '<div class="error"><p><strong>Proxy URL is invalid</strong></p></div>';
        }
    }
    ?>

    <h1>AdDefend Integration Parameters</h1>
    <form method="post" action="">
        <?= wp_nonce_field("submit_addefend_integration_parameters" ) ?>
        <input type="hidden" name="submitted" value="config_form">
        <input type="hidden" name="selected_content_proxy" value="<?= esc_attr($contentProxy) ?>">
        <style>
            .addefend_table {border-collapse: collapse;}
            .addefend_table td {border: 1px solid black; padding: 10px;}
            .addefend_table td * {margin: auto !important;}
            .addefend_table li {list-style-type: none;}
            .addefend_table td > div {height : 20px; width : 140px;}
            .addefend_table td > :not(:first-child) {margin-top: 10px !important;}
            .addefend_table td > div > p {text-align: center; color: white; font-weight: bold;}
            .addefend_table td > label {font-weight: bold;}
            .addefend_table td:first-child { width: 185px; }
        </style>
        <table class="addefend_table">
            <!-- Content-proxy type -->
            <tr>
                <td>
                    <label for="select_content_proxy">Content-Proxy:</label>
                </td>
                <td>
                    <select id="select_content_proxy" name="selected_content_proxy">
                        <option value="apache" <?= $contentProxy == "apache" ? "selected": "" ?>>Apache</option>
                        <option value="intern" <?= $contentProxy == "intern" ? "selected": "" ?>>Internal Proxy</option>
                    </select>
                </td>
            </tr>
            <!-- Script URL -->
            <tr>
                <td>
                    <label for="script_URL_field_id">Script URL: </label>
                </td>
                <td>
                    <input type="text" id="script_URL_field_id" name="script_URL" value="<?= esc_attr($scriptUrl) ?>" size="70">
                </td>
            </tr>
            <!-- Proxy URL -->
            <tr>
                <td>
                    <label for="proxy_URL_field_id">Proxy URL:  </label>
                </td>
                <td>
                    <input type="text" id="proxy_URL_field_id" name="proxy_URL" value="<?= esc_attr($proxyUrl) ?>" size="70">
                </td>
            </tr>
            <!-- Test URL -->
            <tr>
                <td>
                    <label for="test_URL_field_id">Test URL: </label>
                </td>
                <td>
                    <input type="text" id="test_URL_field_id" name="test_URL" value="<?= esc_attr($testUrl) ?>" size="70">
                    <?php if (strlen($testUrl) > 0): ?>
                        <?php
                        $adDefendIsReached = false;
                        $opts = array(
                            'http' => array(
                                'timeout' => 5,
                                'ignore_errors' => true
                            )
                        );
                        $context = stream_context_create($opts);
                        $testUrlResponseBody = @file_get_contents($testUrl, false, $context);
                        foreach ($http_response_header as $header) {
                            if (strpos($header, 'AdDefend') !== false) {
                                $adDefendIsReached = true;
                                break;
                            }
                        }
                        ?>
                        <?php if ($adDefendIsReached): ?>
                            <div style="background-color: green;">
                                <p>Working</p>
                            </div>
                        <?php else: ?>
                            <div style="background-color: red;">
                                <p>Not working</p>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php
                        $adDefendIsReached = false;
                        $testUrlResponseBody = '';
                        ?>
                    <?php endif; ?>
                </td>
                <?php if ($adDefendIsReached && isset($testUrlResponseBody) && strlen($testUrlResponseBody) > 0): ?>
                <td>
                    <script>
                        var testUrlResponseBody;
                        try {
                            testUrlResponseBody = `<?= $testUrlResponseBody ?>`;
                        } catch (err) {
                            testUrlResponseBody = '';
                        }
                    </script>
                    <button type="button" class="button-secondary" onclick="navigator.clipboard.writeText(testUrlResponseBody)">Copy response</button>
                </td>
                <?php endif; ?>
            </tr>
            <!-- Image Directory -->
            <tr>
                <td>
                    <label for="image_dir_id">Images Directory: </label>
                </td>
                <td>
                    <input type="text" id="image_dir_id" name="image_dir" value="<?= esc_attr($imageDir) ?>" size="70">
                </td>
            </tr>
            <!-- AdDefend Script Caching -->
            <tr>
                <td>
                    <label>AdDefend Script Caching: </label>
                </td>
                <td>
                    <?php
                    global $wpdb;
                    $addefend_script = $wpdb->get_var('SELECT script FROM '.ADDEFEND_TABLE_NAME.' WHERE (id = \'1\')');
                    if ($addefend_script): ?>
                        <div style="background-color: green;">
                            <p>Working</p>
                        </div>
                    <?php else: ?>
                        <div style="background-color: red;">
                            <p>Not Working</p>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ($contentProxy == "apache"): ?>
                <!-- Apache Rewrite Module -->
                <tr>
                    <td>
                        <label>Apache Rewrite Module: </label>
                    </td>
                    <td>
                        <?php if (!got_mod_rewrite()): ?>
                            <div style="background-color: red;">
                                <p>Not Loaded</p>
                            </div>
                        <?php else: ?>
                            <div style="background-color: green;">
                                <p>Loaded</p>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <!-- Apache SSL Module  -->
                <tr>
                    <td>
                        <label>Apache SSL Module:</label>
                    </td>
                    <td>
                        <?php if (!apache_mod_loaded("mod_ssl")): ?>
                            <div style="background-color: red;">
                                <p>Not Loaded</p>
                            </div>
                        <?php else: ?>
                            <div style="background-color: green;">
                                <p>Loaded</p>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <!-- AdDefend Content-Proxy Apache -->
                <tr>
                    <td>
                        <label>AdDefend Content-Proxy:</label>
                    </td>
                    <td>
                        <?php
                        $htaccess = ABSPATH.'.htaccess';
                        $addefend_rules = addefend_extract_from_markers($htaccess, ADDEFEND_HTACCESS_MARKER );
                        ?>
                        <?php if (sizeof($addefend_rules) < 5): ?>
                            <div style="background-color: red;">
                                <p>Not Configured</p>
                            </div>
                        <?php else: ?>
                            <div style="background-color: green;">
                                <p>Configured</p>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if (sizeof($addefend_rules) >= 5): ?>
                    <!-- AdDefend Config Fragment Index -->
                    <tr>
                        <td>
                            <label>AdDefend Config Fragment Index: </label>
                        </td>
                        <td>
                            <p><?= get_line_from_file($htaccess, ADDEFEND_HTACCESS_MARKER); ?></p>
                        </td>
                    </tr>
                    <!-- AdDefend Config Fragment Index -->
                    <tr>
                        <td>
                            <label>AdDefend Config Fragment: </label>
                        </td>
                        <td>
                            <?php foreach ($addefend_rules as $value): ?>
                                <li><?= htmlspecialchars($value); ?></li>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <!-- Wordpress Root Directory -->
                    <tr>
                        <td>
                            <label>Wordpress Root Directory: </label>
                        </td>
                        <td>
                            <p><?= get_home_path() ?></p>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php if (get_home_path() !== ABSPATH): ?>
                    <!-- Wordpress Sub-Directory -->
                    <tr>
                        <td>
                            <label>Wordpress Sub-Directory: </label>
                        </td>
                        <td>
                            <p><?= ABSPATH ?></p>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($contentProxy == "intern"): ?>
                <tr>
                    <td>
                        <label>Internal Proxy Status:</label>
                    </td>
                    <td>
                        <?php
                        $isInternalRedirectActionRegistered = has_action('template_redirect', 'addefend_internal_redirect') !== false;
                        ?>
                        <?php if ($isInternalRedirectActionRegistered): ?>
                            <div style="background-color: green;">
                                <p>Active</p>
                            </div>
                        <?php else: ?>
                            <div style="background-color: red;">
                                <p>Inactive</p>
                            </div>
                        <?php endif; ?>
                        <div class="primary" style="display: none;"
                    </td>
                </tr>
            <?php endif; ?>
        </table>
        <?php if ($contentProxy == "intern"): ?>
            <p><strong>Expected behaviour:</strong> If the image results in 404, the request will be forwarded to the specified 'Proxy URL'!</p>
            <table class="addefend_table">
                <tr>
                    <td>
                        <label>Example Image URL:</label>
                    </td>
                    <td>
                        <input type="text" id="example_content-proxy_url" value="" size="70" readonly>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label>Example Forwarded URL:</label>
                    </td>
                    <td>
                        <input type="text" id="example_proxy_url" value="" size="70" readonly>
                    </td>
                </tr>
            </table>
        <?php endif; ?>
        <p class="submit">
            <input type="submit" name="Submit" class="button-primary" value="Apply">
        </p>
    </form>
    <script>
        window.addEventListener("DOMContentLoaded", function() {
            var selectedContentProxy = document.querySelector("input[name=selected_content_proxy]");
            if (!selectedContentProxy || selectedContentProxy.value !== "intern") {
                return;
            }

            var exampleContentProxyUrlElement = document.querySelector("#example_content-proxy_url");
            var exampleProxyUrlElement = document.querySelector("#example_proxy_url");
            var proxyUrlElement = document.querySelector("#proxy_URL_field_id");
            var imagesDirectoryElement = document.querySelector("#image_dir_id");
            if (!exampleContentProxyUrlElement || !exampleProxyUrlElement || !proxyUrlElement || !imagesDirectoryElement) {
                return;
            }

            var relativeProxyPath = imagesDirectoryElement.value + "/example/file.jpg";
            var contentProxyURL = location.protocol + "//" + location.host + relativeProxyPath;
            var proxyURL = proxyUrlElement.value + relativeProxyPath;

            exampleContentProxyUrlElement.value = contentProxyURL;
            exampleProxyUrlElement.value = proxyURL;
        });
    </script>
<?php } ?>