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
$template_options = $wpdb->get_row($wpdb->prepare("SELECT title, image_style FROM {$wpdb->prefix}giftvouchers_template WHERE id = %d", $template));
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
	case 1:
	case 2:
		$pdf = wpgv_pdf_render_standard_document(array(
			'style' => $voucher_style,
			'formtype' => $formtype,
			'image_path' => $image,
			'title' => isset($template_options->title) ? $template_options->title : '',
			'description' => '',
			'for' => $for,
			'from' => $from,
			'buyingfor' => $buyingfor,
			'currency' => $currency,
			'expiry' => $expiry,
			'message' => $message,
			'code' => $code,
			'preview' => true,
			'watermark' => $watermark,
			'voucher_bgcolor' => $voucher_bgcolor,
			'voucher_color' => $voucher_color,
			'footer_url' => isset($setting_options->pdf_footer_url) ? $setting_options->pdf_footer_url : '',
			'footer_email' => isset($setting_options->pdf_footer_email) ? $setting_options->pdf_footer_email : '',
			'hide_price' => get_option('wpgv_hide_price_voucher') ? get_option('wpgv_hide_price_voucher') : 0,
			'leftside_notice' => (get_option('wpgv_leftside_notice') != '') ? get_option('wpgv_leftside_notice') : __('Cash payment is not possible. The terms and conditions apply.', 'gift-voucher'),
			'barcode_enabled' => isset($setting_options->wpgv_barcode_on_voucher) ? $setting_options->wpgv_barcode_on_voucher : 0,
		));
		break;
	default:
		$pdf = new WP_Error('wpgv_pdf_invalid_style', __('Invalid voucher style selected.', 'gift-voucher'));
		break;
}

if (is_wp_error($pdf)) {
	wp_die(esc_html($pdf->get_error_message()));
}
ob_clean();
wpgv_pdf_output_to_browser($pdf);
