<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// PDF Style 3

if($formtype == 'item') {
	$wpgv_hide_price = get_option('wpgv_hide_price_item') ? get_option('wpgv_hide_price_item') : 0;
}
else{
	$wpgv_hide_price = get_option('wpgv_hide_price_voucher') ? get_option('wpgv_hide_price_voucher') : 0;
}

$wpgv_leftside_notice = (get_option('wpgv_leftside_notice') != '') ? get_option('wpgv_leftside_notice') : __('Cash payment is not possible. The terms and conditions apply.', 'gift-voucher' );

$pdf = new WPGV_PDF('P','pt',array(595,700));
$pdf->SetAutoPageBreak(0);
$pdf->AddPage();
$pdf->SetXY(0, 0);
$pdf->SetFillColor($voucher_bgcolor[0], $voucher_bgcolor[1], $voucher_bgcolor[2]);
$pdf->Cell(595,700,'',0,1,'L',1);

if($formtype == 'item') {
	//Title
	$pdf->SetXY(30, 30);
	$pdf->SetFont('Arial','B',16);
	$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
	$pdf->SetFontSize(30);
	$pdf->MultiCell(550, 30, wpgv_em(get_the_title($itemid)), 0, 'C');

	//Description
	$pdf->SetXY(30, 65);
	$pdf->SetFont('Arial','',13);
	$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
	$pdf->MultiCell(550, 12, wpgv_em(esc_html(get_post_meta( $itemid, 'description', true ))), 0, 'C');
} else {
	//Voucher
	$pdf->SetXY(30, 40);
	$pdf->SetFont('Arial','B',16);
	$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
	$pdf->SetFontSize(30);
	$pdf->MultiCell(550,30, wpgv_em($template_options->title),0,'C');
}

//Image
$pdf->Image($image, 30, 100, 265, 210);
//For
$pdf->SetFont('Arial','');
$pdf->SetXY(310, 100);
$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
$pdf->SetFontSize(14);
$pdf->Cell(0,0,wpgv_em(__('Your Name', 'gift-voucher')),0,1,'L',0);
//For Input
$pdf->SetXY(313, 110);
$pdf->SetFillColor(255,255,255);
$pdf->SetTextColor(85,85,85);
$pdf->SetFontSize(15);
$pdf->Cell(265,40,' '.wpgv_text_to_pdf_safe($for),0,1,'L',1);

if($buyingfor != 'yourself') {
	//From
	$pdf->SetXY(310, 180);
	$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
	$pdf->SetFontSize(14);
	$pdf->Cell(0,0,wpgv_em(__('Recipient Name', 'gift-voucher')),0,1,'L',0);
	//From Input
	$pdf->SetXY(313, 190);
	$pdf->SetFillColor(255,255,255);
	$pdf->SetTextColor(85,85,85);
	$pdf->SetFontSize(15);
	$pdf->Cell(265,40,' '.wpgv_text_to_pdf_safe($from),0,1,'L',1);
}

if(!$wpgv_hide_price) {
	//Voucher Value
	$pdf->SetXY(310, 260);
	$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
	$pdf->SetFontSize(14);
	$pdf->Cell(0,0,wpgv_em(__('Voucher Value', 'gift-voucher')),0,1,'L',0);
	//Voucher Value Input
	$pdf->SetXY(313, 270);
	$pdf->SetFillColor(255,255,255);
	$pdf->SetTextColor(85,85,85);
	$pdf->SetFontSize(16);
	$pdf->Cell(265,40,' '.wpgv_em($currency),0,1,'L',1);
}

//Personal Message
$pdf->SetXY(30, 340);
$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
$pdf->SetFontSize(14);
$pdf->Cell(0,0,wpgv_em(__('Personal Message', 'gift-voucher')),0,1,'L',0);
//Personal Message Input
$pdf->SetXY(33, 355);
$pdf->SetFillColor(255,255,255);
$pdf->SetTextColor(85,85,85);
$pdf->SetFontSize(15);
$pdf->Cell(546,140,'',0,1,'L',1);

$pdf->SetXY(35, 358);
$pdf->MultiCell(540,23,wpgv_text_to_pdf_safe($message),0,1,'L',1);
//Date of Expiry
$pdf->SetXY(30, 520);
$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
$pdf->SetFontSize(14);
$pdf->Cell(0,0,wpgv_em(__('Date of Expiry', 'gift-voucher')),0,1,'L',0);
//Date of Expiry Input
$pdf->SetXY(33, 530);
$pdf->SetFillColor(255,255,255);
$pdf->SetTextColor(85,85,85);
$pdf->SetFontSize(16);
$pdf->Cell(265,30,' '.wpgv_em($expiry),0,1,'L',1);
//Coupon Code
$pdf->SetXY(310, 520);
$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
$pdf->SetFontSize(14);
$pdf->Cell(0,0,wpgv_em(__('Coupon Code', 'gift-voucher')),0,1,'L',0);
//Coupon Code Input
$pdf->SetXY(313, 530);
$pdf->SetFillColor(255,255,255);
$pdf->SetTextColor(85,85,85);
$pdf->SetFontSize(16);
$pdf->Cell(265,30,' '.wpgv_em($code),0,1,'L',1);

if($wpgv_barcode_on_voucher && !$preview) {
	$pdf->SetFillColor(0,0,0);
	$pdf->Code128(150, 580, wpgv_em($code), 300, 50);
} elseif($wpgv_barcode_on_voucher) {
	$pdf->SetFillColor(0,0,0);
	$pdf->Code128(150, 580, '4746489065070412', 300, 50);
}

//Company Details
$pdf->SetXY(30, 660);
$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
$pdf->SetFontSize(12);
$pdf->Cell(0,0,$setting_options->pdf_footer_url.' | '.wpgv_em($setting_options->pdf_footer_email),0,1,'C',0);
//Terms
$pdf->SetXY(30, 680);
$pdf->SetTextColor($voucher_color[0],$voucher_color[1],$voucher_color[2]);
$pdf->SetFontSize(10);
$pdf->Cell(0,0,'* '.wpgv_em($wpgv_leftside_notice),0,1,'C',0);

if($preview) {
	//Put the watermark
	$pdf->SetXY(0, 0);
	$pdf->SetFont('Arial','B',55);
	$pdf->SetTextColor(215,215,215);
	$pdf->RotatedText(75,600,wpgv_em($watermark),45);
}