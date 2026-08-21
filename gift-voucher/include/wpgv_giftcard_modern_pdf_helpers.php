<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Helper functions for PDF generation moved out of wpgv_giftcard_pdf.php
 */

if (!function_exists('wpgv_pdf_log')) {
    /**
     * Write a PDF diagnostic line, but only while debugging.
     *
     * Never pass an absolute path, a PDF filename or canvas data to this: the
     * filename carries the coupon code, and debug.log is web-readable on some
     * hosts. Voucher/template IDs and byte counts are fine.
     *
     * @param string $message
     */
    function wpgv_pdf_log($message)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WPGV PDF: ' . $message); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- debug-only diagnostic.
        }
    }
}

function wpgv_get_modern_pdf_upload_dir() {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    WP_Filesystem();

    global $wp_filesystem;

    if (!$wp_filesystem) {
        return false;
    }

    $upload = wp_upload_dir();
    $upload_dir = trailingslashit($upload['basedir']) . 'voucherpdfuploads/';

    if (!$wp_filesystem->exists($upload_dir)) {
        $mkdir_result = $wp_filesystem->mkdir($upload_dir, 0755);
        wpgv_pdf_log('Created the upload directory: ' . ($mkdir_result ? 'SUCCESS' : 'FAILED'));
    }

    if (!$wp_filesystem->is_writable($upload_dir)) {
        wpgv_pdf_log('ERROR: The upload directory is not writable.');
        return false;
    }

    return $upload_dir;
}

function wpgv_get_modern_pdf_orientation($template_id)
{
    $template_style = get_post_meta($template_id, 'wpgv_customize_template_template-style', true);
    $choose_template = get_post_meta($template_id, 'wpgv_customize_template_chosse_template', true);

    return (strpos($template_style, 'lanscape') !== false || $choose_template == 'lanscape')
        ? 'landscape'
        : 'portrait';
}

function wpgv_render_modern_canvas_pdf_file($template_id, $canvas_file_path, $pdf_path)
{
    if (empty($canvas_file_path) || !file_exists($canvas_file_path)) {
        return false;
    }

    require_once WPGIFT__PLUGIN_DIR . '/include/pdf-wrapper.php';

    $orientation = wpgv_get_modern_pdf_orientation($template_id);
    $rendered = wpgv_pdf_render_image_to_file($canvas_file_path, $pdf_path, array(
        'engine' => 'dompdf',
        'paper' => 'A4',
        'orientation' => $orientation,
        'fit' => 'contain',
        'background' => '#ffffff',
    ));

    return $rendered === true && file_exists($pdf_path);
}

function wpgv_render_modern_template_pdf_file($voucher_data, $template_id, $pdf_path)
{
    if (!$voucher_data || !$template_id || empty($pdf_path)) {
        return false;
    }

    require_once WPGIFT__PLUGIN_DIR . '/include/pdf-wrapper.php';

    $orientation = wpgv_get_modern_pdf_orientation($template_id);
    $template_image_path = wpgv_get_template_image_path($template_id);
    $background_src = $template_image_path ? wpgv_pdf_file_to_data_uri($template_image_path) : '';
    $amount_display = '';

    if (isset($voucher_data->amount) && $voucher_data->amount !== '') {
        $amount_display = function_exists('wpgv_price_format')
            ? wpgv_price_format($voucher_data->amount)
            : strval($voucher_data->amount);
    }

    $coupon_code = isset($voucher_data->couponcode) ? strval($voucher_data->couponcode) : '';
    $barcode_src = '';
    if (get_option('wpgv_barcode_on_voucher') && $coupon_code !== '') {
        $barcode_src = wpgv_pdf_get_code128_svg_data_uri($coupon_code, 320, 60);
    }

    $html = wpgv_pdf_capture_html_template(WPGIFT__PLUGIN_DIR . '/templates/pdf-html/modern.php', array(
        'background_src' => $background_src,
        'recipient_name' => isset($voucher_data->to_name) ? $voucher_data->to_name : '',
        'sender_name' => isset($voucher_data->from_name) ? $voucher_data->from_name : '',
        'amount_display' => $amount_display,
        'message' => isset($voucher_data->message) ? $voucher_data->message : '',
        'coupon_code' => $coupon_code,
        'expiry_display' => isset($voucher_data->expiry) ? $voucher_data->expiry : '',
        'barcode_src' => $barcode_src,
    ));

    if (is_wp_error($html)) {
        return false;
    }

    $document = wpgv_pdf_render_html_document($html, array(
        'engine' => 'dompdf',
        'paper' => 'A4',
        'orientation' => $orientation,
        'default_font' => 'DejaVu Sans',
    ));

    if (is_wp_error($document)) {
        return false;
    }

    return wpgv_pdf_output_to_file($document, $pdf_path) && file_exists($pdf_path);
}

