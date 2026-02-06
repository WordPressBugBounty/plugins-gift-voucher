<?php
if (!defined('ABSPATH')) exit; // Prevent direct access
/*
 * Template Name: PDF Viewer Page Template
 * Description: A Page Template for pdf viewer.
 */

if (!isset($_GET['action']) || !isset($_GET['nonce'])) {
	exit();
}

$action = sanitize_text_field(wp_unslash($_GET['action']));
$nonce = sanitize_text_field(wp_unslash($_GET['nonce']));

if ($action === 'preview' && wp_verify_nonce($nonce, 'wpgv_giftitems_form_verify')) {
	$watermark = __('This is a preview voucher.', 'gift-voucher');
} else {
	wp_die(esc_html__('Security check failed', 'gift-voucher'));
}



$get = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING) ?: [];
$catid = !empty($get['catid']) ? sanitize_text_field(base64_decode(wp_unslash($get['catid']))) : '';
$itemid    = !empty($get['itemid']) ? sanitize_text_field(base64_decode(wp_unslash($get['itemid']))) : '';
$buyingfor = !empty($get['buyingfor']) ? sanitize_text_field(base64_decode(wp_unslash($get['buyingfor']))) : '';
$for       = !empty($get['yourname']) ? sanitize_text_field(base64_decode(wp_unslash($get['yourname']))) : '';
$from      = !empty($get['recipientname']) ? sanitize_text_field(base64_decode(wp_unslash($get['recipientname']))) : '';
$value     = !empty($get['totalprice']) ? sanitize_text_field(base64_decode(wp_unslash($get['totalprice']))) : '';
$message   = !empty($get['recipientmessage']) ? sanitize_text_field(base64_decode(wp_unslash($get['recipientmessage']))) : '';

$code = '################';

global $wpdb;
$setting_options = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}giftvouchers_setting WHERE id = %d", 1));

$voucher_bgcolor = wpgv_hex2rgb($setting_options->voucher_bgcolor);
$voucher_color = wpgv_hex2rgb($setting_options->voucher_color);
$currency = ($setting_options->currency_position == 'Left') ? $setting_options->currency . ' ' . $value : $value . ' ' . $setting_options->currency;

$wpgv_hide_expiry = get_option('wpgv_hide_expiry') ? get_option('wpgv_hide_expiry') : 'yes';
$wpgv_expiry_date_format = get_option('wpgv_expiry_date_format') ? get_option('wpgv_expiry_date_format') : 'd.m.Y';

if ($wpgv_hide_expiry == 'no') {
	$expiry = __('No Expiry', 'gift-voucher');
} else {
	$expiry = ($setting_options->voucher_expiry_type == 'days')
		? gmdate($wpgv_expiry_date_format, strtotime('+' . $setting_options->voucher_expiry . ' days', time())) . PHP_EOL
		: $setting_options->voucher_expiry;
}

$formtype = 'item';
$preview = true;

if ($setting_options->is_style_choose_enable) {
	$voucher_style = !empty($get['style']) ? sanitize_text_field(base64_decode(wp_unslash($get['style']))) : '';
	$style_image = esc_html(get_post_meta($itemid, 'style' . ($voucher_style + 1) . '_image', true));
	$image_attributes = get_attached_file($style_image);
	$image = ($image_attributes) ? $image_attributes : get_option('wpgv_demoimageurl_item');
} else {
	$voucher_style = $setting_options->voucher_style;
	$style_image = esc_html(get_post_meta($itemid, 'style1_image', true));
	$image_attributes = get_attached_file($style_image);
	$image = ($image_attributes) ? $image_attributes : get_option('wpgv_demoimageurl_item');
}

switch ($voucher_style) {
	case 0:
		require_once(WPGIFT__PLUGIN_DIR . '/templates/pdfstyles/style1.php');
		break;
	case 1:
		require_once(WPGIFT__PLUGIN_DIR . '/templates/pdfstyles/style2.php');
		break;
	case 2:
		require_once(WPGIFT__PLUGIN_DIR . '/templates/pdfstyles/style3.php');
		break;
	default:
		require_once(WPGIFT__PLUGIN_DIR . '/templates/pdfstyles/style1.php');
		break;
}
ob_clean();
$pdf->Output();
