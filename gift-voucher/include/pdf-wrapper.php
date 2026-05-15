<?php

if (!defined('ABSPATH')) exit;  // Exit if accessed directly

/**
 * WPGV PDF wrapper helpers for the Dompdf-only runtime.
 */

/**
 * Resolve the active PDF engine.
 *
 * @return string
 */
function wpgv_get_pdf_engine()
{
    return 'dompdf';
}

/**
 * Check whether Dompdf is available.
 *
 * @return bool
 */
function wpgv_pdf_is_dompdf_available()
{
    return class_exists('\\Dompdf\\Dompdf') && class_exists('\\Dompdf\\Options');
}

/**
 * Detect whether the provided document is a Dompdf instance.
 *
 * @param mixed $document
 * @return bool
 */
function wpgv_pdf_is_dompdf_document($document)
{
    return is_object($document) && wpgv_pdf_is_dompdf_available() && ($document instanceof \Dompdf\Dompdf);
}

/**
 * Get the main PDF upload directory used by the plugin.
 *
 * @param string $relative_path Optional path inside voucherpdfuploads.
 * @return string
 */
function wpgv_pdf_get_upload_path($relative_path = '')
{
    $upload = wp_upload_dir();
    $base_dir = trailingslashit($upload['basedir']) . 'voucherpdfuploads';

    if ($relative_path === '') {
        return trailingslashit($base_dir);
    }

    return trailingslashit($base_dir) . ltrim($relative_path, '/\\');
}

/**
 * Return Dompdf runtime directories and ensure they exist.
 *
 * @return array
 */
function wpgv_pdf_get_dompdf_runtime_dirs()
{
    $base_dir = wpgv_pdf_get_upload_path('dompdf');
    $dirs = array(
        'base_dir' => untrailingslashit($base_dir),
        'temp_dir' => trailingslashit($base_dir) . 'tmp',
        'font_dir' => trailingslashit($base_dir) . 'fonts',
        'font_cache' => trailingslashit($base_dir) . 'font-cache',
    );

    foreach ($dirs as $key => $dir_path) {
        if ($key === 'base_dir') {
            continue;
        }

        wpgv_pdf_ensure_directory($dir_path);
    }

    return $dirs;
}

/**
 * Ensure a directory exists before writing PDF files into it.
 *
 * @param string $path Directory path.
 * @return bool
 */
function wpgv_pdf_ensure_directory($path)
{
    if (empty($path)) {
        return false;
    }

    if (file_exists($path)) {
        return is_dir($path);
    }

    return wp_mkdir_p($path);
}

/**
 * Output a PDF document to a file.
 *
 * @param mixed  $document
 * @param string $file_path
 * @return bool
 */
function wpgv_pdf_output_to_file($document, $file_path)
{
    if (!$document || empty($file_path)) {
        return false;
    }

    $directory = dirname($file_path);
    if (!wpgv_pdf_ensure_directory($directory)) {
        return false;
    }

    if (wpgv_pdf_is_dompdf_document($document)) {
        $bytes = file_put_contents($file_path, $document->output());
        return $bytes !== false;
    }

    return false;
}

/**
 * Stream a PDF document to the browser.
 *
 * @param mixed   $document
 * @param string  $filename
 * @param boolean $download
 * @return bool
 */
function wpgv_pdf_output_to_browser($document, $filename = 'document.pdf', $download = false)
{
    if (!$document) {
        return false;
    }

    if (wpgv_pdf_is_dompdf_document($document)) {
        $document->stream($filename, array('Attachment' => $download ? 1 : 0));
        return true;
    }

    return false;
}

/**
 * Render HTML into a Dompdf document.
 *
 * This helper is added in phase 1 so later phases can switch specific flows
 * without changing call sites again.
 *
 * @param string $html
 * @param array  $options
 * @return \Dompdf\Dompdf|\WP_Error
 */
function wpgv_pdf_render_html_document($html, $options = array())
{
    $engine = !empty($options['engine']) ? strtolower($options['engine']) : wpgv_get_pdf_engine();

    if ($engine !== 'dompdf') {
        return new WP_Error('wpgv_pdf_engine_not_supported', 'HTML rendering is only available for the dompdf engine.');
    }

    if (!wpgv_pdf_is_dompdf_available()) {
        return new WP_Error('wpgv_pdf_dompdf_missing', 'Dompdf is not installed.');
    }

    $runtime_dirs = wpgv_pdf_get_dompdf_runtime_dirs();
    $upload = wp_get_upload_dir();
    $default_chroot = array(
        str_replace('\\', '/', ABSPATH),
        str_replace('\\', '/', WPGIFT__PLUGIN_DIR),
        str_replace('\\', '/', $upload['basedir']),
    );

    $dompdf_options = new \Dompdf\Options();
    $dompdf_options->set('isRemoteEnabled', !empty($options['is_remote_enabled']));
    $dompdf_options->set('isHtml5ParserEnabled', true);
    $dompdf_options->set('isPhpEnabled', false);
    $dompdf_options->set('tempDir', !empty($options['temp_dir']) ? $options['temp_dir'] : $runtime_dirs['temp_dir']);
    $dompdf_options->set('fontDir', !empty($options['font_dir']) ? $options['font_dir'] : $runtime_dirs['font_dir']);
    $dompdf_options->set('fontCache', !empty($options['font_cache']) ? $options['font_cache'] : $runtime_dirs['font_cache']);
    $dompdf_options->set('defaultFont', !empty($options['default_font']) ? $options['default_font'] : 'DejaVu Sans');
    $dompdf_options->set('chroot', !empty($options['chroot']) ? $options['chroot'] : $default_chroot);

    $document = new \Dompdf\Dompdf($dompdf_options);
    $document->setPaper(!empty($options['paper']) ? $options['paper'] : 'A4', !empty($options['orientation']) ? $options['orientation'] : 'portrait');
    $document->loadHtml($html, 'UTF-8');
    $document->render();

    return $document;
}