/**
 * Generate Modern Giftcard PDF for Product Page Gift Checkbox
 *
 * @param int $voucher_id Database voucher ID
 * @param object $voucher_data Voucher data from database
 * @param int $template_id Template post ID
 * @return string|false Path to generated PDF file or false on failure
 */
function wpgv_generate_modern_giftcard_pdf($voucher_id, $voucher_data, $template_id, $canvas_data = null, $canvas_file_path = '', $pdf_filename_base = '') {
    wpgv_pdf_log('Starting PDF generation for voucher ID ' . $voucher_id . ', template ID ' . $template_id . '.');

    if (!$voucher_data || !$template_id) {
        wpgv_pdf_log('ERROR: Missing voucher data or template ID.');
        return false;
    }

    global $wpdb;
    $voucher_table = $wpdb->prefix . 'giftvouchers_list';

    $upload_dir = wpgv_get_modern_pdf_upload_dir();
    if ($upload_dir === false) {
        return false;
    }

    if (!empty($pdf_filename_base)) {
        $pdf_filename_base = wpgv_sanitize_voucher_pdf_basename($pdf_filename_base);
    } else {
        $curr_time = time();
        $pdf_filename_base = wpgv_sanitize_voucher_pdf_basename($curr_time . $voucher_data->couponcode);
    }

    if ($pdf_filename_base === '') {
        $pdf_filename_base = wpgv_sanitize_voucher_pdf_basename($voucher_id . '-' . time());
    }
    $pdf_filename = $pdf_filename_base . '.pdf';
    $pdf_path = $upload_dir . $pdf_filename;

    if ($voucher_id > 0) {
        $update_result = $wpdb->update(
            $voucher_table,
            array('voucherpdf_link' => $pdf_filename_base),
            array('id' => $voucher_id),
            array('%s'),
            array('%d')
        );

        if ($update_result === false) {
            wpgv_pdf_log('ERROR: Failed to update voucherpdf_link in the database. Error: ' . $wpdb->last_error);
        }
    }

    try {
        if (!empty($canvas_file_path) && file_exists($canvas_file_path)) {
            if (wpgv_render_modern_canvas_pdf_file($template_id, $canvas_file_path, $pdf_path)) {
                wpgv_pdf_log('Created the PDF from the canvas file. Size: ' . filesize($pdf_path) . ' bytes.');
                return $pdf_path;
            }

            wpgv_pdf_log('ERROR: The Dompdf canvas render failed.');
        } elseif (!empty($canvas_data)) {
            wpgv_pdf_log('Received canvas data. Length: ' . strlen($canvas_data) . '.');

            if (preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $canvas_data, $type)) {
                $canvas_data = substr($canvas_data, strpos($canvas_data, ',') + 1);
                $canvas_data = base64_decode($canvas_data);
            } else {
                wpgv_pdf_log('ERROR: Invalid canvas data format.');
            }

            if ($canvas_data !== false && !empty($canvas_data)) {
                $canvas_image_path = $upload_dir . 'temp_canvas_' . $voucher_id . '.png';
                $bytes_written = file_put_contents($canvas_image_path, $canvas_data);
                wpgv_pdf_log('Wrote the temporary canvas image: ' . var_export($bytes_written, true) . ' bytes.');

                if (file_exists($canvas_image_path)) {
                    if (wpgv_render_modern_canvas_pdf_file($template_id, $canvas_image_path, $pdf_path)) {
                        wpgv_pdf_log('Created the PDF from canvas data. Size: ' . filesize($pdf_path) . ' bytes.');
                        @unlink($canvas_image_path);
                        return $pdf_path;
                    }

                    @unlink($canvas_image_path);
                }
            }

            wpgv_pdf_log('ERROR: Failed to generate the PDF from canvas data.');
        }

        wpgv_pdf_log('Using the Dompdf HTML fallback for modern template rendering.');
        if (wpgv_render_modern_template_pdf_file($voucher_data, $template_id, $pdf_path)) {
            wpgv_pdf_log('Created the PDF from the HTML fallback. Size: ' . filesize($pdf_path) . ' bytes.');
            return $pdf_path;
        }

        wpgv_pdf_log('ERROR: The HTML fallback could not create the PDF.');
        return false;
    } catch (Exception $e) {
        wpgv_pdf_log('ERROR: Exception during PDF generation: ' . $e->getMessage());
        return false;
    }
}

