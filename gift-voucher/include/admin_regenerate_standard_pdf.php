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


    // This implementation currently regenerates standard-style vouchers via style1 renderer.
    $upload = wp_upload_dir();

    // Always use primary output folder /voucherpdfuploads/ to match Pro behavior.
    $upload_dir_primary = $upload['basedir'] . '/voucherpdfuploads/';
    if (!file_exists($upload_dir_primary)) {
        wp_mkdir_p($upload_dir_primary);
    }

    $pdf_filename_base = sanitize_file_name($voucher_data->voucherpdf_link ? $voucher_data->voucherpdf_link : ($voucher_id . '-' . time()));
    $pdf_file_path = trailingslashit($upload_dir_primary) . $pdf_filename_base . '.pdf';

    require_once WPGIFT__PLUGIN_DIR . '/include/pdf-wrapper.php';

    $template_options = get_post($template_id);
    $template_voucher = $template_options ? get_the_title($template_id) : '';

    // Setup settings/variables expected by style1 template
    $setting_options = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}giftvouchers_setting WHERE id = 1");
    $voucher_bgcolor = wpgv_hex2rgb(isset($setting_options->voucher_bgcolor) ? $setting_options->voucher_bgcolor : '#ffffff');
    $voucher_color = wpgv_hex2rgb(isset($setting_options->voucher_color) ? $setting_options->voucher_color : '#000000');

    $style1_image = get_post_meta($template_id, 'style1_image', true);
    $style2_image = get_post_meta($template_id, 'style2_image', true);
    $style3_image = get_post_meta($template_id, 'style3_image', true);
    $style_images_array = array($style1_image, $style2_image, $style3_image);
    $images = $style_images_array ? $style_images_array : array('', '', '');

    // standard regenerate uses style1 by default (like older standard workflow)
    $style_choice = 0;
    $image_path = (!empty($images[$style_choice])) ? get_attached_file($images[$style_choice]) : '';
    $image = ($image_path && file_exists($image_path)) ? $image_path : get_option('wpgv_demoimageurl_voucher');
    $formtype = 'voucher';
    $preview = false;
    $for = $voucher_data->to_name;
    $from = $voucher_data->from_name;
    $value = $voucher_data->amount;
    $currency = wpgv_price_format($value);
    $message = $voucher_data->message;
    $expiry = $voucher_data->expiry;
    $template = $template_id;
    $itemid = $template_id;
    $code = $voucher_data->couponcode ? $voucher_data->couponcode : $voucher_id;
    $buyingfor = !empty($voucher_data->buying_for) ? $voucher_data->buying_for : 'someone_else';
    $wpgv_enable_pdf_saving = 1; // ensure output path is respected
    $upload_dir = $pdf_file_path; // style1 expects this path when outputting

    $wpgv_hide_expiry = get_option('wpgv_hide_expiry') ? get_option('wpgv_hide_expiry') : 'yes';
    $wpgv_customer_receipt = get_option('wpgv_customer_receipt') ? get_option('wpgv_customer_receipt') : 0;
    $wpgv_expiry_date_format = get_option('wpgv_expiry_date_format') ? get_option('wpgv_expiry_date_format') : 'd.m.Y';
    $wpgv_add_extra_charges = get_option('wpgv_add_extra_charges') ? get_option('wpgv_add_extra_charges') : 0;

    $pdf_created = false;

    // Attempt rendering with existing style1 template (preferred)
    $pdf = wpgv_create_pdf_safe();

    if ($pdf) {
        clearstatcache(true, $pdf_file_path);
        error_log('[wpgv-debug] style1 path: before require style1. pdf_file_path=' . $pdf_file_path);
        error_log('[wpgv-debug] style1 path: available vars: wpgv_enable_pdf_saving=' . intval($wpgv_enable_pdf_saving)
            . ' upload_dir=' . $upload_dir
            . ' formtype=' . $formtype
            . ' image=' . (empty($image) ? 'empty' : 'set')
            . ' voucher_bgcolor=' . json_encode($voucher_bgcolor)
            . ' voucher_color=' . json_encode($voucher_color)
            . ' setting_options=' . json_encode(array(
                'pdf_footer_url' => isset($setting_options->pdf_footer_url) ? $setting_options->pdf_footer_url : '',
                'pdf_footer_email' => isset($setting_options->pdf_footer_email) ? $setting_options->pdf_footer_email : '',
            ))
        );

        require_once(WPGIFT__PLUGIN_DIR . '/templates/pdfstyles/style1.php');

        // style1 template draws onto $pdf; we must output the PDF to file explicitly for this custom regeneration path.
        try {
            $pdf->Output($pdf_file_path, 'F');
        } catch (Exception $e) {
            error_log('[wpgv-debug] style1 path: Output exception: ' . $e->getMessage());
        }

        clearstatcache(true, $pdf_file_path);
        $after_exists = file_exists($pdf_file_path) ? 'yes' : 'no';
        $filesize = $after_exists === 'yes' ? filesize($pdf_file_path) : 0;
        error_log('[wpgv-debug] style1 path: after require style1. file exists=' . $after_exists . ' filesize=' . $filesize);

        if ($after_exists === 'yes' && $filesize > 0) {
            $pdf_created = true;
        }
    } else {
        error_log('[wpgv-debug] style1 path: wpgv_create_pdf_safe returned false');
    }

    // If style1 did not create file, fail immediately (no fallback).
    if (!$pdf_created) {
        error_log('[wpgv-debug] style1 did not create file. failing. pdf_file_path=' . $pdf_file_path);
    }

    if (!$pdf_created || !file_exists($pdf_file_path)) {
        wp_send_json_error(array(
            'message' => __('Failed to generate PDF file with style1.php.', 'gift-voucher'),
            'debug' => array(
                'pdf_file_path' => $pdf_file_path,
                'voucher_id' => $voucher_id,
                'template_kind' => $template_info['kind'],
                'template_id' => $template_id,
            ),
        ));
    }

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
