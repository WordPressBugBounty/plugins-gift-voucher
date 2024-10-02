<?php

if( !defined( 'ABSPATH' ) ) exit;  // Exit if accessed directly

function wpgv_order_item_woocommerce_get_items_key( $key, $item ) {
    if ( is_a( $item, 'WC_Order_Item_WPGV_Gift_Voucher' ) ) {
        return 'wpgv_gift_voucher_lines';
    }

    return $key;
}
add_filter( 'woocommerce_get_items_key', 'wpgv_order_item_woocommerce_get_items_key', 10, 2 );


function wpgv_woocommerce_order_type_to_group( $groups ) {
    $groups['wpgv_gift_voucher'] = 'wpgv_gift_voucher_lines';
    return $groups;
}
add_filter( 'woocommerce_order_type_to_group', 'wpgv_woocommerce_order_type_to_group', 10, 2 );


function wpgv_woocommerce_get_order_item_classname( $classname, $item_type, $id ) {
    if ( $item_type == 'wpgv_gift_voucher' ) {
        return 'WC_Order_Item_WPGV_Gift_Voucher';
    }
    return $classname;
}
add_filter( 'woocommerce_get_order_item_classname', 'wpgv_woocommerce_get_order_item_classname', 10, 3 );

if ( class_exists( 'WC_Order_Item' ) ) :
    
class WC_Order_Item_WPGV_Gift_Voucher extends WC_Order_Item {

    protected $extra_data = array(
        'card_number'   => '',
        'amount'        => 0,
    );

    public function set_name( $value ) {
        return $this->set_card_number( $value );
    }

    public function set_card_number( $value ) {
        $this->set_prop( 'card_number', wc_clean( $value ) );
    }

    public function set_amount( $value ) {
        $this->set_prop( 'amount', wc_format_decimal( $value ) );
    }

    public function get_name( $context = 'view' ) {
        return $this->get_card_number( $context );
    }

    public function get_type() {
        return 'wpgv_gift_voucher';
    }

    public function get_card_number( $context = 'view' ) {
        return $this->get_prop( 'card_number', $context );
    }

    public function get_amount( $context = 'view' ) {
        return $this->get_prop( 'amount', $context );
    }
}

endif;