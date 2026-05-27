<?php
if (!defined('ABSPATH')) exit;

function wpgv_admin_regenerate_standard_pdf_func()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'gift-voucher')));
    }

    $voucher_id = isset($_POST['voucher_id']) ? intval($_POST['voucher_id']) : 0;
    if (!$voucher_id) {
        wp_send_json_error(array('message' => __('Invalid voucher ID.', 'gift-voucher')));
    }

    check_ajax_referer('wpgv_regen_standard_pdf_' . $voucher_id, 'nonce');

    global $wpdb;
    $voucher_table = $wpdb->prefix . 'giftvouchers_list';
    $voucher_data  = $wpdb->get_row($wpdb->prepare("SELECT * FROM $voucher_table WHERE id = %d", $voucher_id));

    if (!$voucher_data) {
        wp_send_json_error(array('message' => __('Voucher not found.', 'gift-voucher')));
    }
    if ($voucher_data->payment_status !== 'Paid') {
        wp_send_json_error(array('message' => __('Voucher is not paid.', 'gift-voucher')));
    }

    $template_info = wpgv_get_voucher_template_kind($voucher_data);
    if (!in_array($template_info['kind'], array('standard_list', 'standard_grid'), true)) {
        wp_send_json_error(array('message' => __('This is not a standard voucher order.', 'gift-voucher')));
    }

    $template_id = intval($template_info['template_id']);
    if (!$template_id) {
        wp_send_json_error(array('message' => __('Template id is invalid for standard voucher.', 'gift-voucher')));
    }

    $pdf_filename_base = wpgv_sanitize_voucher_pdf_basename($voucher_data->voucherpdf_link ? $voucher_data->voucherpdf_link : ($voucher_id . '-' . time()));
    if ($pdf_filename_base === '') {
        $pdf_filename_base = wpgv_sanitize_voucher_pdf_basename($voucher_id . '-' . time());
    }
    $pdf_file_path = wpgv_get_voucher_pdf_path($pdf_filename_base);
    $style_choice = wpgv_get_voucher_pdf_style($voucher_id, 0);

    $pdf_args = wpgv_get_standard_pdf_args_for_voucher($voucher_data, $style_choice);
    if (is_wp_error($pdf_args)) {
        wp_send_json_error(array(
            'message' => $pdf_args->get_error_message(),
            'debug' => array(
                'voucher_id' => $voucher_id,
                'template_kind' => $template_info['kind'],
                'template_id' => $template_id,
                'style' => $style_choice,
            ),
        ));
    }

    $pdf = wpgv_pdf_render_standard_document($pdf_args);
    if (is_wp_error($pdf)) {
        wp_send_json_error(array(
            'message' => $pdf->get_error_message(),
            'debug' => array(
                'voucher_id' => $voucher_id,
                'template_kind' => $template_info['kind'],
                'template_id' => $template_id,
                'style' => $style_choice,
            ),
        ));
    }

    $pdf_created = wpgv_pdf_output_to_file($pdf, $pdf_file_path);
    if (!$pdf_created || !file_exists($pdf_file_path)) {
        wp_send_json_error(array(
            'message' => __('Failed to generate PDF file.', 'gift-voucher'),
            'debug' => array(
                'pdf_file_path' => $pdf_file_path,
                'voucher_id' => $voucher_id,
                'template_kind' => $template_info['kind'],
                'template_id' => $template_id,
                'style' => $style_choice,
            ),
        ));
    }

    wpgv_save_voucher_pdf_style($voucher_id, $style_choice);

    // Trigger receipt regeneration when customer receipts are enabled (same as modern path)
    if (get_option('wpgv_customer_receipt')) {
        require_once WPGIFT__PLUGIN_DIR . '/include/receipt-functions.php';
        if (function_exists('wpgv_generate_receipt_pdf_for_voucher')) {
            wpgv_generate_receipt_pdf_for_voucher($voucher_id);
        }
    }

    wp_send_json_success(array('message' => __('PDF regenerated successfully.', 'gift-voucher'), 'pdf_file' => $pdf_file_path));
}

add_action('wp_ajax_wpgv_admin_regenerate_standard_pdf', 'wpgv_admin_regenerate_standard_pdf_func');
