<?php

if( !defined( 'ABSPATH' ) ) exit;  // Exit if accessed directly

function wpgv_order_item_woocommerce_data_stores( $stores ) {
    if ( !isset( $stores[ 'order-item-wpgv_gift_voucher' ] ) ) {
        $stores[ 'order-item-wpgv_gift_voucher' ] = 'WC_Order_Item_WPGV_Gift_Voucher_Data_Store';
    }

    return $stores;
}
add_filter( 'woocommerce_data_stores', 'wpgv_order_item_woocommerce_data_stores' );

class WC_Order_Item_WPGV_Gift_Voucher_Data_Store extends Abstract_WC_Order_Item_Type_Data_Store implements WC_Object_Data_Store_Interface, WC_Order_Item_Type_Data_Store_Interface {

    protected $internal_meta_keys = array( 'card_number', 'amount' );

    public function read( &$item ) {
        parent::read( $item );
        $id = $item->get_id();
        $item->set_props( array(
            'card_number'   => get_metadata( 'order_item', $id, 'card_number', true ),
            'amount'        => get_metadata( 'order_item', $id, 'amount', true ),
        ) );
        $item->set_object_read( true );
    }

    public function save_item_data( &$item ) {
        $id          = $item->get_id();
        $save_values = array(
            'card_number'   => $item->get_card_number( 'edit' ),
            'amount'        => $item->get_amount( 'edit' ),
        );
        foreach ( $save_values as $key => $value ) {
            update_metadata( 'order_item', $id, $key, $value );
        }
    }
}