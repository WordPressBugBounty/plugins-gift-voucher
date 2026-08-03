<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the additive Stripe webhook endpoint.
 *
 * The endpoint is additive; the existing success page remains a fallback.
 */
function wpgv_register_stripe_webhook_rest_route()
{
    register_rest_route(
        'gift-voucher/v1',
        '/stripe-webhook',
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'wpgv_handle_stripe_webhook_rest_request',
            'permission_callback' => '__return_true',
        )
    );
}
add_action('rest_api_init', 'wpgv_register_stripe_webhook_rest_route');

/**
 * Verify and process a Stripe webhook request.
 *
 * @param WP_REST_Request $request Incoming REST request.
 * @return WP_REST_Response|WP_Error
 */
function wpgv_handle_stripe_webhook_rest_request(WP_REST_Request $request)
{
    if (!class_exists('\\Stripe\\Webhook')) {
        return new WP_Error(
            'wpgv_stripe_sdk_missing',
            __('Stripe SDK is unavailable.', 'gift-voucher'),
            array('status' => 503)
        );
    }

    $endpoint_secret = (string) get_option('wpgv_stripe_webhook_key', '');
    $payload = $request->get_body();
    $signature = (string) $request->get_header('stripe-signature');

    if ($endpoint_secret === '' || $payload === '' || $signature === '') {
        return new WP_Error(
            'wpgv_stripe_webhook_incomplete',
            __('Stripe webhook request is incomplete.', 'gift-voucher'),
            array('status' => 400)
        );
    }

    try {
        $event = \Stripe\Webhook::constructEvent($payload, $signature, $endpoint_secret);
    } catch (\UnexpectedValueException $exception) {
        return new WP_Error(
            'wpgv_stripe_webhook_invalid_payload',
            __('Invalid Stripe webhook payload.', 'gift-voucher'),
            array('status' => 400)
        );
    } catch (\Stripe\Exception\SignatureVerificationException $exception) {
        return new WP_Error(
            'wpgv_stripe_webhook_invalid_signature',
            __('Invalid Stripe webhook signature.', 'gift-voucher'),
            array('status' => 400)
        );
    } catch (\Exception $exception) {
        return new WP_Error(
            'wpgv_stripe_webhook_verification_failed',
            __('Stripe webhook verification failed.', 'gift-voucher'),
            array('status' => 400)
        );
    }

    if (!isset($event->type) || $event->type !== 'checkout.session.completed') {
        return new WP_REST_Response(
            array(
                'received'           => true,
                'event_id'           => isset($event->id) ? sanitize_text_field($event->id) : '',
                'event_type'         => sanitize_text_field((string) ($event->type ?? '')),
                'fulfillment_status' => 'ignored',
            ),
            200
        );
    }

    if (!wpgv_is_stripe_webhook_fulfillment_enabled()) {
        return new WP_REST_Response(array(
            'received' => true,
            'event_id' => isset($event->id) ? sanitize_text_field($event->id) : '',
            'event_type' => sanitize_text_field((string) $event->type),
            'fulfillment_status' => 'disabled',
        ), 200);
    }

    $session = $event->data->object ?? null;
    $session_data = is_object($session) && method_exists($session, 'jsonSerialize') ? $session->jsonSerialize() : array();
    $payment_intent_id = sanitize_text_field((string) ($session_data['payment_intent'] ?? ''));
    if (!$session || $payment_intent_id === '' || !class_exists('\\Stripe\\PaymentIntent')) {
        return new WP_Error('wpgv_stripe_fulfillment_incomplete', __('Stripe Checkout Session is missing its PaymentIntent.', 'gift-voucher'), array('status' => 400));
    }

    try {
        $settings = get_option('wpgv_settings');
        global $wpdb;
        $settings = $wpdb->get_row("SELECT * FROM `{$wpdb->prefix}giftvouchers_setting` WHERE `id` = 1");
        if (!$settings || empty($settings->stripe_secret_key)) {
            return new WP_Error('wpgv_stripe_secret_missing', __('Stripe secret key is not configured.', 'gift-voucher'), array('status' => 503));
        }
        \Stripe\Stripe::setApiKey($settings->stripe_secret_key);
        $payment_intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);
        $result = wpgv_fulfill_verified_stripe_voucher($session, $payment_intent, $event->id ?? '');
    } catch (\Exception $exception) {
        return new WP_Error('wpgv_stripe_fulfillment_failed', __('Stripe fulfillment failed and will be retried.', 'gift-voucher'), array('status' => 500));
    }

    if (is_wp_error($result)) {
        return new WP_REST_Response(
            array(
                'received'           => true,
                'event_id'           => isset($event->id) ? sanitize_text_field($event->id) : '',
                'event_type'         => sanitize_text_field((string) $event->type),
                'fulfillment_status' => 'ignored',
                'reason'             => $result->get_error_code(),
            ),
            200
        );
    }

    return new WP_REST_Response(
        array(
            'received'           => true,
            'event_id'           => isset($event->id) ? sanitize_text_field($event->id) : '',
            'event_type'         => isset($event->type) ? sanitize_text_field($event->type) : '',
            'fulfillment_status' => 'fulfilled',
            'voucher_id'         => $result['voucher_id'],
            'updated'            => $result['updated'],
            'mail_status'        => $result['mail_status'] === true ? 'sent' : 'deferred',
        ),
        200
    );
}