/**
 * Build a full-page HTML document that displays one image.
 *
 * @param string $image_path
 * @param array  $options
 * @return string
 */
function wpgv_pdf_build_full_page_image_html($image_path, $options = array())
{
    $fit = !empty($options['fit']) ? $options['fit'] : 'contain';
    $background = !empty($options['background']) ? $options['background'] : '#ffffff';
    $safe_path = str_replace('\\', '/', $image_path);

    return '<!doctype html>'
        . '<html><head><meta charset="utf-8"><style>'
        . '@page { margin: 0; }'
        . 'html, body { margin: 0; padding: 0; width: 100%; height: 100%; background: ' . esc_attr($background) . '; }'
        . '.wpgv-page { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; }'
        . '.wpgv-page img { width: 100%; height: 100%; object-fit: ' . esc_attr($fit) . '; }'
        . '</style></head><body>'
        . '<div class="wpgv-page"><img src="' . esc_attr($safe_path) . '" alt=""></div>'
        . '</body></html>';
}

/**
 * Render an image-only PDF through Dompdf and save it to disk.
 *
 * @param string $image_path
 * @param string $file_path
 * @param array  $options
 * @return bool|\WP_Error
 */
function wpgv_pdf_render_image_to_file($image_path, $file_path, $options = array())
{
    $html = wpgv_pdf_build_full_page_image_html($image_path, $options);
    $document = wpgv_pdf_render_html_document($html, $options);

    if (is_wp_error($document)) {
        return $document;
    }

    return wpgv_pdf_output_to_file($document, $file_path);
}

/**
 * Render a PHP template into an HTML string.
 *
 * @param string $template_path
 * @param array  $vars
 * @return string|\WP_Error
 */
function wpgv_pdf_capture_html_template($template_path, $vars = array())
{
    if (empty($template_path) || !file_exists($template_path)) {
        return new WP_Error('wpgv_pdf_template_missing', 'HTML PDF template not found.');
    }

    if (!empty($vars) && is_array($vars)) {
        extract($vars, EXTR_SKIP);
    }

    ob_start();
    require $template_path;
    return ob_get_clean();
}

function wpgv_pdf_resolve_local_file_path($file_path)
{
    if (is_array($file_path)) {
        $file_path = reset($file_path);
    }

    if (!is_string($file_path) || $file_path === '') {
        return '';
    }

    $file_path = trim($file_path);

    if (file_exists($file_path) && is_readable($file_path)) {
        return $file_path;
    }

    if (preg_match('/^[a-zA-Z]:[\\\\\\/]/', $file_path) && is_readable($file_path)) {
        return $file_path;
    }

    $candidate_paths = array();

    if (defined('WPGIFT__PLUGIN_URL') && defined('WPGIFT__PLUGIN_DIR') && strpos($file_path, WPGIFT__PLUGIN_URL) === 0) {
        $relative = ltrim(substr($file_path, strlen(WPGIFT__PLUGIN_URL)), '/\\');
        $candidate_paths[] = trailingslashit(WPGIFT__PLUGIN_DIR) . str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $relative);
    }

    $url_parts = wp_parse_url($file_path);
    if (!empty($url_parts['path'])) {
        $site_url = function_exists('home_url') ? home_url('/') : '';
        $site_parts = $site_url ? wp_parse_url($site_url) : array();
        $site_path = !empty($site_parts['path']) ? rtrim($site_parts['path'], '/') : '';
        $request_path = $url_parts['path'];

        if ($site_path !== '' && strpos($request_path, $site_path) === 0) {
            $request_path = substr($request_path, strlen($site_path));
        }

        $request_path = ltrim($request_path, '/\\');
        if ($request_path !== '' && defined('ABSPATH')) {
            $candidate_paths[] = trailingslashit(ABSPATH) . str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $request_path);
        }
    }

    foreach ($candidate_paths as $candidate_path) {
        if ($candidate_path && file_exists($candidate_path) && is_readable($candidate_path)) {
            return $candidate_path;
        }
    }

    return '';
}

function wpgv_pdf_file_to_data_uri($file_path)
{
    $resolved_path = wpgv_pdf_resolve_local_file_path($file_path);

    if ($resolved_path === '' && is_string($file_path) && preg_match('#^https?://#i', $file_path)) {
        $response = wp_remote_get($file_path, array('timeout' => 15));
        if (!is_wp_error($response) && intval(wp_remote_retrieve_response_code($response)) === 200) {
            $contents = wp_remote_retrieve_body($response);
            if ($contents !== '') {
                $extension = strtolower(pathinfo(wp_parse_url($file_path, PHP_URL_PATH), PATHINFO_EXTENSION));
                $mime_types = array(
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'svg' => 'image/svg+xml',
                    'webp' => 'image/webp',
                );
                $mime_type = isset($mime_types[$extension]) ? $mime_types[$extension] : 'application/octet-stream';

                return 'data:' . $mime_type . ';base64,' . base64_encode($contents);
            }
        }
    }

    if ($resolved_path === '') {
        return '';
    }

    $contents = file_get_contents($resolved_path);
    if ($contents === false) {
        return '';
    }

    $extension = strtolower(pathinfo($resolved_path, PATHINFO_EXTENSION));
    $mime_types = array(
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
    );

    $mime_type = isset($mime_types[$extension]) ? $mime_types[$extension] : 'application/octet-stream';

    return 'data:' . $mime_type . ';base64,' . base64_encode($contents);
}

