<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Helper functions for PDF generation moved out of wpgv_giftcard_pdf.php
 */

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
    error_log('WPGV PDF: Starting PDF generation for voucher ID: ' . $voucher_id . ', Template ID: ' . $template_id . ', base file: ' . $pdf_filename_base);

    if (!$voucher_data || !$template_id) {
        error_log('WPGV PDF ERROR: Missing voucher data or template ID');
        return false;
    }

    global $wpdb;
    $voucher_table = $wpdb->prefix . 'giftvouchers_list';

    $upload = wp_upload_dir();
    $upload_dir = $upload['basedir'] . '/voucherpdfuploads/';

    error_log('WPGV PDF: Upload directory: ' . $upload_dir);

    if (!file_exists($upload_dir)) {
        $mkdir_result = mkdir($upload_dir, 0755, true);
        error_log('WPGV PDF: Created directory: ' . $upload_dir . ' - Result: ' . ($mkdir_result ? 'SUCCESS' : 'FAILED'));
    } else {
        error_log('WPGV PDF: Directory already exists: ' . $upload_dir);
    }

    if (!is_writable($upload_dir)) {
        error_log('WPGV PDF ERROR: Directory is not writable: ' . $upload_dir);
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

    error_log('WPGV PDF: PDF filename: ' . $pdf_filename . ', Full path: ' . $pdf_path);

    if ($voucher_id > 0) {
        $update_result = $wpdb->update(
            $voucher_table,
            array('voucherpdf_link' => $pdf_filename_base),
            array('id' => $voucher_id),
            array('%s'),
            array('%d')
        );

        if ($update_result === false) {
            error_log('WPGV PDF ERROR: Failed to update voucherpdf_link in database. Error: ' . $wpdb->last_error);
        } else {
            error_log('WPGV PDF: Updated voucherpdf_link in database: ' . $pdf_filename_base);
        }
    }

    try {
        if (!empty($canvas_file_path) && file_exists($canvas_file_path)) {
            error_log('WPGV PDF: Using existing canvas file path: ' . $canvas_file_path);

            if (wpgv_render_modern_canvas_pdf_file($template_id, $canvas_file_path, $pdf_path)) {
                $file_size = filesize($pdf_path);
                error_log('WPGV PDF: PDF created successfully using canvas file. File size: ' . $file_size . ' bytes');
                return $pdf_path;
            }

            error_log('WPGV PDF ERROR: Dompdf canvas render failed at: ' . $pdf_path);
        } elseif (!empty($canvas_data)) {
            error_log('WPGV PDF: [DEBUG] Canvas data received. Length: ' . strlen($canvas_data));
            error_log('WPGV PDF: [DEBUG] Canvas data (first 100 chars): ' . substr($canvas_data, 0, 100));

            if (preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $canvas_data, $type)) {
                $canvas_data = substr($canvas_data, strpos($canvas_data, ',') + 1);
                $canvas_data = base64_decode($canvas_data);
            } else {
                error_log('WPGV PDF ERROR: Invalid canvas data format');
            }

            if ($canvas_data !== false && !empty($canvas_data)) {
                $canvas_image_path = $upload_dir . 'temp_canvas_' . $voucher_id . '.png';
                $bytes_written = file_put_contents($canvas_image_path, $canvas_data);
                error_log('WPGV PDF: [DEBUG] file_put_contents result: ' . var_export($bytes_written, true));

                if (file_exists($canvas_image_path)) {
                    if (wpgv_render_modern_canvas_pdf_file($template_id, $canvas_image_path, $pdf_path)) {
                        $file_size = filesize($pdf_path);
                        error_log('WPGV PDF: PDF created successfully using canvas. File size: ' . $file_size . ' bytes');
                        @unlink($canvas_image_path);
                        return $pdf_path;
                    }

                    @unlink($canvas_image_path);
                }
            }

            error_log('WPGV PDF ERROR: Failed to generate PDF from canvas data');
        }

        error_log('WPGV PDF: Using Dompdf HTML fallback for modern template rendering');
        if (wpgv_render_modern_template_pdf_file($voucher_data, $template_id, $pdf_path)) {
            $file_size = filesize($pdf_path);
            error_log('WPGV PDF: PDF created successfully using HTML fallback. File size: ' . $file_size . ' bytes');
            return $pdf_path;
        }

        error_log('WPGV PDF ERROR: HTML fallback could not create PDF at: ' . $pdf_path);
        return false;
    } catch (Exception $e) {
        error_log('WPGV PDF ERROR: Exception during PDF generation: ' . $e->getMessage());
        error_log('WPGV PDF ERROR: Stack trace: ' . $e->getTraceAsString());
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
        error_log('WPGV PDF: Getting template image path for template ID: ' . $template_id);

        $select_status_template = get_post_meta($template_id, 'wpgv_customize_template_select_template', true);
        $selected_voucher_template = get_post_meta($template_id, 'wpgv_customize_template_template-style', true);

        error_log('WPGV PDF: Select status: ' . $select_status_template . ', Template style: ' . $selected_voucher_template);

        $upload = wp_upload_dir();
        $upload_dir = $upload['basedir'] . '/voucherpdfuploads/';

        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        if ($select_status_template == 'custom') {
            error_log('WPGV PDF: Using custom template');
            $get_bg_temp = get_post_meta($template_id, 'wpgv_customize_template_bg_result', true);
            error_log('WPGV PDF: Custom background attachment ID: ' . $get_bg_temp);

            if ($get_bg_temp) {
                if (is_numeric($get_bg_temp)) {
                    $attachment_path = get_attached_file($get_bg_temp);
                    error_log('WPGV PDF: Attachment path: ' . $attachment_path);
                    if ($attachment_path && file_exists($attachment_path)) {
                        error_log('WPGV PDF: Custom template image found: ' . $attachment_path);
                        return $attachment_path;
                    } else {
                        error_log('WPGV PDF ERROR: Custom template attachment not found');
                    }
                } else {
                    $custom_url = $get_bg_temp;
                    error_log('WPGV PDF: Custom template URL: ' . $custom_url);
                    $tmp_file = $upload_dir . 'custom_template_' . md5($custom_url) . '.' . pathinfo($custom_url, PATHINFO_EXTENSION);
                    if (!file_exists($tmp_file)) {
                        $response = wp_remote_get($custom_url, array('timeout' => 30));
                        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                            file_put_contents($tmp_file, wp_remote_retrieve_body($response));
                        }
                    }
                    if (file_exists($tmp_file)) {
                        error_log('WPGV PDF: Custom template URL downloaded to: ' . $tmp_file);
                        return $tmp_file;
                    }
                    error_log('WPGV PDF ERROR: Custom template URL could not be downloaded');
                }
            }
        } else {
            error_log('WPGV PDF: Using default template from S3');
            $template_url = 'https://gift-card-pro.s3.eu-central-1.amazonaws.com/templates/png/' . $selected_voucher_template;
            $temp_file = $upload_dir . 'temp_' . basename($selected_voucher_template);

            error_log('WPGV PDF: Template URL: ' . $template_url);
            error_log('WPGV PDF: Temp file path: ' . $temp_file);

            if (!file_exists($temp_file)) {
                error_log('WPGV PDF: Template not cached, downloading from S3...');
                $response = wp_remote_get($template_url, array('timeout' => 30));

                if (is_wp_error($response)) {
                    error_log('WPGV PDF ERROR: Failed to download template. Error: ' . $response->get_error_message());
                } elseif (wp_remote_retrieve_response_code($response) == 200) {
                    $image_data = wp_remote_retrieve_body($response);
                    $bytes_written = file_put_contents($temp_file, $image_data);
                    error_log('WPGV PDF: Template downloaded successfully. Bytes written: ' . $bytes_written);
                } else {
                    $response_code = wp_remote_retrieve_response_code($response);
                    error_log('WPGV PDF ERROR: Failed to download template. HTTP code: ' . $response_code);
                }
            } else {
                error_log('WPGV PDF: Using cached template: ' . $temp_file);
            }

            if (file_exists($temp_file)) {
                error_log('WPGV PDF: Template image ready: ' . $temp_file);
                return $temp_file;
            } else {
                error_log('WPGV PDF ERROR: Template file does not exist after download attempt');
            }
        }

        error_log('WPGV PDF ERROR: Could not get template image path');
        return false;
    }
}
