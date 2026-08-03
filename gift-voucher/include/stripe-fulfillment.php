<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fulfil a verified Stripe Checkout payment for one voucher.
 *
 * This function is intentionally shared by the webhook and the existing
 * success-page fallback. The caller must pass an already verified Checkout
 * Session and its PaymentIntent.
 *
 * @return array|WP_Error
 */
function wpgv_fulfill_verified_stripe_voucher($checkout_session, $payment_intent, $event_id = '', $legacy_voucher_id = 0, $legacy_currency = '')
{
    global $wpdb;

    if (!is_object($checkout_session) || !is_object($payment_intent)) {
        return new WP_Error('wpgv_stripe_context_invalid', __('Stripe payment context is invalid.', 'gift-voucher'));
    }

    $session_data = method_exists($checkout_session, 'jsonSerialize')
        ? $checkout_session->jsonSerialize()
        : array();
    $session_metadata = wpgv_normalize_stripe_metadata($session_data['metadata'] ?? array());
    $voucher_id = absint($session_metadata['voucher_id'] ?? 0);
    $order_key = sanitize_text_field($session_metadata['order_key'] ?? '');

    $is_legacy = absint($legacy_voucher_id) > 0;
    if ($is_legacy) {
        $voucher_id = absint($legacy_voucher_id);
    }
    if (!$voucher_id || (!$is_legacy && ($order_key === '' || !wpgv_is_valid_voucher_order_key($voucher_id, $order_key)))) {
        return new WP_Error('wpgv_stripe_metadata_invalid', __('Stripe voucher metadata is invalid.', 'gift-voucher'));
    }

    $voucher_table = $wpdb->prefix . 'giftvouchers_list';
    $voucher = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$voucher_table}` WHERE `id` = %d", $voucher_id));
    if (!$voucher || $voucher->pay_method !== 'Stripe') {
        return new WP_Error('wpgv_stripe_voucher_invalid', __('Stripe voucher order was not found.', 'gift-voucher'));
    }

    $expected_metadata = array(
        'voucher_id'   => (string) $voucher_id,
        'order_key'    => $order_key,
        'amount_minor' => sanitize_text_field($session_metadata['amount_minor'] ?? ''),
        'currency'     => strtoupper(sanitize_text_field($session_metadata['currency'] ?? '')),
    );

    if ($is_legacy) {
        $legacy_amount_minor = wpgv_get_stripe_amount_minor_units($voucher->amount);
        if ($legacy_amount_minor === null || !wpgv_is_legacy_stripe_checkout_session_bound_to_voucher($checkout_session, $legacy_amount_minor, $legacy_currency) || !wpgv_is_legacy_stripe_payment_intent_bound_to_voucher($payment_intent, $legacy_amount_minor, $legacy_currency)) {
            return new WP_Error('wpgv_stripe_legacy_payment_mismatch', __('Legacy Stripe payment does not match the voucher.', 'gift-voucher'));
        }
    } elseif (!wpgv_is_stripe_checkout_session_bound_to_voucher($checkout_session, $expected_metadata)) {
        return new WP_Error('wpgv_stripe_session_mismatch', __('Stripe Checkout Session does not match the voucher.', 'gift-voucher'));
    } elseif (!wpgv_is_stripe_payment_intent_bound_to_voucher($payment_intent, $expected_metadata)) {
        return new WP_Error('wpgv_stripe_payment_mismatch', __('Stripe PaymentIntent does not match the voucher.', 'gift-voucher'));
    }

    $session_id = isset($session_data['id']) ? sanitize_text_field((string) $session_data['id']) : '';
    $payment_intent_data = method_exists($payment_intent, 'jsonSerialize') ? $payment_intent->jsonSerialize() : array();
    $payment_intent_id = isset($payment_intent_data['id']) ? sanitize_text_field((string) $payment_intent_data['id']) : '';
    $event_id = sanitize_text_field((string) $event_id);
    $settings = $wpdb->get_row("SELECT * FROM `{$wpdb->prefix}giftvouchers_setting` WHERE `id` = 1");

    // The conditional update is the durable idempotency gate for retries and races.
    $updated = $wpdb->query($wpdb->prepare(
        "UPDATE `{$voucher_table}` SET `payment_status` = %s, `voucheradd_time` = %s WHERE `id` = %d AND `pay_method` = %s AND `payment_status` <> %s",
        'Paid',
        current_time('mysql'),
        $voucher_id,
        'Stripe',
        'Paid'
    ));

    update_post_meta($voucher_id, 'wpgv_stripe_session_key', $session_id);
    update_post_meta($voucher_id, 'wpgv_stripe_payment_intent_key', $payment_intent_id);
    if ($event_id !== '') {
        update_post_meta($voucher_id, 'wpgv_stripe_webhook_event_id', $event_id);
    }
    // Preserve the existing success-page metadata format for compatibility.
    $stripe_publishable_key = $settings && isset($settings->stripe_publishable_key) ? (string) $settings->stripe_publishable_key : '';
    update_post_meta($voucher_id, 'wpgv_stripe_mode_for_transaction', $stripe_publishable_key);

    if ((int) $updated > 0) {
        $activity_table = $wpdb->prefix . 'giftvouchers_activity';
        $activity_exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$activity_table}` WHERE `voucher_id` = %d AND `action` = %s",
            $voucher_id,
            'firsttransact'
        ));
        if (!$activity_exists) {
            WPGV_Gift_Voucher_Activity::record($voucher_id, 'firsttransact', $voucher->amount, 'Voucher payment recieved.');
        }
    }

    $voucher = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$voucher_table}` WHERE `id` = %d", $voucher_id));
    $mail_status = wpgv_send_stripe_voucher_emails_once($voucher, $settings);

    return array(
        'voucher_id' => $voucher_id,
        'updated'    => (int) $updated > 0,
        'mail_sent'  => $mail_status === true,
        'mail_status' => $mail_status,
    );
}

/**
 * Send the existing Stripe confirmation emails once using a DB claim.
 *
 * @return bool|null True when sent/already sent, null when sending failed or another request owns the claim.
 */
function wpgv_send_stripe_voucher_emails_once($voucher, $settings)
{
    global $wpdb;
    if (!$voucher || !$settings) {
        return null;
    }
    if ($voucher->check_send_mail === 'sent') {
        return true;
    }

    $table = $wpdb->prefix . 'giftvouchers_list';
    $claimed = $wpdb->query($wpdb->prepare(
        "UPDATE `{$table}` SET `check_send_mail` = %s WHERE `id` = %d AND `check_send_mail` = %s",
        'sending',
        absint($voucher->id),
        'unsent'
    ));
    if ((int) $claimed !== 1) {
        return null;
    }

    $subject = get_option('wpgv_emailsubject') ?: 'Order Confirmation - Your Order with {company_name} (Voucher Order No: {order_number} ) has been successfully placed!';
    $body = get_option('wpgv_emailbody') ?: '<p>Dear <strong>{customer_name}</strong>,</p><p>Order successfully placed.</p><p>We are pleased to confirm your order no {order_number}</p><p>Thank you for shopping with <strong>{company_name}</strong>!</p><p>You can download the voucher from {pdf_link}.</p><p>- For any clarifications please feel free to email us at {sender_email}.</p><p><strong>Warm Regards, <br /></strong> <strong>{company_name}<br />{website_url}</strong></p>';
    $recipient_subject = get_option('wpgv_recipientemailsubject') ?: 'Gift Voucher - Your have received voucher from {company_name}';
    $recipient_body = get_option('wpgv_recipientemailbody') ?: '<p>Dear <strong>{recipient_name}</strong>,</p><p>You have received gift voucher fromÂ <strong>{customer_name}</strong>.</p><p>You can download the voucher from {pdf_link}.</p><p>- For any clarifications please feel free to email us at {sender_email}.</p><p><strong>Warm Regards, <br /></strong> <strong>{company_name}<br />{website_url}</strong></p>';
    $admin_subject = get_option('wpgv_adminemailsubject') ?: 'New Voucher Order Received from {customer_name}Â  (Order No: {order_number})!';
    $admin_body = get_option('wpgv_adminemailbody') ?: '<p>Hello, New Voucher Order received.</p><p><strong>Order Id:</strong> {order_number}</p><p><strong>Name:</strong> {customer_name}<br /><strong>Email:</strong> {customer_email}<br /><strong>Address:</strong> {customer_address}<br /><strong>Postcode:</strong> {customer_postcode}</p>';

    $attachments = array(wpgv_get_voucher_pdf_path($voucher->voucherpdf_link));
    $headers = 'Content-type: text/html;charset=utf-8' . "\r\n";
    $headers .= 'From: ' . $settings->sender_name . ' <' . $settings->sender_email . ">\r\n";
    $headers .= 'Reply-to: ' . $settings->sender_name . ' <' . $settings->sender_email . ">\r\n";

    if ($voucher->shipping_type !== 'shipping_as_post') {
        $recipient_to = $voucher->to_name . '<' . $voucher->shipping_email . '>';
        if ($voucher->buying_for === 'yourself') {
            $recipient_to = $voucher->from_name . '<' . $voucher->email . '>';
        }
        wp_mail($recipient_to, wpgv_mailvarstr($recipient_subject, $settings, $voucher), wpgv_mailvarstr($recipient_body, $settings, $voucher), $headers, $attachments);
    }

    $attachments[] = wpgv_get_voucher_pdf_path($voucher->voucherpdf_link, '-receipt');
    $buyer_sent = wp_mail(
        $voucher->from_name . '<' . $voucher->email . '>',
        wpgv_mailvarstr($subject, $settings, $voucher),
        wpgv_mailvarstr($body, $settings, $voucher),
        $headers,
        $attachments
    );
    if (!$buyer_sent) {
        update_check_send_mail($voucher->id, 'unsent');
        return null;
    }

    $admin_headers = 'Content-type: text/html;charset=utf-8' . "\r\n";
    $admin_headers .= 'From: ' . $settings->sender_name . ' <' . $settings->sender_email . ">\r\n";
    $admin_headers .= 'Reply-to: ' . $voucher->from_name . ' <' . $voucher->email . ">\r\n";
    wp_mail(
        $settings->sender_name . ' <' . $settings->sender_email . '>',
        wpgv_mailvarstr($admin_subject, $settings, $voucher),
        wpgv_mailvarstr($admin_body, $settings, $voucher),
        $admin_headers,
        $attachments
    );
    update_check_send_mail($voucher->id, 'sent');
    return true;
}
