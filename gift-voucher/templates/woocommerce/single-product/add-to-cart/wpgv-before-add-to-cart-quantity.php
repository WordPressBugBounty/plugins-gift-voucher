<?php

defined('ABSPATH') or exit;

global $product;

$selected = isset($_REQUEST['attribute_' . WPGV_DENOMINATION_ATTRIBUTE_SLUG]);
global $wpdb;
$setting_table_name = $wpdb->prefix . 'giftvouchers_setting';
$options = $wpdb->get_row("SELECT * FROM $setting_table_name WHERE id = 1");

$is_order_form_enable = $options->is_order_form_enable;
?>
<style>
    .wpgv-field-container {
        margin-bottom: 14px;
    }

    .wpgv-label {
        font-weight: 600;
        display: block;
    }

    .wpgv-subtitle {
        font-size: 11px;
        line-height: 1.465;
        color: #767676;
    }

    .wpgv-input-text {
        width: 95%;
    }

    #wpgv-recipient-count {
        font-weight: 600;
    }

    #wpgv-quantity-one-per-recipient {
        display: none;
    }

    #wpgv-message {
        display: block;
        height: 100px;
        width: 95%;
    }

    .wpgv-hidden {
        display: none;
    }

    <?php
    if (is_a($product, 'WC_Gift_Voucher_Product')) {
    ?>.woocommerce-variation-description,
    .woocommerce-variation-price,
    .woocommerce-variation-availability {
        display: none !important;
    }

    <?php
    }
    ?>.add_to_cart_wrapper {
        flex-wrap: wrap;
    }

    #wpgv-purchase-container {
        width: 100%;
        flex-basis: 100% !important;
    }

    .single_add_to_cart_button {
        flex: 1;
    }

    .woocommerce-variation-add-to-cart {
        flex-wrap: wrap !important;
    }
</style>

<div id="wpgv-purchase-container" style="<?php echo esc_attr($selected ? '' : 'display: none;'); ?>">
    <div class="wpgv-field-container" style="<?php echo esc_attr($is_order_form_enable == 1 ? '' : 'display: none;'); ?>">
        <label for="wpgv_your_name" class="wpgv-label"><?php echo esc_html__('Your Name', 'gift-voucher'); ?></label>
        <input type="text" id="wpgv_your_name" name="wpgv_your_name" class="wpgv-input-text" value="" <?php echo esc_attr($is_order_form_enable == 1 ? 'required' : ''); ?>>
    </div>

    <div class="wpgv-field-container" style="<?php echo esc_attr($is_order_form_enable == 1 ? '' : 'display: none;'); ?>">
        <label for="wpgv_recipient_name" class="wpgv-label"><?php echo esc_html__('Recipient Name', 'gift-voucher'); ?></label>
        <input type="text" id="wpgv_recipient_name" name="wpgv_recipient_name" class="wpgv-input-text" value="" <?php echo esc_attr($is_order_form_enable == 1 ? 'required' : ''); ?>>
    </div>

    <div class="wpgv-field-container" style="<?php echo esc_attr($is_order_form_enable == 1 ? '' : 'display: none;'); ?>">
        <label for="wpgv_recipient_email" class="wpgv-label"><?php echo esc_html__('Send the voucher to recipient email here', 'gift-voucher'); ?></label>
        <input type="email" id="wpgv_recipient_email" name="wpgv_recipient_email" class="wpgv-input-text" value="" <?php echo esc_attr($is_order_form_enable == 1 ? 'required' : ''); ?>>
    </div>

    <div class="wpgv-field-container" style="<?php echo esc_attr($is_order_form_enable == 1 ? '' : 'display: none;'); ?>">
        <label for="wpgv_your_email" class="wpgv-label"><?php echo esc_html__('Your email address (for the receipt)', 'gift-voucher'); ?></label>
        <input type="email" id="wpgv_your_email" name="wpgv_your_email" class="wpgv-input-text" value="" <?php echo esc_attr($is_order_form_enable == 1 ? 'required' : ''); ?>>
    </div>

    <div class="wpgv-field-container" style="<?php echo esc_attr($is_order_form_enable == 1 ? '' : 'display: none;'); ?>">
        <label for="wpgv-message" class="wpgv-label"><?php echo esc_html__('Personal Message (Optional) (Max: 250 Characters)', 'gift-voucher'); ?> </label>
        <textarea maxlength="50" id="wpgv_message" name="wpgv_message"></textarea>
    </div>

    <div id="wpgv-quantity-one-per-recipient" class="wpgv-field-container">
        <div class="wpgv-label"><?php echo esc_html__('Quantity', 'gift-voucher'); ?>: <span id="wpgv-recipient-count">1</span></div>
        <div class="wpgv-subtitle"><?php echo esc_html__('1 to each recipient', 'gift-voucher'); ?></div>
    </div>
</div>