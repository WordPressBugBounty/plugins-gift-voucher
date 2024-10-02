<?php

if( !defined( 'ABSPATH' ) ) exit;  // Exit if accessed directly

	?>
	<div class="woocommerce-form-coupon-toggle">
		<?php wc_print_notice( __( 'Have a gift voucher?', 'gift-voucher' ) . ' <a href="#" id="wpgv_show-gift-card">' . __( 'Click here to enter your gift voucher code', 'gift-voucher' ) . '</a>', 'notice' ); ?>
	</div>

	<form class="checkout_wpgv_gift_voucher woocommerce-form-coupon" method="post" style="display:none">

		<p><?php esc_html_e( 'If you have a gift card number, please apply it below.', 'gift-voucher' ); ?></p>

		<p class="form-row form-row-first">
			<input type="text" name="wpgv_gift_card_number" class="input-text" placeholder="<?php esc_attr_e( 'Gift voucher code', 'gift-voucher' ); ?>" id="wpgv-redeem-gift-voucher-number" value="" />
		</p>

		<p class="form-row form-row-last">
			<input type="submit" class="button" name="apply_wpgv_gift_voucher" id="wpgv-apply-gift-voucher-checkout" value="<?php esc_attr_e( 'Apply gift card', 'gift-voucher' ); ?>" data-wait-text="<?php esc_html_e( 'Please wait...', 'gift-voucher' ); ?>">
		</p>

		<p>
			<div id="wpgv-redeem-error" style="color: red;"></div>
		</p>

		<div class="clear"></div>
	</form>