<?php

if (!defined('ABSPATH')) exit;

add_action('wp', 'custom_remove_woocommerce_product_gallery');
function custom_remove_woocommerce_product_gallery()
{
    if (is_product()) {
        $product_id = get_the_ID(); // Get the product ID

        // Get the product object based on ID
        $product = wc_get_product($product_id);

        if (is_a($product, 'WC_Product')) {
            $product_type = $product->get_type();

            // Change the condition to check for 'gift_voucher' product type
            if ($product_type === 'gift_voucher') {
                remove_action('woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20);

                // Add HTML to this area
                add_action('woocommerce_before_single_product_summary', 'custom_additional_html', 20);
            }
        }
    }
}

function custom_additional_html()
{
    global $wpdb; // Add this line to globalize the $wpdb variable

    // Get product object
    $product_id = get_the_ID();
    $product = wc_get_product($product_id);

    // Get product image URL
    $product_image_url = '';
    if ($product) {
        $product_image_id = $product->get_image_id();
        $product_image_url = wp_get_attachment_image_url($product_image_id, 'full');
    }




    // Get other values
    $setting_table = $wpdb->prefix . 'giftvouchers_setting';
    $setting_options = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}giftvouchers_setting WHERE id = %d", 1));


    $voucher_bgcolor = $setting_options->voucher_bgcolor;
    $voucher_color = $setting_options->voucher_color;
    $wpgv_custom_css = get_option('wpgv_custom_css') ? stripslashes(trim(get_option('wpgv_custom_css'))) : '';
    $wpgv_hide_price = get_option('wpgv_item_hide_price') ? get_option('wpgv_item_hide_price') : 0;
    $wpgv_hide_expiry = get_option('wpgv_hide_expiry') ? get_option('wpgv_hide_expiry') : 'yes';
    $wpgv_expiry_date_format = get_option('wpgv_expiry_date_format') ? get_option('wpgv_expiry_date_format') : 'd.m.Y';

    $voucher_value_html = (!$wpgv_hide_price) ? '<div class="voucherValueForm">
                        <label>' . __('Voucher Value', 'gift-voucher') . '</label>
                        <span class="currencySymbol" style="' . (($setting_options->currency_position == 'Left') ? 'left:15px;right:unset;' : 'right:15px;left:unset;') . '"> ' . $setting_options->currency . ' </span>
                        <input style="' . (($setting_options->currency_position == 'Left') ? 'padding-left:30px;padding-right:10px' : 'padding-right:30px;padding-left:10px') . '" type="text" name="voucherValueCard" class="voucherValueCard" readonly>
                    </div>' : '';

    $expiryCard = ($wpgv_hide_expiry == 'no') ? __('No Expiry', 'gift-voucher') : (($setting_options->voucher_expiry_type == 'days') ? date_i18n($wpgv_expiry_date_format, strtotime('+' . $setting_options->voucher_expiry . ' days', time())) . PHP_EOL : $setting_options->voucher_expiry);
    $wpgv_leftside_notice = (get_option('wpgv_leftside_notice') != '') ? get_option('wpgv_leftside_notice') : __('Cash payment is not possible. The terms and conditions apply.', 'gift-voucher');

    // HTML code
    $html = '<div class="wpgv_preview-box wpgvstyle1">
            <div class="cardDiv">
                <div class="cardImgTop">';

    if ($product_image_url === false) {
        $html .= '<p class="error-message">' . __('Please select an image.', 'gift-voucher') . '</p>';
    } else {
        $html .= '<img class="uk-thumbnail" src="' . $product_image_url . '">';
    }

    $html .= '        </div>
                <div class="voucherBottomDiv">
                    <h2 class="itemtitle"></h2>
                    <p class="itemdescription"></p>
                    <div class="uk-form-row">
                        <div class="nameFormLeft">
                            <label>' . __('Your Name', 'gift-voucher') . '</label>
                            <input type="text" name="forNameCard" class="forNameCard" readonly>
                        </div>
                        <div class="nameFormRight">
                            <label>' . __('Recipient Name', 'gift-voucher') . '</label>
                            <input type="text" name="fromNameCard" class="fromNameCard" readonly>
                        </div>
                        ' . $voucher_value_html . '
                        <div class="messageForm">
                            <label>' . __('Personal Message', 'gift-voucher') . '</label>
                            <textarea name="personalMessageCard" class="personalMessageCard" readonly></textarea>
                        </div>
                        <div class="expiryFormLeft">
                            <label>' . __('Date of Expiry', 'gift-voucher') . '</label>
                            <input type="text" name="expiryCard" class="expiryCard" value="' . $expiryCard . '" readonly>
                        </div>
                        <div class="codeFormRight">
                            <label>' . __('Coupon Code', 'gift-voucher') . '</label>
                            <input type="password" name="codeCard" class="codeCard" readonly value="6234256841004311">
                        </div>
                        <div class="clearfix"></div>
                        <div class="voucherSiteInfo"><a href="' . $setting_options->pdf_footer_url . '">' . $setting_options->pdf_footer_url . '</a> | <a href="mailto:' . $setting_options->pdf_footer_email . '">' . $setting_options->pdf_footer_email . '</a></div>
                        <div class="termsCard">* ' . $wpgv_leftside_notice . '</div>
                    </div>
                </div>
            </div>
        </div>';
    $html .= '<style type="text/css">
        .wpgv_preview-box .cardDiv,
        .wpgv-item .wpgv-buy button,
        .buying-options div.selected,
        .shipping-options div.selected,
        .wpgv-buttons .next-button,
        .wpgv-buttons #paynowbtn {
            background-color: #' . $voucher_bgcolor . '!important;
        }
        .wpgv_preview-box .cardDiv .voucherBottomDiv label,
        .wpgv-item .wpgv-buy button,
        .wpgv-buttons .next-button,
        .wpgv-buttons #paynowbtn,
        .buying-options div.selected,
        .shipping-options div.selected,
        .wpgv_preview-box .cardDiv .voucherBottomDiv .itemtitle,
        .wpgv_preview-box .cardDiv .voucherBottomDiv .itemdescription,
        .wpgv_preview-box .cardDiv .voucherBottomDiv .termsCard,
        .wpgv_preview-box .cardDiv .voucherBottomDiv .voucherSiteInfo a {
            color:  #' . $voucher_color . '!important;
        }
			.clearfix {
    zoom: 1
}
    </style>';
