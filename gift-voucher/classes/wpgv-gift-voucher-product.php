<?php

if (!defined('ABSPATH')) exit;  // Exit if accessed directly

if (! class_exists('wpgv_gift_voucher_product')) :

    class wpgv_gift_voucher_product
    {

        public $gift_voucher_meta;

        function __construct()
        {

            global $wpdb;

            define('WPGV_DENOMINATION_ATTRIBUTE_NAME', __('Gift Voucher Amount', 'gift-voucher'));
            define('WPGV_GIFT_VOUCHER_NUMBER_META_DISPLAY_NAME', __('Gift Voucher', 'gift-voucher'));
            define('WPGV_AMOUNT_META_DISPLAY_NAME', __('Amount', 'gift-voucher'));
            define('WPGV_YOUR_NAME_META_DISPLAY_NAME', __('Your Name', 'gift-voucher'));
            define('WPGV_RECIPIENT_NAME_META_DISPLAY_NAME', __('Recipient Name', 'gift-voucher'));
            define('WPGV_RECIPIENT_EMAIL_META_DISPLAY_NAME', __('Recipient email', 'gift-voucher'));
            define('WPGV_YOUR_EMAIL_META_DISPLAY_NAME', __('Your email', 'gift-voucher'));
            define('WPGV_MESSAGE_META_DISPLAY_NAME', __('Personal Message', 'gift-voucher'));

            $this->gift_voucher_meta = array(
                WPGV_AMOUNT_META_KEY                    => WPGV_AMOUNT_META_DISPLAY_NAME,
                WPGV_YOUR_NAME_META_KEY                        => WPGV_YOUR_NAME_META_DISPLAY_NAME,
                WPGV_RECIPIENT_NAME_META_KEY                      => WPGV_RECIPIENT_NAME_META_DISPLAY_NAME,
                WPGV_RECIPIENT_EMAIL_META_KEY                   => WPGV_RECIPIENT_EMAIL_META_DISPLAY_NAME,
                WPGV_YOUR_EMAIL_META_KEY                   => WPGV_YOUR_EMAIL_META_DISPLAY_NAME,
                WPGV_MESSAGE_META_KEY                   => WPGV_MESSAGE_META_DISPLAY_NAME,
            );

            require_once(WPGIFT__PLUGIN_DIR . '/classes/wpgv-gift-voucher-cart-process.php');
        }


        function set_current_currency_to_default()
        {
            // WooCommerce Currency Switcher by realmag777
            if (isset($GLOBALS['WOOCS']) && method_exists($GLOBALS['WOOCS'], 'get_currencies')) {
                $default_currency = false;
                foreach ($GLOBALS['WOOCS']->get_currencies() as $currency) {
                    if ($currency['is_etalon'] === 1) {
                        $default_currency = $currency;
                        break;
                    }
                }

                if ($default_currency) {
                    $GLOBALS['WOOCS']->current_currency = $default_currency['name'];
                }

                return;
            }

            // WooCommerce Ultimate Multi Currency Suite
            if (class_exists('WooCommerce_Ultimate_Multi_Currency_Suite_Main') && isset($GLOBALS['woocommerce_ultimate_multi_currency_suite'])) {
                $cs = $GLOBALS['woocommerce_ultimate_multi_currency_suite'];
                if (is_object($cs) && property_exists($cs, 'frontend') && !empty($cs->frontend)) {
                    remove_filter('woocommerce_currency_symbol', array($cs->frontend, 'custom_currency_symbol'), 9999);
                    remove_filter('wc_price_args', array($cs->frontend, 'price_formatting'), 9999);
                    remove_filter('woocommerce_price_format', array($cs->frontend, 'custom_price_format'), 9999);
                    remove_filter('raw_woocommerce_price', array($cs->frontend, 'custom_reference_price'), 9999);
                    remove_filter('woocommerce_currency', array($cs->frontend, 'custom_currency'), 9999);
                }
            }

            // Aelia Currency Switcher
            if (!has_filter('wc_aelia_cs_selected_currency', array($this, 'wc_aelia_cs_selected_currency'))) {
                add_filter('wc_aelia_cs_selected_currency', array($this, 'wc_aelia_cs_selected_currency'));
            }

            // WPML (WooCommerce Multilingual plugin)
            if (isset($GLOBALS['woocommerce_wpml'])) {
                $wpml = $GLOBALS['woocommerce_wpml'];
                if (is_object($wpml) && property_exists($wpml, 'multi_currency') && is_object($wpml->multi_currency) && property_exists($wpml->multi_currency, 'prices') && property_exists($wpml->multi_currency, 'orders')) {
                    $prices = $wpml->multi_currency->prices;
                    remove_filter('wc_price', array($prices, 'price_in_specific_currency'), 10, 3);
                    remove_filter('woocommerce_currency', array($prices, 'currency_filter'));
                    remove_filter('wc_price_args', array($prices, 'filter_wc_price_args'));
                    remove_filter('woocommerce_adjust_price', array($prices, 'raw_price_filter'), 10);
                    remove_filter('option_woocommerce_price_thousand_sep', array($prices, 'filter_currency_thousand_sep_option'));
                    remove_filter('option_woocommerce_price_decimal_sep', array($prices, 'filter_currency_decimal_sep_option'));
                    remove_filter('option_woocommerce_price_num_decimals', array($prices, 'filter_currency_num_decimals_option'));
                    remove_filter('option_woocommerce_currency_pos', array($prices, 'filter_currency_position_option'));

                    $orders = $wpml->multi_currency->orders;
                    remove_filter('woocommerce_currency_symbol', array($orders, '_use_order_currency_symbol'));
                }
            }

            // Multi-Currency for WooCommerce by TIV.NET INC
            if (class_exists('WOOMC\App')) {
                // Ensure this is only attached once.
                remove_filter('woocommerce_currency_symbol', array($this, 'woomc_default_currency_symbol'));
                add_filter('woocommerce_currency_symbol', array($this, 'woomc_default_currency_symbol'));
            }
        }

        function price_sort($a, $b)
        {
            if (!$a || !$b) {
                return 0;
            }

            $a_price = $this->numeric_price($a->get_regular_price());
            $b_price = $this->numeric_price($b->get_regular_price());

            if ($a_price == $b_price) {
                return 0;
            }

            // Make sure the "Custom Amount" floats to the bottom.
            if ($a_price == 0) {
                return 1;
            } else if ($b_price == 0) {
                return -1;
            }

            return ($a_price < $b_price) ? -1 : 1;
        }

        function only_numbers_and_decimal($value)
        {
            return preg_replace('/[^0-9.]/', '', strip_tags(html_entity_decode($value)));
        }

        function sanitize_amount($amount)
        {
            $thousand_separator = wc_get_price_thousand_separator();
            $decimal_separator = wc_get_price_decimal_separator();

            $amount = strip_tags(html_entity_decode($amount));
            $amount = str_replace($thousand_separator, '', $amount);
            $amount = str_replace($decimal_separator, '.', $amount);

            return apply_filters('wpgv_sanitize_amount', $amount);
        }

        function equal_prices($price_a, $price_b)
        {
            // Compare prices numerically.
            $price_a = $this->numeric_price($price_a);
            $price_b = $this->numeric_price($price_b);

            return ($price_a == $price_b);
        }

        function numeric_price($price)
        {
            $numbers = $this->only_numbers_and_decimal($price);
            if ($numbers != '') {
                return floatval($numbers);
            } else {
                return $price;
            }
        }

        function pretty_price($price)
        {
            $amount = $this->only_numbers_and_decimal($price);
            if ($amount != '') {

                if ('yes' === get_option('wpgv_format_prices', 'yes')) {
                    $decimals = fmod($amount, 1) > 0 ? wc_get_price_decimals() : 0;
                    $amount = wc_price($amount, array('decimals' => $decimals));
                }

                $amount = strip_tags($amount);
                $amount = html_entity_decode($amount);
                return $amount;
            } else {
                return $price;
            }
        }
    }

    global $wpgv_gift_voucher;
    $wpgv_gift_voucher = new wpgv_gift_voucher_product();

endif;
