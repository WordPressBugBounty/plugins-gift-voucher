<?php

use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;

class PayPalAuth
{
    public static function client()
    {
        return new PayPalHttpClient(self::environment());
    }
    public static function environment()
    {
        global $wpdb;
        $setting_table  = $wpdb->prefix . 'giftvouchers_setting';
        $setting_options = $wpdb->get_row("SELECT * FROM $setting_table WHERE id = 1");

        $mode = (!$setting_options->test_mode) ? 'live' : 'sandbox';

        $wpgv_paypal_client_id = get_option('wpgv_paypal_client_id') ? get_option('wpgv_paypal_client_id') : '';
        $wpgv_paypal_secret_key = get_option('wpgv_paypal_secret_key') ? get_option('wpgv_paypal_secret_key') : '';

        if ($mode == "sandbox") {
            $clientId = getenv("CLIENT_ID") ?: $wpgv_paypal_client_id;
            $clientSecret = getenv("CLIENT_SECRET") ?: $wpgv_paypal_secret_key;
            return new SandboxEnvironment($clientId, $clientSecret);
        } else if ($mode == "live") {
            $clientId = getenv("CLIENT_ID") ?: $wpgv_paypal_client_id;
            $clientSecret = getenv("CLIENT_SECRET") ?: $wpgv_paypal_secret_key;
            return new ProductionEnvironment($clientId, $clientSecret);
        }
    }
    public static function isCredentialsValid()
    {
        $client = self::client();
        try {
            $response = $client->execute(new \PayPalCheckoutSdk\Orders\OrdersGetRequest("INVALID_ID"));
            return false;
        } catch (\PayPalHttp\HttpException $e) {
            if ($e->statusCode === 401) {
                return false;
            } else if ($e->statusCode === 404) {
                return true;
            } else {
                return false;
            }
        }
    }
}
