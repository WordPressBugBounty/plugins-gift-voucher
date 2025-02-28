<?php

defined('ABSPATH') or exit;

if (!class_exists('wpgv-')) :

    final class wpgv_gift_voucher_cart_process
    {

        function __construct()
        {
            add_filter('woocommerce_get_price_html', array($this, 'woocommerce_get_price_html'), 10, 2);
            add_filter('woocommerce_dropdown_variation_attribute_options_args', array($this, 'woocommerce_dropdown_variation_attribute_options_args'));
            add_filter('woocommerce_add_to_cart_handler', array($this, 'woocommerce_add_to_cart_handler'), 10, 2);
            add_filter('woocommerce_add_cart_item', array($this, 'woocommerce_add_cart_item'));
            add_filter('woocommerce_add_cart_item_data', array($this, 'woocommerce_add_cart_item_data'), 10, 3);
            add_filter('woocommerce_get_cart_item_from_session', array($this, 'woocommerce_get_cart_item_from_session'), 10, 2);
            add_filter('woocommerce_get_item_data', array($this, 'woocommerce_get_item_data'), 10, 2);
            add_filter('woocommerce_order_item_display_meta_key', array($this, 'woocommerce_order_item_display_meta_key'), 10, 3);
            add_filter('woocommerce_order_item_get_formatted_meta_data', array($this, 'woocommerce_order_item_get_formatted_meta_data'), 10, 2);
            add_action('woocommerce_order_again_cart_item_data', array($this, 'woocommerce_order_again_cart_item_data'), 10, 3);
            add_filter('woocommerce_product_get_price', array($this, 'woocommerce_product_get_price'), 10, 2);
            add_filter('woocommerce_checkout_create_order_line_item', array($this, 'woocommerce_checkout_create_order_line_item'), 10, 4);
            add_filter('woocommerce_order_status_completed', array($this, 'woocommerce_order_status_completed'), 11, 2);
            add_filter('woocommerce_order_status_cancelled', array($this, 'woocommerce_order_status_cancelled'), 11, 2);
            add_filter('woocommerce_order_status_refunded', array($this, 'woocommerce_order_status_refunded'), 11, 2);
            add_filter('wp_trash_post', array($this, 'order_deleted'));
            add_filter('untrash_post', array($this, 'order_restored'));

            // add_filter( 'woocommerce_order_item_needs_processing', array( $this, 'woocommerce_order_item_needs_processing' ), 10, 3 );

            add_filter('et_option_ajax_addtocart', array($this, 'et_option_ajax_addtocart'));
            add_filter('theme_mod_disable_wc_sticky_cart', array($this, 'theme_mod_disable_wc_sticky_cart'));
            add_filter('theme_mod_ocean_woo_product_ajax_add_to_cart', array($this, 'theme_mod_ocean_woo_product_ajax_add_to_cart'));
            add_filter('option_rigid', array($this, 'option_rigid'), 10, 2);

            add_action('woocommerce_thankyou', array($this, 'wpgv_woocommerce_auto_complete_order'));
        }

        function woocommerce_get_price_html($price, $product)
        {
            if (is_a($product, 'WC_Product_Variation') && empty($product->get_price())) {
                $product_id = $product->get_parent_id();
                $parent_product = wc_get_product($product_id);
                if (is_a($parent_product, 'WC_Gift_Voucher_Product')) {
                    $price = '';
                }
            }

            return $price;
        }

        function woocommerce_dropdown_variation_attribute_options_args($args)
        {
            if ($args['product'] && is_a($args['product'], 'WC_Gift_Voucher_Product')) {
                $args['show_option_none'] = __('Choose an amount', 'gift-voucher');
            }

            return $args;
        }

        function woocommerce_add_to_cart_handler($product_type, $product)
        {
            if ($product_type == WPGV_PRODUCT_TYPE_SLUG) {
                return 'variable';
            } else {
                return $product_type;
            }
        }

        function woocommerce_add_cart_item($cart_item)
        {
            global $wpgv_gift_voucher;

            if (isset($cart_item[WPGV_RECIPIENT_EMAIL_META_KEY]) && !empty($cart_item[WPGV_RECIPIENT_EMAIL_META_KEY])) {
                $recipients = preg_split('/[\s,]+/', $cart_item[WPGV_RECIPIENT_EMAIL_META_KEY], WPGV_RECIPIENT_LIMIT, PREG_SPLIT_NO_EMPTY);
                if (count($recipients) > 1) {
                    $cart_item['quantity'] = count($recipients);
                }
            }

            /*echo "<pre>";
        print_r($cart_item);
        echo "</pre>";
        exit();*/

            return $cart_item;
        }

        function woocommerce_add_cart_item_data($cart_item_data, $product_id, $variation_id)
        {

            global $wpgv_gift_voucher;

            foreach ($wpgv_gift_voucher->gift_voucher_meta as $key => $display) {
                if (isset($_REQUEST[$key])) {
                    $value = sanitize_textarea_field(wp_unslash($_REQUEST[$key]));

                    if ($key == WPGV_MESSAGE_META_KEY) {
                        $cart_item_data[$key] = $value;
                    } else {
                        $cart_item_data[$key] = $value;
                    }
                }
            }


            return $cart_item_data;
        }

        function woocommerce_get_cart_item_from_session($cart_item, $values)
        {

            global $wpgv_gift_voucher;

            foreach ($wpgv_gift_voucher->gift_voucher_meta as $key => $display) {
                if (isset($values[$key])) {
                    $cart_item[$key] = $values[$key];
                }
            }

            /*echo "<pre>";
        print_r($cart_item);
        echo "</pre>";
        exit();
*/
            return $cart_item;
        }
        function woocommerce_get_item_data($item_data, $cart_item)
        {
            global $wpgv_gift_voucher;

            foreach ($wpgv_gift_voucher->gift_voucher_meta as $key => $display) {
                if (isset($cart_item[$key])) {
                    $value = $cart_item[$key];
                    if (!empty($value)) {
                        $item_data[] = array(
                            'key' => $display,
                            'value' => $value
                        );
                    }
                }
            }
            return $item_data;
        }

        function woocommerce_checkout_create_order_line_item($order_item, $cart_item_key, $cart_item, $order)
        {
            global $wpgv_gift_voucher;

            $product = wc_get_product($order_item->get_product_id());
            if (is_a($product, 'WC_Gift_Voucher_Product')) {
                foreach ($wpgv_gift_voucher->gift_voucher_meta as $key => $display) {
                    if ($key == WPGV_AMOUNT_META_KEY) {
                        $price = $cart_item['data']->get_regular_price();

                        // WooCommerce Ultimate Multi Currency Suite
                        if (class_exists('WooCommerce_Ultimate_Multi_Currency_Suite_Main') && isset($GLOBALS['woocommerce_ultimate_multi_currency_suite'])) {
                            $cs = $GLOBALS['woocommerce_ultimate_multi_currency_suite'];
                            if (!empty($cs->frontend)) {
                                remove_filter('woocommerce_product_variation_get_regular_price', array($cs->frontend, 'custom_item_price'), 9999, 2);
                                $price = $cart_item['data']->get_regular_price();
                                add_filter('woocommerce_product_variation_get_regular_price', array($cs->frontend, 'custom_item_price'), 9999, 2);
                            }
                        }

                        if (
                            // Multi Currency for WooCommerce by VillaTheme
                            function_exists('wmc_get_price')

                            // WooCommerce Price Based on Country by Oscar Gare
                            || function_exists('wcpbc_get_zone_by_country')

                            // Multi-Currency for WooCommerce by TIV.NET INC
                            || class_exists('WOOMC\App')
                        ) {
                            $price = apply_filters('wpgv_to_default_currency', $price);
                        }

                        $order_item->add_meta_data($key, $price);
                    } else if (isset($cart_item[$key])) {
                        $order_item->add_meta_data($key, $cart_item[$key]);
                    }
                }
            }
        }

        function woocommerce_order_item_display_meta_key($display_key, $meta_data, $order_item)
        {
            switch ($display_key) {
                case WPGV_GIFT_VOUCHER_NUMBER_META_KEY:
                    $display_key = WPGV_GIFT_VOUCHER_NUMBER_META_DISPLAY_NAME;

                    $gift_voucher = new WPGV_Gift_Voucher($meta_data->value);
                    if (!$gift_voucher->get_active()) {
                        $display_key .= __(' (inactive)', 'gift-voucher');
                    }
                    break;
                case WPGV_AMOUNT_META_KEY:
                    $display_key = WPGV_AMOUNT_META_DISPLAY_NAME;
                    break;
                case WPGV_YOUR_NAME_META_KEY:
                    $display_key = WPGV_YOUR_NAME_META_DISPLAY_NAME;
                    break;
                case WPGV_RECIPIENT_NAME_META_KEY:
                    $display_key = WPGV_RECIPIENT_NAME_META_DISPLAY_NAME;
                    break;
                case WPGV_RECIPIENT_EMAIL_META_KEY:
                    $display_key = WPGV_RECIPIENT_EMAIL_META_DISPLAY_NAME;
                    break;
                case WPGV_RECIPIENT_NAME_META_KEY:
                    $display_key = WPGV_RECIPIENT_NAME_META_DISPLAY_NAME;
                    break;
                case WPGV_YOUR_EMAIL_META_KEY:
                    $display_key = WPGV_YOUR_EMAIL_META_DISPLAY_NAME;
                    break;
                case WPGV_MESSAGE_META_KEY:
                    $display_key = WPGV_MESSAGE_META_DISPLAY_NAME;
                    break;
            }


            return $display_key;
        }

        function woocommerce_order_item_get_formatted_meta_data($formatted_meta, $order_item)
        {
            if (is_admin() && is_a($order_item, 'WC_Order_Item_Product') && !empty($order_item->get_product_id())) {
                $product = wc_get_product($order_item->get_product_id());
                if (is_a($product, 'WC_Gift_Voucher_Product')) {
                    $has_gift_voucher_number = false;
                    foreach ($formatted_meta as $id => $meta) {
                        if ($meta->key == WPGV_GIFT_VOUCHER_NUMBER_META_KEY) {
                            $has_gift_voucher_number = true;
                            break;
                        }
                    }

                    if (!$has_gift_voucher_number) {
                        $meta = new stdClass();
                        $meta->key = WPGV_GIFT_VOUCHER_NUMBER_META_KEY . '_placeholder';
                        $meta->value = false;
                        $meta->display_key = WPGV_GIFT_VOUCHER_NUMBER_META_DISPLAY_NAME;
                        $meta->display_value = '<i>' . __('Generated and emailed after the order is marked Complete.', 'gift-voucher') . '</i>';
                        $formatted_meta[] = $meta;
                    }
                }
            }

            /*echo "here";
        print_r($formatted_meta);
        exit();*/

            return $formatted_meta;
        }

        function woocommerce_order_again_cart_item_data($cart_item_data, $order_item, $order)
        {
            global $wpgv_gift_voucher;

            foreach ($wpgv_gift_voucher->gift_voucher_meta as $key => $display) {
                if (isset($order_item[$key])) {
                    if ($key == WPGV_MESSAGE_META_KEY) {
                        $cart_item_data[$key] = sanitize_textarea_field(stripslashes($order_item[$key]));
                    } else {
                        $cart_item_data[$key] = sanitize_text_field(stripslashes($order_item[$key]));
                    }
                }
            }

            return $cart_item_data;
        }

        function woocommerce_product_get_price($value, $product)
        {
            if (is_a($product, 'WC_Gift_Voucher_Product') && '' === $value) {
                return '0';
            } else {
                return $value;
            }
        }

        function woocommerce_checkout_update_order_meta($order_id, $data)
        {
            $order = wc_get_order($order_id);
            $this->add_gift_vouchers_to_order($order_id, $order, "order_id: $order_id created");
        }



        function woocommerce_order_status_completed($order_id, $order)
        {

            $this->add_gift_vouchers_to_order($order_id, $order, "order_id: $order_id completed");
        }

        function woocommerce_order_status_cancelled($order_id, $order)
        {
            $this->deactivate_gift_vouchers_from_order($order_id, $order, "order_id: $order_id cancelled");
        }


        function woocommerce_order_status_refunded($order_id, $order)
        {
            $this->deactivate_gift_vouchers_from_order($order_id, $order, "order_id: $order_id refunded");
        }

        function wpgv_woocommerce_auto_complete_order($order_id)
        {
            if (!$order_id)
                return;

            // Get an instance of the WC_Product object
            $order = wc_get_order($order_id);
            global $wpdb;
            $setting_table_name = $wpdb->prefix . 'giftvouchers_setting';
            $options = $wpdb->get_row($wpdb->prepare("SELECT * FROM `" . esc_sql($setting_table_name) . "` WHERE id = %d", 1));

            foreach ($order->get_items('line_item') as $order_item_id => $order_item) {
                if ($order_item->get_quantity() <= 0) {
                    continue;
                }

                $product_id = absint($order_item['product_id']);
                if (!($product = wc_get_product($product_id))) {
                    continue;
                }

                if (!is_a($product, 'WC_Gift_Voucher_Product')) {
                    continue;
                }

                if (in_array($order->get_payment_method(), array('bacs', 'cod', 'cheque', ''))) {
                    return;
                }
                // For paid Orders with all others payment methods (paid order status "processing")
                elseif ($order->has_status('processing')) {
                    if ($options->is_stripe_enable == 0) {
                        $order->update_status('processing');
                    } else {
                        $order->update_status('completed');
                    }
                }

                exit();
            }
        }

        function order_deleted($id)
        {
            global $post_type;

            if ($post_type !== 'shop_order') {
                return;
            }

            $order = wc_get_order($id);
            if ($order) {
                $this->deactivate_gift_vouchers_from_order($id, $order, "order_id: $id deleted");
            }
        }

        function order_restored($id)
        {
            global $post_type;

            if ($post_type !== 'shop_order') {
                return;
            }

            $order = wc_get_order($id);
            if ($order) {
                $this->add_gift_vouchers_to_order($id, $order, "order_id: $id restored");
            }
        }

        function add_gift_vouchers_to_order($order_id, $order, $note)
        {

            $create_note = sprintf(
                __('Order %1$s purchased by %2$s %3$s', 'gift-voucher'),
                $order->get_id(),
                $order->get_billing_first_name(),
                $order->get_billing_last_name()
            );


            foreach ($order->get_items('line_item') as $order_item_id => $order_item) {

                // Make sure we have a quantity (should always be true, right? Oh well, prevents a divide-by-zero error just in case).
                if ($order_item->get_quantity() <= 0) {
                    continue;
                }

                // Get the product.
                $product_id = absint($order_item['product_id']);
                if (!($product = wc_get_product($product_id))) {
                    continue;
                }

                // We're only interested in these guys.
                if (!is_a($product, 'WC_Gift_Voucher_Product')) {
                    continue;
                }

                // Grab the Variation, otherwise there will be trouble.
                $variation_id = absint($order_item['variation_id']);
                if (!($variation = wc_get_product($variation_id))) {
                    wp_die(esc_html__('Unable to retrieve variation ', 'gift-voucher') . esc_html($variation_id));
                }

                $credit_amount = wc_get_order_item_meta($order_item_id, WPGV_AMOUNT_META_KEY);
                if (!is_numeric($credit_amount) || empty($credit_amount)) {

                    // Previously we didn't store the WPGV_AMOUNT_META_KEY so we need to calculate based on purchase price.
                    $credit_amount = round($order_item->get_subtotal() / $order_item->get_quantity(), wc_get_price_decimals());
                    if (!is_numeric($credit_amount) || empty($credit_amount)) {
                        continue;
                    }
                }

                $credit_amount = apply_filters('wpgv_to_default_currency', $credit_amount);
                $item_note = $note . ", order_item_id: $order_item_id";

                // Create a gift card for each quantity ordered.
                $gift_card_numbers = (array) wc_get_order_item_meta($order_item_id, WPGV_GIFT_VOUCHER_NUMBER_META_KEY, false);

                // Make sure any existing gift cards are activated.
                foreach ($gift_card_numbers as $gift_card_number) {
                    $gift_voucher = new WPGV_Gift_Voucher($gift_card_number);
                    $gift_voucher->reactivate($item_note);
                }

                $value = wc_get_order_item_meta($order_item_id, WPGV_AMOUNT_META_KEY);
                $your_name = wc_get_order_item_meta($order_item_id, WPGV_YOUR_NAME_META_KEY);
                $recipient_name = wc_get_order_item_meta($order_item_id, WPGV_RECIPIENT_NAME_META_KEY);

                $recipient_email = wc_get_order_item_meta($order_item_id, WPGV_RECIPIENT_EMAIL_META_KEY);
                $your_email = wc_get_order_item_meta($order_item_id, WPGV_YOUR_EMAIL_META_KEY);

                $msg = wc_get_order_item_meta($order_item_id, WPGV_MESSAGE_META_KEY);
                $product_img = wp_get_attachment_url(get_post_thumbnail_id($product_id));
                $order_quantity = $order_item['quantity'];
                $payment_method = $order->get_payment_method();


                $order = wc_get_order($order_id);
                $wvgc_order_id = $order->get_id();
                $billing_email  = $order->get_billing_email();
                if ($recipient_email == null) {
                    $recipient_email = $billing_email;
                } else {
                    $recipient_email = wc_get_order_item_meta($order_item_id, WPGV_RECIPIENT_EMAIL_META_KEY);
                }
                // Create any new/missing gift cards.

                $numbers = array();
                for ($x = 1; $x <= $order_quantity; $x++) {
                    $numbers[] = $x;
                }

                foreach ($numbers as $valueNumber) {
                    $code = wp_rand(1000000000000000, 9000000000000000);
                    do_action('wc_wpgv_voucher_pdf_save_func', $value, $your_name, $recipient_name, $your_email, $recipient_email, $msg, $code, $payment_method, $product_img, $product_id, $wvgc_order_id);
                    wc_add_order_item_meta($order_item_id, WPGV_GIFT_VOUCHER_NUMBER_META_KEY, $code);
                }
            }


            global $wpdb;
            $setting_options = $wpdb->get_row($wpdb->prepare("SELECT * FROM `" . esc_sql($wpdb->prefix . 'giftvouchers_setting') . "` WHERE id = %d", 1));
            $voucher_options_results = $wpdb->get_results($wpdb->prepare("SELECT * FROM `" . esc_sql($wpdb->prefix . 'giftvouchers_list') . "` WHERE order_id = %d", $wvgc_order_id));

            $emailsubject = get_option('wpgv_emailsubject') ? get_option('wpgv_emailsubject') : 'Order Confirmation - Your Order with {company_name} (Voucher Order No: {order_number} ) has been successfully placed!';

            $recipientemailsubject = get_option('wpgv_recipientemailsubject') ? get_option('wpgv_recipientemailsubject') : 'Gift Voucher - Your have received voucher from {company_name}';

            $recipientemailbody = get_option('wpgv_recipientemailbody') ? get_option('wpgv_recipientemailbody') : '<p>Dear <strong>{recipient_name}</strong>,</p><p>You have received gift voucher product from <strong>{customer_name}</strong>.</p><p>You can download the voucher from {pdf_link}.</p><p>- For any clarifications please feel free to email us at {sender_email}.</p><p><strong>Warm Regards, <br /></strong> <strong>{company_name}<br />{website_url}</strong></p>';


            $emailbody = get_option('wpgv_emailbody') ? get_option('wpgv_emailbody') : '<p>Dear <strong>{customer_name}</strong>,</p><p>Order successfully placed.</p><p>We are pleased to confirm your order no {order_number}</p><p>Thank you for shopping with <strong>{company_name}</strong>!</p><p>You can download the voucher from {pdf_link}.</p><p>- For any clarifications please feel free to email us at {sender_email}.</p><p><strong>Warm Regards, <br /></strong> <strong>{company_name}<br />{website_url}</strong></p>';

            $adminemailsubject = get_option('wpgv_adminemailsubject') ? get_option('wpgv_adminemailsubject') : 'New Voucher Order Received from {customer_name}  (Order No: {order_number})!';

            $adminemailbody = get_option('wpgv_adminemailbody') ? get_option('wpgv_adminemailbody') : '<p>Hello, New Voucher Order received.</p><p><strong>Order Id:</strong> {order_number}</p><p><strong>Name:</strong> {customer_name}<br /><strong>Email:</strong> {customer_email}<br /><strong>Address:</strong> {customer_address}<br /><strong>Postcode:</strong> {customer_postcode}</p>';

            $headers = 'Content-type: text/html;charset=utf-8' . "\r\n";
            $headers .= 'From: ' . $setting_options->sender_name . ' <' . $setting_options->sender_email . '>' . "\r\n";
            $headers .= 'Reply-to: ' . $setting_options->sender_name . ' <' . $setting_options->sender_email . '>' . "\r\n";



            $attachments = array();
            $mail_sent = null;
            foreach ($voucher_options_results as $voucher_options) {
                $shipping_email = null;
                $buy_email = null;
                if ($setting_options->is_order_form_enable == 0) {
                    $shipping_email = $billing_email;
                    $buy_email = $billing_email;
                } else {
                    $shipping_email = $voucher_options->shipping_email;
                    $buy_email = $voucher_options->email;
                }
                // echo $buy_email;
                // exit();
                $upload = wp_upload_dir();
                $upload_dir = $upload['basedir'];
                $attachments[] = $upload_dir . '/voucherpdfuploads/' . $voucher_options->voucherpdf_link . '.pdf';
                $attachments_user = $upload_dir . '/voucherpdfuploads/' . $voucher_options->voucherpdf_link  . '.pdf';
                $voucherpdf_link = $voucher_options->voucherpdf_link;

                /* Recipient Mail */
                if ($voucher_options->shipping_type != 'shipping_as_post') {
                    $recipientsub = wpgv_mailvarstr_multiple($recipientemailsubject, $setting_options, $voucher_options_results, $voucherpdf_link);
                    $recipientmsg = wpgv_mailvarstr_multiple($recipientemailbody, $setting_options, $voucher_options_results, $voucherpdf_link);
                    $recipientto = $voucher_options->from_name . '<' . $shipping_email . '>';

                    if ($voucher_options->buying_for == 'yourself') {
                        $recipientto = $voucher_options->from_name . '<' . $buy_email . '>';
                    }
                    if ($voucher_options->email_send_date_time == 'send_instantly') {
                        $checkmail1 = wp_mail($recipientto, $recipientsub, $recipientmsg, $headers, $attachments_user);
                    } else {
                        $timezone = wp_timezone();
                        $datetime = new DateTime($voucher_options->email_send_date_time, $timezone);
                        $send_gift_voucher_email_event_date_time = $datetime->getTimestamp();

                        $send_gift_voucher_email_event_args = array($recipientto, $recipientsub, $recipientmsg, $headers, $attachments_user);
                        wp_schedule_single_event($send_gift_voucher_email_event_date_time, 'send_gift_voucher_email_event', $send_gift_voucher_email_event_args);
                    }
                }

                $attachments[] = $upload_dir . '/voucherpdfuploads/' . $voucher_options->voucherpdf_link . '-receipt.pdf';
                $attachments[] = $upload_dir . '/voucherpdfuploads/' . $voucher_options->voucherpdf_link . '-invoice.pdf';

                // /* Your email address (for the receipt) | Name email Order Confirmation */
                $buyersub = wpgv_mailvarstr_multiple($emailsubject, $setting_options, $voucher_options_results, $voucherpdf_link);
                $buyermsg = wpgv_mailvarstr_multiple($emailbody, $setting_options, $voucher_options_results, $voucherpdf_link);
                $buyerto  = null;
                if ($setting_options->is_order_form_enable == 1) {
                    if ($voucher_options->email == null) {
                        $buyerto = $voucher_options->from_name . '<' . $billing_email . '>';
                    } else {
                        $buyerto = $voucher_options->from_name . '<' . $voucher_options->email . '>';
                    }
                } else {
                    $buyerto = $voucher_options->from_name . '<' . $billing_email . '>';
                }

                $mail_sent = wp_mail($buyerto, $buyersub, $buyermsg, $headers, $attachments_user);

                /* Your email address (for the receipt) | Name email Order Confirmation */



                // break;
            }
            // Send mail enter checkout woocommerce
            if ($mail_sent == true) {
                $successpagemessage = get_option('wpgv_successpagemessage') ? get_option('wpgv_successpagemessage') : 'We have got your order! <br>E-Mail Sent Successfully to %s';
                $return .= '<div class="success">' . sprintf(stripslashes($successpagemessage), $voucher_options_results->email) . '</div>';

                if (isset($_GET['per_invoice']) && $_GET['per_invoice'] == 1) {
                    $return .= $setting_options->bank_info;
                }
                $toadmin = null;
                if ($setting_options->is_order_form_enable == 1) {
                    $toadmin = $voucher_options->from_name . '<' . $billing_email . '>';
                } else {
                    if ($voucher_options->email == null) {
                        $toadmin = $setting_options->sender_name . ' <' . $billing_email . '>';
                    } else {
                        $toadmin = $voucher_options->from_name . '<' . $voucher_options->email . '>';
                    }
                }

                $subadmin = wpgv_mailvarstr_multiple_admin($adminemailsubject, $setting_options, $voucher_options_results);
                $bodyadmin = wpgv_mailvarstr_multiple_admin($adminemailbody, $setting_options, $voucher_options_results);
                $headersadmin = 'Content-type: text/html;charset=utf-8' . "\r\n";
                $headersadmin .= 'From: ' . $setting_options->sender_name . ' <' . $setting_options->sender_email . '>' . "\r\n";
                $headersadmin .= 'Reply-to: ' . $voucher_options->from_name . ' <' . $buy_email . '>' . "\r\n";
                wp_mail($toadmin, $subadmin, $bodyadmin, $headersadmin, $attachments);
            }
            // // Send mail enter checkout woocommerce
        }

        function deactivate_gift_vouchers_from_order($order_id, $order, $note)
        {
            foreach ($order->get_items('line_item') as $order_item_id => $order_item) {
                $item_note = $note . ", order_item_id_789: $order_item_id";

                $gift_card_numbers = (array) wc_get_order_item_meta($order_item_id, 'WPGV_GIFT_CARD_NUMBER_META_KEY', false);
                foreach ($gift_card_numbers as $gift_card_number) {
                    $gift_voucher = new WPGV_Gift_Voucher($gift_card_number);
                    $gift_voucher->deactivate($item_note);
                }
            }
        }

        function et_option_ajax_addtocart($value)
        {
            global $product;

            if (!empty($product)) {
                if (is_a($product, 'WC_Gift_Voucher_Product')) {
                    return false;
                }
            }

            return $value;
        }

        function theme_mod_disable_wc_sticky_cart($value)
        {
            global $product;

            if (!empty($product)) {
                if (is_a($product, 'WC_Gift_Voucher_Product')) {
                    return 1;
                }
            }

            return $value;
        }

        function theme_mod_ocean_woo_product_ajax_add_to_cart($value)
        {
            global $post;

            if (!empty($post)) {
                $product = wc_get_product($post->ID);
                if (is_a($product, 'WC_Gift_Voucher_Product')) {
                    return false;
                }
            }

            return $value;
        }

        function option_rigid($value, $option)
        {
            global $post;

            if (!empty($post)) {
                $product = wc_get_product($post->ID);
                if (is_a($product, 'WC_Gift_Voucher_Product')) {
                    if (!empty($value) && is_array($value)) {
                        if (isset($value['ajax_to_cart_single']) && true === boolval($value['ajax_to_cart_single'])) {
                            $value['ajax_to_cart_single'] = 0;
                        }
                    }
                }
            }

            return $value;
        }
    }

    global $wpgv_gift_voucher_cart_process;
    $wpgv_gift_voucher_cart_process = new wpgv_gift_voucher_cart_process();

endif;
