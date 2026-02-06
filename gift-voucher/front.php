<?php

if (!defined('ABSPATH')) exit;  // Exit if accessed directly

// Add Voucher Shortcode
function wpgv_voucher_shortcode()
{
    global $wp, $wpdb;
    $html = '';
    $find = array('http://', 'https://');
    $replace = '';
    $siteURL = str_replace($find, $replace, get_site_url());
    $voucher_table     = $wpdb->prefix . 'giftvouchers_list';
    $setting_table     = $wpdb->prefix . 'giftvouchers_setting';
    $template_table = $wpdb->prefix . 'giftvouchers_template';
    wp_enqueue_style('wpgv-voucher-style');
    wp_enqueue_script('wpgv-jquery-validate');
    wp_enqueue_script('wpgv-jquery-steps');
    wp_enqueue_script('wpgv-stripe-js');
    wp_enqueue_script('wpgv-paypal-js');
    wp_enqueue_script('wpgv-voucher-script');
    $wpgv_termstext = get_option('wpgv_termstext') ? get_option('wpgv_termstext') : 'I hereby accept the terms and conditions, the revocation of the privacy policy and confirm that all information is correct.';

    $setting_options = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}giftvouchers_setting WHERE id = %d", 1));

    $template_options = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}giftvouchers_template WHERE active = %d", 1));

    $nonce = wp_create_nonce('voucher_form_verify');
    $wpgv_custom_css = get_option('wpgv_custom_css') ? stripslashes(trim(get_option('wpgv_custom_css'))) : '';

    $wpgv_stripe_alternative_text = get_option('wpgv_stripe_alternative_text') ? get_option('wpgv_stripe_alternative_text') : 'Stripe';

    $wpgv_buying_for = get_option('wpgv_buying_for') ? get_option('wpgv_buying_for') : 'both';
    $wpgv_add_extra_charges = 0;

    if ($wpgv_buying_for == 'both') {
        $buying_for_html = '<div class="buying-for flex-field">
                <label>' . __('Buying For', 'gift-voucher') . '</label>
                <div class="buying-options">
                    <div class="someone_else selected" data-value="someone_else">
                        <img src="' . esc_url(WPGIFT__PLUGIN_URL . '/assets/img/giftbox.png') . '">
                        <span>' . __('Someone Else', 'gift-voucher') . '</span>
                    </div>
                    <div class="yourself" data-value="yourself">
                        <img src="' . esc_url(WPGIFT__PLUGIN_URL . '/assets/img/users.png') . '">
                        <span>' . __('Yourself', 'gift-voucher') . '</span>
                    </div>
                </div>
                <input type="hidden" name="buying_for" id="buying_for" value="' . esc_html("someone_else") . '">
            </div>
            <div class="form-group">
                <label for="voucherForName">' . __('Your Name', 'gift-voucher') . ' <sup>*</sup></label>
                <input type="text" name="voucherForName" id="voucherForName" class="required">
            </div>
            <div class="form-group fromname">
                <label for="voucherFromName">' . __('Recipient Name', 'gift-voucher') . ' <sup>*</sup></label>
                <input type="text" name="voucherFromName" id="voucherFromName" class="required">
            </div>';
    } else {
        if ($wpgv_buying_for == 'someone_else') {
            $buying_for_html = '<input type="hidden" name="buying_for" id="buying_for" value="' . esc_html("someone_else") . '">
            <div class="form-group">
                <label for="voucherForName">' . __('Your Name', 'gift-voucher') . ' <sup>*</sup></label>
                <input type="text" name="voucherForName" id="voucherForName" class="required">
            </div>
            <div class="form-group fromname">
                <label for="voucherFromName">' . __('Recipient Name', 'gift-voucher') . ' <sup>*</sup></label>
                <input type="text" name="voucherFromName" id="voucherFromName" class="required">
            </div>';
        } else {
            $buying_for_html = '<input type="hidden" name="buying_for" id="buying_for" value="' . esc_html("yourself") . '">
            <div class="form-group">
                <label for="voucherForName">' . __('Your Name', 'gift-voucher') . ' <sup>*</sup></label>
                <input type="text" name="voucherForName" id="voucherForName" class="required">
            </div>';
        }
    }

    $wpgv_hide_price = get_option('wpgv_hide_price_voucher') ? get_option('wpgv_hide_price_voucher') : 0;
    $wpgv_leftside_notice = (get_option('wpgv_leftside_notice') != '') ? get_option('wpgv_leftside_notice') : __('Cash payment is not possible. The terms and conditions apply.', 'gift-voucher');

    $voucher_value_html = (!$wpgv_hide_price) ? '<div class="voucherValueForm">
                        <label>' . __('Voucher Value', 'gift-voucher') . '</label>
                        <span class="currencySymbol"> ' . esc_attr($setting_options->currency) . ' </span>
                        <input type="text" name="voucherValueCard" class="voucherValueCard" readonly>
                    </div>' : '';

    $voucher_brcolor = get_option('wpgv_voucher_border_color') ? get_option('wpgv_voucher_border_color') : '81c6a9';
    $voucher_bgcolor = $setting_options->voucher_bgcolor;
    $voucher_color = $setting_options->voucher_color;

    $minVoucherValue = $setting_options->voucher_min_value ? $setting_options->voucher_min_value : 1;
    // translators: %s: minimum voucher value
    $minVoucherValueMsg = $setting_options->voucher_min_value ? sprintf(__('(Min Voucher Value %s)', 'gift-voucher'), wpgv_price_format($setting_options->voucher_min_value)) : '';

    $maxVoucherValue = $setting_options->voucher_max_value ? $setting_options->voucher_max_value : 10000;
    $custom_loader = $setting_options->custom_loader ? $setting_options->custom_loader : WPGIFT__PLUGIN_URL . '/assets/img/loader.gif';

    $shipping_methods = explode(',', $setting_options->shipping_method);
    $shipping_methods_string = '';
    foreach ($shipping_methods as $method) {
        if ($method != '') {
            $shipping_method = explode(':', $method);
            $shipping_methods_string .= '<label data-value="' . trim(esc_attr($shipping_method[0] ?? '')) . '">
    <input type="radio" name="shipping_method" value="' . esc_attr(trim(stripslashes($shipping_method[1] ?? ''))) . '" class="radio-field">
    ' . esc_attr(trim(stripslashes($shipping_method[1] ?? ''))) . '
</label>';
        }
    }

    $html .= '<style type="text/css">
        #voucher-multistep-form .secondRightDiv .cardDiv{
            background-color: #' . esc_attr($voucher_bgcolor) . '!important;
        }
        #voucher-multistep-form.wizard>.steps .done a,
        #voucher-multistep-form.wizard>.steps .done a:hover,
        #voucher-multistep-form.wizard>.steps .done a:active,
        #voucher-multistep-form.wizard>.actions a,
        #voucher-multistep-form.wizard>.actions a:hover,
        #voucher-multistep-form.wizard>.actions a:active,
        #voucher-multistep-form .voucherPreviewButton button,
        #voucher-multistep-form #voucherPaymentButton,
        #voucher-multistep-form .sin-template input[type="radio"]:checked:before,
        .buying-options div.selected, .shipping-options div.selected {
            background-color: #' . esc_attr($voucher_brcolor) . '!important;
        }
        #voucher-multistep-form .content .voucherform .form-group input[type="text"],
        #voucher-multistep-form .content .form-group input[type="email"],
        #voucher-multistep-form .content .form-group input[type="tel"],
        #voucher-multistep-form .content .form-group input[type="number"],
        #voucher-multistep-form .content .form-group select,
        #voucher-multistep-form .content .form-group textarea,
        #voucher-multistep-form .content .sin-template label.selectImage {
            border-color: #' . esc_attr($voucher_brcolor) . '!important;
        }
        #voucher-multistep-form .paymentUserInfo .full,
        #voucher-multistep-form .paymentUserInfo .half,
        #voucher-multistep-form .secondRightDiv .voucherBottomDiv h2,
        #voucher-multistep-form .voucherBottomDiv .termsCard,
        #voucher-multistep-form .voucherBottomDiv .voucherSiteInfo a {
            color: #' . esc_attr($voucher_color) . '!important;
        }
        #voucher-multistep-form.wizard>.content>.body .voucherBottomDiv label{
            color:  #' . esc_attr($voucher_color) . '!important;
        }
        #voucher-multistep-form.wizard>.content>.body.loading.current:after {
            content: url(' . esc_url($custom_loader) . ') !important;
        }
    </style>';

    $chooseStyle = '';
    if ($setting_options->is_style_choose_enable) {
        $voucher_styles = json_decode($setting_options->voucher_style);
        $chooseStyle = '<label for="chooseStyle">' . __('Choose Voucher Style', 'gift-voucher') . ' <sup>*</sup></label><select name="chooseStyle" id="chooseStyle" class="required">';
        foreach ($voucher_styles as $key => $value) {
            $chooseStyle .= '<option value="' . esc_attr($value) . '">' . __('Style', 'gift-voucher') . ' ' . esc_html(($value + 1)) . '</option>';
        }
        $chooseStyle .= '</select>';
    }

    $paymenyGateway = __('Payment Method', 'gift-voucher');
    if ($setting_options->paypal || $setting_options->sofort || $setting_options->stripe || $setting_options->per_invoice) {
        $paymenyGateway = '<select name="voucherPayment" id="voucherPayment" class="required">';
        $paymenyGateway .= $setting_options->paypal ? '<option value="' . esc_html("Paypal") . '">' . __('Paypal', 'gift-voucher') . '</option>' : '';
        $paymenyGateway .= $setting_options->sofort ? '<option value="' . esc_html("Sofort") . '">' . __('Sofort', 'gift-voucher') . '</option>' : '';
        $paymenyGateway .= $setting_options->stripe ? '<option value="' . esc_html("Stripe") . '">' . esc_html($wpgv_stripe_alternative_text) . '</option>' : '';
        $paymenyGateway .= $setting_options->per_invoice ? '<option value="' . esc_html("Per Invoice") . '">' . __('Per Invoice', 'gift-voucher') . '</option>' : '';
        $paymenyGateway .= '</select>';
    }

    $paymentCount = $setting_options->paypal + $setting_options->sofort + $setting_options->stripe;

    $wpgv_hide_expiry = get_option('wpgv_hide_expiry') ? get_option('wpgv_hide_expiry') : 'yes';
    $wpgv_expiry_date_format = get_option('wpgv_expiry_date_format') ? get_option('wpgv_expiry_date_format') : 'd.m.Y';

    if ($wpgv_hide_expiry == 'no') {
        $expiryCard = __('No Expiry', 'gift-voucher');
    } else {
        $expiryCard = ($setting_options->voucher_expiry_type == 'days')
            ? gmdate($wpgv_expiry_date_format, strtotime('+' . $setting_options->voucher_expiry . ' days', time())) . PHP_EOL
            : $setting_options->voucher_expiry;
    }

    $voucherstyle1 = '<div class="sideview secondRight secondRightDiv voucherstyle1">
        <div class="cardDiv">
            <div class="cardImgTop">
                <img class="uk-thumbnail" src="' . esc_url(get_option('wpgv_demoimageurl')) . '">
            </div>
            <div class="voucherBottomDiv">
                <h2>' . __('Gift Voucher', 'gift-voucher') . '</h2>
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
                        <input type="text" name="expiryCard" class="expiryCard" value="' . esc_attr($expiryCard) . '" readonly>
                    </div>
                    <div class="codeFormRight">
                        <label>' . __('Coupon Code', 'gift-voucher') . '</label>
                        <input type="text" name="codeCard" class="codeCard" readonly>
                    </div>
                    <div class="clearfix"></div>
                    <div class="voucherSiteInfo"><a href="' . esc_url($setting_options->pdf_footer_url) . '">' . esc_url($setting_options->pdf_footer_url) . '</a> | <a href="mailto:' . esc_url($setting_options->pdf_footer_email) . '">' . esc_html($setting_options->pdf_footer_email) . '</a></div>
                    <div class="termsCard">* ' . esc_html($wpgv_leftside_notice) . '</div>
                </div></div></div>
        </div>';

    $voucherstyle2 = '<div class="sideview secondRight secondRightDiv voucherstyle2">
        <div class="cardDiv">
            <div class="voucherBottomDiv">
                <div class="cardImgTop">
                    <img class="uk-thumbnail" src="' . esc_url(get_option('wpgv_demoimageurl')) . '">
                </div>
                <div class="sidedetails">
                    <h2>' . __('Gift Voucher', 'gift-voucher') . '</h2>
                    <div class="nameFormLeft">
                        <label>' . __('Your Name', 'gift-voucher') . '</label>
                        <input type="text" name="forNameCard" class="forNameCard" readonly>
                    </div>
                    <div class="nameFormRight">
                        <label>' . __('Recipient Name', 'gift-voucher') . '</label>
                        <input type="text" name="fromNameCard" class="fromNameCard" readonly>
                    </div>
                    ' . $voucher_value_html . '
                </div>
                <div class="uk-form-row">
                    <div class="messageForm">
                        <label>' . __('Personal Message', 'gift-voucher') . '</label>
                        <textarea name="personalMessageCard" class="personalMessageCard" readonly></textarea>
                    </div>
                    <div class="expiryFormLeft">
                        <label>' . __('Date of Expiry', 'gift-voucher') . '</label>
                        <input type="text" name="expiryCard" class="expiryCard" value="' . esc_attr($expiryCard) . '" readonly>
                    </div>
                    <div class="codeFormRight">
                        <label>' . __('Coupon Code', 'gift-voucher') . '</label>
                        <input type="text" name="codeCard" class="codeCard" readonly>
                    </div>
                    <div class="clearfix"></div>
                    <div class="voucherSiteInfo"><a href="' . esc_url($setting_options->pdf_footer_url) . '">' . esc_url($setting_options->pdf_footer_url) . '</a> | <a href="mailto:' . esc_url($setting_options->pdf_footer_email) . '">' . esc_html($setting_options->pdf_footer_email) . '</a></div>
                    <div class="termsCard">* ' . esc_html($wpgv_leftside_notice) . '</div>
                </div></div></div>
        </div>';

    $voucherstyle3 = '<div class="sideview secondRight secondRightDiv voucherstyle3">
        <div class="cardDiv">
            <div class="voucherBottomDiv">
                <h2>' . __('Gift Voucher', 'gift-voucher') . '</h2>
                <div class="cardImgTop">
                    <img class="uk-thumbnail" src="' . esc_url(get_option('wpgv_demoimageurl')) . '">
                </div>
                <div class="sidedetails">
                    <div class="nameFormLeft">
                        <label>' . __('Your Name', 'gift-voucher') . '</label>
                        <input type="text" name="forNameCard" class="forNameCard" readonly>
                    </div>
                    <div class="nameFormRight">
                        <label>' . __('Recipient Name', 'gift-voucher') . '</label>
                        <input type="text" name="fromNameCard" class="fromNameCard" readonly>
                    </div>
                    ' . $voucher_value_html . '
                </div>
                <div class="uk-form-row">
                    <div class="messageForm">
                        <label>' . __('Personal Message', 'gift-voucher') . '</label>
                        <textarea name="personalMessageCard" class="personalMessageCard" readonly></textarea>
                    </div>
                    <div class="expiryFormLeft">
                        <label>' . __('Date of Expiry', 'gift-voucher') . '</label>
                        <input type="text" name="expiryCard" class="expiryCard" value="' . esc_attr($expiryCard) . '" readonly>
                    </div>
                    <div class="codeFormRight">
                        <label>' . __('Coupon Code', 'gift-voucher') . '</label>
                        <input type="text" name="codeCard" class="codeCard" readonly>
                    </div>
                    <div class="clearfix"></div>
                    <div class="voucherSiteInfo"><a href="' . esc_url($setting_options->pdf_footer_url) . '">' . esc_url($setting_options->pdf_footer_url) . '</a> | <a href="mailto:' . esc_url($setting_options->pdf_footer_email) . '">' . esc_html($setting_options->pdf_footer_email) . '</a></div>
                    <div class="termsCard">* ' . esc_html($wpgv_leftside_notice) . '</div>
                </div></div></div>
        </div>';

    $voucherstyle = '';
    if ($setting_options->is_style_choose_enable) {
        $voucher_styles = json_decode($setting_options->voucher_style);
        foreach ($voucher_styles as $key => $value) {
            $voucherstyle .= ${'voucherstyle' . ($value + 1)};
        }
    } else {
        switch ($setting_options->voucher_style) {
            case 0:
                $voucherstyle = $voucherstyle1;
                break;
            case 1:
                $voucherstyle = $voucherstyle2;
                break;
            case 2:
                $voucherstyle = $voucherstyle3;
                break;
            default:
                $voucherstyle = $voucherstyle1;
                break;
        }
    }

    $html .= '<form name="voucherform" id="voucher-multistep-form" action="' . esc_url(home_url($wp->request)) . '" enctype="multipart/form-data">
        <input type="hidden" name="voucher_form_verify" value="' . esc_attr($nonce) . '">
        <input type="hidden" name="wpgv_total_price" id="total_price">
        <input type="hidden" name="wpgv_website_commission_price" id="website_commission_price" data-price="' . esc_html($wpgv_add_extra_charges) . '">
        <h3>' . __('Select Templates', 'gift-voucher') . '</h3>
        <fieldset>
            <legend>' . __('Select Templates', 'gift-voucher') . '</legend><div class="voucher-row">';
    foreach ($template_options as $key => $options) {

        $images = $options->image_style ? json_decode($options->image_style) : ['', '', ''];
        $image_attributes = wp_get_attachment_image_src($images[0], 'voucher-thumb');
        $image = ($image_attributes) ? $image_attributes[0] : get_option('wpgv_demoimageurl');
        $html .= '<div class="vouchercol' . esc_html($setting_options->template_col) . '"><div class="sin-template"><label for="template_id' . esc_html($options->id) . '"><img src="' . esc_url($image) . '" width=""/><span>' . esc_html($options->title) . '</span></label><input type="radio" name="template_id" value="' . esc_attr($options->id) . '" id="template_id' . esc_html($options->id) . '" class="required"></div></div>';
    }
    $html .= '</div></fieldset>

	<h3>' . __('Personalize', 'gift-voucher') . '</h3>
	<fieldset>
		<legend>' . __('Personalize', 'gift-voucher') . '</legend><div class="voucher-row">
		<div class="voucherform secondLeft">
            <div class="form-group">
                ' . $chooseStyle . '
            </div>
            ' . $buying_for_html . '
            <div class="form-group">
                <label for="voucherAmount">' . __('Voucher Value', 'gift-voucher') . ' ' . esc_html($minVoucherValueMsg) . '<sup>*</sup></label>
                <span class="currencySymbol"> ' . esc_html($setting_options->currency) . ' </span>
                <input type="number" name="voucherAmount" id="voucherAmount" class="required" min="' . esc_html($minVoucherValue) . '" max="' . esc_html($maxVoucherValue) . '">
            </div>
            <div class="form-group">
                <label for="voucherMessage">' . __('Personal Message (Optional)', 'gift-voucher') . ' (' . __('Max: 250 Characters', 'gift-voucher') . ')</label>
                <textarea name="voucherMessage" id="voucherMessage" maxlength="250"></textarea>
                <div class="maxchar"></div>';

    // prepare border color and zebra backgrounds for quote items
    $voucher_brcolor_hex = ltrim($voucher_brcolor, '#');
    if (strlen($voucher_brcolor_hex) === 3) {
        $voucher_brcolor_hex = $voucher_brcolor_hex[0] . $voucher_brcolor_hex[0]
            . $voucher_brcolor_hex[1] . $voucher_brcolor_hex[1]
            . $voucher_brcolor_hex[2] . $voucher_brcolor_hex[2];
    }
    $r = hexdec(substr($voucher_brcolor_hex, 0, 2));
    $g = hexdec(substr($voucher_brcolor_hex, 2, 2));
    $b = hexdec(substr($voucher_brcolor_hex, 4, 2));
    $li_bg1 = "background-color: rgba($r,$g,$b,0.08);";
    $li_bg2 = "background-color: rgba($r,$g,$b,0.16);";

    // Load quotes (JSON) so we can render suggestions
    $quotes_raw = get_option('wpgv_quotes', '');
    $quotes = array();
    if (!empty($quotes_raw)) {
        $decoded = json_decode($quotes_raw, true);
        if (is_array($decoded)) {
            $quotes = $decoded;
        }
    }
    $has_quotes = !empty($quotes);

    $html .= '<div class="voucher-quotes" id="voucher-quotes" style="font-size: 12px;margin: 5px 0 0; font-style: italic;' . ($has_quotes ? '' : ' display:none;') . '">
            <span>' . __('Quotes:', 'gift-voucher') . '</span>
            <style>
              /* spacing and hover effect for voucher description suggestions */
              #voucher-multistep-form .voucher-quotes ul li {
                margin: 5px 0;
                cursor: pointer;
              }
              #voucher-multistep-form .voucher-quotes ul li:hover {
                box-shadow: 2px 2px 5px rgba(0,0,0,0.1);
              }
              #voucher-multistep-form .voucher-quotes .show-more-quotes {
                display: inline-block; margin-top: 6px; font-style: normal; cursor: pointer; color: #0073aa;
              }
              #voucher-multistep-form .voucher-quotes .show-more-quotes:hover {
                text-decoration: underline;
              }
            </style>
            <ul style="margin: 5px 0 0 18px; padding: 0;">';
    if (!empty($quotes)) {
        $total_quotes = count($quotes);
        foreach ($quotes as $i => $qtext) {
            $zebra = ($i % 2 === 0) ? $li_bg1 : $li_bg2;
            $hidden = ($i >= 5) ? 'display:none;' : '';
            $html .= '<li class="quote-item" style="' . $zebra . ' ' . $hidden . ' padding: 4px 6px; list-style: none;border-left: 3px solid #' . $voucher_brcolor_hex . '; border-radius: 3px; cursor: pointer;">' . esc_html($qtext) . '</li>';
        }
    }
    $html .= '</ul>';
    if (!empty($quotes) && $total_quotes > 5) {
        $html .= '<a href="javascript:;" class="show-more-quotes" data-shown="5" data-step="5">' . __('Show more', 'gift-voucher') . '</a>';
    }
    $html .= '</div>
            </div>
        </div>
        ' . $voucherstyle . '
        </div>
    </fieldset>';

    $html .= '<h3>' . __('Payment', 'gift-voucher') . '</h3>
    <fieldset>
        <legend>' . __('Payment', 'gift-voucher') . '</legend><div class="voucher-row">';

    if ($setting_options->post_shipping) {
        $html .= '<div class="voucherform secondLeft">
            <div class="shipping flex-field">
                <label><b>' . __('Shipping', 'gift-voucher') . '</b></label>
                <div class="shipping-options">
                    <div class="shipping_as_email selected" data-value="shipping_as_email">
                        <img src="' . esc_url(WPGIFT__PLUGIN_URL . '/assets/img/envelope.png') . '">
                        <span>' . __('Email', 'gift-voucher') . '</span>
                    </div>
                    <div class="shipping_as_post" data-value="shipping_as_post">
                        <img src="' . esc_url(WPGIFT__PLUGIN_URL . '/assets/img/delivery-truck.png') . '">
                        <span>' . __('Post', 'gift-voucher') . '</span>
                    </div>
                </div>
                <input type="hidden" name="shipping" id="shipping" value="' . esc_attr("shipping_as_email") . '">
            </div>
            <div class="form-group" id="wpgv-shipping_email">
                <label for="shipping_email">' . __('Send the voucher to recipient email here', 'gift-voucher') . '</label>
                <input type="email" name="shipping_email" id="shipping_email" class="form-field required">
            </div>
            <div class="form-group">
                <label for="voucherEmail">' . __('Your email address (for the receipt)', 'gift-voucher') . '</label>
                <input type="email" name="voucherEmail" id="voucherEmail" class="form-field required">
            </div>
			<div class="form-group wpgv-post-data">
				<label for="voucherFirstName">' . __('First Name', 'gift-voucher') . ' <sup>*</sup></label>
				<input type="text" name="voucherFirstName" id="voucherFirstName" class="required">
			</div>
			<div class="form-group wpgv-post-data">
				<label for="voucherLastName">' . __('Last Name', 'gift-voucher') . ' <sup>*</sup></label>
				<input type="text" name="voucherLastName" id="voucherLastName" class="required">
			</div>
			<div class="form-group wpgv-post-data">
				<label for="voucherAddress">' . __('Address', 'gift-voucher') . ' <sup>*</sup></label>
    			<input type="text" name="voucherAddress" id="voucherAddress" class="required">
    		</div>
    		<div class="form-group wpgv-post-data">
    			<label for="voucherPincode">' . __('Postcode', 'gift-voucher') . '</label>
    			<input type="text" name="voucherPincode" id="voucherPincode">
    		</div>
            <div class="form-group wpgv-post-data" id="wpgv-shipping_method">
                <label id="shipping_method">' . __('Shipping method', 'gift-voucher') . '</label>
                ' . $shipping_methods_string . '
            </div>';
    } else {
        $html .= '<div class="voucherform secondLeft">
            <input type="hidden" name="shipping" id="shipping" value="' . esc_attr("shipping_as_email") . '">
            <div class="form-group" id="wpgv-shipping_email">
                <label for="shipping_email">' . __('Send the voucher to recipient email here', 'gift-voucher') . '</label>
                <input type="email" name="shipping_email" id="shipping_email" class="form-field required">
            </div>
            <div class="form-group">
                <label for="voucherEmail">' . __('Your email address (for the receipt)', 'gift-voucher') . '</label>
                <input type="email" name="voucherEmail" id="voucherEmail" class="form-field required">
            </div>';
    }

    $html .= '<div class="form-group paymentcount' . esc_html($paymentCount) . '">
                <label for="voucherPayment">' . __('Payment Method', 'gift-voucher') . ' <sup>*</sup></label>' . $paymenyGateway . '
            </div>
            <div class="order_details_preview">
                <h3>' . __('Your Order', 'gift-voucher') . '</h3>
                <div class="wpgv_preview_box">
                    <div>
                        <h4 class="wpgv-itemtitle">-</h4>
                        <span>' . __('Your Name', 'gift-voucher') . ': <i id="autoyourname"></i></span>
                    </div>
                    ' . (($setting_options->currency_position == 'Left') ? '<div id="itemprice">' . esc_html($setting_options->currency) . ' <span></span> </div>' : '<div id="itemprice"> <span></span> ' . esc_html($setting_options->currency) . '</div>') . '
                </div>
                <div class="wpgv_shipping_box">
                    <div>
                        <h4>' . __('Shipping', 'gift-voucher') . '</h4>
                    </div>
                    ' . (($setting_options->currency_position == 'Left') ? '<div id="shippingprice">' . esc_html($setting_options->currency) . ' <span></span> </div>' : '<div id="shippingprice"> <span></span> ' . esc_html($setting_options->currency) . '</div>') . '
                </div>
                ' . ($wpgv_add_extra_charges ? '<div class="wpgv_commission_box"><div><h4>' . __('Website Commission', 'gift-voucher') . '</h4></div><div id="commissionprice">' . wpgv_price_format(esc_html($wpgv_add_extra_charges)) . '</div></div>' : '') . '
                <div class="wpgv_total_box">
                    <div>
                        <h4><b>' . __('Total', 'gift-voucher') . '</b></h4>
                    </div>
                    ' . (($setting_options->currency_position == 'Left') ? '<div id="totalprice"><b>' . esc_html($setting_options->currency) . ' <span></span> </b></div>' : '<div id="totalprice"><b> <span></span> ' . esc_html($setting_options->currency) . '</b></div>') . '
                </div>
            </div>
    	</div>
    	' . $voucherstyle . '
        </div>
    </fieldset>

    <h3>' . __('Overview', 'gift-voucher') . '</h3>
    <fieldset>
    	<legend>' . __('Overview', 'gift-voucher') . '</legend><div class="voucher-row">
		<div class="voucherform secondLeft">
			<div class="paymentUserInfo">
                <div class="full">
                    <div class="labelInfo">' . __('Voucher Value', 'gift-voucher') . '</div>
                    ' . (($setting_options->currency_position == 'Left') ? '<div class="voucherAmountInfo">' . esc_html($setting_options->currency) . ' <span></span> </div>' : '<div class="voucherAmountInfo"> <span></span> ' . esc_html($setting_options->currency) . '</div>') . '
                </div>
                <div class="half">
                    <div class="labelInfo">' . __('Your Name', 'gift-voucher') . '</div>
                    <div class="voucherYourNameInfo"></div>
                </div>
                <div class="half">
                    <div class="labelInfo">' . __('Recipient Name', 'gift-voucher') . '</div>
                    <div class="voucherReceiverInfo"></div>
                </div>
                <div class="full">
                    <div class="labelInfo">' . __('Personal Message', 'gift-voucher') . '</div>
                    <div class="voucherMessageInfo"></div>
                </div><div class="clearfix"></div>
                <div class="clearfix"></div>
				<hr>
                <div class="full">
                    <div class="labelInfo">' . __('Shipping', 'gift-voucher') . '</div>
                    <div class="voucherShippingInfo">' . __('Shipping via Email', 'gift-voucher') . '</div>
                </div>
                <div class="full shippingasemail">
                    <div class="labelInfo">' . __('Send the voucher to recipient email here', 'gift-voucher') . '</div>
                    <div class="voucherShippingEmailInfo"></div>
                </div>
                <div class="full">
                    <div class="labelInfo">' . __('Your email address (for the receipt)', 'gift-voucher') . '</div>
                    <div class="voucherEmailInfo"></div>
                </div>
                <div class="half shippingaspost">
                    <div class="labelInfo">' . __('First Name', 'gift-voucher') . '</div>
                    <div class="voucherFirstNameInfo"></div>
                </div>
                <div class="half shippingaspost">
                    <div class="labelInfo">' . __('Last Name', 'gift-voucher') . '</div>
                    <div class="voucherLastNameInfo"></div>
                </div>
                <div class="full shippingaspost">
                    <div class="labelInfo">' . __('Address', 'gift-voucher') . '</div>
                    <div class="voucherAddressInfo"></div>
                </div>
                <div class="full shippingaspost">
                    <div class="labelInfo">' . __('Postcode', 'gift-voucher') . '</div>
                    <div class="voucherPincodeInfo"></div>
                </div>
                <div class="full shippingaspost">
                    <div class="labelInfo">' . __('Shipping method', 'gift-voucher') . '</div>
                    <div class="voucherShippingMethodInfo"></div>
                </div>
                <div class="full paymentcount' . esc_html($paymentCount) . '">
                    <div class="labelInfo">' . __('Payment Method', 'gift-voucher') . '</div>
                    <div class="voucherPaymentInfo"></div>
                </div>
				<hr>
				<div class="acceptVoucherTerms">
					<label><input type="checkbox" class="required" name="acceptVoucherTerms"> ' . esc_html(stripslashes($wpgv_termstext)) . '</label>
				</div>
				<div class="voucherNote">' . esc_html($setting_options->voucher_terms_note) . '</div>
				<button type="button" id="voucherPaymentButton" name="finalPayment">' . __('Pay Now', 'gift-voucher') . ' - ' . (($setting_options->currency_position == 'Left') ? esc_html($setting_options->currency) . ' <span></span> ' : ' <span></span> ' . esc_html($setting_options->currency)) . '</button>
			</div>
    	</div>
    	' . $voucherstyle . '';
    if ($setting_options->preview_button) {
        $html .= '<div class="voucherPreviewButton"><button type="button" data-fullurl="" data-src="' . esc_html(get_site_url() . "/voucher-pdf-preview") . '" target="_blank">' . __('Show Preview as PDF', 'gift-voucher') . '</button></div>';
    }
    $html .= '</div>
    </fieldset>