function wpgv_pdf_color_to_css($color, $fallback = '#000000')
{
    if (is_array($color) && count($color) >= 3) {
        return sprintf('#%02x%02x%02x', intval($color[0]), intval($color[1]), intval($color[2]));
    }

    return $fallback;
}

function wpgv_get_standard_voucher_style_layout($style_index)
{
    switch ((int) $style_index) {
        case 1:
            return array(
                'style' => 1,
                'page_width' => 595,
                'page_height' => 800,
                'image' => array('x' => 30, 'y' => 40, 'w' => 265, 'h' => 370),
                'title' => array('x' => 310, 'y' => 90, 'w' => 265, 'font_size' => 30, 'line_height' => 30, 'align' => 'left'),
                'description' => array('x' => 310, 'y' => 130, 'w' => 265, 'font_size' => 12, 'line_height' => 12, 'align' => 'left'),
                'for_label' => array('x' => 313, 'y' => 215, 'font_size' => 12),
                'for_box' => array('x' => 313, 'y' => 230, 'w' => 265, 'h' => 30, 'font_size' => 13, 'line_height' => 40),
                'recipient_label' => array('x' => 313, 'y' => 285, 'font_size' => 12),
                'recipient_box' => array('x' => 313, 'y' => 300, 'w' => 265, 'h' => 30, 'font_size' => 13, 'line_height' => 40),
                'price_label' => array('x' => 313, 'y' => 355, 'font_size' => 12),
                'price_box' => array('x' => 313, 'y' => 370, 'w' => 265, 'h' => 30, 'font_size' => 16, 'line_height' => 40),
                'message_label' => array('x' => 30, 'y' => 440, 'font_size' => 12),
                'message_box' => array('x' => 33, 'y' => 455, 'w' => 545, 'h' => 140, 'font_size' => 13, 'line_height' => 23),
                'expiry_label' => array('x' => 30, 'y' => 615, 'font_size' => 12),
                'expiry_box' => array('x' => 33, 'y' => 630, 'w' => 265, 'h' => 30, 'font_size' => 13, 'line_height' => 30),
                'code_label' => array('x' => 310, 'y' => 615, 'font_size' => 12),
                'code_box' => array('x' => 313, 'y' => 630, 'w' => 265, 'h' => 30, 'font_size' => 16, 'line_height' => 30),
                'barcode' => array('x' => 150, 'y' => 680, 'w' => 300, 'h' => 50),
                'footer' => array('x' => 30, 'y' => 760, 'w' => 535, 'font_size' => 13),
                'notice' => array('x' => 30, 'y' => 780, 'w' => 535, 'font_size' => 9, 'align' => 'center', 'rotate' => 0),
                'watermark' => array('x' => 75, 'y' => 700, 'font_size' => 45, 'rotate' => -45),
            );

        case 2:
            return array(
                'style' => 2,
                'page_width' => 595,
                'page_height' => 700,
                'image' => array('x' => 30, 'y' => 120, 'w' => 265, 'h' => 210),
                'title' => array('x' => 30, 'y' => 30, 'w' => 550, 'font_size' => 30, 'line_height' => 30, 'align' => 'center'),
                'description' => array('x' => 30, 'y' => 65, 'w' => 550, 'font_size' => 12, 'line_height' => 12, 'align' => 'center'),
                'for_label' => array('x' => 310, 'y' => 135, 'font_size' => 12),
                'for_box' => array('x' => 313, 'y' => 150, 'w' => 265, 'h' => 30, 'font_size' => 13, 'line_height' => 40),
                'recipient_label' => array('x' => 310, 'y' => 205, 'font_size' => 12),
                'recipient_box' => array('x' => 313, 'y' => 220, 'w' => 265, 'h' => 30, 'font_size' => 13, 'line_height' => 40),
                'price_label' => array('x' => 310, 'y' => 275, 'font_size' => 12),
                'price_box' => array('x' => 313, 'y' => 290, 'w' => 265, 'h' => 30, 'font_size' => 16, 'line_height' => 40),
                'message_label' => array('x' => 33, 'y' => 335, 'font_size' => 12),
                'message_box' => array('x' => 33, 'y' => 355, 'w' => 546, 'h' => 140, 'font_size' => 13, 'line_height' => 23),
                'expiry_label' => array('x' => 33, 'y' => 515, 'font_size' => 12),
                'expiry_box' => array('x' => 33, 'y' => 530, 'w' => 265, 'h' => 30, 'font_size' => 16, 'line_height' => 30),
                'code_label' => array('x' => 310, 'y' => 515, 'font_size' => 12),
                'code_box' => array('x' => 313, 'y' => 530, 'w' => 265, 'h' => 30, 'font_size' => 16, 'line_height' => 30),
                'barcode' => array('x' => 150, 'y' => 580, 'w' => 300, 'h' => 50),
                'footer' => array('x' => 30, 'y' => 660, 'w' => 535, 'font_size' => 13),
                'notice' => array('x' => 30, 'y' => 680, 'w' => 535, 'font_size' => 9, 'align' => 'center', 'rotate' => 0),
                'watermark' => array('x' => 70, 'y' => 600, 'font_size' => 45, 'rotate' => -45),
            );

        case 0:
        default:
            return array(
                'style' => 0,
                'page_width' => 595,
                'page_height' => 900,
                'image' => array('x' => 0, 'y' => 0, 'w' => 595, 'h' => 453),
                'title' => array('x' => 30, 'y' => 460, 'w' => 550, 'font_size' => 25, 'line_height' => 25, 'align' => 'center'),
                'description' => array('x' => 30, 'y' => 500, 'w' => 550, 'font_size' => 13, 'line_height' => 12, 'align' => 'center'),
                'for_label' => array('x' => 33, 'y' => 545, 'font_size' => 12),
                'for_box' => array('x' => 33, 'y' => 560, 'w' => 265, 'h' => 30, 'font_size' => 13, 'line_height' => 30),
                'recipient_label' => array('x' => 310, 'y' => 545, 'font_size' => 12),
                'recipient_box' => array('x' => 313, 'y' => 560, 'w' => 265, 'h' => 30, 'font_size' => 13, 'line_height' => 30),
                'price_label' => array('x' => 33, 'y' => 610, 'font_size' => 12),
                'price_box' => array('x' => 33, 'y' => 625, 'w' => 265, 'h' => 30, 'font_size' => 16, 'line_height' => 30),
                'message_label' => array('x' => 33, 'y' => 675, 'font_size' => 12),
                'message_box' => array('x' => 33, 'y' => 690, 'w' => 546, 'h' => 100, 'font_size' => 13, 'line_height' => 20),
                'expiry_label' => array('x' => 310, 'y' => 610, 'font_size' => 12),
                'expiry_box' => array('x' => 313, 'y' => 625, 'w' => 265, 'h' => 30, 'font_size' => 16, 'line_height' => 30),
                'code_label' => array('x' => 33, 'y' => 800, 'font_size' => 12),
                'code_box' => array('x' => 33, 'y' => 815, 'w' => 265, 'h' => 30, 'font_size' => 16, 'line_height' => 30),
                'barcode' => array('x' => 313, 'y' => 790, 'w' => 265, 'h' => 50),
                'footer' => array('x' => 30, 'y' => 868, 'w' => 535, 'font_size' => 12),
                'notice' => array('x' => 10, 'y' => 850, 'w' => 320, 'font_size' => 9, 'align' => 'left', 'rotate' => -90),
                'watermark' => array('x' => 70, 'y' => 700, 'font_size' => 45, 'rotate' => -45),
            );
    }
}

