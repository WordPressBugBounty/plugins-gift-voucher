<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// PDF Style 2

if($formtype == 'item') {
	$wpgv_hide_price = get_option('wpgv_hide_price_item') ? get_option('wpgv_hide_price_item') : 0;
}
else{
	$wpgv_hide_price = get_option('wpgv_hide_price_voucher') ? get_option('wpgv_hide_price_voucher') : 0;
}

$wpgv_leftside_notice = (get_option('wpgv_leftside_notice') != '') ? get_option('wpgv_leftside_notice') : __('Cash payment is not possible. The terms and conditions apply.', 'gift-voucher' );

$pdf = new WPGV_PDF('P','pt',array(595,800));
$pdf->SetAutoPageBreak(0);
$pdf->AddPage();
//Image
$pdf->SetXY(0, 0);
$pdf->SetFillColor($voucher_bgcolor[0], $voucher_bgcolor[1], $voucher_bgcolor[2]);
$pdf->Cell(595,800,'',0,1,'L',1);
$pdf->Image($image, 30, 40, 265, 370);
$pdf->SetFont('Arial','',16);

if($formtype == 'item') {
	//Title
	$pdf->SetXY(310, 90);
	$pdf->SetFont('Arial','B',16);
	$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
	$pdf->SetFontSize(30);
	$pdf->MultiCell(265, 30, wpgv_text_to_pdf_safe(get_the_title($itemid)), 0, 'L');

	//Description
	$pdf->SetXY(310, 130);
	$pdf->SetFont('Arial','',13);
	$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
	$pdf->MultiCell(265, 12, wpgv_text_to_pdf_safe(esc_html(get_post_meta( $itemid, 'description', true ))), 0, 'L');
} else {
	//Voucher
	$pdf->SetXY(310, 100);
	$pdf->SetFont('Arial','B',16);
	$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
	$pdf->SetFontSize(30);
	$pdf->MultiCell(265,30, wpgv_text_to_pdf_safe($template_options->title),0,'L');
}

//For
$pdf->SetFont('Arial','');
$pdf->SetXY(310, 200);
$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
$pdf->SetFontSize(14);
$pdf->Cell(0,0,wpgv_text_to_pdf_safe(__('Your Name', 'gift-voucher')),0,1,'L',0);
//For Input
$pdf->SetXY(313, 210);
$pdf->SetFillColor(255,255,255);
$pdf->SetTextColor(85,85,85);
$pdf->SetFontSize(15);
$pdf->Cell(265,40,' '.wpgv_text_to_pdf_safe($for),0,1,'L',1);

if($buyingfor != 'yourself') {
	//From
	$pdf->SetXY(310, 280);
	$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
	$pdf->SetFontSize(14);
	$pdf->Cell(0,0,wpgv_text_to_pdf_safe(__('Recipient Name', 'gift-voucher')),0,1,'L',0);
	//From Input
	$pdf->SetXY(313, 290);
	$pdf->SetFillColor(255,255,255);
	$pdf->SetTextColor(85,85,85);
	$pdf->SetFontSize(15);
	$pdf->Cell(265,40,' '.wpgv_text_to_pdf_safe($from),0,1,'L',1);
}

if(!$wpgv_hide_price) {
	//Voucher Value
	$pdf->SetXY(310, 360);
	$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
	$pdf->SetFontSize(14);
	$pdf->Cell(0,0,wpgv_text_to_pdf_safe(__('Voucher Value', 'gift-voucher')),0,1,'L',0);
	//Voucher Value Input
	$pdf->SetXY(313, 370);
	$pdf->SetFillColor(255,255,255);
	$pdf->SetTextColor(85,85,85);
	$pdf->SetFontSize(16);
	$pdf->Cell(265,40,' '.wpgv_text_to_pdf_safe($currency),0,1,'L',1);
}

//Personal Message
$pdf->SetXY(30, 440);
$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
$pdf->SetFontSize(14);
$pdf->Cell(0,0,wpgv_text_to_pdf_safe(__('Personal Message', 'gift-voucher')),0,1,'L',0);
//Personal Message Input
$pdf->SetXY(33, 455);
$pdf->SetFillColor(255,255,255);
$pdf->SetTextColor(85,85,85);
$pdf->SetFontSize(15);
$pdf->Cell(546,140,'',0,1,'L',1);

$pdf->SetXY(35, 458);
$pdf->MultiCell(540,23,wpgv_text_to_pdf_safe($message),0,1,'L',1);
//Date of Expiry
$pdf->SetXY(30, 620);
$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
$pdf->SetFontSize(14);
$pdf->Cell(0,0,wpgv_text_to_pdf_safe(__('Date of Expiry', 'gift-voucher')),0,1,'L',0);
//Date of Expiry Input
$pdf->SetXY(33, 630);
$pdf->SetFillColor(255,255,255);
$pdf->SetTextColor(85,85,85);
$pdf->SetFontSize(16);
$pdf->Cell(265,30,' '.wpgv_text_to_pdf_safe($expiry),0,1,'L',1);
//Coupon Code
$pdf->SetXY(310, 620);
$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
$pdf->SetFontSize(14);
$pdf->Cell(0,0,wpgv_text_to_pdf_safe(__('Coupon Code', 'gift-voucher')),0,1,'L',0);
//Coupon Code Input
$pdf->SetXY(313, 630);
$pdf->SetFillColor(255,255,255);
$pdf->SetTextColor(85,85,85);
$pdf->SetFontSize(16);
$pdf->Cell(265,30,' '.wpgv_text_to_pdf_safe($code),0,1,'L',1);

if($wpgv_barcode_on_voucher && !$preview) {
	$pdf->SetFillColor(0,0,0);
	$pdf->Code128(150,680,wpgv_text_to_pdf_safe($code),300,50);
} elseif($wpgv_barcode_on_voucher) {
	$pdf->SetFillColor(0,0,0);
	$pdf->Code128(150,680,'4746489065070412',300,50);
}

//Company Details
$pdf->SetXY(30, 760);
$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
$pdf->SetFontSize(12);
$pdf->Cell(0,0,$setting_options->pdf_footer_url.' | '.wpgv_text_to_pdf_safe($setting_options->pdf_footer_email),0,1,'C',0);
//Terms
$pdf->SetXY(30, 780);
$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
$pdf->SetFontSize(10);
$pdf->Cell(0,0,'* '.wpgv_text_to_pdf_safe($wpgv_leftside_notice),0,1,'C',0);

if($preview) {
	//Put the watermark
	$pdf->SetXY(0, 0);
	$pdf->SetFont('Arial','B',55);
	$pdf->SetTextColor(215,215,215);
	$pdf->RotatedText(75,700,wpgv_text_to_pdf_safe($watermark),45);
}