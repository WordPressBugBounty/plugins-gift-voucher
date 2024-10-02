<?php

if( !defined( 'ABSPATH' ) ) exit;  // Exit if accessed directly

if ( ! class_exists( 'WPGV_Redeem_Voucher' ) ) :

final class WPGV_Redeem_Voucher {

    function __construct() {
		global $wpdb;
		$setting_table_name = $wpdb->prefix . 'giftvouchers_setting';
		$options = $wpdb->get_row( "SELECT * FROM $setting_table_name WHERE id = 1" );
		if($options->is_woocommerce_enable) {
        	add_action( 'init', array( $this, 'woocommerce_init' ) );
        	add_filter( 'query_vars', array( $this, 'woocommerce_query_vars' ), 0 );
        	add_filter( 'woocommerce_account_menu_items', array( $this, 'woocommerce_account_menu_items' ) );
        	add_action( 'woocommerce_account_check-voucher-balance_endpoint', array( $this, 'check_voucher_balance_endpoint' ) );
            add_action( 'woocommerce_after_cart_contents', array( $this, 'woocommerce_after_cart_contents' ) );
            add_action( 'woocommerce_cart_totals_before_order_total', array( $this, 'woocommerce_cart_totals_before_order_total' ) );
        	add_action( 'woocommerce_before_checkout_form', array( $this, 'woocommerce_before_checkout_form' ), 40 );
        	add_action( 'woocommerce_review_order_before_order_total', array( $this, 'woocommerce_review_order_before_order_total' ) );
        	add_action( 'woocommerce_after_calculate_totals', array( $this, 'woocommerce_after_calculate_totals' ), 40 );
        	add_action( 'woocommerce_update_order', array( $this, 'woocommerce_update_order' ) );
        	add_filter( 'woocommerce_order_status_processing', array( $this, 'woocommerce_order_status_processing' ), 10, 2 );
        	add_filter( 'woocommerce_order_status_pre-ordered', array( $this, 'woocommerce_order_status_processing' ), 10, 2 );
        	add_filter( 'woocommerce_order_status_completed', array( $this, 'woocommerce_order_status_completed' ), 10, 2 );
        	add_filter( 'woocommerce_order_status_cancelled', array( $this, 'woocommerce_order_status_cancelled' ), 10, 2 );
        	add_filter( 'woocommerce_order_status_refunded', array( $this, 'woocommerce_order_status_refunded' ), 10, 2 );
        	add_filter( 'woocommerce_get_order_item_totals', array( $this, 'woocommerce_get_order_item_totals' ), 10, 3 );
        	add_action( 'woocommerce_checkout_create_order', array( $this, 'woocommerce_checkout_create_order' ), 10, 2 );
        	add_filter( 'woocommerce_paypal_args', array( $this, 'woocommerce_paypal_args' ), 10, 2 );
            add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'filter_woocommerce_payment_complete_order_status_gift_voucher' ), 10, 3 );
	        add_action( 'wp_ajax_nopriv_wpgv-gift-voucher-redeem', array( $this, 'wpgv_ajax_redeem' ) );
    	    add_action( 'wp_ajax_wpgv-gift-voucher-redeem', array( $this, 'wpgv_ajax_redeem' ) );

