<?php
/*
 * Template Name: PDF Viewer Page Template
 * Description: A Page Template for pdf viewer.
 */

if (!isset($_GET['action'])) {
	exit();
}



$watermark = __('This is a preview voucher.', 'gift-voucher');
if (sanitize_text_field(wp_unslash($_GET['action'])) == 'preview') {
	$watermark = __('This is a preview voucher.', 'gift-voucher');
} else {
	exit();
}

$template = isset($_GET['template']) ? wp_kses_post(base64_decode(wp_unslash($_GET['template']))) : '';
$buyingfor = isset($_GET['buying_for']) ? sanitize_textarea_field(base64_decode(wp_unslash($_GET['buying_for']))) : '';
$for = isset($_GET['for']) ? sanitize_textarea_field(base64_decode(wp_unslash($_GET['for']))) : '';
$from = isset($_GET['from']) ? sanitize_textarea_field(base64_decode(wp_unslash($_GET['from']))) : '';
$value = isset($_GET['value']) ? sanitize_textarea_field(base64_decode(wp_unslash($_GET['value']))) : '';
$message = isset($_GET['message']) ? sanitize_textarea_field(base64_decode(wp_unslash($_GET['message']))) : '';
$expiry = isset($_GET['expiry']) ? sanitize_textarea_field(base64_decode(wp_unslash($_GET['expiry']))) : '';
$code = '################';

global $wpdb;
$setting_table = $wpdb->prefix . 'giftvouchers_setting';
$template_table = $wpdb->prefix . 'giftvouchers_template';

$setting_options = $wpdb->get_row($wpdb->prepare("SELECT * FROM $setting_table WHERE id = %d", 1));
$template_options = $wpdb->get_row($wpdb->prepare("SELECT image_style FROM $template_table WHERE id = %d", $template));
$images = $template_options->image_style ? json_decode($template_options->image_style) : ['', '', ''];


$voucher_bgcolor = wpgv_hex2rgb($setting_options->voucher_bgcolor);
$voucher_color = wpgv_hex2rgb($setting_options->voucher_color);
$currency = ($setting_options->currency_position == 'Left') ? $setting_options->currency . ' ' . $value : $value . ' ' . $setting_options->currency;

$formtype = 'voucher';
$preview = true;

if ($setting_options->is_style_choose_enable) {
	$voucher_style = sanitize_textarea_field(base64_decode($_GET['style']));
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
