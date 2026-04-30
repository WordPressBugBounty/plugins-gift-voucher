<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Generate or regenerate the customer receipt PDF for a voucher.
 *
 * @param int $voucher_id
 * @return bool True on success, false otherwise
 */
function wpgv_generate_receipt_pdf_for_voucher($voucher_id)
{
    global $wpdb;

    $voucher_table = $wpdb->prefix . 'giftvouchers_list';
    $setting_table = $wpdb->prefix . 'giftvouchers_setting';

    $setting_options = $wpdb->get_row("SELECT * FROM $setting_table WHERE id = 1");
    $voucher = $wpdb->get_row($wpdb->prepare("SELECT * FROM $voucher_table WHERE id = %d", $voucher_id));

    if (!$voucher) {
        return false;
    }

    $receiptupload_dir = wpgv_pdf_get_upload_path($voucher->voucherpdf_link . '-receipt.pdf');

    $template_vars = array(
        'customer_name' => isset($voucher->from_name) ? $voucher->from_name : '',
        'recipient_name' => isset($voucher->to_name) ? $voucher->to_name : '',
        'buyer_email' => ($voucher->shipping_email != '') ? $voucher->shipping_email : $voucher->email,
        'amount_display' => wpgv_price_format(isset($voucher->amount) ? $voucher->amount : 0),
        'coupon_code' => isset($voucher->couponcode) ? $voucher->couponcode : '',
        'expiry_display' => isset($voucher->expiry) ? $voucher->expiry : '',
        'payment_method' => isset($voucher->pay_method) ? $voucher->pay_method : '',
        'payment_status' => isset($voucher->payment_status) ? $voucher->payment_status : __('Not Paid', 'gift-voucher'),
        'order_number' => $voucher_id,
        'order_date' => gmdate('d.m.Y', strtotime(!empty($voucher->voucheradd_time) ? $voucher->voucheradd_time : current_time('mysql'))),
        'company_name' => isset($setting_options->company_name) ? $setting_options->company_name : '',
        'company_email' => isset($setting_options->pdf_footer_email) ? $setting_options->pdf_footer_email : '',
        'company_website' => isset($setting_options->pdf_footer_url) ? $setting_options->pdf_footer_url : '',
        'buying_for' => isset($voucher->buying_for) ? $voucher->buying_for : 'someone_else',
    );

    $html = wpgv_pdf_capture_html_template(WPGIFT__PLUGIN_DIR . '/templates/pdf-html/receipt.php', $template_vars);
    if (is_wp_error($html)) {
        return false;
    }

    $receipt = wpgv_pdf_render_html_document($html, array(
        'engine' => 'dompdf',
        'paper' => array(0, 0, 595, 900),
        'orientation' => 'portrait',
        'default_font' => 'DejaVu Sans',
        'chroot' => array(
            str_replace('\\', '/', ABSPATH),
            str_replace('\\', '/', WPGIFT__PLUGIN_DIR),
            str_replace('\\', '/', wp_get_upload_dir()['basedir']),
        ),
    ));

    if (is_wp_error($receipt)) {
        return false;
    }

    return wpgv_pdf_output_to_file($receipt, $receiptupload_dir);
}

/**
 * Placeholder for regenerate-PDF functionality.
 *
 * This function is intentionally left as a no-op because the current
 * plugin version does not support regenerating the voucher PDF.
 *
 * @param int $voucher_id
 * @return bool Always returns false.
 */
function wpgv_regenerate_voucher_pdf_for_voucher($voucher_id)
{
    // PDF regeneration is not supported in the current release.
    // This placeholder exists only to avoid fatal errors if called.
    return false;
}
