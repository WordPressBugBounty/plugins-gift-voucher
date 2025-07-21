<?php

defined('ABSPATH') or exit;
?>
<style>
    #wpgv-redeem-error {
        color: red;
    }
</style>
<tr style="display:none">
    <td colspan="6" class="actions">
        <div class="coupon gift-vocuher-coupon">
            <div id="wpgv-redeem-error"></div>
            <label for="wpgv-redeem-gift-card-number"><?php esc_html_e('Gift Card:', 'gift-voucher'); ?></label>
            <input type="text" name="wpgv_gift_card_number" class="input-text" placeholder="<?php esc_attr_e('Gift voucher code', 'gift-voucher'); ?>" id="wpgv-redeem-gift-voucher-number" value="" />
            <input type="submit" class="button" name="apply_wpgv_gift_voucher" id="wpgv-apply-gift-voucher-checkout" value="<?php esc_attr_e('Apply gift card', 'gift-voucher'); ?>" data-wait-text="<?php esc_html_e('Please wait...', 'gift-voucher'); ?>">
        </div>
    </td>
</tr>