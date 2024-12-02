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
            global $wpgv_gift_voucher;

            $product = new wpgv_wc_product_gift_voucher($post_id);

            /*echo "<pre>";
        print_r($product);
        echo "</pre>";
        exit;*/

            $new_amount = wc_clean($_POST['wpgv_price']);
            if (!empty($new_amount)) {
                $result = $product->add_amount($new_amount);
                if (!is_numeric($result)) {
                    wp_die($result);
                }

                /*echo "<pre>";
            print_r($result);
            echo "</pre>";
            exit;*/
            }

            $product->save();
        }

        // ajax add new gift voucher price varation
        function ajax_add_wpgv_voucher_amount()
        {
            global $wpgv_gift_voucher;
            //global $product_object;

            $wpgv_gift_voucher->set_current_currency_to_default();

            if (! current_user_can('edit_products')) {
                wp_die(-1);
            }

            $product_id = absint($_POST['product_id']);
            $new_amount = wc_clean($_POST['wpgv_price']);
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
                wp_send_json_error(array('succsess' => 0, 'message' => sprintf(__('Could not locate product id %s', 'gift-voucher'), $product_id)));
            }
        }

        // remove gift voucher price variation
        function ajax_remove_wpgv_voucher_amount()
        {

            if (! current_user_can('edit_products')) {
                wp_die(-1);
            }

            $product_id = absint($_POST['product_id']);
            $variation_id = absint($_POST['variation_id']);

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
