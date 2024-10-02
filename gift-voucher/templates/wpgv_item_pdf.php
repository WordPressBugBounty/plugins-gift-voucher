<?php
/*
 * Template Name: PDF Viewer Page Template
 * Description: A Page Template for pdf viewer.
 */

if(!isset($_GET['action'])) {
	exit();
}

$watermark = __('This is a preview voucher.', 'gift-voucher');
if(sanitize_text_field($_GET['action']) == 'preview') {
	$watermark = __('This is a preview voucher.', 'gift-voucher');
} else {
	exit();
}

$catid = sanitize_text_field(base64_decode($_GET['catid']));
$itemid = sanitize_text_field(base64_decode($_GET['itemid']));
$buyingfor = sanitize_text_field(base64_decode($_GET['buyingfor']));
$for = sanitize_text_field(base64_decode($_GET['yourname']));
$from = sanitize_text_field(base64_decode($_GET['recipientname']));
$value = sanitize_text_field(base64_decode($_GET['totalprice']));
$message = sanitize_text_field(base64_decode($_GET['recipientmessage']));
$code = '################';

global $wpdb;
$setting_table 	= $wpdb->prefix . 'giftvouchers_setting';
$setting_options = $wpdb->get_row( "SELECT * FROM $setting_table WHERE id = 1" );
$voucher_bgcolor = wpgv_hex2rgb($setting_options->voucher_bgcolor);
$voucher_color = wpgv_hex2rgb($setting_options->voucher_color);
$currency = ($setting_options->currency_position == 'Left') ? $setting_options->currency.' '.$value : $value.' '.$setting_options->currency;

$wpgv_hide_expiry = get_option('wpgv_hide_expiry') ? get_option('wpgv_hide_expiry') : 'yes';
$wpgv_expiry_date_format = get_option('wpgv_expiry_date_format') ? get_option('wpgv_expiry_date_format') : 'd.m.Y';

if($wpgv_hide_expiry == 'no') {
    $expiry = __('No Expiry', 'gift-voucher' );
} else {
	$expiry = ($setting_options->voucher_expiry_type == 'days') ? date($wpgv_expiry_date_format,strtotime('+'.$setting_options->voucher_expiry.' days',time())) . PHP_EOL : $setting_options->voucher_expiry;
}

$formtype = 'item';
$preview = true;

if ($setting_options->is_style_choose_enable) {
	$voucher_style = sanitize_text_field(base64_decode($_GET['style']));
	$style_image = esc_html(get_post_meta($itemid, 'style'.($voucher_style+1).'_image', true));
	$image_attributes = get_attached_file( $style_image );
	$image = ($image_attributes) ? $image_attributes : get_option('wpgv_demoimageurl_item');
} else {
	$voucher_style = $setting_options->voucher_style;
	$style_image = esc_html(get_post_meta($itemid, 'style1_image', true));
	$image_attributes = get_attached_file( $style_image );
	$image = ($image_attributes) ? $image_attributes : get_option('wpgv_demoimageurl_item');
}

switch ($voucher_style) {
	case 0:
		require_once( WPGIFT__PLUGIN_DIR .'/templates/pdfstyles/style1.php');
        break;
	case 1:
    	require_once( WPGIFT__PLUGIN_DIR .'/templates/pdfstyles/style2.php');
        break;
	case 2:
    	require_once( WPGIFT__PLUGIN_DIR .'/templates/pdfstyles/style3.php');
        break;
	default:
    	require_once( WPGIFT__PLUGIN_DIR .'/templates/pdfstyles/style1.php');
        break;
}
ob_clean();
$pdf->Output();