function wpgv_pdf_get_standard_layout($style)
{
    return wpgv_get_standard_voucher_style_layout($style);
}

function wpgv_pdf_code128_patterns()
{
    return array(
        array(2, 1, 2, 2, 2, 2), array(2, 2, 2, 1, 2, 2), array(2, 2, 2, 2, 2, 1), array(1, 2, 1, 2, 2, 3),
        array(1, 2, 1, 3, 2, 2), array(1, 3, 1, 2, 2, 2), array(1, 2, 2, 2, 1, 3), array(1, 2, 2, 3, 1, 2),
        array(1, 3, 2, 2, 1, 2), array(2, 2, 1, 2, 1, 3), array(2, 2, 1, 3, 1, 2), array(2, 3, 1, 2, 1, 2),
        array(1, 1, 2, 2, 3, 2), array(1, 2, 2, 1, 3, 2), array(1, 2, 2, 2, 3, 1), array(1, 1, 3, 2, 2, 2),
        array(1, 2, 3, 1, 2, 2), array(1, 2, 3, 2, 2, 1), array(2, 2, 3, 2, 1, 1), array(2, 2, 1, 1, 3, 2),
        array(2, 2, 1, 2, 3, 1), array(2, 1, 3, 2, 1, 2), array(2, 2, 3, 1, 1, 2), array(3, 1, 2, 1, 3, 1),
        array(3, 1, 1, 2, 2, 2), array(3, 2, 1, 1, 2, 2), array(3, 2, 1, 2, 2, 1), array(3, 1, 2, 2, 1, 2),
        array(3, 2, 2, 1, 1, 2), array(3, 2, 2, 2, 1, 1), array(2, 1, 2, 1, 2, 3), array(2, 1, 2, 3, 2, 1),
        array(2, 3, 2, 1, 2, 1), array(1, 1, 1, 3, 2, 3), array(1, 3, 1, 1, 2, 3), array(1, 3, 1, 3, 2, 1),
        array(1, 1, 2, 3, 1, 3), array(1, 3, 2, 1, 1, 3), array(1, 3, 2, 3, 1, 1), array(2, 1, 1, 3, 1, 3),
        array(2, 3, 1, 1, 1, 3), array(2, 3, 1, 3, 1, 1), array(1, 1, 2, 1, 3, 3), array(1, 1, 2, 3, 3, 1),
        array(1, 3, 2, 1, 3, 1), array(1, 1, 3, 1, 2, 3), array(1, 1, 3, 3, 2, 1), array(1, 3, 3, 1, 2, 1),
        array(3, 1, 3, 1, 2, 1), array(2, 1, 1, 3, 3, 1), array(2, 3, 1, 1, 3, 1), array(2, 1, 3, 1, 1, 3),
        array(2, 1, 3, 3, 1, 1), array(2, 1, 3, 1, 3, 1), array(3, 1, 1, 1, 2, 3), array(3, 1, 1, 3, 2, 1),
        array(3, 3, 1, 1, 2, 1), array(3, 1, 2, 1, 1, 3), array(3, 1, 2, 3, 1, 1), array(3, 3, 2, 1, 1, 1),
        array(3, 1, 4, 1, 1, 1), array(2, 2, 1, 4, 1, 1), array(4, 3, 1, 1, 1, 1), array(1, 1, 1, 2, 2, 4),
        array(1, 1, 1, 4, 2, 2), array(1, 2, 1, 1, 2, 4), array(1, 2, 1, 4, 2, 1), array(1, 4, 1, 1, 2, 2),
        array(1, 4, 1, 2, 2, 1), array(1, 1, 2, 2, 1, 4), array(1, 1, 2, 4, 1, 2), array(1, 2, 2, 1, 1, 4),
        array(1, 2, 2, 4, 1, 1), array(1, 4, 2, 1, 1, 2), array(1, 4, 2, 2, 1, 1), array(2, 4, 1, 2, 1, 1),
        array(2, 2, 1, 1, 1, 4), array(4, 1, 3, 1, 1, 1), array(2, 4, 1, 1, 1, 2), array(1, 3, 4, 1, 1, 1),
        array(1, 1, 1, 2, 4, 2), array(1, 2, 1, 1, 4, 2), array(1, 2, 1, 2, 4, 1), array(1, 1, 4, 2, 1, 2),
        array(1, 2, 4, 1, 1, 2), array(1, 2, 4, 2, 1, 1), array(4, 1, 1, 2, 1, 2), array(4, 2, 1, 1, 1, 2),
        array(4, 2, 1, 2, 1, 1), array(2, 1, 2, 1, 4, 1), array(2, 1, 4, 1, 2, 1), array(4, 1, 2, 1, 2, 1),
        array(1, 1, 1, 1, 4, 3), array(1, 1, 1, 3, 4, 1), array(1, 3, 1, 1, 4, 1), array(1, 1, 4, 1, 1, 3),
        array(1, 1, 4, 3, 1, 1), array(4, 1, 1, 1, 1, 3), array(4, 1, 1, 3, 1, 1), array(1, 1, 3, 1, 4, 1),
        array(1, 1, 4, 1, 3, 1), array(3, 1, 1, 1, 4, 1), array(4, 1, 1, 1, 3, 1), array(2, 1, 1, 4, 1, 2),
        array(2, 1, 1, 2, 1, 4), array(2, 1, 1, 2, 3, 2), array(2, 3, 3, 1, 1, 1), array(2, 1),
    );
}

