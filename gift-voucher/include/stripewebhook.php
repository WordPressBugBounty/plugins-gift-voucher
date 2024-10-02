<?php

if( !defined( 'ABSPATH' ) ) exit;  // Exit if accessed directly

global $wpdb;

$setting_table  = $wpdb->prefix . 'giftvouchers_setting';
$setting_options = $wpdb->get_row( "SELECT * FROM $setting_table WHERE id = 1" );

$wpgv_stripe_webhook_key = get_option('wpgv_stripe_webhook_key') ? get_option('wpgv_stripe_webhook_key') : '';

//include Stripe PHP library
if(!class_exists('\Stripe\Checkout\Session')) {
  require_once( WPGIFT__PLUGIN_DIR .'/vendor/autoload.php');
}

//set api key
$stripe = array(
  "publishable_key" => $setting_options->stripe_publishable_key,
  "secret_key"      => $setting_options->stripe_secret_key,
  "webhook_key"     => $wpgv_stripe_webhook_key
);

\Stripe\Stripe::setApiKey($stripe['secret_key']);

// You can find your endpoint's secret in your webhook settings
$endpoint_secret = $stripe['webhook_key'];

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$event = null;

try {
  $event = \Stripe\Webhook::constructEvent(
    $payload, $sig_header, $endpoint_secret
  );
} catch(\UnexpectedValueException $e) {
  // Invalid payload
  http_response_code(400);
  exit();
} catch(\Stripe\Error\SignatureVerification $e) {
  // Invalid signature
  http_response_code(400);
  exit();
}

// Handle the checkout.session.completed event
if ($event->type == 'checkout.session.completed') {
  $session = $event->data->object;

  // Fulfill the purchase...
  // handle_checkout_session($session);
}

http_response_code(200);