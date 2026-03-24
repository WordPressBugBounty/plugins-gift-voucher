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

    // Variables expected by templates/pdfstyles/receipt.php
    $for = isset($voucher->from_name) ? $voucher->from_name : '';
    $from = isset($voucher->to_name) ? $voucher->to_name : '';
    $email = ($voucher->shipping_email != '') ? $voucher->shipping_email : $voucher->email;
    $value = isset($voucher->amount) ? $voucher->amount : 0;
    $code = isset($voucher->couponcode) ? $voucher->couponcode : '';
    $expiry = isset($voucher->expiry) ? $voucher->expiry : '';
    $paymentmethod = isset($voucher->pay_method) ? $voucher->pay_method : '';
    $lastid = $voucher_id;
    $voucher_options = $voucher; // keep name used in templates
    $payment_status = isset($voucher->payment_status) ? $voucher->payment_status : __('Not Paid', 'gift-voucher');

    $upload = wp_upload_dir();
    $upload_dir = $upload['basedir'];
    $receiptupload_dir = $upload_dir . '/voucherpdfuploads/' . $voucher->voucherpdf_link . '-receipt.pdf';

    // Generate receipt PDF using same template as initial creation
    $receipt = wpgv_create_pdf_safe('P', 'pt', array(595, 900));
    if ($receipt) {
        require_once(WPGIFT__PLUGIN_DIR . '/templates/pdfstyles/receipt.php');
    }

    $wpgv_enable_pdf_saving = get_option('wpgv_enable_pdf_saving') ? get_option('wpgv_enable_pdf_saving') : 0;
    if ($wpgv_enable_pdf_saving) {
        $receipt->Output($receiptupload_dir, 'F');
    } else {
        $receipt->Output('F', $receiptupload_dir);
    }

    return true;
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