?>
    <style type="text/css">
        .clearfix {
            zoom: 1
        }

        .clearfix:before,
        .clearfix:after {
            display: table;
            content: "";
        }

        .clearfix:after {
            clear: both;
        }

        .wpgv-giftitem-wrapper {
            display: flex;
            flex-wrap: wrap;
            position: relative;
            clear: both;
        }

        .wpgv-giftitem-wrapper:before,
        .wpgv-giftitem-wrapper:after {
            content: "";
            display: table;
            clear: both;
        }

        .wpgv-giftitem-wrapper * {
            box-sizing: border-box;
        }

        .wpgv-giftitem-wrapper .mailhidden {
            display: none !important;
        }

        #wpgv-giftitems {
            width: 50%;
            position: relative;
        }

        #wpgv-giftitems-step2,
        #wpgv-giftitems-step3,
        #wpgv-giftitems-step4 {
            display: none;
            padding-left: 30px;
            padding-right: 30px;
        }

        .wpgv-items-wrap {
            background: #fafafa;
            border-radius: 0 0 5px 5px;
            padding: 15px 15px 0;
            margin-top: -15px;
            box-shadow: 0 2px 2px 0 rgba(0, 0, 0, .14), 0 1px 5px 0 rgba(0, 0, 0, .12), 0 3px 1px -2px rgba(0, 0, 0, .2);
        }

        .wpgv-giftitemimage img {
            -webkit-box-shadow: 0 4px 8px 0 rgba(0, 0, 0, .1), 0 1px 16px 0 rgba(0, 0, 0, .1), 0 2px 4px 0 rgba(0, 0, 0, .1);
            box-shadow: 0 4px 8px 0 rgba(0, 0, 0, .1), 0 1px 16px 0 rgba(0, 0, 0, .1), 0 2px 4px 0 rgba(0, 0, 0, .1);
            border-radius: 5px 5px 0 0;
            position: relative;
            z-index: 111;
            width: 100%;
        }

        .wpgv-according-title {
            padding: 20px;
            background: #f2f2f2;
            margin: 0 -15px;
            cursor: pointer;
            border-top: 1px solid #ddd;
            border-bottom: 1px solid #ddd;
            position: relative;
        }

        .wpgv-according-title h2 {
            width: 90%;
            font-size: 20px;
            color: #000;
            font-weight: bold;
            margin: 0;
            line-height: 1.5;
        }

        .wpgv-according-title span {
            font-size: 0.7em;
            font-weight: normal;
            color: #333;
            display: block;
        }

        .wpgv-item {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            padding: 20px;
            margin: 0 -15px;
        }

        .wpgv-item .wpgv-content {
            width: 60%;
            font-size: 0.85em;
        }

        .wpgv-item .wpgv-price {
            width: 25%;
            text-align: right;
            padding-right: 15px;
            color: #333;
        }

        .wpgv-item .wpgv-buy {
            width: 15%;
            text-align: right;
        }

        .wpgv-item .wpgv-buy button {
            padding: 10px 18px;
            border-radius: 5px;
            color: #fff;
            background: #81c6a9;
            line-height: 1;
            cursor: pointer;
            outline: 0
        }

        .wpgv-item:not(:last-child) {
            border-bottom: 1px solid #eee;
        }

        .wpgv-item .wpgv-content h4 {
            color: #000;
            margin-bottom: 0;
            line-height: 1.5;
            font-weight: 400;
        }

        .wpgv-item .wpgv-content p {
            margin-bottom: 0;
        }

        .wpgv-according-title:after {
            content: '';
            position: absolute;
            border: solid black;
            border-width: 0 3px 3px 0;
            display: inline-block;
            padding: 5px;
            transform: rotate(45deg);
            right: 25px;
            top: 50%;
            margin-top: -10px;
            transition: transform 0.3s linear;
        }

        .wpgv-according-category.catclose .wpgv-according-title:after {
            transform: rotate(225deg);
            margin-top: -5px;
        }

        #wpgv-giftitems.loading:after {
            position: absolute;
            content: url(../img/loader.gif);
            background-color: #eee;
            top: 0;
            left: 0;
            z-index: 1111;
            background: rgba(255, 255, 255, 0.8);
            width: 100%;
            height: 100%;
            transform: scale(1);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .wpgv-gifttitle {
            padding: 30px 0;
            text-align: center;
            font-size: 14px;
        }

        .wpgv-gifttitle h3 {
            font-size: 1.5em;
            color: #000;
            font-weight: bold;
        }

        .wpgv-buttons {
            text-align: center;
            padding-bottom: 20px;
        }

        .wpgv-buttons .next-button,
        .wpgv-buttons #paynowbtn {
            display: block;
            width: 100%;
            background: #81c6a9;
            color: #fff;
            border-radius: 5px;
            box-sizing: border-box;
            line-height: 1;
            font-size: 20px;
            margin-bottom: 10px;
            cursor: pointer;
            border: 0;
            padding: 10px;
            outline: 0;
            box-shadow: 0 0 2px 0px rgba(0, 0, 0, 0.2);
        }

        .wpgv-buttons .next-button:after,
        .wpgv-buttons #paynowbtn:after {
            content: "";
            border: solid #fff;
            border-width: 0 2px 2px 0;
            display: inline-block;
            padding: 3px;
            transform: rotate(-45deg);
            vertical-align: middle;
            margin-left: 3px;
        }

        .wpgv-buttons .back-button {
            cursor: pointer;
            font-size: 1.2em;
        }

        .wpgv-items-wrap label {
            display: block;
            color: #000;
            margin-bottom: 3px;
            font-weight: 600;
            font-size: 15px;
        }

        .buying-options,
        .shipping-options {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .buying-options div,
        .shipping-options div {
            width: 50%;
            border: 1px solid #ddd;
            text-align: center;
            padding: 10px;
            background: #fff;
            border-radius: 5px 0 0 5px;
            cursor: pointer;
            box-sizing: border-box;
        }

        .buying-options .yourself,
        .shipping-options .shipping_as_post {
            border-radius: 0 5px 5px 0;
        }

        .buying-options div.selected,
        .shipping-options div.selected {
            background: #81c6a9;
            color: #fff;
        }

        .buying-options div img,
        .shipping-options div img {
            width: 30px;
            margin: 0 auto;
            display: block;
        }

        .wpgv-form-fields {
            margin-bottom: 20px;
        }

        .wpgv-form-fields .error,
        .wpgv-form-fields .eqlerror {
            display: none;
            color: #f00;
            font-size: 0.8em;
        }

        .wpgv-form-fields .form-field {
            width: 100%;
            height: 40px;
            border-radius: 5px;
            padding: 0 10px;
            border: 0;
            background: #fff;
            box-shadow: inset 0 0 3px 1px rgba(0, 0, 0, 0.2);
            box-sizing: border-box;
            margin: 0;
        }

        .wpgv-form-fields .form-field.half-field {
            width: 49%;
            float: left;
        }

        .wpgv-form-fields .form-field.half-field.first {
            margin-right: 2%;
        }

        .wpgv-form-fields textarea.form-field {
            height: 100px;
            padding: 5px 10px;
        }

        .wpgv-form-fields .form-field:focus {
            background: #fff;
            outline: 0;
        }

        .shipping label {
            font-size: 18px;
        }

        #wpgv-giftitems-step3 {
            padding-top: 50px;
        }

        .wpgv-items-wrap label:not(:first-child) {
            font-weight: normal;
        }

        #wpgv-post_name .form-field:not(:last-child) {
            margin-bottom: 10px;
        }

        .order_details_preview {
            margin: 0 -30px;
            border-top: 1px dashed #ddd;
            padding: 30px;
        }

        #wpgv_payment_gateway {
            margin: 0 -30px;
            padding: 30px;
            background: #f2f2f2;
            border-top: 1px solid #ddd;
        }

        #wpgv-giftitems-step3 .wpgv-buttons {
            margin: 0 -30px;
            padding: 0 30px 30px;
            background: #f2f2f2;
        }

        .order_details_preview h3 {
            font-weight: bold;
            font-size: 1.5em;
            margin-bottom: 20px;
        }

        .order_details_preview>div {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .order_details_preview>div:last-child {
            margin: 0;
        }

        .order_details_preview>div> :first-child {
            width: 80%;
        }

        .order_details_preview>div> :last-child {
            width: 20%;
            text-align: right;
            font-size: 16px;
        }

        .order_details_preview h4 {
            font-size: 1.2em;
            margin: 0;
            line-height: 1.3;
            color: #000;
        }

        .order_details_preview span {
            font-size: 0.9em;
            color: #888;
        }

        .order_details_preview>div b,
        .order_details_preview>div b * {
            color: #000;
            font-size: 20px;
        }

        .preview-box #itempreview {
            width: 100%;
            text-align: center;
            padding: 10px;
            border-radius: 5px;
            background: #000;
            color: #fff;
            border: 0;
            outline: 0;
            font-size: 15px;
        }

        #wpgv-post_name,
        #wpgv-shipping_method,
        .order_details_preview .wpgv_shipping_box {
            display: none;
        }

        .wpgv_preview-box {
            width: 40%;
            float: left;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .wpgv_preview-box .cardDiv {
            max-width: 450px;
            margin: 0 0 0 auto;
            background-color: #81c6a9;
            color: #fff;
        }

        .wpgv_preview-box .cardDiv h3 {
            text-align: center;
            background: #fff;
            margin: 0;
            color: #aaa;
            font-size: 14px;
            padding: 10px
        }

        .wpgv_preview-box .cardDiv * {
            font-family: Arial !important;
        }

        .wpgv_preview-box .cardDiv .cardImgTop img {
            width: 100%;
        }

        .wpgv_preview-box .cardDiv .voucherBottomDiv {
            padding: 10px 15px;
            position: relative;
        }

        .wpgv_preview-box .cardDiv .voucherBottomDiv .itemtitle {
            font-size: 25px;
            text-align: center;
            color: #fff;
            line-height: 1.2;
            font-weight: bold;
            margin-top: 0;
            margin-bottom: 15px;
        }

        .wpgv_preview-box .cardDiv .voucherBottomDiv .itemdescription {
            text-align: center;
            font-size: 12px;
            line-height: 1.3;
        }

        .wpgv_preview-box .cardDiv .voucherBottomDiv .nameFormLeft,
        .wpgv_preview-box .cardDiv .voucherBottomDiv .nameFormRight,
        .wpgv_preview-box .cardDiv .voucherBottomDiv .voucherValueForm,
        .wpgv_preview-box .cardDiv .voucherBottomDiv .messageForm,
        .wpgv_preview-box .cardDiv .voucherBottomDiv .expiryFormLeft,
        .wpgv_preview-box .cardDiv .voucherBottomDiv .codeFormRight {
            position: relative;
            width: 50%;
            float: left;
            padding: 0 10px;
            box-sizing: border-box;
        }

        .wpgv_preview-box .cardDiv .voucherBottomDiv .messageForm {
            width: 100%
        }

        .voucherBottomDiv .voucherValueForm .currencySymbol {
            position: absolute;
            left: 15px;
            color: #000;
            line-height: 25px;
            font-weight: 700;
            font-size: 13px;
        }

        .wpgv_preview-box .cardDiv .voucherBottomDiv label {
            font-size: 12px;
            line-height: 1.5;
            display: block;
            margin-bottom: 3px;
            color: #555;
            font-weight: 400;
        }

        .wpgv_preview-box .cardDiv .voucherBottomDiv input[type="password"],
        .wpgv_preview-box .cardDiv .voucherBottomDiv input[type="text"],
        .wpgv_preview-box .cardDiv .voucherBottomDiv input[type="text"]:focus,
        .wpgv_preview-box .cardDiv .voucherBottomDiv input[type="text"]:hover,
        .wpgv_preview-box .cardDiv .voucherBottomDiv textarea,
        .wpgv_preview-box .cardDiv .voucherBottomDiv textarea:hover,
        .wpgv_preview-box .cardDiv .voucherBottomDiv textarea:focus {
            width: 100%;
            height: 25px;
            border: 0;
            display: block;
            border-radius: 0;
            background-color: #fff;
            font-size: 12px;
            padding: 0 10px;
            outline: 0;
            color: #555;
            margin: 0;
            margin-bottom: 10px;
            box-sizing: border-box;
            overflow: hidden;
        }

        .wpgv_preview-box .cardDiv .voucherBottomDiv .voucherValueForm .voucherValueCard,
        .wpgv_preview-box .cardDiv .voucherBottomDiv .voucherValueForm .voucherValueCard:focus,
        .wpgv_preview-box .cardDiv .voucherBottomDiv .voucherValueForm .voucherValueCard:hover {
            padding-left: 20px;
            font-weight: 700;
            font-size: 13px;
        }

        .wpgv_preview-box .cardDiv .voucherBottomDiv textarea,
        .wpgv_preview-box .cardDiv .voucherBottomDiv textarea:hover,
        .wpgv_preview-box .cardDiv .voucherBottomDiv textarea:focus {
            height: 120px;
            resize: none;
            padding: 10px;
            line-height: 1.6;
        }

        .wpgv_preview-box .cardDiv .voucherBottomDiv .voucherSiteInfo {
            text-align: center;
            font-size: 11px;
            color: #fff;
            margin-bottom: 10px;
            padding: 0 10px;
        }

        .wpgv_preview-box .cardDiv .voucherBottomDiv .voucherSiteInfo a {
            color: #fff;
            text-decoration: none;
            border: 0;
            outline: 0;
            box-shadow: none;
        }

        .wpgv_preview-box .cardDiv .voucherBottomDiv .termsCard {
            position: absolute;
            transform: rotate(270deg);
            font-size: 9px;
            left: -188px;
            width: 400px;
            bottom: 210px;
            height: 15px;
        }

        .wpgvmodaloverlay {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
            background: rgba(0, 0, 0, .6);
            display: flex;
            justify-content: center;
            z-index: 999999;
            padding: 30px;
            align-items: flex-start;
            overflow: auto;
            -webkit-overflow-scrolling: touch;
            height: 100vh;
        }

        .wpgvmodaloverlay .wpgvmodalcontent {
            width: 320px;
            background: #eee;
            padding: 20px;
            margin-top: 20px !important;
            border-radius: 5px;
            overflow-y: scroll;
            height: 100vh;
            -webkit-overflow-scrolling: touch;
            box-shadow: 0 0 13px 2px rgba(0, 0, 0, .6)
        }

        .wpgvmodalcontent h4 {
            text-align: center;
            font-size: 20px;
            line-height: 1.4;
            font-weight: 700;
            margin-bottom: 20px
        }

        .wpgvmodalcontent #stripePaymentForm {
            margin: 0;
            padding: 0;
            border: 0
        }

        .wpgvmodalcontent input[type="text"],
        .wpgvmodalcontent input[type="email"] {
            width: 100%;
            background: transparent;
            padding: 0;
            height: 40px;
            border: 0;
            box-shadow: none;
            outline: 0;
            padding: 10px;
            margin: 0 !important;
            box-sizing: border-box;
            display: inline-block
        }

        .wpgvmodalcontent .payeremail,
        .wpgvmodalcontent .paymentinfo {
            margin-bottom: 15px;
            box-sizing: border-box;
            background: #fff;
            border-radius: 4px;
            border: 1px solid #ddd;
            box-shadow: inset 0 0 3px rgba(0, 0, 0, .3)
        }

        .wpgvmodalcontent .paymentinfo .wpgv-card-number {
            border-bottom: 1px solid #ddd
        }

        .wpgvmodalcontent .paymentinfo .wpgv-card-cvc {
            width: 50%;
            border-right: 1px solid #ddd
        }

        .wpgvmodalcontent .paymentinfo .wpgv-card-expiry-month {
            width: 15%;
            padding: 5px 5px 5px 10px
        }

        .wpgvmodalcontent .paymentinfo .wpgv-card-expiry-year {
            width: 30%;
            padding: 5px
        }

        .wpgvmodalcontent #wpgvpayBtn {
            display: block;
            width: 100%;
            background: #3ca940;
            color: #fff;
            border-radius: 5px;
            height: 35px;
            padding: 0;
            margin-bottom: 10px
        }

        .wpgvmodalcontent .wpgv-cancel {
            text-align: center
        }

        @media only screen and (max-width: 1200px) {
            .wpgv-giftitemimage img {
                transform: scale(1.01);
                margin: 0;
            }
        }

        @media only screen and (max-width: 991px) {

            #wpgv-giftitems,
            .wpgv_preview-box {
                width: 100%;
                max-width: 500px;
                margin: 0 auto;
            }
        }

        @media only screen and (max-width: 500px) {
            .wpgv-item {
                padding: 15px;
            }

            .wpgv-item .wpgv-content {
                width: 100%;
            }

            .wpgv-item .wpgv-price,
            .wpgv-item .wpgv-buy {
                width: 50%;
                margin-top: 10px;
                text-align: left;
            }

            .wpgv-item .wpgv-buy {
                text-align: right;
            }
        }

        /* Style 2 */
        .wpgvstyle2.wpgv_preview-box .voucherBottomDiv {
            display: flex;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .wpgvstyle2.wpgv_preview-box .cardImgTop,
        .wpgvstyle2.wpgv_preview-box .sidedetails {
            width: 50%;
            padding: 10px;
        }

        .wpgvstyle2.wpgv_preview-box .cardDiv .voucherBottomDiv .nameFormLeft,
        .wpgvstyle2.wpgv_preview-box .cardDiv .voucherBottomDiv .nameFormRight,
        .wpgvstyle2.wpgv_preview-box .cardDiv .voucherBottomDiv .voucherValueForm {
            width: 100%;
            padding: 0;
        }

        .wpgvstyle2.wpgv_preview-box .cardDiv .voucherBottomDiv .itemtitle,
        .wpgvstyle2.wpgv_preview-box .cardDiv .voucherBottomDiv .itemdescription {
            text-align: left;
        }

        .wpgvstyle2.wpgv_preview-box .cardDiv .voucherBottomDiv .voucherSiteInfo {
            margin-top: 20px;
        }

        .wpgvstyle2.wpgv_preview-box .cardDiv .voucherBottomDiv .termsCard {
            position: static;
            transform: rotate(0);
            text-align: center;
            margin-bottom: 10px;
            font-size: 11px;
        }

        .wpgvstyle2.wpgv_preview-box .cardDiv .voucherBottomDiv textarea,
        .wpgvstyle2.wpgv_preview-box .cardDiv .voucherBottomDiv textarea:hover,
        .wpgvstyle2.wpgv_preview-box .cardDiv .voucherBottomDiv textarea:focus {
            height: 120px;
        }

        .wpgvstyle2.wpgv_preview-box .voucherBottomDiv .voucherValueForm .currencySymbol {
            left: 5px;
        }

        .wpgvstyle2.wpgv_preview-box .voucherBottomDiv .sidedetails {
            align-self: flex-end;
            padding: 0 10px;
        }

        .wpgvstyle2.wpgv_preview-box .cardDiv .voucherBottomDiv .itemtitle {
            margin-bottom: 5px;
        }

        .wpgvstyle2.wpgv_preview-box .cardDiv .cardImgTop img {
            height: 100%;
        }


        /* Style 3 */
        .wpgvstyle3.wpgv_preview-box .voucherBottomDiv {
            display: flex;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .wpgvstyle3.wpgv_preview-box .cardImgTop,
        .wpgvstyle3.wpgv_preview-box .sidedetails {
            width: 50%;
            padding: 0 10px 20px;
        }

        .wpgvstyle3.wpgv_preview-box .cardDiv .voucherBottomDiv .nameFormLeft,
        .wpgvstyle3.wpgv_preview-box .cardDiv .voucherBottomDiv .nameFormRight,
        .wpgvstyle3.wpgv_preview-box .cardDiv .voucherBottomDiv .voucherValueForm {
            width: 100%;
            padding: 0;
        }

        .wpgvstyle3.wpgv_preview-box .cardDiv .voucherBottomDiv .itemtitle,
        .wpgvstyle3.wpgv_preview-box .cardDiv .voucherBottomDiv .itemdescription {
            width: 100%;
            padding: 0 10px;
        }

        .wpgvstyle3.wpgv_preview-box .cardDiv .voucherBottomDiv .voucherSiteInfo {
            margin-top: 20px;
        }

        .wpgvstyle3.wpgv_preview-box .cardDiv .voucherBottomDiv .termsCard {
            position: static;
            transform: rotate(0);
            text-align: center;
            margin-bottom: 10px;
            font-size: 11px;
        }

        .wpgvstyle3.wpgv_preview-box .voucherBottomDiv .voucherValueForm .currencySymbol {
            left: 5px;
        }

        .wpgvstyle3.wpgv_preview-box .voucherBottomDiv .sidedetails {
            align-self: flex-end;
            padding: 0 10px 10px;
        }

        .wpgvstyle3.wpgv_preview-box .cardDiv .voucherBottomDiv .itemtitle {
            margin-bottom: 5px;
        }

        .wpgvstyle3.wpgv_preview-box .cardDiv .cardImgTop img {
            height: 100%;
        }

        .wpgv-giftitem-wrapper .acceptVoucherTerms {
            font-size: 0.9em;
            background: #f2f2f2;
            margin: 0 -30px;
            padding: 0 30px 20px;
        }

        .wpgv-giftitem-wrapper .voucherNote {
            color: #f00;
            font-size: 0.9em;
            background: #f2f2f2;
            margin: 0 -30px;
            padding: 0 30px 20px;
        }
    </style>
    <script>
        jQuery(document).ready(function($) {
            setupEvents();

            function setupEvents() {
                $('#gift-voucher-amount').change(updateValue);
                $('#wpgv_your_name, #wpgv_recipient_name, #wpgv_message').on('input', updateValue);
            }

            function updateValue() {
                var inputId = $(this).attr('id');
                var value = '';

                if (inputId === 'gift-voucher-amount') {
                    value = $('#gift-voucher-amount').find(':selected').text();
                    value = value.replace(/[^\d]/g, '');
                } else {
                    value = $(this).val();
                }

                var targetField = '';
                switch (inputId) {
                    case 'gift-voucher-amount':
                        targetField = 'voucherValueCard';
                        break;
                    case 'wpgv_your_name':
                        targetField = 'forNameCard';
                        break;
                    case 'wpgv_recipient_name':
                        targetField = 'fromNameCard';
                        break;
                    case 'wpgv_message':
                        targetField = 'personalMessageCard';
                        break;
                }

                $('input[name="' + targetField + '"], textarea[name="' + targetField + '"]').val(value);
            }

        });
    </script>



<?php
    echo wp_kses_post($html);
}