if (! function_exists('wpgv_get_template_image_path')) {
    /**
     * Get template image path for PDF generation
     *
     * @param int $template_id Template post ID
     * @return string|false Path to template image or false
     */
    function wpgv_get_template_image_path($template_id) {
        wpgv_pdf_log('Resolving the template image for template ID ' . $template_id . '.');

        $select_status_template = get_post_meta($template_id, 'wpgv_customize_template_select_template', true);
        $selected_voucher_template = get_post_meta($template_id, 'wpgv_customize_template_template-style', true);

        wpgv_pdf_log('Select status: ' . $select_status_template . ', template style: ' . $selected_voucher_template . '.');

        $upload_dir = wpgv_get_modern_pdf_upload_dir();
        if ($upload_dir === false) {
            wpgv_pdf_log('ERROR: Could not initialize the upload directory.');
            return false;
        }

        if ($select_status_template == 'custom') {
            $get_bg_temp = get_post_meta($template_id, 'wpgv_customize_template_bg_result', true);
            wpgv_pdf_log('Using a custom template. Background attachment ID: ' . $get_bg_temp . '.');

            if ($get_bg_temp) {
                if (is_numeric($get_bg_temp)) {
                    $attachment_path = get_attached_file($get_bg_temp);
                    if ($attachment_path && file_exists($attachment_path)) {
                        wpgv_pdf_log('Found the custom template attachment.');
                        return $attachment_path;
                    } else {
                        wpgv_pdf_log('ERROR: The custom template attachment was not found.');
                    }
                } else {
                    $custom_url = $get_bg_temp;
                    $tmp_file = $upload_dir . 'custom_template_' . md5($custom_url) . '.' . pathinfo($custom_url, PATHINFO_EXTENSION);
                    if (!file_exists($tmp_file)) {
                        $response = wp_remote_get($custom_url, array('timeout' => 30));
                        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                            file_put_contents($tmp_file, wp_remote_retrieve_body($response));
                        }
                    }
                    if (file_exists($tmp_file)) {
                        wpgv_pdf_log('Cached the custom template from its URL.');
                        return $tmp_file;
                    }
                    wpgv_pdf_log('ERROR: The custom template URL could not be downloaded.');
                }
            }
        } else {
            wpgv_pdf_log('Using the default template from S3: ' . $selected_voucher_template . '.');
            $template_url = 'https://gift-card-pro.s3.eu-central-1.amazonaws.com/templates/png/' . $selected_voucher_template;
            $temp_file = $upload_dir . 'temp_' . basename($selected_voucher_template);

            if (!file_exists($temp_file)) {
                wpgv_pdf_log('The template is not cached, downloading it from S3.');
                $response = wp_remote_get($template_url, array('timeout' => 30));

                if (is_wp_error($response)) {
                    wpgv_pdf_log('ERROR: Failed to download the template. Error: ' . $response->get_error_message());
                } elseif (wp_remote_retrieve_response_code($response) == 200) {
                    $image_data = wp_remote_retrieve_body($response);
                    $bytes_written = file_put_contents($temp_file, $image_data);
                    wpgv_pdf_log('Downloaded the template. Bytes written: ' . $bytes_written . '.');
                } else {
                    $response_code = wp_remote_retrieve_response_code($response);
                    wpgv_pdf_log('ERROR: Failed to download the template. HTTP code: ' . $response_code);
                }
            } else {
                wpgv_pdf_log('Using the cached template.');
            }

            if (file_exists($temp_file)) {
                return $temp_file;
            } else {
                wpgv_pdf_log('ERROR: The template file does not exist after the download attempt.');
            }
        }

        wpgv_pdf_log('ERROR: Could not resolve the template image path.');
        return false;
    }
}
