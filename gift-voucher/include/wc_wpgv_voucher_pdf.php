<?php

// namespace Sofort\SofortLib;

if (!defined('ABSPATH')) exit;  // Exit if accessed directly

function wc_wpgv_voucher_pdf_save_func($value, $for, $from, $email, $shipping_email, $message, $code, $payment_method, $product_img, $product_id, $wvgc_order_id)
{

    $buyingfor = "someone_else";
    $expiry = "";
    $shipping = "shipping_as_email";
    $firstname = "";
    $lastname = '';
    $address = '';
    $pincode = '';
    $shipping_method = '';

    global $wpdb;
    $voucher_table     = $wpdb->prefix . 'giftvouchers_list';
    $setting_table     = $wpdb->prefix . 'giftvouchers_setting';
    $template_table = $wpdb->prefix . 'giftvouchers_template';
    $setting_options = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}giftvouchers_setting WHERE id = %d", 1));

    /*$template_options = $wpdb->get_row( "SELECT * FROM $template_table WHERE id = $template" );
	$images = $template_options->image_style ? json_decode($template_options->image_style) : ['','',''];*/

    $voucher_bgcolor = wpgv_hex2rgb($setting_options->voucher_bgcolor);
    $voucher_color = wpgv_hex2rgb($setting_options->voucher_color);
    $currency = wpgv_price_format($value);

    $wpgv_hide_expiry = get_option('wpgv_hide_expiry') ? get_option('wpgv_hide_expiry') : 'yes';
    $wpgv_customer_receipt = get_option('wpgv_customer_receipt') ? get_option('wpgv_customer_receipt') : 0;
    $wpgv_expiry_date_format = get_option('wpgv_expiry_date_format') ? get_option('wpgv_expiry_date_format') : 'd.m.Y';
    $wpgv_enable_pdf_saving = get_option('wpgv_enable_pdf_saving') ? get_option('wpgv_enable_pdf_saving') : 0;
    $wpgv_add_extra_charges = get_option('wpgv_add_extra_charges') ? get_option('wpgv_add_extra_charges') : 0;

    if ($wpgv_hide_expiry == 'no') {
        $expiry = __('No Expiry', 'gift-voucher');
    } else {
        $expiry = ($setting_options->voucher_expiry_type == 'days') ? gmdate($wpgv_expiry_date_format, strtotime('+' . $setting_options->voucher_expiry . ' days', time())) . PHP_EOL : $setting_options->voucher_expiry;
    }


    $upload = wp_upload_dir();
    $upload_dir = $upload['basedir'];
    $curr_time = time();
    $upload_dir = $upload_dir . '/voucherpdfuploads/' . $curr_time . $code . '.pdf';
    $upload_url = $curr_time . $code;

    $formtype = 'wpgv_voucher_product';
    $preview = false;
    $order_type = "gift_voucher_product";

    $voucher_style = 0;
    $image = $product_img;
    $stripeimage = $product_img;

    if ($image == "") {
        $image = get_option('wpgv_demoimageurl_voucher');
    }
    if ($setting_options->is_order_form_enable == 1) {
        require(WPGIFT__PLUGIN_DIR . '/templates/pdfstyles/style1.php');
    } else {
        require(WPGIFT__PLUGIN_DIR . '/templates/pdfstyles/style1-remove.php');
    }

    if ($wpgv_enable_pdf_saving) {
        $pdf->Output($upload_dir, 'F');
    } else {
        $pdf->Output('F', $upload_dir);
    }



    $wpdb->insert(
        $voucher_table,
        array(
            'order_type'        => 'gift_voucher_product',
            'product_id'         => $product_id,
            'order_id'             => $wvgc_order_id,
            'buying_for'        => $buyingfor,
            'from_name'         => $for,
            'to_name'             => $from,
            'amount'            => $value,
            'message'            => $message,
            'shipping_type'        => $shipping,
            'shipping_email'    => $shipping_email,
            'firstname'            => $firstname,
            'lastname'            => $lastname,
            'email'                => $email,
            'address'            => $address,
            'postcode'            => $pincode,
            'shipping_method'    => $shipping_method,
            'pay_method'        => $payment_method,
            'expiry'            => $expiry,
            'couponcode'        => $code,
            'voucherpdf_link'    => $upload_url,
            'payment_status'    => 'Paid',
            'status'            => 'unused',
            'voucheradd_time'    => current_time('mysql')
        )
    );

    $lastid = $wpdb->insert_id;

    WPGV_Gift_Voucher_Activity::record($lastid, 'create', '', 'Voucher ordered by ' . $for . ', Message: ' . $message);
    WPGV_Gift_Voucher_Activity::record($lastid, 'firsttransact', $value, 'Voucher payment added from gift voucher product');

    if ($shipping != 'shipping_as_email') {
        $preshipping_methods = explode(',', $setting_options->shipping_method);
        foreach ($preshipping_methods as $method) {
            $preshipping_method = explode(':', $method);
            if (trim(stripslashes($preshipping_method[1])) == trim(stripslashes($shipping_method))) {
                $value += trim($preshipping_method[0]);
                break;
            }
        }
    }
    $value += $wpgv_add_extra_charges;
    $currency = wpgv_price_format($value);
    update_post_meta($lastid, 'wpgv_extra_charges', $wpgv_add_extra_charges);
    update_post_meta($lastid, 'wpgv_total_payable_amount', $currency);

    $voucher_table     = $wpdb->prefix . 'giftvouchers_list';
    $setting_table     = $wpdb->prefix . 'giftvouchers_setting';

    $setting_options = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}giftvouchers_setting WHERE id = %d", 1));
    $voucher_options = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}giftvouchers_list WHERE id = %d", $lastid));

    $emailsubject = get_option('wpgv_emailsubject') ? get_option('wpgv_emailsubject') : 'Order Confirmation - Your Order with {company_name} (Voucher Order No: {order_number} ) has been successfully placed!';

    $recipientemailsubject = get_option('wpgv_recipientemailsubject') ? get_option('wpgv_recipientemailsubject') : 'Gift Voucher - Your have received voucher from {company_name}';

    $recipientemailbody = get_option('wpgv_recipientemailbody') ? get_option('wpgv_recipientemailbody') : '<p>Dear <strong>{recipient_name}</strong>,</p><p>You have received gift voucher product from <strong>{customer_name}</strong>.</p><p>You can download the voucher from {pdf_link}.</p><p>- For any clarifications please feel free to email us at {sender_email}.</p><p><strong>Warm Regards, <br /></strong> <strong>{company_name}<br />{website_url}</strong></p>';

    $emailbody = get_option('wpgv_emailbody') ? get_option('wpgv_emailbody') : '<p>Dear <strong>{customer_name}</strong>,</p><p>Order successfully placed.</p><p>We are pleased to confirm your order no {order_number}</p><p>Thank you for shopping with <strong>{company_name}</strong>!</p><p>You can download the voucher from {pdf_link}.</p><p>- For any clarifications please feel free to email us at {sender_email}.</p><p><strong>Warm Regards, <br /></strong> <strong>{company_name}<br />{website_url}</strong></p>';

    $adminemailsubject = get_option('wpgv_adminemailsubject') ? get_option('wpgv_adminemailsubject') : 'New Voucher Order Received from {customer_name}  (Order No: {order_number})!';

    $adminemailbody = get_option('wpgv_adminemailbody') ? get_option('wpgv_adminemailbody') : '<p>Hello, New Voucher Order received.</p><p><strong>Order Id:</strong> {order_number}</p><p><strong>Name:</strong> {customer_name}<br /><strong>Email:</strong> {customer_email}<br /><strong>Address:</strong> {customer_address}<br /><strong>Postcode:</strong> {customer_postcode}</p>';

    $upload = wp_upload_dir();
    $upload_dir = $upload['basedir'];
    $attachments[0] = $upload_dir . '/voucherpdfuploads/' . $voucher_options->voucherpdf_link . '.pdf';

    $headers = 'Content-type: text/html;charset=utf-8' . "\r\n";
    $headers .= 'From: ' . $setting_options->sender_name . ' <' . $setting_options->sender_email . '>' . "\r\n";
    $headers .= 'Reply-to: ' . $setting_options->sender_name . ' <' . $setting_options->sender_email . '>' . "\r\n";

    /* Recipient Mail */
    if ($voucher_options->shipping_type != 'shipping_as_post') {
        $recipientsub = wpgv_mailvarstr($recipientemailsubject, $setting_options, $voucher_options);
        $recipientmsg = wpgv_mailvarstr($recipientemailbody, $setting_options, $voucher_options);
        $recipientto = $voucher_options->from_name . '<' . $voucher_options->shipping_email . '>';
        if ($voucher_options->buying_for == 'yourself') {
            $recipientto = $voucher_options->from_name . '<' . $voucher_options->email . '>';
        }

        if ($voucher_options->email_send_date_time == 'send_instantly') {
            wp_mail($recipientto, $recipientsub, $recipientmsg, $headers, $attachments);
        } else {

            $timezone = get_option('timezone_string') ? get_option('timezone_string') : 'UTC';

            $send_gift_voucher_email_event_date_time = strtotime($voucher_options->email_send_date_time);

            $send_gift_voucher_email_event_date_time = get_date_from_gmt(gmdate('Y-m-d H:i:s', $send_gift_voucher_email_event_date_time), 'Y-m-d H:i:s');

            $send_gift_voucher_email_event_args = array($recipientto, $recipientsub, $recipientmsg, $headers, $attachments);
            wp_schedule_single_event($send_gift_voucher_email_event_date_time, 'send_gift_voucher_email_event', $send_gift_voucher_email_event_args);
        }
    }

    $attachments[1] = $upload_dir . '/voucherpdfuploads/' . $voucher_options->voucherpdf_link . '-receipt.pdf';
    $attachments[2] = $upload_dir . '/voucherpdfuploads/' . $voucher_options->voucherpdf_link . '-invoice.pdf';

    /* Buyer Mail */
    $buyersub = wpgv_mailvarstr($emailsubject, $setting_options, $voucher_options);
    $buyermsg = wpgv_mailvarstr($emailbody, $setting_options, $voucher_options);
    $buyerto = $voucher_options->from_name . '<' . $voucher_options->email . '>';
    $mail_sent = wp_mail($buyerto, $buyersub, $buyermsg, $headers, $attachments);

    if ($mail_sent == true) {
        $successpagemessage = get_option('wpgv_successpagemessage') ? get_option('wpgv_successpagemessage') : 'We have got your order! <br>E-Mail Sent Successfully to %s';
        $return .= '<div class="success">' . sprintf(stripslashes($successpagemessage), $voucher_options->email) . '</div>';

        if (isset($_GET['per_invoice']) && $_GET['per_invoice'] == 1) {
            $return .= $setting_options->bank_info;
        }

        $toadmin = $setting_options->sender_name . ' <' . $setting_options->sender_email . '>';
        $subadmin = wpgv_mailvarstr($adminemailsubject, $setting_options, $voucher_options);
        $bodyadmin = wpgv_mailvarstr($adminemailbody, $setting_options, $voucher_options);
        $headersadmin = 'Content-type: text/html;charset=utf-8' . "\r\n";
        $headersadmin .= 'From: ' . $setting_options->sender_name . ' <' . $setting_options->sender_email . '>' . "\r\n";
        $headersadmin .= 'Reply-to: ' . $voucher_options->from_name . ' <' . $voucher_options->email . '>' . "\r\n";

        wp_mail($toadmin, $subadmin, $bodyadmin, $headersadmin, $attachments);
    }
}

add_action('wc_wpgv_voucher_pdf_save_func', 'wc_wpgv_voucher_pdf_save_func', 1, 11);
