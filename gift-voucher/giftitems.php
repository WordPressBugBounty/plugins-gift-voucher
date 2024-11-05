<?php

if (!defined('ABSPATH')) exit;  // Exit if accessed directly

// Add Voucher Shortcode
function wpgv_giftitems_shortcode($atts = '')
{
    $shortcode_attr = shortcode_atts(array(
        'item_id' => 0,
        'item_cat_id' => 0
    ), $atts);

    global $wp, $wpdb;
    $html = '';
    $find = array('http://', 'https://');
    $replace = '';
    $siteURL = str_replace($find, $replace, get_site_url());
    $setting_table     = $wpdb->prefix . 'giftvouchers_setting';
    $voucher_table  = $wpdb->prefix . 'giftvouchers_list';
    wp_enqueue_style('wpgv-item-style');
    wp_enqueue_script('wpgv-jquery-validate');
    wp_enqueue_script('wpgv-item-script');
    wp_enqueue_script('wpgv-stripe-js');
    wp_enqueue_script('wpgv-paypal-js');

    $setting_options = $wpdb->get_row("SELECT * FROM $setting_table WHERE id = 1");
    $nonce = wp_create_nonce('wpgv_giftitems_form_verify');
    $wpgv_custom_css = get_option('wpgv_custom_css') ? stripslashes(trim(get_option('wpgv_custom_css'))) : '';

    $wpgv_stripe_alternative_text = get_option('wpgv_stripe_alternative_text') ? get_option('wpgv_stripe_alternative_text') : 'Stripe';

    $wpgv_buying_for = get_option('wpgv_buying_for') ? get_option('wpgv_buying_for') : 'both';
    $wpgv_add_extra_charges = get_option('wpgv_add_extra_charges_item') ? get_option('wpgv_add_extra_charges_item') : 0;

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
                    <input type="hidden" name="buying_for" id="buying_for" value="someone_else">
                </div>
                <div class="wpgv-form-fields" id="wpgv-your_name">
                    <label for="your_name">' . __('Your Name', 'gift-voucher') . '</label>
                    <span class="error">' . __('Your name is required', 'gift-voucher') . '</span>
                    <input type="text" name="your_name" id="your_name" class="form-field">
                </div>
                <div class="wpgv-form-fields" id="wpgv-recipient_name">
                    <label for="receipt_email">' . __('Recipient Name', 'gift-voucher') . '</label>
                    <span class="error">' . __('Recipient name is required', 'gift-voucher') . '</span>
                    <input type="text" name="recipient_name" id="recipient_name" class="form-field" />
                </div>';
    } else {
        if ($wpgv_buying_for == 'someone_else') {
            $buying_for_html = '<input type="hidden" name="buying_for" id="buying_for" value="someone_else">
                <div class="wpgv-form-fields" id="wpgv-your_name">
                    <label for="your_name">' . __('Your Name', 'gift-voucher') . '</label>
                    <span class="error">' . __('Your name is required', 'gift-voucher') . '</span>
                    <input type="text" name="your_name" id="your_name" class="form-field">
                </div>
                <div class="wpgv-form-fields" id="wpgv-recipient_name">
                    <label for="receipt_email">' . __('Recipient Name', 'gift-voucher') . '</label>
                    <span class="error">' . __('Recipient name is required', 'gift-voucher') . '</span>
                    <input type="text" name="recipient_name" id="recipient_name" class="form-field" />
                </div>';
        } else {
            $buying_for_html = '<input type="hidden" name="buying_for" id="buying_for" value="yourself">
                <div class="wpgv-form-fields" id="wpgv-your_name">
                    <label for="your_name">' . __('Your Name', 'gift-voucher') . '</label>
                    <span class="error">' . __('Your name is required', 'gift-voucher') . '</span>
                    <input type="text" name="your_name" id="your_name" class="form-field">
                </div>';
        }
    }

    $wpgv_hide_price = get_option('wpgv_hide_price_item') ? get_option('wpgv_hide_price_item') : 0;
    $wpgv_leftside_notice = (get_option('wpgv_leftside_notice') != '') ? get_option('wpgv_leftside_notice') : __('Cash payment is not possible. The terms and conditions apply.', 'gift-voucher');

    $voucher_value_html = (!$wpgv_hide_price) ? '<div class="voucherValueForm">
                        <label>' . __('Voucher Value', 'gift-voucher') . '</label>
                        <span class="currencySymbol"> ' . $setting_options->currency . ' </span>
                        <input type="text" name="voucherValueCard" class="voucherValueCard" readonly>
                    </div>' : '';

    $voucher_bgcolor = $setting_options->voucher_bgcolor;
    $voucher_color = $setting_options->voucher_color;
    $custom_loader = $setting_options->custom_loader ? $setting_options->custom_loader : WPGIFT__PLUGIN_URL . '/assets/img/loader.gif';
    $wpgv_termstext = get_option('wpgv_termstext') ? get_option('wpgv_termstext') : 'I hereby accept the terms and conditions, the revocation of the privacy policy and confirm that all information is correct.';

    $shipping_methods = explode(',', $setting_options->shipping_method);
    $shipping_methods_string = '';
    foreach ($shipping_methods as $method) {
        if ($method != '') {
            $shipping_method = explode(':', $method);
            $shipping_methods_string .= '<label data-value="' . trim(stripslashes($shipping_method[0])) . '"><input type="radio" name="shipping_method" value="' . trim(stripslashes($shipping_method[1])) . '" class="radio-field"> ' . trim(stripslashes($shipping_method[1])) . '</label>';
        }
    }

    $wpgv_hide_expiry = get_option('wpgv_hide_expiry') ? get_option('wpgv_hide_expiry') : 'yes';
    $wpgv_expiry_date_format = get_option('wpgv_expiry_date_format') ? get_option('wpgv_expiry_date_format') : 'd.m.Y';

    if ($wpgv_hide_expiry == 'no') {
        $expiryCard = __('No Expiry', 'gift-voucher');
    } else {
        $expiryCard = ($setting_options->voucher_expiry_type == 'days')
            ? gmdate($wpgv_expiry_date_format, strtotime('+' . $setting_options->voucher_expiry . ' days', time())) . PHP_EOL
            : $setting_options->voucher_expiry;
    }

    $chooseStyle = '';
    if ($setting_options->is_style_choose_enable) {
        $voucher_styles = json_decode($setting_options->voucher_style);
        $chooseStyle = '<select name="chooseStyle" id="chooseStyle" class="form-field">';
        foreach ($voucher_styles as $key => $value) {
            $chooseStyle .= '<option value="' . $value . '">' . __('Style', 'gift-voucher') . ' ' . ($value + 1) . '</option>';
        }
        $chooseStyle .= '</select>';
    }

    $paymenyGateway = __('Payment Method', 'gift-voucher');
    if ($setting_options->paypal || $setting_options->sofort || $setting_options->stripe || $setting_options->per_invoice) {
        $paymenyGateway = '<select name="payemnt_gateway" id="payemnt_gateway" class="form-field">';
        $paymenyGateway .= $setting_options->paypal ? '<option value="Paypal">' . __('Paypal', 'gift-voucher') . '</option>' : '';
        $paymenyGateway .= $setting_options->sofort ? '<option value="Sofort">' . __('Sofort', 'gift-voucher') . '</option>' : '';
        $paymenyGateway .= $setting_options->stripe ? '<option value="Stripe">' . $wpgv_stripe_alternative_text . '</option>' : '';
        $paymenyGateway .= $setting_options->per_invoice ? '<option value="Per Invoice">' . __('Per Invoice', 'gift-voucher') . '</option>' : '';
        $paymenyGateway .= '</select>';
    }

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
        #wpgv-giftitems.loading:after {
            content: url(' . $custom_loader . ') !important;
        }
    </style>';

    $html .= '<div class="wpgv-giftitem-wrapper"><form id="wpgv-giftitems" name="wpgv_giftitems" method="post" action="' . home_url($wp->request) . '" enctype="multipart/form-data">
        <input type="hidden" name="wpgv_giftitems_form_verify" value="' . $nonce . '">
        <input type="hidden" name="wpgv_category_id" id="category_id">
        <input type="hidden" name="wpgv_item_id" id="item_id">
        <input type="hidden" name="wpgv_total_price" id="total_price">
        <input type="hidden" name="item_pdf_price" id="item_pdf_price">
        <input type="hidden" name="wpgv_website_commission_price" id="website_commission_price" data-price="' . $wpgv_add_extra_charges . '">
        ';

    $wpgv_voucher_categories = get_categories('taxonomy=wpgv_voucher_category&post_type=wpgv_voucher_product&order_by=term_id&order=DESC');
    if ($wpgv_voucher_categories) {
        $image_id = get_term_meta($wpgv_voucher_categories[0]->term_id, 'wpgv-voucher-category-image-id', true);
        $image_attributes = wp_get_attachment_image_src($image_id, 'full');
        $itemimage = ($image_attributes) ? $image_attributes[0] : get_option('wpgv_demoimageurl');
    } else {
        $itemimage = get_option('wpgv_demoimageurl');
    }
    $html .= '<div class="wpgv-giftitemimage"><img src="' . $itemimage . '"></div>';

    //Step 1
    $html .= '<div id="wpgv-giftitems-step1" class="wpgv-items-wrap">
                <div class="wpgv-according-categories">';

    if ($shortcode_attr['item_id'] == 0 && $shortcode_attr['item_cat_id'] == 0) {
        foreach ($wpgv_voucher_categories as $category) {
            $html .= '<div class="wpgv-according-category" id="itemcat' . $category->term_id . '">
    <div class="wpgv-according-title" data-cat-id="' . $category->term_id . '">
        <h2>' . $category->name . '<span>' . wp_strip_all_tags(term_description($category->term_id, 'wpgv_voucher_category')) . '</span></h2>
    </div>';

            $items = get_posts(
                array(
                    'posts_per_page' => -1,
                    'post_type' => 'wpgv_voucher_product',
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'wpgv_voucher_category',
                            'field' => 'term_id',
                            'terms' => $category->term_id,
                        )
                    )
                )
            );
            $html .= '<div class="wpgv-items">';
            foreach ($items as $item) {

                $item_id = $item->ID;
                $description = esc_html(get_post_meta($item_id, 'description', true));
                $price = esc_html(get_post_meta($item_id, 'price', true));
                $special_price = esc_html(get_post_meta($item_id, 'special_price', true));
                $subprice = ($special_price) ? $special_price : $price;
                $pricestring = ($special_price) ? '<del>' . wpgv_price_format($price) . '</del> <span>' . wpgv_price_format($special_price) . '</span>' : '<span>' . wpgv_price_format($price) . '</span>';
                $html .= '<div class="wpgv-item">
                            <div class="wpgv-content"><h4>' . get_the_title($item_id) . '</h4><p>' . $description . '</p></div>
                            <div class="wpgv-price">' . $pricestring . '</div>
                            <div class="wpgv-buy"><button type="button" data-item-id="' . $item_id . '" data-cat-id="' . $category->term_id . '" data-item-price="' . $price . '" data-item-price-pdf="' . $price . '">' . __('Buy', 'gift-voucher') . '</button></div>
                        </div>';
            }
            $html .= '</div>';

            $html .= '</div>';
        }
    } else if ($shortcode_attr['item_id'] != 0) {
        $single_item_args = array(
            'post_type' => 'wpgv_voucher_product',
            'post__in' => array($shortcode_attr['item_id'])
        );
        $items = get_posts($single_item_args);

        $html .= '<div class="wpgv-items">';

        foreach ($items as $item) {

            $item_id = $item->ID;
            $description = esc_html(get_post_meta($item_id, 'description', true));
            $price = esc_html(get_post_meta($item_id, 'price', true));
            $special_price = esc_html(get_post_meta($item_id, 'special_price', true));
            $subprice = ($special_price) ? $special_price : $price;
            $pricestring = ($special_price) ? '<del>' . wpgv_price_format($price) . '</del> <span>' . wpgv_price_format($special_price) . '</span>' : '<span>' . wpgv_price_format($price) . '</span>';

            $html .= '<div class="wpgv-item">

                    <div class="wpgv-content"><h4>' . get_the_title($item_id) . '</h4><p>' . $description . '</p></div>

                    <div class="wpgv-price">' . $pricestring . '</div>

                    <div class="wpgv-buy"><button type="button" data-item-id="' . $item_id . '" data-cat-id="' . $category->term_id . '" data-item-price="' . $subprice . '">' . __('Buy', 'gift-voucher') . '</button></div>
                </div>';
        }
        $html .= '</div>';
    } else if ($shortcode_attr['item_cat_id'] != 0) {
        $category = get_term($shortcode_attr['item_cat_id']);

        $html .= '<div class="wpgv-according-category" id="itemcat' . $category->term_id . '">
                    <div class="wpgv-according-title" data-cat-id="' . $category->term_id . '">
                        <h2>' . $category->name . '<span>' . wp_strip_all_tags(term_description($category->term_id, 'wpgv_voucher_category')) . '</span></h2>
                    </div>';

        $items = get_posts(
            array(
                'posts_per_page' => -1,
                'post_type' => 'wpgv_voucher_product',
                'tax_query' => array(
                    array(
                        'taxonomy' => 'wpgv_voucher_category',
                        'field' => 'term_id',
                        'terms' => $category->term_id,
                    )
                )
            )
        );

        $html .= '<div class="wpgv-items">';

        foreach ($items as $item) {
            $item_id = $item->ID;
            $description = esc_html(get_post_meta($item_id, 'description', true));
            $price = esc_html(get_post_meta($item_id, 'price', true));
            $special_price = esc_html(get_post_meta($item_id, 'special_price', true));
            $subprice = ($special_price) ? $special_price : $price;
            $pricestring = ($special_price) ? '<del>' . wpgv_price_format($price) . '</del> <span>' . wpgv_price_format($special_price) . '</span>' : '<span>' . wpgv_price_format($price) . '</span>';

            $html .= '<div class="wpgv-item">
                        <div class="wpgv-content"><h4>' . get_the_title($item_id) . '</h4><p>' . $description . '</p></div>
                        <div class="wpgv-price">' . $pricestring . '</div>
                        <div class="wpgv-buy"><button type="button" data-item-id="' . $item_id . '" data-cat-id="' . $category->term_id . '" data-item-price="' . $subprice . '">' . __('Buy', 'gift-voucher') . '</button></div>
                    </div>';
        }

        $html .= '</div>';
        $html .= '</div>';
    }

    $html .= '</div></div>';

    // Step 2
    $html .= '<div id="wpgv-giftitems-step2" class="wpgv-items-wrap">
                <div class="wpgv-gifttitle">
                    <h3></h3>
                    <span></span>
                </div>
                <div class="wpgv-form-fields">
                    ' . $chooseStyle . '
                </div>
                ' . $buying_for_html . '
                <div class="wpgv-form-fields" id="wpgv-message">
                    <label for="message">' . __('Personal Message (Optional)', 'gift-voucher') . ' (' . __('Max: 250 Characters', 'gift-voucher') . ')</label>
                    <span class="error">' . __('Please enter no more than 250 characters.', 'gift-voucher') . '</span>
                    <textarea name="message" id="message" class="form-field" maxlength="250"></textarea>
                    <div class="maxchar"></div>
                </div>
                <div class="wpgv-buttons">
                    <button type="button" data-next="step3" class="next-button">' . __('Continue', 'gift-voucher') . '</button>
                    <span class="back-button" data-prev="step1">' . __('Back', 'gift-voucher') . '</span>
                </div>
            </div>';


    // Step 3
    if ($setting_options->post_shipping) {
        $html .= '<div id="wpgv-giftitems-step3" class="wpgv-items-wrap">
                <div class="shipping flex-field">
                    <label>' . __('Shipping', 'gift-voucher') . '</label>
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
                    <input type="hidden" name="shipping" id="shipping" value="shipping_as_email">
                </div>
                <div class="wpgv-form-fields" id="wpgv-shipping_email">
                    <label>' . __('Send the voucher to recipient email here', 'gift-voucher') . '</label>
                    <span class="error">' . __('Required', 'gift-voucher') . '</span>
                    <input type="email" name="shipping_email" id="shipping_email" class="form-field">
                </div>
                <div class="wpgv-form-fields" id="wpgv-receipt_email">
                    <label>' . __('Your email address (for the receipt)', 'gift-voucher') . '</label>
                    <span class="error">' . __('Your email address is required', 'gift-voucher') . '</span>
                    <span class="eqlerror">' . __('This email must be be different from above email.', 'gift-voucher') . '</span>
                    <input type="email" name="receipt_email" id="receipt_email" class="form-field">
                </div>
                <div class="wpgv-form-fields" id="wpgv-post_name">
                    <label>' . __('Shipping address', 'gift-voucher') . '</label>
                    <input type="text" name="post_firstname" id="post_firstname" placeholder="' . __('First Name', 'gift-voucher') . '" class="form-field half-field first" />
                    <input type="text" name="post_lastname" id="post_lastname" placeholder="' . __('Last Name', 'gift-voucher') . '" class="form-field half-field" />
                    <input type="text" name="post_address" id="post_address" placeholder="' . __('Address', 'gift-voucher') . '" class="form-field" />
                    <input type="text" name="post_code" id="post_code" placeholder="' . __('Postcode', 'gift-voucher') . '" class="form-field" />
                </div>
                <div class="wpgv-form-fields" id="wpgv-shipping_method">
                    <label id="shipping_method">' . __('Shipping method', 'gift-voucher') . '</label>
                    ' . $shipping_methods_string . '
                </div>
                <div class="order_details_preview">
                    <h3>' . __('Your Order', 'gift-voucher') . '</h3>
                    <div class="wpgv_preview_box">
                        <div>
                            <h4 class="wpgv-itemtitle">-</h4>
                            <span>' . __('Your Name', 'gift-voucher') . ': <i id="autoyourname"></i></span>
                        </div>
                        ' . (($setting_options->currency_position == 'Left') ? '<div id="itemprice">' . $setting_options->currency . ' <span></span> </div>' : '<div id="itemprice"> <span></span> ' . $setting_options->currency . '</div>') . '
                    </div>
                    <div class="wpgv_shipping_box">
                        <div>
                            <h4>' . __('Shipping', 'gift-voucher') . '</h4>
                        </div>
                        ' . (($setting_options->currency_position == 'Left') ? '<div id="shippingprice">' . $setting_options->currency . ' <span></span> </div>' : '<div id="shippingprice"> <span></span> ' . $setting_options->currency . '</div>') . '
                    </div>
                    ' . ($wpgv_add_extra_charges ? '<div class="wpgv_commission_box"><div><h4>' . __('Website Commission', 'gift-voucher') . '</h4></div><div id="commissionprice">' . wpgv_price_format($wpgv_add_extra_charges) . '</div></div>' : '') . '
                    <div class="wpgv_total_box">
                        <div>
                            <h4><b>' . __('Total', 'gift-voucher') . '</b></h4>
                        </div>
                        ' . (($setting_options->currency_position == 'Left') ? '<div id="totalprice"><b>' . $setting_options->currency . ' <span></span> </b></div>' : '<div id="totalprice"><b> <span></span> ' . $setting_options->currency . '</b></div>') . '
                    </div>';
        if ($setting_options->preview_button) {
            $html .= '<div class="preview-box"><button type="button" id="itempreview" data-url="' . get_site_url() . '/gift-item-pdf-preview/?action=preview&nonce=' . $nonce . '">' . __('Show Preview as PDF', 'gift-voucher') . '</button></div>';
        }
        $html .= '</div>
                <div class="wpgv-form-fields" id="wpgv_payment_gateway">
                    <label>' . __('Payment Method', 'gift-voucher') . '</label>
                    ' . $paymenyGateway . '
                </div>
                <div class="acceptVoucherTerms">
                    <label><input type="checkbox" class="required" name="acceptVoucherTerms"> ' . stripslashes($wpgv_termstext) . '</label>
                </div>
                <div class="voucherNote">' . $setting_options->voucher_terms_note . '</div>
                <div class="wpgv-buttons">
                    <button type="button" data-next="step4" id="paynowbtn" data-url="action=wpgv_doajax_item_pdf_save_func&nonce=' . $nonce . '">' . __('Pay Now', 'gift-voucher') . ' - ' . (($setting_options->currency_position == 'Left') ? $setting_options->currency . ' <span></span> ' : ' <span></span> ' . $setting_options->currency) . '</button>
                    <span data-prev="step2" class="back-button">' . __('Back', 'gift-voucher') . '</span>
                </div>
            </div>';
    } else {
        $html .= '<div id="wpgv-giftitems-step3" class="wpgv-items-wrap">
                <input type="hidden" name="shipping" id="shipping" value="shipping_as_email">
                <div class="wpgv-form-fields" id="wpgv-shipping_email">
                    <label>' . __('Send the voucher to recipient email here', 'gift-voucher') . '</label>
                    <span class="error">' . __('Required', 'gift-voucher') . '</span>
                    <input type="email" name="shipping_email" id="shipping_email" class="form-field">
                </div>
                <div class="wpgv-form-fields" id="wpgv-receipt_email">
                    <label>' . __('Your email address (for the receipt)', 'gift-voucher') . '</label>
                    <span class="error">' . __('Your email address is required', 'gift-voucher') . '</span>
                    <input type="email" name="receipt_email" id="receipt_email" class="form-field">
                </div>
                <div class="order_details_preview">
                    <h3>' . __('Your Order', 'gift-voucher') . '</h3>
                    <div class="wpgv_preview_box">
                        <div>
                            <h4 class="wpgv-itemtitle">-</h4>
                            <span>' . __('Your Name', 'gift-voucher') . ': <i id="autoyourname"></i></span>
                        </div>
                        ' . (($setting_options->currency_position == 'Left') ? '<div id="itemprice">' . $setting_options->currency . ' <span></span> </div>' : '<div id="itemprice"> <span></span> ' . $setting_options->currency . '</div>') . '
                    </div>';
        if ($setting_options->preview_button) {
            $html .= '<div class="preview-box"><button type="button" id="itempreview" data-url="' . get_site_url() . '/gift-item-pdf-preview/?action=preview&nonce=' . $nonce . '">' . __('Show Preview as PDF', 'gift-voucher') . '</button></div>';
        }
        $html .= '</div>
                <div class="wpgv-form-fields" id="wpgv_payment_gateway">
                    <label>' . __('Payment Method', 'gift-voucher') . '</label>
                    ' . $paymenyGateway . '
                </div>
                <div class="acceptVoucherTerms">
                    <label><input type="checkbox" class="required" name="acceptVoucherTerms"> ' . stripslashes($wpgv_termstext) . '</label>
                </div>
                <div class="voucherNote">' . $setting_options->voucher_terms_note . '</div>
                <div class="wpgv-buttons">
                    <button type="button" data-next="step4" id="paynowbtn" data-url="action=wpgv_doajax_item_pdf_save_func&nonce=' . $nonce . '">' . __('Pay Now', 'gift-voucher') . '</button>
                    <span data-prev="step2" class="back-button">' . __('Back', 'gift-voucher') . '</span>
                </div>
            </div>';
    }

    $html .= '</form>';

    $htmlstyle1 = '<div class="wpgv_preview-box wpgvstyle1">
                <div class="cardDiv">
                    <div class="cardImgTop">
                        <img class="uk-thumbnail" src="' . $itemimage . '">
                    </div>
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
                                <input type="text" name="codeCard" class="codeCard" readonly value="6234256841004311">
                            </div>
                            <div class="clearfix"></div>
                            <div class="voucherSiteInfo"><a href="' . $setting_options->pdf_footer_url . '">' . $setting_options->pdf_footer_url . '</a> | <a href="mailto:' . $setting_options->pdf_footer_email . '">' . $setting_options->pdf_footer_email . '</a></div>
                            <div class="termsCard">* ' . $wpgv_leftside_notice . '</div>
                        </div>
                    </div>
                    <h3>' . __('Voucher Preview', 'gift-voucher') . '</h3>
                </div>
        </div>';

    $htmlstyle2 = '<div class="wpgv_preview-box wpgvstyle2">
                <div class="cardDiv">
                    <div class="voucherBottomDiv">
                        <div class="cardImgTop">
                            <img class="uk-thumbnail" src="' . $itemimage . '">
                        </div>
                        <div class="sidedetails">
                            <h2 class="itemtitle"></h2>
                            <p class="itemdescription"></p>
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
                                <input type="text" name="expiryCard" class="expiryCard" value="' . $expiryCard . '" readonly>
                            </div>
                            <div class="codeFormRight">
                                <label>' . __('Coupon Code', 'gift-voucher') . '</label>
                                <input type="text" name="codeCard" class="codeCard" readonly value="6234256815004311">
                            </div>
                            <div class="clearfix"></div>
                            <div class="voucherSiteInfo"><a href="' . $setting_options->pdf_footer_url . '">' . $setting_options->pdf_footer_url . '</a> | <a href="mailto:' . $setting_options->pdf_footer_email . '">' . $setting_options->pdf_footer_email . '</a></div>
                            <div class="termsCard">* ' . $wpgv_leftside_notice . '</div>
                        </div>
                    </div>
                    <h3>' . __('Voucher Preview', 'gift-voucher') . '</h3>
                </div>
        </div>';

    $htmlstyle3 = '<div class="wpgv_preview-box wpgvstyle3">
                <div class="cardDiv">
                    <div class="voucherBottomDiv">
                        <h2 class="itemtitle"></h2>
                        <p class="itemdescription"></p>
                        <div class="cardImgTop">
                            <img class="uk-thumbnail" src="' . $itemimage . '">
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
                                <input type="text" name="expiryCard" class="expiryCard" value="' . $expiryCard . '" readonly>
                            </div>
                            <div class="codeFormRight">
                                <label>' . __('Coupon Code', 'gift-voucher') . '</label>
                                <input type="text" name="codeCard" class="codeCard" readonly value="6234255681004311">
                            </div>
                            <div class="clearfix"></div>
                            <div class="voucherSiteInfo"><a href="' . $setting_options->pdf_footer_url . '">' . $setting_options->pdf_footer_url . '</a> | <a href="mailto:' . $setting_options->pdf_footer_email . '">' . $setting_options->pdf_footer_email . '</a></div>
                            <div class="termsCard">* ' . $wpgv_leftside_notice . '</div>
                        </div>
                    </div>
                    <h3>' . __('Voucher Preview', 'gift-voucher') . '</h3>
                </div>
        </div>';

    $voucherstyle = '';
    if ($setting_options->is_style_choose_enable) {
        $voucher_styles = json_decode($setting_options->voucher_style);
        foreach ($voucher_styles as $key => $value) {
            $html .= ${'htmlstyle' . ($value + 1)};
        }
    } else {
        switch ($setting_options->voucher_style) {
            case 0:
                $html .= $htmlstyle1;
                break;
            case 1:
                $html .= $htmlstyle2;
                break;
            case 2:
                $html .= $htmlstyle3;
                break;
            default:
                $html .= $htmlstyle1;
                break;
        }
    }

    $html .= '</div><style>' . $wpgv_custom_css . '</style>';

    return $html;
}