</form>';

    $html .= '<style>' . esc_html(stripslashes($wpgv_custom_css)) . '</style>';

    return $html;
}

function wpgv__doajax_front_template()
{
    global $wpdb;
    $template_table = $wpdb->prefix . 'giftvouchers_template';
    $template_id = isset($_REQUEST['template_id']) ? (int) base64_decode(sanitize_text_field(wp_unslash($_REQUEST['template_id']))) : 0;
    $template_options = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}giftvouchers_template WHERE id = %d", $template_id));


    $images = $template_options->image_style ? json_decode($template_options->image_style) : ['', '', ''];
    $image_styles = array();
    foreach ($images as $key => $value) {
        $image_attributes = wp_get_attachment_image_src($value, 'voucher-medium');
        $image_styles[] = ($image_attributes) ? esc_url($image_attributes[0]) : esc_url(get_option('wpgv_demoimageurl'));
    }

    // Prepare the data with escaping
    $data = array(
        'images' => $image_styles,
        'title' => esc_html($template_options->title)
    );

    // Send JSON response
    wp_send_json($data); // wp_send_json automatically escapes the output for JSON

    wp_die();
}



add_shortcode('wpgv_giftvoucher', 'wpgv_voucher_shortcode');
add_action('wp_ajax_nopriv_wpgv_doajax_front_template', 'wpgv__doajax_front_template');
add_action('wp_ajax_wpgv_doajax_front_template', 'wpgv__doajax_front_template');
