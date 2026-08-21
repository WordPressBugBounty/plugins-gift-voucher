<?php

if (!defined('ABSPATH')) exit;  // Exit if accessed directly

if (! class_exists('WPGV_Redeem_Voucher')) :

    final class WPGV_Redeem_Voucher
    {
        private $store_api_registered = false;

        function __construct()
        {
            global $wpdb;
            $setting_table_name = $wpdb->prefix . 'giftvouchers_setting';
            $options = $wpdb->get_row("SELECT * FROM $setting_table_name WHERE id = 1");
            if ($options->is_woocommerce_enable) {
                add_action('init', array($this, 'woocommerce_init'));
                add_filter('query_vars', array($this, 'woocommerce_query_vars'), 0);
                add_filter('woocommerce_account_menu_items', array($this, 'woocommerce_account_menu_items'));
                add_action('woocommerce_account_check-voucher-balance_endpoint', array($this, 'check_voucher_balance_endpoint'));
                add_action('woocommerce_after_cart_contents', array($this, 'woocommerce_after_cart_contents'));
                add_action('woocommerce_cart_totals_before_order_total', array($this, 'woocommerce_cart_totals_before_order_total'));
                add_action('woocommerce_before_checkout_form', array($this, 'woocommerce_before_checkout_form'), 40);
                add_action('woocommerce_review_order_before_order_total', array($this, 'woocommerce_review_order_before_order_total'));
                add_action('woocommerce_after_calculate_totals', array($this, 'woocommerce_after_calculate_totals'), 40);
                add_action('woocommerce_update_order', array($this, 'woocommerce_update_order'));
                add_filter('woocommerce_order_status_processing', array($this, 'woocommerce_order_status_processing'), 10, 2);
                add_filter('woocommerce_order_status_pre-ordered', array($this, 'woocommerce_order_status_processing'), 10, 2);
                add_filter('woocommerce_order_status_completed', array($this, 'woocommerce_order_status_completed'), 10, 2);
                add_filter('woocommerce_order_status_cancelled', array($this, 'woocommerce_order_status_cancelled'), 10, 2);
                add_filter('woocommerce_order_status_refunded', array($this, 'woocommerce_order_status_refunded'), 10, 2);
                add_filter('woocommerce_get_order_item_totals', array($this, 'woocommerce_get_order_item_totals'), 10, 3);
                add_action('woocommerce_checkout_create_order', array($this, 'woocommerce_checkout_create_order'), 10, 2);
                add_action('woocommerce_store_api_checkout_order_created', array($this, 'woocommerce_store_api_checkout_order_created'), 10, 1);
                add_action('woocommerce_store_api_checkout_update_order_meta', array($this, 'woocommerce_store_api_checkout_update_order_meta'), 10, 1);
                add_action('woocommerce_checkout_validate_order_before_payment', array($this, 'woocommerce_store_api_validate_order_before_payment'), 10, 2);
                add_filter('woocommerce_paypal_args', array($this, 'woocommerce_paypal_args'), 10, 2);
                add_filter('woocommerce_payment_complete_order_status', array($this, 'filter_woocommerce_payment_complete_order_status_gift_voucher'), 10, 3);

                add_action('wp_ajax_nopriv_wpgv-gift-voucher-redeem', array($this, 'wpgv_ajax_redeem'));
                add_action('wp_ajax_wpgv-gift-voucher-redeem', array($this, 'wpgv_ajax_redeem'));

                add_action('wp_ajax_nopriv_wpgv-gift-voucher-remove', array($this, 'wpgv_ajax_remove'));
                add_action('wp_ajax_wpgv-gift-voucher-remove', array($this, 'wpgv_ajax_remove'));

                // This class is loaded from woocommerce_init, which may run after
                // WooCommerce has already fired woocommerce_blocks_loaded.
                if (did_action('woocommerce_blocks_loaded')) {
                    $this->register_store_api_integration();
                } else {
                    add_action('woocommerce_blocks_loaded', array($this, 'register_store_api_integration'), 10);
                }
            }
        }

        /**
         * Register the Store API callback and Cart extension data.
         *
         * Older WooCommerce versions keep the classic AJAX flow because the
         * Store API helpers are not available there.
         */
        function register_store_api_integration()
        {
            if (!wpgv_is_woocommerce_enable() || $this->store_api_registered || !function_exists('woocommerce_store_api_register_update_callback') || !function_exists('woocommerce_store_api_register_endpoint_data')) {
                return;
            }

            $callback_result = woocommerce_store_api_register_update_callback(array(
                'namespace' => 'gift-voucher',
                'callback'  => array($this, 'store_api_update_callback'),
            ));

            $data_result = woocommerce_store_api_register_endpoint_data(array(
                'endpoint'        => 'cart',
                'namespace'       => 'gift-voucher',
                'schema_callback' => array($this, 'store_api_cart_schema'),
                'data_callback'   => array($this, 'store_api_cart_data'),
                'schema_type'     => ARRAY_A,
            ));

            if (!is_wp_error($callback_result) && !is_wp_error($data_result)) {
                $this->store_api_registered = true;
            }
        }

        /**
         * Apply or remove a voucher through /wc/store/v1/cart/extensions.
         *
         * @param mixed $data Store API extension payload.
         * @return void
         * @throws WC_REST_Exception For malformed or ineligible requests.
         */
        function store_api_update_callback($data)
        {
            if (!wpgv_is_woocommerce_enable()) {
                $this->throw_store_api_error();
            }

            if (!is_array($data) || array_diff(array_keys($data), array('action', 'code'))) {
                $this->throw_store_api_error();
            }

            $action = isset($data['action']) && is_string($data['action']) ? sanitize_key($data['action']) : '';
            $code = isset($data['code']) && is_string($data['code']) ? wc_clean(wp_unslash($data['code'])) : '';
            $code = is_string($code) ? trim($code) : '';

            if (!in_array($action, array('apply', 'remove'), true) || $code === '' || strlen($code) > 100) {
                $this->throw_store_api_error();
            }

            if ($action === 'apply' && !wpgv_is_woocommerce_redeem_form_enabled()) {
                $this->throw_store_api_error();
            }

            if ($action === 'apply') {
                // Reuse the legacy server-side validation and session writer.
                // Its detailed result is deliberately replaced with a generic
                // Store API error so arbitrary codes cannot be enumerated.
                if ($this->add_gift_voucher_to_session($code) !== true) {
                    $this->throw_store_api_error();
                }
                return;
            }

            $this->remove_gift_voucher_from_session($code);
        }

        /**
         * Cart extension schema exposed to the Blocks client.
         *
         * @return array
         */
        function store_api_cart_schema()
        {
            return array(
                'applied_vouchers' => array(
                    'description' => esc_html__('Applied voucher codes and server-calculated amounts.', 'gift-voucher'),
                    'type'        => 'array',
                    'readonly'    => true,
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'code' => array(
                                'description' => esc_html__('Applied voucher code.', 'gift-voucher'),
                                'type'        => 'string',
                                'readonly'    => true,
                            ),
                            'amount' => array(
                                'description' => esc_html__('Amount applied to the current cart.', 'gift-voucher'),
                                'type'        => 'string',
                                'readonly'    => true,
                            ),
                        ),
                    ),
                ),
            );
        }

        /**
         * Return only the voucher fields needed by the Blocks UI.
         *
         * @return array
         */
        function store_api_cart_data()
        {
            $applied_vouchers = array();
            $session_data = WC()->session ? (array) WC()->session->get(WPGIFT_SESSION_KEY) : array();
            $vouchers = isset($session_data['gift_voucher']) && is_array($session_data['gift_voucher']) ? $session_data['gift_voucher'] : array();

            foreach ($vouchers as $code => $amount) {
                $applied_vouchers[] = array(
                    'code'   => (string) $code,
                    'amount' => wc_format_decimal($amount, wc_get_price_decimals()),
                );
            }

            return array('applied_vouchers' => $applied_vouchers);
        }

        /**
         * Throw one safe error for all Store API voucher failures.
         *
         * @return void
         * @throws WC_REST_Exception
         */
        private function throw_store_api_error()
        {
            throw new WC_REST_Exception(
                'wpgv_voucher_update_failed',
                esc_html__('Unable to update this voucher.', 'gift-voucher'),
                400
            );
        }

        function woocommerce_init()
        {
            add_rewrite_endpoint('check-voucher-balance', EP_ROOT | EP_PAGES);
        }

        function woocommerce_query_vars($vars)
        {
            $vars[] = 'check-voucher-balance';
            return $vars;
        }

        function woocommerce_account_menu_items($items)
        {
            $items['check-voucher-balance'] = esc_html__('Check Voucher Balance', 'gift-voucher');
            return $items;
        }

        function check_voucher_balance_endpoint()
        {
            echo '<h3>';
            esc_html_e('Check Voucher Balance', 'gift-voucher');
            echo '</h3>';
            echo wp_kses_post(do_shortcode(' [wpgv-check-voucher-balance] '));
        }

        function woocommerce_after_cart_contents()
        {
            if (!wpgv_is_woocommerce_redeem_form_enabled()) {
                return;
            }

            wp_enqueue_script('wpgv-woocommerce-script');
            wc_get_template('cart/wpgv-gift-voucher-form.php', '', '', WPGIFT__PLUGIN_DIR . '/templates/woocommerce/');
        }

        function woocommerce_cart_totals_before_order_total()
        {
            // The applied-voucher row is also needed when the customer entered
            // a voucher through WooCommerce's native Coupon code field.
            wp_enqueue_script('wpgv-woocommerce-script');
            wc_get_template('cart/wpgv-gift-vouchers.php', '', '', WPGIFT__PLUGIN_DIR . '/templates/woocommerce/');
        }

        function woocommerce_before_checkout_form()
        {
            if (!wpgv_is_woocommerce_redeem_form_enabled()) {
                return;
            }

            wp_enqueue_script('wpgv-woocommerce-script');
            wc_get_template('checkout/wpgv-gift-voucher-form.php', '', '', WPGIFT__PLUGIN_DIR . '/templates/woocommerce/');
        }

        function woocommerce_review_order_before_order_total()
        {
            // Keep the remove action available for vouchers entered through the
            // native Coupon code field when the plugin form is hidden.
            wp_enqueue_script('wpgv-woocommerce-script');
            wc_get_template('checkout/wpgv-gift-vouchers.php', '', '', WPGIFT__PLUGIN_DIR . '/templates/woocommerce/');
        }

        function woocommerce_after_calculate_totals($cart)
        {

            // The "recurring cart" for WooCommerce Subscriptions should not be adjusted.
            if (property_exists($cart, 'recurring_cart_key')) {
                return;
            }

            $session_data = (array) WC()->session->get(WPGIFT_SESSION_KEY);
            if (!isset($session_data['gift_voucher'])) {
                return;
            }

            $calculation = $this->calculate_gift_voucher_amounts(
                $cart->total,
                $session_data['gift_voucher']
            );

            // The cart total has already included WooCommerce's item, coupon, tax,
            // shipping, and fee calculations at this hook. Preserve that behavior
            // while always calculating from the freshly reset total. Do not cache a
            // reduced total on the cart object: Store API and classic requests can
            // recalculate the same cart more than once.
            $cart->total = max(0, $cart->total - $calculation['total']);
            $session_data['gift_voucher'] = $calculation['amounts'];

            WC()->session->set(WPGIFT_SESSION_KEY, $session_data);
        }

        /**
         * Calculate the applicable amount for each voucher in application order.
         *
         * The amount in the session is output from this method, never an input to
         * the calculation. Voucher balance, payment state, and expiry are checked
         * again here on every cart recalculation.
         *
         * @param float $eligible_amount The current WooCommerce cart total.
         * @param array $applied_vouchers Voucher codes keyed by their session amount.
         * @return array{amounts: array, total: float}
         */
        function calculate_gift_voucher_amounts($eligible_amount, $applied_vouchers)
        {
            $eligible_amount = max(0, (float) $eligible_amount);
            $applied_amounts = array();
            $gift_voucher_total = 0;
            $price_decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;

            foreach ((array) $applied_vouchers as $card_number => $unused_amount) {
                $amount = 0;
                $gift_voucher = new WPGV_Gift_Voucher($card_number);

                if ($gift_voucher->get_id() && !$gift_voucher->has_expired()) {
                    $remaining_amount = max(0, $eligible_amount - $gift_voucher_total);
                    $balance = max(0, (float) $gift_voucher->get_balance());
                    // A zero-balance voucher must not remain applied after a
                    // recalculation. A positive-balance voucher may remain in
                    // the list with amount zero when an earlier voucher covers
                    // the rest of the cart, so it can still be removed.
                    if ($balance > 0) {
                        $amount = min($balance, $remaining_amount);
                    } else {
                        continue;
                    }
                }

                $amount = round($amount, $price_decimals);
                $applied_amounts[$card_number] = $amount;
                $gift_voucher_total = round($gift_voucher_total + $amount, $price_decimals);

                if ($gift_voucher_total >= $eligible_amount) {
                    break;
                }
            }

            // Keep any codes after a fully covering voucher visible with a zero
            // amount so removal/order serialization remains deterministic.
            foreach ((array) $applied_vouchers as $card_number => $unused_amount) {
                if (!array_key_exists($card_number, $applied_amounts)) {
                    $gift_voucher = new WPGV_Gift_Voucher($card_number);
                    if ($gift_voucher->get_id() && !$gift_voucher->has_expired() && (float) $gift_voucher->get_balance() > 0) {
                        $applied_amounts[$card_number] = 0;
                    }
                }
            }

            return array(
                'amounts' => $applied_amounts,
                'total'   => $gift_voucher_total,
            );
        }
        // checking status order payment  stripe
        function filter_woocommerce_payment_complete_order_status_gift_voucher($status, $order_id, $instance)
        {
            // Keep the original status if no order ID is provided (e.g., email previews)
            if ( empty( $order_id ) ) {
                return $status;
            }

            $order = wc_get_order( $order_id );

            // Ensure we have a valid order object before calling methods on it
            if ( ! $order || ! $order instanceof WC_Order ) {
                return $status;
            }

            // Debug logging when WP_DEBUG enabled
            if ( defined('WP_DEBUG') && WP_DEBUG ) {
                $items = array();
                foreach ( $order->get_items() as $item ) {
                    $p = $item->get_product();
                    $items[] = array(
                        'product_id' => $item->get_product_id(),
                        'is_virtual' => $p ? ( method_exists( $p, 'is_virtual' ) ? $p->is_virtual() : null ) : null,
                        'is_downloadable' => $p ? ( method_exists( $p, 'is_downloadable' ) ? $p->is_downloadable() : null ) : null,
                        'quantity' => $item->get_quantity(),
                    );
                }
                error_log('WPGV_STATUS_DEBUG: order_id=' . $order_id . ', status_in=' . $status . ', payment_method=' . $order->get_payment_method() . ', needs_shipping=' . ( $order->needs_shipping() ? '1' : '0' ) . ', items=' . json_encode( $items )); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- debug-only diagnostic.
            }

            // Only adjust for Stripe payments
            if ( $order->get_payment_method() !== 'stripe' ) {
                return $status;
            }

            // Only override when WooCommerce intends to mark as 'completed'.
            // This avoids forcing 'processing' in contexts like preview or for digital-only orders.
            if ( $status !== 'completed' ) {
                return $status;
            }

            // Determine whether the order requires shipping (i.e., has any non-virtual, non-downloadable product)
            $needs_processing = false;
            foreach ( $order->get_items() as $item ) {
                $product = $item->get_product();
                if ( $product && ! $product->is_virtual() && ! $product->is_downloadable() ) {
                    $needs_processing = true;
                    break;
                }
            }

            if ( $needs_processing ) {
                return 'processing';
            }

            return $status;
        }

        // Ensure we have the right total, even after recalculations and such.
        function woocommerce_update_order($order_id)
        {
            remove_action('woocommerce_update_order', array($this, 'woocommerce_update_order'));

            $order = wc_get_order($order_id);
            if ($order) {

                $cart_total = 0;
                $gift_voucher_total = 0;

                foreach ($order->get_items('wpgv_gift_voucher') as $line) {
                    $gift_voucher_total += $line->get_amount();
                }

                if (!empty($gift_voucher_total)) {
                    foreach ($order->get_items() as $item) {
                        $cart_total += $item->get_total();
                    }

                    $new_total = round($cart_total + $order->get_shipping_total() + $order->get_cart_tax() + $order->get_shipping_tax() + $order->get_total_fees() - $gift_voucher_total, wc_get_price_decimals());

                    if ($order->get_total() != $new_total) {
                        $order->set_total(max(0, $new_total));
                        $order->save();
                    }
                }
            }

            add_action('woocommerce_update_order', array($this, 'woocommerce_update_order'));
        }

        function woocommerce_order_status_processing($order_id, $order)
        {
            $this->debit_gift_voucher($order_id, $order, "order_id: $order_id processing");
        }

        function woocommerce_order_status_completed($order_id, $order)
        {
            $this->debit_gift_voucher($order_id, $order, "order_id: $order_id completed");
        }

        function woocommerce_order_status_cancelled($order_id, $order)
        {
            $this->credit_gift_voucher($order_id, $order, "order_id: $order_id cancelled");
        }

        function woocommerce_order_status_refunded($order_id, $order)
        {
            $this->credit_gift_voucher($order_id, $order, "order_id: $order_id refunded");
        }

        function debit_gift_voucher($order_id, $order, $note)
        {
            foreach ($order->get_items('wpgv_gift_voucher') as $order_item_id => $line) {
                $gift_voucher = new WPGV_Gift_Voucher($line->get_card_number());

                if ($gift_voucher->get_id()) {
                    if (!$line->meta_exists('_wpgv_gift_voucher_debited')) {
                        $gift_voucher->debit(($line->get_amount() * -1), "$note, order_item_id: $order_item_id");

                        $line->add_meta_data('_wpgv_gift_voucher_debited', true);
                        $line->save();
                        if (WC()->session) {
                            WC()->session->set('wpgv-gift-voucher-data', null);
                        }
                    }
                }
            }
        }

        function credit_gift_voucher($order_id, $order, $note)
        {
            foreach ($order->get_items('wpgv_gift_voucher') as $order_item_id => $line) {
                $gift_voucher = new WPGV_Gift_Voucher($line->get_card_number());
                if ($gift_voucher->get_id()) {
                    if ($line->meta_exists('_wpgv_gift_voucher_debited')) {
                        $gift_voucher->credit($line->get_amount(), "$note, order_item_id: $order_item_id");

                        $line->delete_meta_data('_wpgv_gift_voucher_debited');
                        $line->save();
                    }
                }
            }
        }

        function woocommerce_get_order_item_totals($total_rows, $order, $tax_display)
        {
            if (!isset($total_rows['wpgv_gift_vouchers'])) {
                $gift_voucher_count = 0;
                $gift_voucher_total = 0;
                $gift_voucher_code = array();
                foreach ($order->get_items('wpgv_gift_voucher') as $line) {
                    $gift_voucher_count++;
                    $gift_voucher_total += $line->get_amount();
                    $gift_voucher_code[] = $line->get_card_number();
                }
                $total_codes = implode(", ", $gift_voucher_code);

                if (!empty($gift_voucher_total)) {
                    // Determine the label based on the count
                    $label = _n('Gift Voucher:', 'Gift Vouchers:', $gift_voucher_count, 'gift-voucher');

                    // Order totals labels are escaped by WooCommerce templates;
                    // keep the voucher codes as plain text instead of injecting HTML.
                    $label .= ' ' . $total_codes;

                    $gift_voucher_row = array(
                        'label'  => $label,
                        'value'  => wpgv_price_format($gift_voucher_total * -1),
                    );


                    $total_index = array_search('order_total', array_keys($total_rows));
                    if ($total_index !== false) {
                        // Insert this just before the Total row.
                        $total_rows = array_slice($total_rows, 0, $total_index, true) + array('wpgv_gift_vouchers' => $gift_voucher_row) + array_slice($total_rows, $total_index, count($total_rows) - $total_index, true);
                    } else {
                        $total_rows['wpgv_gift_vouchers'] = $gift_voucher_row;
                    }
                }
            }

            return $total_rows;
        }

        function woocommerce_checkout_create_order($order, $data)
        {
            $this->add_gift_voucher_items_to_order($order);
        }

        /**
         * Add voucher items to orders created by the Cart/Checkout Blocks Store API.
         * The Store API does not run woocommerce_checkout_create_order.
         */
        function woocommerce_store_api_checkout_order_created($order)
        {
            $this->add_gift_voucher_items_to_order($order);
        }

        /**
         * Synchronize the Store API order total after its billing data is updated.
         * Voucher amounts are stored as custom order items, while the cart total is
         * the server-calculated source of truth for the final payable amount.
         */
        function woocommerce_store_api_checkout_update_order_meta($order)
        {
            if (!$order instanceof WC_Order) {
                return;
            }

            $this->add_gift_voucher_items_to_order($order);

            if (WC()->cart) {
                $cart_total = (float) WC()->cart->get_total('edit');
                $order->set_total(max(0, wc_format_decimal($cart_total)));
            }
        }

        /**
         * Final Store API guard before a payment gateway is called.
         * Never allow a Blocks order to charge the pre-voucher amount when the
         * session still contains an applied voucher.
         */
        function woocommerce_store_api_validate_order_before_payment($order, $validation_errors)
        {
            if (!$order instanceof WC_Order || 'store-api' !== $order->get_created_via()) {
                return;
            }

            $this->add_gift_voucher_items_to_order($order);

            if (WC()->cart) {
                $cart_total = (float) WC()->cart->get_total('edit');
                $order->set_total(max(0, wc_format_decimal($cart_total)));
                $order->save();
            }

            $session_data = (array) WC()->session->get(WPGIFT_SESSION_KEY);
            $session_vouchers = isset($session_data['gift_voucher']) && is_array($session_data['gift_voucher'])
                ? $session_data['gift_voucher']
                : array();
            $order_voucher_items = $order->get_items('wpgv_gift_voucher');

            if ($session_vouchers && count($order_voucher_items) < count($session_vouchers)) {
                $validation_errors->add(
                    'wpgv_voucher_order_sync_failed',
                    esc_html__('Unable to synchronize the voucher with this order. Please refresh the checkout and try again.', 'gift-voucher')
                );
            }
        }

        function add_gift_voucher_items_to_order($order)
        {
            if (!$order instanceof WC_Order || !WC()->session) {
                return;
            }

            $session_data = (array) WC()->session->get(WPGIFT_SESSION_KEY);
            if (!isset($session_data['gift_voucher'])) {
                return;
            }

            $existing_codes = array();
            foreach ($order->get_items('wpgv_gift_voucher') as $existing_item) {
                $existing_codes[(string) $existing_item->get_card_number()] = true;
            }

            foreach ($session_data['gift_voucher'] as $card_number => $amount) {
                $gift_voucher = new WPGV_Gift_Voucher($card_number);
                if ($gift_voucher->get_id() && !isset($existing_codes[(string) $card_number])) {

                    $item = new WC_Order_Item_WPGV_Gift_Voucher();

                    $item->set_props(array(
                        'card_number'   => $card_number,
                        'amount'        => $amount,
                    ));

                    $order->add_item($item);
                    $existing_codes[(string) $card_number] = true;
                }
            }
        }

        function woocommerce_paypal_args($args, $order)
        {
            if (isset($args['shipping_1']) && !isset($args['item_name_1'])) {
                // Check that shipping is not the **only** cost as PayPal won't allow payment if the items have no cost.

                // Instead, we'll remove shipping_1 and then add a new item for Shipping.
                unset($args['shipping_1']);
                // translators: %s: shipping method
                $args['item_name_1'] = sprintf(esc_html__('Shipping via %s', 'gift-voucher'), $order->get_shipping_method());

                $args['quantity_1'] = 1;
                $args['amount_1'] = $order->get_total();
                $args['item_number_1'] = '';
            }

            return $args;
        }

        function wpgv_ajax_redeem()
        {
            if (!wpgv_is_woocommerce_redeem_form_enabled()) {
                wp_send_json_error(array('message' => esc_html__('Voucher redemption is currently unavailable.', 'gift-voucher')), 403);
            }

            check_ajax_referer('wpgv_gift_voucher_session', 'nonce');

            $voucher_code = wc_clean($_POST['voucher_code']);

            $result = $this->add_gift_voucher_to_session($voucher_code);

            if ($result === true) {
                wc_add_notice(esc_html__('Gift card applied.', 'gift-voucher'));

                wp_send_json_success();
            } else {
                wp_send_json_error(array('message' => $result));
            }
        }

        function wpgv_ajax_remove()
        {
            if (!wpgv_is_woocommerce_enable()) {
                wp_send_json_error(array('message' => esc_html__('Voucher redemption is currently unavailable.', 'gift-voucher')), 403);
            }

            check_ajax_referer('wpgv_gift_voucher_session', 'nonce');

            $voucher_code = wc_clean($_POST['voucher_code']);

            $this->remove_gift_voucher_from_session($voucher_code);

            wc_add_notice(esc_html__('Gift card removed.', 'gift-voucher'));

            wp_send_json_success();
        }

        function add_gift_voucher_to_session($voucher_code)
        {

            $gift_voucher = new WPGV_Gift_Voucher($voucher_code);
            if ($gift_voucher->get_id() && ($gift_voucher->get_payment_status() == 'Paid')) {
                $balance = $gift_voucher->get_balance();
                if ((float) $balance > 0) {
                    if (!$gift_voucher->has_expired()) {
                        $session_data = (array) WC()->session->get(WPGIFT_SESSION_KEY);

                        if (!isset($session_data['gift_voucher'])) {
                            $session_data['gift_voucher'] = array();
                        }

                        if (! WC()->session->has_session()) {
                            WC()->session->set_customer_session_cookie(true);
                        }

                        $session_data['gift_voucher'][$voucher_code] = 0; // This will get calculated in woocommerce_after_calculate_totals()
                        WC()->session->set(WPGIFT_SESSION_KEY, $session_data);
                        return true;
                    } else {
                        $error_message = esc_html__('Your voucher has expired.', 'gift-voucher');
                    }
                } else {
                    $error_message = esc_html__('This gift voucher has a zero balance.', 'gift-voucher');
                }
            } else {
                $error_message = $gift_voucher->get_error_message();

                // Tar-pit to make brute-force guessing inefficient.
                sleep(3);
            }

            return $error_message;
        }

        function remove_gift_voucher_from_session($voucher_code)
        {
            $session_data = (array) WC()->session->get(WPGIFT_SESSION_KEY);
            if (isset($session_data['gift_voucher'][$voucher_code])) {
                unset($session_data['gift_voucher'][$voucher_code]);
                WC()->session->set(WPGIFT_SESSION_KEY, $session_data);
            }
        }
    }

    global $wpgv_redeem_voucher;
    $wpgv_redeem_voucher = new WPGV_Redeem_Voucher();

endif;
