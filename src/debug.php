<?php
defined('ABSPATH') || die('');

add_action('wp_ajax_translator_download_debug', function () {
    // check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    $debug_file = LINGUISE_PLUGIN_PATH . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'translator' . DIRECTORY_SEPARATOR . 'script-php' . DIRECTORY_SEPARATOR . 'debug.php';
    if (!file_exists($debug_file)) {
        wp_die('No debug file found');
    }

    if (file_exists($debug_file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="debug.txt"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($debug_file));
        ob_clean();
        ob_end_flush();
        $handle = fopen($debug_file, 'rb');
        while (! feof($handle)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo fread($handle, 1000);
        }
        die();
    }
});