function wpgv__doajax_get_itemcat_image()
{
    $catid = sanitize_text_field($_REQUEST['catid']);
    $image_id = get_term_meta($catid, 'wpgv-voucher-category-image-id', true);
    $image_attributes = wp_get_attachment_image_src($image_id, 'full');
    $itemimage = ($image_attributes) ? esc_url($image_attributes[0]) : esc_url(get_option('wpgv_demoimageurl'));

    // Prepare the data with escaping
    $data = array('image' => $itemimage);

    // Send JSON response
    wp_send_json($data); // wp_send_json automatically escapes the output for JSON

    wp_die();
}


function wpgv__doajax_get_item_data()
{
    $item_id = sanitize_text_field($_REQUEST['itemid']);
    $image_styles = array();

    for ($i = 0; $i < 3; $i++) {
        $style_image = sanitize_text_field(get_post_meta($item_id, 'style' . ($i + 1) . '_image', true));
        $image_attributes = wp_get_attachment_image_src($style_image, 'voucher-medium');
        $image_styles[] = ($image_attributes) ? esc_url($image_attributes[0]) : esc_url(get_option('wpgv_demoimageurl'));
    }

    // Prepare the data with escaping
    $data = array(
        'title' => esc_html(wp_strip_all_tags(get_the_title($item_id))),
        'images' => array_map('esc_url', $image_styles),
        'description' => esc_html(html_entity_decode(wp_strip_all_tags(get_post_meta($item_id, 'description', true)))),
        'price' => esc_html(get_post_meta($item_id, 'price', true)),
        'special_price' => esc_html(get_post_meta($item_id, 'special_price', true))
    );

    // Send JSON response
    wp_send_json($data); // wp_send_json automatically escapes the output for JSON

    wp_die();
}


add_shortcode('wpgv_giftitems', 'wpgv_giftitems_shortcode');
add_action('wp_ajax_nopriv_wpgv_doajax_get_itemcat_image', 'wpgv__doajax_get_itemcat_image');
add_action('wp_ajax_wpgv_doajax_get_itemcat_image', 'wpgv__doajax_get_itemcat_image');
add_action('wp_ajax_nopriv_wpgv_doajax_get_item_data', 'wpgv__doajax_get_item_data');
add_action('wp_ajax_wpgv_doajax_get_item_data', 'wpgv__doajax_get_item_data');
