<?php

if( !defined( 'ABSPATH' ) ) exit;  // Exit if accessed directly

global $wpdb;
$setting_table 	= $wpdb->prefix . 'giftvouchers_setting';
$setting_options = $wpdb->get_row( "SELECT * FROM $setting_table WHERE id = 1" );
$items = isset($_GET['items']) ? sanitize_textarea_field($_GET['items']) : '';
$voucher_code = isset($_GET['voucher_code']) ? sanitize_textarea_field($_GET['voucher_code']) : '';
?>
<div class="wrap voucher-page">
	<h1><?php echo esc_html_e( 'Voucher Orders', 'gift-voucher' ) ?></h1><br>
	<div class="image-banner" style="margin-bottom: 10px;">
		<a href="https://www.wp-giftcard.com/" target="_blank"><img src="<?php echo esc_url(WPGIFT__PLUGIN_URL.'/assets/img/banner6.png'); ?>" style="width: 100%;"></a>
	</div>
	<div class="content">
    	<?php
    		$sql = "SELECT id, amount FROM {$wpdb->prefix}giftvouchers_list WHERE `status` = 'unused' AND `payment_status` = 'Paid' ORDER BY `id` DESC";
			$orders = $wpdb->get_results( $sql, ARRAY_A );
			$columns = array();
			$amount = 0;
    		foreach($orders as $row) {
        		$columns[] = $row['id'];
        		$amount += $row['amount'];
    		}
   		do_wpgv_check_voucher_status();
    		$num_fields = count($columns);
			if ( $num_fields > 0 ) { ?>
			<div class="total-unused">
     			<div class="count"><span><?php echo esc_html($num_fields) ?></span><?php echo esc_html_e('Unused Gift Vouchers', 'gift-voucher'); ?></div>
     			<div class="amount"><span><?php echo wpgv_price_format($amount) ?></span><?php echo esc_html_e('Total Unused Voucher Amount', 'gift-voucher'); ?></div>
				<form action="<?php echo admin_url( 'admin.php' ); ?>">
					<!-- <input type="hidden" name="post_type" value="wpgv_voucher_product"> -->
					<input type="hidden" name="page" value="<?php echo esc_html("vouchers-lists"); ?>">
					<?php if($items): ?><input type="hidden" name="items" value="<?php echo esc_html("1"); ?>"><?php endif; ?>
					<input type="hidden" name="search" value="<?php echo esc_html("1"); ?>">
        			<input type="text" name="voucher_code" autocomplete="off" placeholder="Search by Gift voucher code or email" value="<?php echo esc_html($voucher_code); ?>" style="width: 400px;">
        			<input type="submit" class="button button-primary" value="<?php echo esc_html("Search"); ?>">
    			</form>
     		</div>
			<?php } ?>
		<!-- <a href="<?php echo admin_url( 'edit.php' ); ?>?post_type=wpgv_voucher_product&page=import-orders" class="button button-primary" style="display: inline-block;padding: 0 10px;float:right;"><?php echo esc_html_e( 'Import Vouchers', 'gift-voucher' ) ?></a> -->
		<h2 class="nav-tab-wrapper">
			<a class="nav-tab <?php if(!$items): ?>nav-tab-active<?php endif; ?>" href="?page=vouchers-lists"><?php echo esc_html_e( 'Purchased Voucher Codes', 'gift-voucher' ) ?></a>
			<a class="nav-tab <?php if($items): ?>nav-tab-active<?php endif; ?>" href="?page=vouchers-lists&items=1"><?php echo esc_html_e( 'Purchased Items', 'gift-voucher' ) ?></a>
		</h2>
		<div id="post-body" class="metabox-holder">
			<div id="post-body-content">
				<div class="meta-box-sortables ui-sortable">
					<form method="post">
						<?php
						$this->vouchers_obj->prepare_items();
						$this->vouchers_obj->display(); ?>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>