function wpgv_pdf_code128_encode($code)
{
    $patterns = wpgv_pdf_code128_patterns();
    $abc_set = '';
    for ($index = 32; $index <= 95; $index++) {
        $abc_set .= chr($index);
    }

    $a_set = $abc_set;
    $b_set = $abc_set;

    for ($index = 0; $index <= 31; $index++) {
        $abc_set .= chr($index);
        $a_set .= chr($index);
    }

    for ($index = 96; $index <= 127; $index++) {
        $abc_set .= chr($index);
        $b_set .= chr($index);
    }

    for ($index = 200; $index <= 210; $index++) {
        $abc_set .= chr($index);
        $a_set .= chr($index);
        $b_set .= chr($index);
    }

    $c_set = '0123456789' . chr(206);
    $set_from_a = '';
    $set_from_b = '';
    $set_to_a = '';
    $set_to_b = '';

    for ($index = 0; $index < 96; $index++) {
        $set_from_a .= chr($index);
        $set_from_b .= chr($index + 32);
        $set_to_a .= chr($index < 32 ? $index + 64 : $index - 32);
        $set_to_b .= chr($index);
    }

    for ($index = 96; $index < 107; $index++) {
        $set_from_a .= chr($index + 104);
        $set_from_b .= chr($index + 104);
        $set_to_a .= chr($index);
        $set_to_b .= chr($index);
    }

    $guides = array('A' => '', 'B' => '', 'C' => '');
    $length = strlen($code);
    for ($offset = 0; $offset < $length; $offset++) {
        $needle = substr($code, $offset, 1);
        $guides['A'] .= (strpos($a_set, $needle) === false) ? 'N' : 'O';
        $guides['B'] .= (strpos($b_set, $needle) === false) ? 'N' : 'O';
        $guides['C'] .= (strpos($c_set, $needle) === false) ? 'N' : 'O';
    }

    $crypt = '';
    $mini_c = 'OOOO';

    while ($code !== '') {
        $position = strpos($guides['C'], $mini_c);
        if ($position !== false) {
            $guides['A'][$position] = 'N';
            $guides['B'][$position] = 'N';
        }

        if (substr($guides['C'], 0, 4) === $mini_c) {
            $crypt .= chr($crypt !== '' ? 99 : 105);
            $made = strpos($guides['C'], 'N');
            if ($made === false) {
                $made = strlen($guides['C']);
            }
            if ($made % 2 === 1) {
                $made--;
            }
            for ($offset = 0; $offset < $made; $offset += 2) {
                $crypt .= chr(intval(substr($code, $offset, 2)));
            }
        } else {
            $made_a = strpos($guides['A'], 'N');
            $made_b = strpos($guides['B'], 'N');
            if ($made_a === false) {
                $made_a = strlen($guides['A']);
            }
            if ($made_b === false) {
                $made_b = strlen($guides['B']);
            }

            $made = ($made_a < $made_b) ? $made_b : $made_a;
            $set_name = ($made_a < $made_b) ? 'B' : 'A';
            $crypt .= chr($crypt !== '' ? ($set_name === 'A' ? 101 : 100) : ($set_name === 'A' ? 103 : 104));

            $source = ($set_name === 'A') ? $set_from_a : $set_from_b;
            $target = ($set_name === 'A') ? $set_to_a : $set_to_b;
            $crypt .= strtr(substr($code, 0, $made), $source, $target);
        }

        $code = substr($code, $made);
        $guides['A'] = substr($guides['A'], $made);
        $guides['B'] = substr($guides['B'], $made);
        $guides['C'] = substr($guides['C'], $made);
    }

    if ($crypt === '') {
        return array();
    }

    $check = ord($crypt[0]);
    $length = strlen($crypt);
    for ($offset = 0; $offset < $length; $offset++) {
        $check += ord($crypt[$offset]) * $offset;
    }
    $check %= 103;

    $crypt .= chr($check) . chr(106) . chr(107);

    $encoded = array();
    $length = strlen($crypt);
    for ($offset = 0; $offset < $length; $offset++) {
        $encoded[] = $patterns[ord($crypt[$offset])];
    }

    return $encoded;
}

