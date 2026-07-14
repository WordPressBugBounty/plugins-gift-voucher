<?php

defined('ABSPATH') or exit;

if (! class_exists('wpgv_gift_voucher_admin')) :

    final class wpgv_gift_voucher_admin
    {

        function __construct()
        {
            global $wpgv_gift_voucher;

            add_action('woocommerce_process_product_meta_' . WPGV_PRODUCT_TYPE_SLUG, array($this, 'process_wpgv_product_meta_data'));

            add_action('wp_ajax_ajax_add_wpgv_voucher_amount', array($this, 'ajax_add_wpgv_voucher_amount'));

            add_action('wp_ajax_ajax_remove_wpgv_voucher_amount', array($this, 'ajax_remove_wpgv_voucher_amount'));
        }

        function process_wpgv_product_meta_data($post_id)
        {
            if (!isset($_POST['_wpgv_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpgv_nonce'])), 'wpgv_save_product_meta')) {
                wp_die(esc_html__('Invalid request.', 'gift-voucher'));
            }

            if (!current_user_can('edit_post', $post_id)) {
                wp_die(esc_html__('You do not have permission to edit this product.', 'gift-voucher'));
            }

            // Denominations are created by the dedicated AJAX Add action below.
            // Do not process wpgv_price here: it is still part of the product form
            // and would be submitted again when the administrator clicks Update.
            // That caused an existing denomination to be added a second time.
            // WooCommerce has already saved the product before this type-specific hook.
            // The companion sync callback keeps the denomination attributes current.
        }


        // ajax add new gift voucher price varation
        function ajax_add_wpgv_voucher_amount()
        {
            global $wpgv_gift_voucher;
            //global $product_object;

            $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

            if (!wp_verify_nonce($nonce, 'wpgv_nonce_action')) {
                wp_send_json_error(array('success' => 0, 'message' => __('Nonce verification failed', 'gift-voucher')));
                wp_die();
            }


            $wpgv_gift_voucher->set_current_currency_to_default();

            if (! current_user_can('edit_products')) {
                wp_die(-1);
            }

            $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
            $new_amount = isset($_POST['wpgv_price']) ? sanitize_text_field(wp_unslash($_POST['wpgv_price'])) : '';
            $new_amount = $wpgv_gift_voucher->sanitize_amount($new_amount);

            if ($product = new wpgv_wc_product_gift_voucher($product_id)) {
                $result = $product->add_amount($new_amount);

                if (is_numeric($result)) {
                    $handle = new WC_Product_Variable($product_id);
                    //$variations=$handle->get_children();
                    $variations = array_map('wc_get_product', $handle->get_children());
                    //$variations = array_map( 'wc_get_product', $product_object->get_children() );
                    $variations_html = '';
                    foreach ($variations as $variation) {
                        if ($variation->get_regular_price() > 0) {
                            $variations_html .= '
                        <span class="wpgv-tag wpgv-amount-container" data-variation_id="' . $variation->get_id() . '">' . $wpgv_gift_voucher->pretty_price($variation->get_regular_price()) . '<span class="wpgv-remove-amount-button wpgv-price-remove" data-role="remove">×</span> </span>';
                        }
                    }
                    wp_send_json_success(array('succsess' => 1, 'variations_html' => $variations_html));
                } else {
                    wp_send_json_error(array('succsess' => 0, 'message' => $result));
                }
            } else {
                // translators: %s is the product ID.
                wp_send_json_error(array('succsess' => 0, 'message' => sprintf(__('Could not locate product id %s', 'gift-voucher'), $product_id)));
            }
        }

        // remove gift voucher price variation
        function ajax_remove_wpgv_voucher_amount()
        {
            $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

            if (!wp_verify_nonce($nonce, 'wpgv_nonce_action')) {
                wp_send_json_error(array('success' => 0, 'message' => __('Nonce verification failed', 'gift-voucher')));
                wp_die();
            }

            if (! current_user_can('edit_products')) {
                wp_die(-1);
            }

            $product_id   = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
            $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;;

            if ($product = new wpgv_wc_product_gift_voucher($product_id)) {

                $result = $product->delete_amount($variation_id);
                if ($result === true) {
                    wp_send_json_success(array('succsess' => 1));
                } else {
                    wp_send_json_error(array('message' => $result));
                }
            } else {
                wp_send_json_error(array('message' => __('Could not locate product using product_id ', 'gift-voucher') . $variation->get_parent_id()));
            }
        }
    }

    global $wpgv_gift_voucher_admin;
    $wpgv_gift_voucher_admin = new wpgv_gift_voucher_admin();

endif;
