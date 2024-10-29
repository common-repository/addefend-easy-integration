<?php
add_action('template_redirect', 'addefend_internal_redirect');
register_deactivation_hook( ADDEFEND_PLUGIN_PHP_FILE, 'addefend_cleanup_internal_redirect');

function addefend_internal_redirect() {
    addefend_log('ADDEFEND -- TRACE : internal_redirect called!');

    $contentProxy = get_option('addefend_content_proxy');
    if (isset($contentProxy) && $contentProxy == "intern") {
        $currentUrl = $_SERVER['REQUEST_URI'];
        $imageDir = get_option('addefend_image_dir');

        if (is_404() && strpos($currentUrl, $imageDir) === 0) {
            $proxyUrl = get_option('addefend_proxy_URL');
            $targetUrl = $proxyUrl . $_SERVER['REQUEST_URI'];
            addefend_log('ADDEFEND -- TRACE : internal_redirect - To: ' . $targetUrl);

            $headers = getHeaders();
            $headers["X-Forwarded-For"] = $_SERVER['REMOTE_ADDR'];
            $headers['Connection']= 'close';
            $streamHeaders = '';
            foreach ($headers as $key => $value) {
                $streamHeaders .= "$key: $value" . "\r\n";
            }

            $opts = array(
                'http' => array(
                    'method' => 'GET',
                    'header' => $streamHeaders,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                    'follow_location' => 0,
                    'timeout' => 5,
                    'ignore_errors' => true
                )
            );
            $context = stream_context_create($opts);
            $data = file_get_contents($targetUrl, false, $context);
            if (strpos($http_response_header[0], '404') === false) {
                foreach ($http_response_header as $header) {
                    header(trim($header));
                }
		        ob_clean();
                echo $data;
                die();
            }
        }
    }
}

function addefend_cleanup_internal_redirect(){
    remove_action('template_redirect', 'addefend_internal_redirect');
}
