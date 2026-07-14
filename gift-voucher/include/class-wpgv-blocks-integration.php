<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WPGV_Blocks_Integration') && interface_exists('Automattic\\WooCommerce\\Blocks\\Integrations\\IntegrationInterface')) {

    final class WPGV_Blocks_Integration implements Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface
    {
        const SCRIPT_HANDLE = 'wpgv-blocks-voucher-redemption';

        public function get_name()
        {
            return 'gift-voucher';
        }

        public function initialize()
        {
            if (!wpgv_is_woocommerce_enable()) {
                return;
            }

            $asset_path = WPGIFT__PLUGIN_DIR . '/assets/js/wpgv-blocks-voucher-redemption.asset.php';
            $asset = file_exists($asset_path) ? require $asset_path : array();
            $dependencies = isset($asset['dependencies']) && is_array($asset['dependencies']) ? $asset['dependencies'] : array(
                'wc-blocks-checkout',
                'wc-price-format',
                'wp-element',
                'wp-i18n',
                'wp-plugins',
            );
            $version = isset($asset['version']) ? $asset['version'] : WPGIFT_VERSION;

            wp_register_script(
                self::SCRIPT_HANDLE,
                WPGIFT__PLUGIN_URL . '/assets/js/wpgv-blocks-voucher-redemption.js',
                $dependencies,
                $version,
                true
            );
            wp_register_style(
                self::SCRIPT_HANDLE,
                WPGIFT__PLUGIN_URL . '/assets/css/wpgv-blocks-voucher-redemption.css',
                array(),
                $version
            );
            if (function_exists('wp_set_script_translations')) {
                wp_set_script_translations(self::SCRIPT_HANDLE, 'gift-voucher', WPGIFT__PLUGIN_DIR . '/languages');
            }
            add_action('wp_enqueue_scripts', array($this, 'enqueue_style'));
        }

        public function get_script_handles()
        {
            return wpgv_is_woocommerce_enable() ? array(self::SCRIPT_HANDLE) : array();
        }

        public function get_editor_script_handles()
        {
            return array();
        }

        public function get_script_data()
        {
            return array(
                'namespace' => 'gift-voucher',
                'enabled'   => wpgv_is_woocommerce_enable(),
                'redeem_form_enabled' => wpgv_is_woocommerce_redeem_form_enabled(),
            );
        }

        public function enqueue_style()
        {
            if (wpgv_is_woocommerce_enable() && (has_block('woocommerce/cart') || has_block('woocommerce/checkout'))) {
                wp_enqueue_style(self::SCRIPT_HANDLE);
            }
        }
    }
}

function wpgv_register_blocks_integration()
{
    if (!interface_exists('Automattic\\WooCommerce\\Blocks\\Integrations\\IntegrationInterface')) {
        return;
    }

    require_once WPGIFT__PLUGIN_DIR . '/include/class-wpgv-blocks-integration.php';

    if (!class_exists('WPGV_Blocks_Integration')) {
        return;
    }

    $integration = new WPGV_Blocks_Integration();
    $register = static function ($registry) use ($integration) {
        if (is_object($registry) && method_exists($registry, 'register')) {
            $registry->register($integration);
        }
    };

    add_action('woocommerce_blocks_cart_block_registration', $register, 10, 1);
    add_action('woocommerce_blocks_checkout_block_registration', $register, 10, 1);
}