function wpgv_pdf_get_code128_svg_data_uri($code, $width = 265, $height = 50)
{
    if ($code === '') {
        return '';
    }

    $encoded = wpgv_pdf_code128_encode($code);
    if (empty($encoded)) {
        return '';
    }

    $module_count = 0;
    foreach ($encoded as $pattern) {
        foreach ($pattern as $unit) {
            $module_count += $unit;
        }
    }

    if ($module_count <= 0) {
        return '';
    }

    $module_width = $width / $module_count;
    $cursor = 0;
    $bars = '';

    foreach ($encoded as $pattern) {
        $pattern_count = count($pattern);
        for ($index = 0; $index < $pattern_count; $index++) {
            $segment_width = $pattern[$index] * $module_width;
            if ($index % 2 === 0) {
                $bars .= '<rect x="' . round($cursor, 3) . '" y="0" width="' . round($segment_width, 3) . '" height="' . intval($height) . '" fill="#000"/>';
            }
            $cursor += $segment_width;
        }
    }

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . intval($width) . '" height="' . intval($height) . '" viewBox="0 0 ' . intval($width) . ' ' . intval($height) . '">' . $bars . '</svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function wpgv_pdf_render_standard_document($args)
{
    $defaults = array(
        'style' => 0,
        'formtype' => 'voucher',
        'image_path' => '',
        'title' => '',
        'description' => '',
        'for' => '',
        'from' => '',
        'buyingfor' => 'someone_else',
        'currency' => '',
        'expiry' => '',
        'message' => '',
        'code' => '',
        'preview' => false,
        'watermark' => '',
        'voucher_bgcolor' => array(255, 255, 255),
        'voucher_color' => array(0, 0, 0),
        'footer_url' => '',
        'footer_email' => '',
        'hide_price' => 0,
        'leftside_notice' => '',
        'barcode_enabled' => 0,
        'compact_mode' => false,
    );

    $context = wp_parse_args($args, $defaults);
    $context['style'] = intval($context['style']);
    $context['layout'] = wpgv_pdf_get_standard_layout($context['style']);
    if ($context['formtype'] === 'voucher') {
        $voucher_title_y = array(
            0 => 480,
            1 => 100,
            2 => 40,
        );
        if (isset($voucher_title_y[$context['style']])) {
            $context['layout']['title']['y'] = $voucher_title_y[$context['style']];
        }
    }
    $context['background_color_css'] = wpgv_pdf_color_to_css($context['voucher_bgcolor'], '#ffffff');
    $context['page_background_color_css'] = '#ffffff';
    if (intval($context['style']) !== 0) {
        $context['page_background_color_css'] = $context['background_color_css'];
    }
    $context['text_color_css'] = wpgv_pdf_color_to_css($context['voucher_color'], '#000000');
    $context['image_src'] = wpgv_pdf_file_to_data_uri($context['image_path']);
    $context['barcode_code'] = ($context['preview'] && !empty($context['barcode_enabled'])) ? '4746489065070412' : $context['code'];
    $context['barcode_src'] = !empty($context['barcode_enabled']) ? wpgv_pdf_get_code128_svg_data_uri($context['barcode_code'], $context['layout']['barcode']['w'], $context['layout']['barcode']['h']) : '';

    $html = wpgv_pdf_capture_html_template(WPGIFT__PLUGIN_DIR . '/templates/pdf-html/standard.php', $context);
    if (is_wp_error($html)) {
        return $html;
    }

    return wpgv_pdf_render_html_document($html, array(
        'engine' => 'dompdf',
        'paper' => array(0, 0, $context['layout']['page_width'], $context['layout']['page_height']),
        'orientation' => 'portrait',
        'default_font' => 'DejaVu Sans',
    ));
}

function wpgv_save_voucher_pdf_style($voucher_id, $style)
{
    $voucher_id = intval($voucher_id);
    if ($voucher_id <= 0) {
        return false;
    }

    return (bool) update_post_meta($voucher_id, 'wpgv_pdf_style', intval($style));
}

function wpgv_get_voucher_pdf_style($voucher_id, $default_style = 0)
{
    $style = intval(get_post_meta(intval($voucher_id), 'wpgv_pdf_style', true));

    if (!in_array($style, array(0, 1, 2), true)) {
        $style = intval($default_style);
    }

    return in_array($style, array(0, 1, 2), true) ? $style : 0;
}

function wpgv_save_voucher_pdf_context($voucher_id, $context = array())
{
    $voucher_id = intval($voucher_id);
    if ($voucher_id <= 0 || !is_array($context)) {
        return false;
    }

    $kind = isset($context['kind']) ? sanitize_key($context['kind']) : '';
    $template_id = isset($context['template_id']) ? intval($context['template_id']) : 0;

    if (!in_array($kind, array('modern', 'standard_list', 'standard_grid'), true) || $template_id <= 0) {
        return false;
    }

    update_post_meta($voucher_id, 'wpgv_pdf_template_kind', $kind);
    update_post_meta($voucher_id, 'wpgv_pdf_source_id', $template_id);

    if (array_key_exists('style', $context) && $context['style'] !== null && $context['style'] !== '') {
        wpgv_save_voucher_pdf_style($voucher_id, intval($context['style']));
    }

    return true;
}

function wpgv_get_saved_voucher_pdf_context($voucher_id)
{
    $voucher_id = intval($voucher_id);
    if ($voucher_id <= 0) {
        return array('kind' => 'unknown', 'template_id' => 0);
    }

    $kind = sanitize_key(get_post_meta($voucher_id, 'wpgv_pdf_template_kind', true));
    $template_id = intval(get_post_meta($voucher_id, 'wpgv_pdf_source_id', true));

    if (!in_array($kind, array('modern', 'standard_list', 'standard_grid'), true) || $template_id <= 0) {
        return array('kind' => 'unknown', 'template_id' => 0);
    }

    return array(
        'kind' => $kind,
        'template_id' => $template_id,
    );
}

function wpgv_create_voucher_order_key($voucher_id)
{
    $voucher_id = intval($voucher_id);
    if ($voucher_id <= 0) {
        return '';
    }

    $order_key = wp_generate_password(20, false, false);
    update_post_meta($voucher_id, 'wpgv_order_key', $order_key);

    return $order_key;
}

function wpgv_get_voucher_order_key($voucher_id)
{
    return sanitize_text_field(get_post_meta(intval($voucher_id), 'wpgv_order_key', true));
}

function wpgv_is_valid_voucher_order_key($voucher_id, $provided_key)
{
    $stored_key = wpgv_get_voucher_order_key($voucher_id);
    $provided_key = sanitize_text_field($provided_key);

    if ($stored_key === '' || $provided_key === '') {
        return false;
    }

    return hash_equals($stored_key, $provided_key);
}

function wpgv_cleanup_failed_voucher_order($voucher_id, $voucher_pdf_link = '')
{
    global $wpdb;

    $voucher_id = intval($voucher_id);
    if ($voucher_id <= 0) {
        return false;
    }

    $voucher_table = $wpdb->prefix . 'giftvouchers_list';
    $activity_table = $wpdb->prefix . 'giftvouchers_activity';

    $wpdb->delete($activity_table, array('voucher_id' => $voucher_id), array('%d'));
    $wpdb->delete($voucher_table, array('id' => $voucher_id), array('%d'));

    $meta_keys = array(
        'wpgv_pdf_style',
        'wpgv_pdf_template_kind',
        'wpgv_pdf_source_id',
        'wpgv_total_payable_amount',
        'wpgv_extra_charges',
        'wpgv_order_key',
        'wpgv_paypal_order_id',
        'wpgv_paypal_payment_key',
        'wpgv_paypal_mode_for_transaction',
        'wpgv_stripe_session_key',
        'wpgv_stripe_mode_for_transaction',
    );

    foreach ($meta_keys as $meta_key) {
        delete_post_meta($voucher_id, $meta_key);
    }

    $voucher_pdf_link = sanitize_file_name($voucher_pdf_link);
    if ($voucher_pdf_link !== '') {
        $upload = wp_upload_dir();
        $pdf_dir = trailingslashit($upload['basedir']) . 'voucherpdfuploads/';
        $files = array(
            $pdf_dir . $voucher_pdf_link . '.pdf',
            $pdf_dir . $voucher_pdf_link . '-receipt.pdf',
        );

        foreach ($files as $file_path) {
            if (file_exists($file_path)) {
                wp_delete_file($file_path);
            }
        }
    }

    return true;
}

function wpgv_get_standard_pdf_args_for_voucher($voucher_data, $style = null)
{
    if (!$voucher_data || empty($voucher_data->order_type)) {
        return new WP_Error('wpgv_standard_pdf_missing_voucher', 'Voucher data is missing.');
    }

    global $wpdb;

    $setting_table = $wpdb->prefix . 'giftvouchers_setting';
    $template_table = $wpdb->prefix . 'giftvouchers_template';
    $setting_options = $wpdb->get_row($wpdb->prepare("SELECT * FROM $setting_table WHERE id = %d", 1));

    if (!$setting_options) {
        return new WP_Error('wpgv_standard_pdf_missing_settings', 'Voucher settings could not be loaded.');
    }

    $resolved_style = is_null($style)
        ? wpgv_get_voucher_pdf_style(isset($voucher_data->id) ? $voucher_data->id : 0, 0)
        : intval($style);

    if (!in_array($resolved_style, array(0, 1, 2), true)) {
        $resolved_style = 0;
    }

    $args = array(
        'style' => $resolved_style,
        'for' => isset($voucher_data->from_name) ? $voucher_data->from_name : '',
        'from' => isset($voucher_data->to_name) ? $voucher_data->to_name : '',
        'buyingfor' => !empty($voucher_data->buying_for) ? $voucher_data->buying_for : 'someone_else',
        'currency' => wpgv_price_format(isset($voucher_data->amount) ? $voucher_data->amount : 0),
        'expiry' => isset($voucher_data->expiry) ? $voucher_data->expiry : '',
        'message' => isset($voucher_data->message) ? $voucher_data->message : '',
        'code' => isset($voucher_data->couponcode) ? $voucher_data->couponcode : '',
        'preview' => false,
        'voucher_bgcolor' => wpgv_hex2rgb(isset($setting_options->voucher_bgcolor) ? $setting_options->voucher_bgcolor : '#ffffff'),
        'voucher_color' => wpgv_hex2rgb(isset($setting_options->voucher_color) ? $setting_options->voucher_color : '#000000'),
        'footer_url' => isset($setting_options->pdf_footer_url) ? $setting_options->pdf_footer_url : '',
        'footer_email' => isset($setting_options->pdf_footer_email) ? $setting_options->pdf_footer_email : '',
        'leftside_notice' => (get_option('wpgv_leftside_notice') != '') ? get_option('wpgv_leftside_notice') : __('Cash payment is not possible. The terms and conditions apply.', 'gift-voucher'),
        'barcode_enabled' => isset($setting_options->wpgv_barcode_on_voucher) ? $setting_options->wpgv_barcode_on_voucher : 0,
    );

    switch ($voucher_data->order_type) {
        case 'items':
            $item_id = !empty($voucher_data->item_id) ? intval($voucher_data->item_id) : intval($voucher_data->template_id);
            if ($item_id <= 0) {
                return new WP_Error('wpgv_standard_pdf_missing_item', 'Gift item could not be resolved.');
            }

            $style_image = get_post_meta($item_id, 'style' . ($resolved_style + 1) . '_image', true);
            $image_path = $style_image ? get_attached_file($style_image) : '';
            if (empty($image_path)) {
                $image_path = get_attached_file(get_post_thumbnail_id($item_id));
            }

            $args['formtype'] = 'item';
            $args['image_path'] = $image_path ? $image_path : get_option('wpgv_demoimageurl_item');
            $args['title'] = get_the_title($item_id);
            $args['description'] = esc_html(get_post_meta($item_id, 'description', true));
            $args['hide_price'] = get_option('wpgv_hide_price_item') ? get_option('wpgv_hide_price_item') : 0;
            break;

        case 'gift_voucher_product':
            $product_id = intval($voucher_data->product_id);
            if ($product_id <= 0) {
                return new WP_Error('wpgv_standard_pdf_missing_product', 'Voucher product could not be resolved.');
            }

            $image_path = get_attached_file(get_post_thumbnail_id($product_id));

            $args['formtype'] = 'wpgv_voucher_product';
            $args['image_path'] = $image_path ? $image_path : get_option('wpgv_demoimageurl_voucher');
            $args['title'] = get_the_title($product_id);
            $args['description'] = '';
            $args['hide_price'] = 0;
            $args['compact_mode'] = intval($setting_options->is_order_form_enable) !== 1;
            $args['style'] = 0;
            break;

        case 'vouchers':
            $template_id = intval($voucher_data->template_id);
            if ($template_id <= 0) {
                return new WP_Error('wpgv_standard_pdf_missing_template', 'Voucher template could not be resolved.');
            }

            $template_options = $wpdb->get_row($wpdb->prepare("SELECT * FROM $template_table WHERE id = %d", $template_id));
            if (!$template_options) {
                return new WP_Error('wpgv_standard_pdf_missing_template_row', 'Voucher template row could not be loaded.');
            }

            $images = !empty($template_options->image_style) ? json_decode($template_options->image_style) : array('', '', '');
            $image_id = isset($images[$resolved_style]) ? $images[$resolved_style] : '';
            $image_path = $image_id ? get_attached_file($image_id) : '';

            $args['formtype'] = 'voucher';
            $args['image_path'] = $image_path ? $image_path : get_option('wpgv_demoimageurl_voucher');
            $args['title'] = isset($template_options->title) ? $template_options->title : '';
            $args['description'] = '';
            $args['hide_price'] = get_option('wpgv_hide_price_voucher') ? get_option('wpgv_hide_price_voucher') : 0;
            break;

        default:
            return new WP_Error('wpgv_standard_pdf_unsupported_order_type', 'Order type is not supported by the standard PDF renderer.');
    }

    return $args;
}

/**
 * Check if at least one PDF backend is available.
 *
 * @return bool
 */
function wpgv_pdf_available()
{
    return wpgv_pdf_is_dompdf_available();
}
