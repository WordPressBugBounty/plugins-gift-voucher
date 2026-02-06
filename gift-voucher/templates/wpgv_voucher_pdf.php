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

if ($action === 'preview' && wp_verify_nonce($nonce, 'voucher_form_verify')) {
	$watermark = __('This is a preview voucher.', 'gift-voucher');
} else {
	wp_die(esc_html__('Security check failed', 'gift-voucher'));
}

$get = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING) ?: [];

$template  = !empty($get['template'])   ? sanitize_text_field(base64_decode(wp_unslash($get['template']))) : '';
$buyingfor = !empty($get['buying_for']) ? sanitize_text_field(base64_decode(wp_unslash($get['buying_for']))) : '';
$for       = !empty($get['for'])        ? sanitize_text_field(base64_decode(wp_unslash($get['for']))) : '';
$from      = !empty($get['from'])       ? sanitize_text_field(base64_decode(wp_unslash($get['from']))) : '';
$value     = !empty($get['value'])      ? sanitize_text_field(base64_decode(wp_unslash($get['value']))) : '';
$message   = !empty($get['message'])    ? sanitize_textarea_field(base64_decode(wp_unslash($get['message']))) : '';
$expiry    = !empty($get['expiry'])     ? sanitize_text_field(base64_decode(wp_unslash($get['expiry']))) : '';
$code = '################';

global $wpdb;

$setting_options = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}giftvouchers_setting WHERE id = %d", 1));
$template_options = $wpdb->get_row($wpdb->prepare("SELECT image_style FROM {$wpdb->prefix}giftvouchers_template WHERE id = %d", $template));
$images = $template_options->image_style ? json_decode($template_options->image_style) : ['', '', ''];


$voucher_bgcolor = wpgv_hex2rgb($setting_options->voucher_bgcolor);
$voucher_color = wpgv_hex2rgb($setting_options->voucher_color);
$currency = ($setting_options->currency_position == 'Left') ? $setting_options->currency . ' ' . $value : $value . ' ' . $setting_options->currency;

$formtype = 'voucher';
$preview = true;

if ($setting_options->is_style_choose_enable) {
	$voucher_style = !empty($get['style']) ? sanitize_text_field(base64_decode(wp_unslash($get['style']))) : '';
	$image_attributes = get_attached_file($images[$voucher_style]);
	$image = ($image_attributes) ? $image_attributes : get_option('wpgv_demoimageurl_voucher');
} else {
	$voucher_style = 0;
	$image_attributes = get_attached_file($images[0]);
	$image = ($image_attributes) ? $image_attributes : get_option('wpgv_demoimageurl_voucher');
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