        	add_action( 'wp_ajax_nopriv_wpgv-gift-voucher-remove', array( $this, 'wpgv_ajax_remove' ) );
        	add_action( 'wp_ajax_wpgv-gift-voucher-remove', array( $this, 'wpgv_ajax_remove' ) );

    	}
    }

    function woocommerce_init() {
    	add_rewrite_endpoint( 'check-voucher-balance', EP_ROOT | EP_PAGES );
    }

    function woocommerce_query_vars( $vars ) {
    	$vars[] = 'check-voucher-balance';
    	return $vars;
    }

    function woocommerce_account_menu_items( $items ) {
    	$items['check-voucher-balance'] = __('Check Voucher Balance', 'gift-voucher');
    	return $items;
    }

    function check_voucher_balance_endpoint() {
		echo '<h3>'.esc_html_e('Check Voucher Balance', 'gift-voucher').'</h3>';
		echo do_shortcode( ' [wpgv-check-voucher-balance] ' );
	}

    function woocommerce_after_cart_contents() {
        wp_enqueue_script( 'wpgv-woocommerce-script' );
        wc_get_template( 'cart/wpgv-gift-voucher-form.php', '', '', WPGIFT__PLUGIN_DIR . '/templates/woocommerce/' );
    }

    function woocommerce_cart_totals_before_order_total() {
        wc_get_template( 'cart/wpgv-gift-vouchers.php', '', '', WPGIFT__PLUGIN_DIR . '/templates/woocommerce/' );
    }

    function woocommerce_before_checkout_form() {
        wp_enqueue_script( 'wpgv-woocommerce-script' );
        wc_get_template( 'checkout/wpgv-gift-voucher-form.php', '', '', WPGIFT__PLUGIN_DIR . '/templates/woocommerce/' );
    }

    function woocommerce_review_order_before_order_total() {
        wc_get_template( 'checkout/wpgv-gift-vouchers.php', '', '', WPGIFT__PLUGIN_DIR . '/templates/woocommerce/' );
    }

    function woocommerce_after_calculate_totals( $cart ) {

        // The "recurring cart" for WooCommerce Subscriptions should not be adjusted.
        if ( property_exists( $cart, 'recurring_cart_key' ) ) {
            return;
        }

        $session_data = (array) WC()->session->get( WPGIFT_SESSION_KEY );
        if ( !isset( $session_data['gift_voucher'] ) ) {
            return;
        }

        if ( property_exists( $cart, 'wpgv_calculated_total' ) ) {
            $cart->total = $cart->wpgv_calculated_total;
            return;
        }

        // This is where we could optionally exclude Gift Cards, Shipping amounts, etc.
        $eligible_amount = $cart->total;

        // Sum all the gift card amounts (with a sanity check for good measure).
        $gift_voucher_total = 0;
        foreach ( $session_data['gift_voucher'] as $card_number => $amount ) {
            $wpgv_gift_voucher = new WPGV_Gift_Voucher( $card_number );
            if ( $wpgv_gift_voucher->get_id() ) {

                $amount = 0;
                if ( !$wpgv_gift_voucher->has_expired() ) {
                    $gift_voucher_balance = $wpgv_gift_voucher->get_balance();
                    if ( $gift_voucher_balance < ( $eligible_amount - $gift_voucher_total ) ) {
                        $amount = $gift_voucher_balance;
                    } else {
                        $amount = ( $eligible_amount - $gift_voucher_total );
                    }
                }

                $session_data['gift_voucher'][ $card_number ] = $amount;
                $gift_voucher_total += $amount;
            }

            if ( $gift_voucher_total >= $eligible_amount ) {
                break;
            }
        }

        // Make sure we don't set the cart to a negative amount.
        $new_cart_total = ( $cart->total - $gift_voucher_total );
        $cart->total = max( 0, $new_cart_total );
        $cart->wpgv_calculated_total = $cart->total;

        WC()->session->set( WPGIFT_SESSION_KEY, $session_data );
    }
    // checking status order payment  stripe
    function filter_woocommerce_payment_complete_order_status_gift_voucher( $var, $order_id, $instance ) { 
        if ( ! $order_id ) {
            return;
        }
        $order = wc_get_order( $order_id );
        if ($order->get_payment_method() == 'stripe') {
            return 'processing';
        }else{
            return $var;
        }
    }
    // Ensure we have the right total, even after recalculations and such.
    function woocommerce_update_order( $order_id ) {
        remove_action( 'woocommerce_update_order', array( $this, 'woocommerce_update_order' ) );

        $order = wc_get_order( $order_id );
        if ( $order ) {

            $cart_total = 0;
            $gift_voucher_total = 0;

            foreach( $order->get_items( 'wpgv_gift_voucher' ) as $line ) {
                $gift_voucher_total += $line->get_amount();
            }

            if ( !empty( $gift_voucher_total ) ) {
                foreach ( $order->get_items() as $item ) {
                    $cart_total += $item->get_total();
                }

                $new_total = round( $cart_total + $order->get_shipping_total() + $order->get_cart_tax() + $order->get_shipping_tax() + $order->get_total_fees() - $gift_voucher_total, wc_get_price_decimals() );

                if ( $order->get_total() != $new_total ) {
                    $order->set_total( max( 0, $new_total ) );
                    $order->save();
                }
            }
        }

        add_action( 'woocommerce_update_order', array( $this, 'woocommerce_update_order' ) );
    }

    function woocommerce_order_status_processing( $order_id, $order ) {
        $this->debit_gift_voucher( $order_id, $order, "order_id: $order_id processing" );
    }

    function woocommerce_order_status_completed( $order_id, $order ) {
        $this->debit_gift_voucher( $order_id, $order, "order_id: $order_id completed" );
    }

    function woocommerce_order_status_cancelled( $order_id, $order ) {
        $this->credit_gift_voucher( $order_id, $order, "order_id: $order_id cancelled" );
    }

    function woocommerce_order_status_refunded( $order_id, $order ) {
        $this->credit_gift_voucher( $order_id, $order, "order_id: $order_id refunded" );
    }

    function debit_gift_voucher( $order_id, $order, $note ) {
        foreach( $order->get_items('wpgv_gift_voucher') as $order_item_id => $line ) {
            $gift_voucher = new WPGV_Gift_Voucher( $line->get_card_number() );
            
            if ( $gift_voucher->get_id() ) {
                if ( !$line->meta_exists( '_wpgv_gift_voucher_debited' ) ) {
                    $gift_voucher->debit( ( $line->get_amount() * -1 ), "$note, order_item_id: $order_item_id" );

                    $line->add_meta_data( '_wpgv_gift_voucher_debited', true );
                    $line->save();
                    if (WC()->session) {
                        WC()->session->set( 'wpgv-gift-voucher-data', null );
                    }
                }
            }
        }
    }

    function credit_gift_voucher( $order_id, $order, $note ) {
        foreach( $order->get_items( 'wpgv_gift_voucher' ) as $order_item_id => $line ) {
            $gift_voucher = new WPGV_Gift_Voucher( $line->get_card_number() );
            if ( $gift_voucher->get_id() ) {
                if ( $line->meta_exists( '_wpgv_gift_voucher_debited' ) ) {
                    $gift_voucher->credit( $line->get_amount(), "$note, order_item_id: $order_item_id" );

                    $line->delete_meta_data( '_wpgv_gift_voucher_debited' );
                    $line->save();
                }
            }
        }
    }

    function woocommerce_get_order_item_totals( $total_rows, $order, $tax_display ) {
        if ( !isset( $total_rows['wpgv_gift_vouchers'] ) ) {
            $gift_voucher_count = 0;
            $gift_voucher_total = 0;
            $gift_voucher_code = array();
            foreach( $order->get_items( 'wpgv_gift_voucher' ) as $line ) {
                $gift_voucher_count++;
                $gift_voucher_total += $line->get_amount();
                $gift_voucher_code[] = $line->get_card_number();
            }
            $total_codes = implode(", ", $gift_voucher_code);

            if ( !empty( $gift_voucher_total ) ) {
                $gift_voucher_row = array(
                    'label'  => _n( 'Gift Voucher:<br>'.$total_codes, 'Gift Vouchers:<br>'.$total_codes, $gift_voucher_count, 'gift-voucher' ),
                    'value'  => wpgv_price_format( $gift_voucher_total * -1 ),
                );

                $total_index = array_search( 'order_total', array_keys( $total_rows ) );
                if ( $total_index !== false ) {
                    // Insert this just before the Total row.
                    $total_rows = array_slice( $total_rows, 0, $total_index, true ) + array( 'wpgv_gift_vouchers' => $gift_voucher_row ) + array_slice( $total_rows, $total_index, count( $total_rows ) - $total_index, true );
                } else {
                    $total_rows['wpgv_gift_vouchers'] = $gift_voucher_row;
                }
            }
        }

        return $total_rows;
    }

    function woocommerce_checkout_create_order( $order, $data ) {
        $session_data = (array) WC()->session->get( WPGIFT_SESSION_KEY );
        if ( !isset( $session_data['gift_voucher'] ) ) {
            return;
        }

        foreach ( $session_data['gift_voucher'] as $card_number => $amount ) {
            $gift_voucher = new WPGV_Gift_Voucher( $card_number );
            if ( $gift_voucher->get_id() ) {

                $item = new WC_Order_Item_WPGV_Gift_Voucher();

                $item->set_props( array(
                    'card_number'   => $card_number,
                    'amount'        => $amount,
                ) );

                $order->add_item( $item );
            }
        }
    }

    function woocommerce_paypal_args( $args, $order ) {
        if ( isset( $args['shipping_1'] ) && !isset( $args['item_name_1'] ) ) {
            // Check that shipping is not the **only** cost as PayPal won't allow payment if the items have no cost.

            // Instead, we'll remove shipping_1 and then add a new item for Shipping.
            unset( $args['shipping_1'] );
            $args['item_name_1'] = sprintf( __( 'Shipping via %s', 'gift-voucher' ), $order->get_shipping_method() );
            $args['quantity_1'] = 1;
            $args['amount_1'] = $order->get_total();
            $args['item_number_1'] = '';
        }

        return $args;
    }

    function wpgv_ajax_redeem() {

        $voucher_code = wc_clean( $_POST['voucher_code'] );

        $result = $this->add_gift_voucher_to_session( $voucher_code );

        if ( $result === true ) {
            wc_add_notice( __( 'Gift card applied.', 'gift-voucher' ) );

            wp_send_json_success();
        } else {
            wp_send_json_error( array( 'message' => $result ) );
        }
    }

    function wpgv_ajax_remove() {

        $voucher_code = wc_clean( $_POST['voucher_code'] );

        $this->remove_gift_voucher_from_session( $voucher_code );

        wc_add_notice( __( 'Gift card removed.', 'gift-voucher' ) );

        wp_send_json_success();
    }

    function add_gift_voucher_to_session( $voucher_code ) {

        $gift_voucher = new WPGV_Gift_Voucher( $voucher_code );
        if ( $gift_voucher->get_id() && ($gift_voucher->get_payment_status() == 'Paid') ) {
			$balance = $gift_voucher->get_balance();
            if ( !empty( $balance ) ) {
            	if(!$gift_voucher->has_expired()) {
                    $session_data = (array) WC()->session->get( WPGIFT_SESSION_KEY );

                    if ( !isset( $session_data['gift_voucher'] ) ) {
                        $session_data['gift_voucher'] = array();
                    }

                    if ( ! WC()->session->has_session() ) {
                        WC()->session->set_customer_session_cookie( true );
                    }

                    $session_data['gift_voucher'][ $voucher_code ] = 0; // This will get calculated in woocommerce_after_calculate_totals()
                    WC()->session->set( WPGIFT_SESSION_KEY, $session_data );
                    return true;
                } else {
                	$error_message = __( 'Your voucher has expired.', 'gift-voucher' );                	
                }
            } else {
                $error_message = __( 'This gift voucher has a zero balance.', 'gift-voucher' );
            }
        } else {
            $error_message = $gift_voucher->get_error_message();

            // Tar-pit to make brute-force guessing inefficient.
            sleep(3);
        }

        return $error_message;
    }

    function remove_gift_voucher_from_session( $voucher_code ) {
        $session_data = (array) WC()->session->get( WPGIFT_SESSION_KEY );
        if ( isset( $session_data['gift_voucher'][ $voucher_code ] ) ) {
            unset( $session_data['gift_voucher'][ $voucher_code ] );
            WC()->session->set( WPGIFT_SESSION_KEY, $session_data );
        }
    }
}

global $wpgv_redeem_voucher;
$wpgv_redeem_voucher = new WPGV_Redeem_Voucher();

endif;