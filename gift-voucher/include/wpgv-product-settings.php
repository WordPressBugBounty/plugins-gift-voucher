<?php

if (!defined('ABSPATH')) exit;  // Exit if accessed directly

global $wpdb;

$setting_table_name = $wpdb->prefix . 'giftvouchers_setting';
$options = $wpdb->get_row("SELECT * FROM $setting_table_name WHERE id = 1");

if ($options->is_woocommerce_enable) {
    /*
     * Add New Product Type to Select Dropdown
     */
    add_filter('product_type_selector', 'wpgv_add_gift_voucher_type');

    function wpgv_add_gift_voucher_type($types)
    {
        $types[WPGV_PRODUCT_TYPE_SLUG] = WPGV_PRODUCT_TYPE_NAME;
        return $types;
    }

    /*
     * Add New Product Type Class
     */

    add_action('init', 'wpgv_create_gift_voucher_type');

    function wpgv_create_gift_voucher_type()
    {
        class WC_Gift_Voucher_Product extends WC_Product_Variable
        {
            public function get_type()
            {
                return 'gift_voucher';
            }
        }
    }

    /*
     * Load New Product Type Class
     */

    add_filter('woocommerce_product_class', 'wpgv_woocommerce_product_class', 10, 2);

    function wpgv_woocommerce_product_class($classname, $product_type)
    {
        if ($product_type == 'gift_voucher') {
            $classname = 'WC_Gift_Voucher_Product';
        }
        return $classname;
    }

    /*
     * Add Gift Voucher Type Tab
     */

    add_filter('woocommerce_product_data_tabs', 'gift_voucher_tab');

    function gift_voucher_tab($tabs)
    {

        $tabs['variations']['class'][] = 'show_if_' . WPGV_PRODUCT_TYPE_SLUG;

        // Key should be exactly the same as in the class product_type
        $tabs['gift_voucher'] = array(
            'label'     => __('Gift Voucher', 'gift-voucher'),
            'target' => 'gift_voucher_options',
            'class'  => ('show_if_gift_voucher'),
        );
        return $tabs;
    }

    add_action('woocommerce_product_data_panels', 'wpgv_gift_voucher_options_product_tab_content');

    function wpgv_gift_voucher_options_product_tab_content()
    {

        global $wpgv_gift_voucher;
        global $product_object;

        $variations = array_map('wc_get_product', $product_object->get_children());

        //print_r($variations);

        //exit;

        // Dont forget to change the id in the div with your target of your product tab
?><div id='gift_voucher_options' class='panel woocommerce_options_panel'><?php
                                                                            ?><div class='options_group'>
                <?php

                $post_id = isset($_GET['post']) ? sanitize_text_field($_GET['post']) : 0;

                woocommerce_wp_text_input(array(
                    'id'                => 'wpgv_price',
                    'value'             => '',
                    'label'             => __('Gift Voucher Price', 'gift-voucher') . ' (' . get_woocommerce_currency_symbol() . ')',
                    'data_type'         => 'price',
                    'class'             => 'wpgv-price',
                    'desc_tip'          => 'true',
                    'description'       => sprintf(__('The available denominations that can be purchased. For example: %1$s25.00, %1$s50.00, %1$s100.00', 'gift-voucher'), get_woocommerce_currency_symbol()),
                ));

                /*$post_prices = get_post_meta($post_id,'_product_attributes',true);
                $x = unserialize($post_prices);
                echo '<div class="container" style="justify-content: center;display: flex;">';
                foreach ($x['wpgv-voucher-amount'] as $value) {
                    echo '<span class="wpgv-tag">'.$value.'<span class="wpgv-price-remove" data-role="remove">×</span></span>';
                }
                echo '</div>';*/

                echo '<div id="wpgv_variation_price_main" class="container" style="justify-content: center;display: flex;margin-left: 10px;margin-right: 10px;margin-bottom: 10px;">';
                foreach ($variations as $variation) {
                    if ($variation->get_regular_price() > 0) {
                ?>
                        <span id="wpgv_variation_<?php echo $variation->get_id(); ?>" class="wpgv-tag wpgv-amount-container" data-variation_id="<?php echo $variation->get_id(); ?>"> <?php echo $wpgv_gift_voucher->pretty_price($variation->get_regular_price()); ?> <span class="wpgv-remove-amount-button wpgv-price-remove" data-variation_id="<?php echo $variation->get_id(); ?>" data-role="remove">×</span> </span>
                <?php
                    }
                }
                echo '</div>';

                ?>
                <input type="hidden" name="wpgv_product_id" id="wpgv_product_id" value="<?php echo $post_id; ?>">
                <input type="button" value="Add" name="wpgv-add-price-button" id="wpgv-add-price-button" class="button button-primary">

            </div>
        </div><?php
            }

            /*add_action( 'woocommerce_process_product_meta', 'wpgv_save_custom_settings' );
    function wpgv_save_custom_settings( $post_id ){
        // save custom fields

    }*/

            add_action('admin_head', 'wpgv_update_price_javascript');

            function wpgv_update_price_javascript()
            {
                ?>
        <style type="text/css">
            .wpgv-tag {
                padding: 8px;
                margin-bottom: 8px;
                margin-right: 4px;
                text-align: center;
                border-radius: 5px;
                border-width: 1px;
                color: #ffff;
                border-color: #999999;
                background-color: #007cba;
                border-style: solid;
            }

            .wpgv-price-remove {
                margin-left: 10px;
                cursor: pointer;
                font-size: 17px;
            }
        </style>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // ajax add new price varation
                $('#wpgv-add-price-button').click(function() {
                    var voucherPrice = $("#wpgv_price").val();
                    var productId = $("#wpgv_product_id").val();

                    var data = {
                        action: 'ajax_add_wpgv_voucher_amount',
                        wpgv_price: voucherPrice,
                        product_id: productId
                    };

                    // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
                    $.post(ajaxurl, data, function(response) {
                        if (response.data.succsess == 1) {
                            $('#wpgv_variation_price_main').html(response.data.variations_html);
                        } else {
                            console.log(response);
                        }
                    }, 'json');
                });

                // ajax delete price varation
                $(document).on('click', '.wpgv-price-remove', function() {
                    var productId = $("#wpgv_product_id").val();
                    var variation_id = $(this).data("variation_id");

                    var data = {
                        action: 'ajax_remove_wpgv_voucher_amount',
                        variation_id: variation_id,
                        product_id: productId
                    };

                    // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
                    $.post(ajaxurl, data, function(response) {
                        if (response.data.succsess == 1) {
                            $("#wpgv_variation_" + variation_id).hide();
                        } else {
                            console.log(response);
                        }
                    }, 'json');
                });
            });
        </script>
<?php
            }
        }
