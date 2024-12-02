<?php

defined('ABSPATH') or exit;


if (! class_exists('wpgv_wc_product_gift_voucher')) :

    class wpgv_wc_product_gift_voucher extends WC_Product_Variable
    {
        /*
     *
     * Getters
     *
     */
        public function get_type()
        {
            return WPGV_PRODUCT_TYPE_SLUG;
        }

        public function is_type($type)
        {
            return (
                // Some themes/plugins will check to see if this is a Variable type before including files required for
                // the gift card product to work correctly. By checking for 'variable' we make this compatible with these
                // types of themes and plugins.
                $this->get_type() === $type || 'variable' === $type
                || (is_array($type) && (in_array($this->get_type(), $type) || in_array('variable', $type)))
            );
        }

        /*
     *
     * Other public methods
     *
     */
        public function get_price_html($price = '')
        {
            return parent::get_price_html($price);
        }

        public function add_amount($amount)
        {
            global $wpgv_gift_voucher;

            if ($wpgv_gift_voucher->numeric_price($amount) <= 0) {
                return __('Amount must be greater than zero.', 'gift-voucher');
            }

            $variations = array_map('wc_get_product', $this->get_children());

            // Check for existing amount.
            foreach ($variations as $variation) {
                $variation_attributes = $variation->get_attributes();

                if (isset($variation_attributes[WPGV_DENOMINATION_ATTRIBUTE_SLUG])) {
                    $variation_option = $variation_attributes[WPGV_DENOMINATION_ATTRIBUTE_SLUG];

                    if ($wpgv_gift_voucher->equal_prices($variation_option, $amount)) {
                        return __('Amount already exists: ', 'gift-voucher') . $amount;
                    }
                }
            }

            $variation_id = $this->create_variation($amount);

            if ($variation_id) {

                $this->save();

                return $variation_id;
            } else {
                return __('Could not create variation.', 'gift-voucher');
            }
        }

        public function delete_amount($variation_id)
        {
            if ($variation = wc_get_product($variation_id)) {
                $variation->delete(true);

                // Add the new variation to the current object's children list.
                $children = $this->get_children();
                if (($key = array_search($variation_id, $children)) !== false) {
                    unset($children[$key]);
                }
                $this->set_children($children);

                $this->wpgv_sync_gift_card_amount_attributes();

                $this->save();

                return true;
            } else {
                return __('Could not locate variation using variation_id ', 'gift-voucher') . $variation_id;
            }
        }

        public function has_amount_on_sale()
        {
            $result = false;

            $variations = array_map('wc_get_product', $this->get_children());
            foreach ($variations as $variation) {
                if ($variation->is_on_sale()) {
                    $result = true;
                    break;
                }
            }

            return $result;
        }

        /*
     *
     * Protected methods
     *
     */
        protected function create_variation($amount)
        {
            global $wpgv_gift_voucher;

            $variation = new WC_Product_Variation();
            $variation->set_parent_id($this->get_id());
            $variation->set_virtual('1');

            $variation->set_regular_price($wpgv_gift_voucher->numeric_price($amount));
            $variation->set_attributes(array(WPGV_DENOMINATION_ATTRIBUTE_SLUG => $wpgv_gift_voucher->pretty_price($amount)));

            do_action('product_variation_linked', $variation->save());

            // Add the new variation to the current object's children list.
            $children = $this->get_children();
            array_push($children, $variation->get_id());
            $this->set_children($children);

            $this->wpgv_sync_gift_card_amount_attributes();

            return $variation->get_id();
        }

        public function wpgv_sync_gift_card_amount_attributes()
        {
            global $post;
            global $wpgv_gift_voucher;
            global $wpdb;

            $wpgv_gift_voucher->set_current_currency_to_default();

            $variations = array_map('wc_get_product', $this->get_children());

            // Re-order all Variations based on the amount.
            uasort($variations, array($wpgv_gift_voucher, 'price_sort'));

            $index = 0;
            foreach ($variations as $variation) {
                $wpdb->update($wpdb->posts, array('menu_order' => $index), array('ID' => absint($variation->get_id())));
                $index++;

                // Ensure that the attributes are correct on the variations.
                $variation->set_attributes(array(WPGV_DENOMINATION_ATTRIBUTE_SLUG => $wpgv_gift_voucher->pretty_price($variation->get_regular_price())));
                $variation->save();
            }

            $options = array();
            foreach ($variations as $variation) {
                $price = apply_filters('wpgv_to_default_currency', $variation->get_regular_price());
                if (!in_array($price, $options) && $price > 0) {
                    $options[] = $price;
                }
            }

            $attributes = $this->get_attributes();

            $attribute = new WC_Product_Attribute();
            $attribute->set_name(WPGV_DENOMINATION_ATTRIBUTE_NAME);
            $attribute->is_taxonomy(0);
            $attribute->set_position(0);
            $attribute->set_visible(apply_filters('wpgv_gift_voucher_amount_attribute_visible_on_product_page', true, $this));
            $attribute->set_variation('1');

            $options = array_map(array($wpgv_gift_voucher, 'pretty_price'), $options);

            $attribute->set_options($options);

            $attributes[WPGV_DENOMINATION_ATTRIBUTE_SLUG] = $attribute;

            $this->set_attributes($attributes);

            if (!empty($post) && $post->post_type == 'product') {
                $this->save();
            }
        }
    }


    // Uses the Variable template for the gift card product type.
    add_action('woocommerce_' . WPGV_PRODUCT_TYPE_SLUG . '_add_to_cart', 'woocommerce_variable_add_to_cart', 30);

    if ('yes' === get_option('wpgv_before_add_to_cart_quantity_theme_fix', 'no')) {
        function wpgv_before_add_to_cart_quantity_theme_fix()
        {
            global $product;

            if (is_a($product, 'WC_Gift_Voucher_Product') && !isset($GLOBALS['wpgv_theme_fix_applied'])) {
                $GLOBALS['wpgv_theme_fix_applied'] = true;
                do_action('woocommerce_before_add_to_cart_quantity');
            }
        }

        add_action('woocommerce_before_single_variation', 'wpgv_before_add_to_cart_quantity_theme_fix', 9);
        add_action('woocommerce_single_variation', 'wpgv_before_add_to_cart_quantity_theme_fix', 9);
        add_action('woocommerce_after_single_variation', 'wpgv_before_add_to_cart_quantity_theme_fix', 9);
    }

    add_action('woocommerce_before_add_to_cart_quantity', 'wpgv_woocommerce_before_add_to_cart_quantity', 30);
    function wpgv_woocommerce_before_add_to_cart_quantity()
    {
        global $product;

        // echo "<pre>"; print_r($product); echo "<pre>"; exit();

        if (is_a($product, 'WC_Gift_Voucher_Product')) {

            wp_enqueue_script('wpgv-voucher-product');

            wc_get_template('single-product/add-to-cart/wpgv-before-add-to-cart-quantity.php', array(), '', WPGIFT_PLUGIN_ROOT . 'templates/woocommerce/');

            // A customer's theme was calling woocommerce_before_add_to_cart_quantity multiple times so this is a fix for that scenario.
            if (!defined('WPGV_BEFORE_ADD_TO_CART_QUANTITY_FIX') || WPGV_BEFORE_ADD_TO_CART_QUANTITY_FIX === false) {
                remove_action('woocommerce_before_add_to_cart_quantity', 'wpgv_woocommerce_before_add_to_cart_quantity', 30);
            }
        }
    }


    function wpgv_woocommerce_data_stores($stores)
    {
        if (!isset($stores['product-' . WPGV_PRODUCT_TYPE_SLUG])) {
            $stores['product-' . WPGV_PRODUCT_TYPE_SLUG] = 'WC_Product_Variable_Data_Store_CPT';
        }

        return $stores;
    }
    add_filter('woocommerce_data_stores', 'wpgv_woocommerce_data_stores');

    add_action('woocommerce_process_product_meta_' . WPGV_PRODUCT_TYPE_SLUG, 'wpgv_process_gift_voucher_product_meta_data');
    function wpgv_process_gift_voucher_product_meta_data($post_id)
    {
        $product = new wpgv_wc_product_gift_voucher($post_id);
        $product->wpgv_sync_gift_card_amount_attributes();
    }

    function wpgv_woocommerce_product_add_to_cart_text($text, $product)
    {
        if (is_a($product, 'WC_Gift_Voucher_Product')) {
            return apply_filters('wpgv_select_amount_text', __('Select amount', 'gift-voucher'), $product);
        } else {
            return $text;
        }
    }
    add_filter('woocommerce_product_add_to_cart_text', 'wpgv_woocommerce_product_add_to_cart_text', 10, 2);


    function wpgv_woocommerce_variation_option_name($name)
    {
        global $product;
        global $wpgv_gift_voucher;

        if (is_a($product, 'WC_Gift_Voucher_Product') && 'yes' === get_option('wpgv_format_prices', 'yes') && ! class_exists('Woo_Variation_Swatches')) {
            $name = $wpgv_gift_voucher->sanitize_amount($name);
            $price = $wpgv_gift_voucher->numeric_price($name);

            // Don't want to adjust for some currency switchers.
            if (!isset($GLOBALS['WOOCS'])) {
                $price = apply_filters('wpgv_to_current_currency', $price);
            }

            return strip_tags(wc_price($price));
        }

        return $name;
    }
    add_filter('woocommerce_variation_option_name', 'wpgv_woocommerce_variation_option_name', 10, 2);


endif;
