<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// PDF Style 1

if($formtype == 'item') {
	$wpgv_hide_price = get_option('wpgv_hide_price_item') ? get_option('wpgv_hide_price_item') : 0;
}
else{
	$wpgv_hide_price = get_option('wpgv_hide_price_voucher') ? get_option('wpgv_hide_price_voucher') : 0;
}

$wpgv_leftside_notice = (get_option('wpgv_leftside_notice') != '') ? get_option('wpgv_leftside_notice') : __('Cash payment is not possible. The terms and conditions apply.', 'gift-voucher' );

// Ensure optional settings exist to avoid undefined variable warnings
$wpgv_barcode_on_voucher = isset($wpgv_barcode_on_voucher) ? $wpgv_barcode_on_voucher : (isset($setting_options->wpgv_barcode_on_voucher) ? $setting_options->wpgv_barcode_on_voucher : 0);

$pdf = new WPGV_PDF('P','pt',array(595,900));
$pdf->SetAutoPageBreak(0);
$pdf->AddPage();
$pdf->Image($image, 0, 0, 595, 453);
$pdf->SetFont('Arial','',16);
$pdf->SetXY(0, 453);
$pdf->SetFillColor($voucher_bgcolor[0], $voucher_bgcolor[1], $voucher_bgcolor[2]);
$pdf->Cell(595,450,'',0,1,'L',1);

if($formtype == 'item') {
	//Title
	$pdf->SetXY(30, 460);
	$pdf->SetFont('Arial','B',16);
	$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
	$pdf->SetFontSize(25);
	$pdf->MultiCell(550, 25, wpgv_text_to_pdf_safe(get_the_title($itemid)), 0, 'C');

	//Description
	$pdf->SetXY(30, 500);
	$pdf->SetFont('Arial','',12);
	$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
	$pdf->MultiCell(550, 12, wpgv_text_to_pdf_safe(esc_html(get_post_meta( $itemid, 'description', true ))), 0, 'C');
} else {
	//Voucher
	$pdf->SetXY(30, 480);
	$pdf->SetFont('Arial','B',16);
	$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
	$pdf->SetFontSize(25);
	$pdf->MultiCell(550,25, wpgv_text_to_pdf_safe($template_options->title),0,'C');
}
//For
$pdf->SetFont('Arial','');
$pdf->SetXY(30, 540);
$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
$pdf->SetFontSize(12);
$pdf->Cell(0,0,wpgv_text_to_pdf_safe(__('Your Name', 'gift-voucher')),0,1,'L',0);
//For Input
$pdf->SetXY(33, 550);
$pdf->SetFillColor(255,255,255);
$pdf->SetTextColor(85,85,85);
$pdf->SetFontSize(15);
$pdf->Cell(265,30,' '.wpgv_text_to_pdf_safe($for),0,1,'L',1);

if($buyingfor != 'yourself') {
	//From
	$pdf->SetXY(310, 540);
	$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
	$pdf->SetFontSize(12);
	$pdf->Cell(0,0,wpgv_text_to_pdf_safe(__('Recipient Name', 'gift-voucher')),0,1,'L',0);
	//From Input
	$pdf->SetXY(313, 550);
	$pdf->SetFillColor(255,255,255);
	$pdf->SetTextColor(85,85,85);
	$pdf->SetFontSize(15);
	$pdf->Cell(265,30,' '.wpgv_text_to_pdf_safe($from),0,1,'L',1);
}

if(!$wpgv_hide_price) {
	//Voucher Value
	$pdf->SetXY(30, 600);
	$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
	$pdf->SetFontSize(12);
	$pdf->Cell(0,0,wpgv_text_to_pdf_safe(__('Voucher Value', 'gift-voucher')),0,1,'L',0);
	//Voucher Value Input
	$pdf->SetXY(33, 610);
	$pdf->SetFillColor(255,255,255);
	$pdf->SetTextColor(85,85,85);
	$pdf->SetFontSize(16);
	$pdf->Cell(265,30,' '.wpgv_text_to_pdf_safe($currency),0,1,'L',1);
}

//Date of Expiry
$pdf->SetXY(310, 600);
$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
$pdf->SetFontSize(12);
$pdf->Cell(0,0,wpgv_text_to_pdf_safe(__('Date of Expiry', 'gift-voucher')),0,1,'L',0);
//Date of Expiry Input
$pdf->SetXY(313, 610);
$pdf->SetFillColor(255,255,255);
$pdf->SetTextColor(85,85,85);
$pdf->SetFontSize(16);
$pdf->Cell(265,30,' '.wpgv_text_to_pdf_safe($expiry),0,1,'L',1);

//Personal Message
$pdf->SetXY(30, 660);
$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
$pdf->SetFontSize(12);
$pdf->Cell(0,0,wpgv_text_to_pdf_safe(__('Personal Message', 'gift-voucher')),0,1,'L',0);
//Personal Message Input
$pdf->SetXY(33, 670);
$pdf->SetFillColor(255,255,255);
$pdf->SetTextColor(85,85,85);
$pdf->SetFontSize(14);
$pdf->Cell(546,100,'',0,1,'L',1);
$pdf->SetXY(35, 672);
$pdf->MultiCell(543,20,wpgv_text_to_pdf_safe($message),0,1,'L',1);

//Coupon Code
$pdf->SetXY(30, 790);
$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
$pdf->SetFontSize(12);
$pdf->Cell(0,0,wpgv_text_to_pdf_safe(__('Coupon Code', 'gift-voucher')),0,1,'L',0);
//Coupon Code Input
$pdf->SetXY(33, 800);
$pdf->SetFillColor(255,255,255);
$pdf->SetTextColor(85,85,85);
$pdf->SetFontSize(16);
	$pdf->Cell(265,30,' '.wpgv_text_to_pdf_safe($code),0,1,'L',1);

if($wpgv_barcode_on_voucher && !$preview) {
	$pdf->SetFillColor(0,0,0);
	$pdf->Code128(313,790,wpgv_em($code),265,50);
} elseif($wpgv_barcode_on_voucher) {
	$pdf->SetFillColor(0,0,0);
	$pdf->Code128(313,790,'4746489065070412',265,50);
}

//Company Details
$pdf->SetXY(30, 860);
$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
$pdf->SetFontSize(11);
$pdf->Cell(0,0,$setting_options->pdf_footer_url.' | '.wpgv_text_to_pdf_safe($setting_options->pdf_footer_email),0,1,'C',0);

//Terms
$pdf->SetXY(0, 0);
$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
$pdf->SetFontSize(9);
$pdf->RotatedText(20,850,'* '.wpgv_text_to_pdf_safe($wpgv_leftside_notice),90);

if($preview) {
	//Put the watermark
	$pdf->SetXY(0, 0);
	$pdf->SetFont('Arial','B',55);
	$pdf->SetTextColor(215,215,215);
	$pdf->RotatedText(75,700,wpgv_text_to_pdf_safe($watermark),45);
}