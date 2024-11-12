<?php

if (!defined('ABSPATH')) exit;  // Exit if accessed directly

global $wpdb;
$voucher_table 	= $wpdb->prefix . 'giftvouchers_list';
$setting_table 	= $wpdb->prefix . 'giftvouchers_setting';
$template_table = $wpdb->prefix . 'giftvouchers_template';
$activity_table = $wpdb->prefix . 'giftvouchers_activity';

$url_upload = wp_get_upload_dir();
$baseurl = $url_upload['baseurl'];
if (!current_user_can('manage_options')) {
	wp_die('You are not allowed to be on this page.');
}


$voucher_id = absint($_REQUEST['voucher_id']);
$voucher_options = $wpdb->get_row($wpdb->prepare("SELECT * FROM $voucher_table WHERE id = %d", $voucher_id));
$setting_options = $wpdb->get_row("SELECT * FROM $setting_table WHERE id = 1");
$activity = $wpdb->get_row("SELECT * FROM $activity_table WHERE voucher_id = $voucher_id AND action = 'create'");

if ($voucher_options->order_type == 'vouchers') {
	$template_id = absint($voucher_options->template_id);
	$template_options = $wpdb->get_row(
		$wpdb->prepare("SELECT * FROM $template_table WHERE id = %d", $template_id)
	);
	$images = $template_options->image_style ? json_decode($template_options->image_style) : ['', '', ''];
	$image_attributes = wp_get_attachment_image_src($images[0], 'voucher-medium');
} else {
	$item_id = absint($voucher_options->item_id);
	$style_image = esc_html(get_post_meta($item_id, 'style1_image', true));
	$image_attributes = wp_get_attachment_image_src($style_image, 'voucher-medium');
}

?>
<div class="wrap">
	<h1><?php echo esc_html_e('Voucher Order ID', 'gift-voucher') ?>: <?php echo esc_html($voucher_options->id); ?></h1>
	<p class="description"><?php echo esc_html_e('Here you can find detailed information for a voucher code.', 'gift-voucher') ?></p><br>
	<div id="voucher-details">
		<table class="widefat main">
			<thead>
				<th><?php echo esc_html_e('Voucher Code', 'gift-voucher') ?></th>
				<th><?php echo esc_html_e('Order Date', 'gift-voucher') ?></th>
				<th><?php echo esc_html_e('Status', 'gift-voucher') ?></th>
				<th><?php echo esc_html_e('Notes', 'gift-voucher') ?></th>
				<th><?php echo esc_html_e('See Receipt (PDF)', 'gift-voucher') ?></th>
			</thead>
			<tbody>
				<tr>
					<td>
						<h3><?php echo esc_html($voucher_options->couponcode); ?></h3>
					</td>
					<td><abbr title="<?php echo esc_html(gmdate('Y/m/d H:i:s a', strtotime($voucher_options->voucheradd_time))); ?>"><?php echo esc_html(gmdate('Y/m/d', strtotime($voucher_options->voucheradd_time))); ?></abbr></td>
					<td>
						<?php if ($voucher_options->status == 'unused') echo '<span class="vunused">' . esc_html_e('Unused', 'gift-voucher') . '</span>';
						else echo '<span class="vused">' . esc_html_e('Voucher Used', 'gift-voucher') . '</span>'; ?>
					</td>
					<td>
						<p><?php echo esc_attr($voucher_options->note_order); ?></p>
						<button class="edit-note" id="edit-note" data-voucher-id="<?php echo esc_attr($voucher_options->id); ?>"><i class="dashicons dashicons-edit"></i></button>
					</td>
					<td>
						<?php echo '<a href="' . esc_url($baseurl . '/voucherpdfuploads/' . $voucher_options->voucherpdf_link . '.pdf') . '" title="click to show order receipt" target="_blank"><img src="' . esc_url(WPGIFT__PLUGIN_URL . '/assets/img/pdf.png') . '" width="50"></a>'; ?>
					</td>
				</tr>
			</tbody>
		</table><br>
		<?php if ($voucher_options->order_type == 'vouchers'): ?>
			<h2 class="hndle ui-sortable-handle"><span><?php echo esc_html_e('Template Information', 'gift-voucher') ?></span></h2>
			<table class="widefat">
				<thead>
					<tr>
						<th><?php echo esc_html_e('Template Name', 'gift-voucher') ?></th>
						<th><?php echo esc_html_e('Template Image', 'gift-voucher') ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php echo esc_html($template_options->title); ?></td>
						<td><img src="<?php echo esc_url($image_attributes[0]); ?>" height="60"></td>
					</tr>
				</tbody>
			</table>
		<?php else: ?>
			<h2 class="hndle ui-sortable-handle"><span><?php echo esc_html_e('Item Information', 'gift-voucher') ?></span></h2>
			<table class="widefat">
				<thead>
					<tr>
						<th><?php echo esc_html_e('Item Name', 'gift-voucher') ?></th>
						<th><?php echo esc_html_e('Item Description', 'gift-voucher') ?></th>
						<th><?php echo esc_html_e('Item Image', 'gift-voucher') ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php echo esc_html(get_the_title($voucher_options->item_id)); ?></td>
						<td><?php echo esc_html(get_post_meta($voucher_options->item_id, 'description', true)); ?></td>
						<td><img src="<?php echo esc_url($image_attributes[0]); ?>" height="60"></td>
					</tr>
				</tbody>
			</table>
		<?php endif; ?>
		<h2 class="hndle ui-sortable-handle"><span><?php echo esc_html_e('Voucher Information', 'gift-voucher') ?></span></h2>
		<table class="widefat">
			<thead>
				<tr>
					<th width="15%"><?php echo esc_html_e('Buying For', 'gift-voucher') ?></th>
					<th width="15%"><?php echo esc_html_e('Your Name', 'gift-voucher') ?></th>
					<?php if ($voucher_options->buying_for != 'yourself') { ?>
						<th width="15%"><?php echo esc_html_e('Recipient Name', 'gift-voucher') ?></th>
					<?php } ?>
					<th width="10%"><?php echo esc_html_e('Amount', 'gift-voucher') ?></th>
					<th width="60%"><?php echo esc_html_e('Message', 'gift-voucher') ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php echo ($voucher_options->buying_for == 'yourself') ? esc_html('Yourself') : esc_html('Someone Else'); ?></td>
					<td><?php echo esc_html($voucher_options->from_name); ?></td>
					<?php if ($voucher_options->buying_for != 'yourself') { ?>
						<td><?php echo esc_html($voucher_options->to_name); ?></td>
					<?php } ?>
					<td>
						<?php echo esc_html(wpgv_price_format($voucher_options->amount)); ?>
						<button class="edit-price" id="edit-price" data-voucher-id="<?php echo esc_attr($voucher_options->id); ?>"><i class="dashicons dashicons-edit"></i></button>
					</td>
					<td><?php echo esc_html($voucher_options->message); ?></td>
				</tr>
			</tbody>
		</table>
		<h2 class="hndle ui-sortable-handle"><span><?php echo esc_html_e('Buyers Information', 'gift-voucher') ?></span></h2>
		<table class="widefat">
			<thead>
				<tr>
					<th width="10%"><?php echo esc_html_e('Shipping', 'gift-voucher') ?></th>
					<?php if ($voucher_options->shipping_type == 'shipping_as_email') { ?>
						<th width="10%"><?php echo esc_html_e('Email', 'gift-voucher') ?></th>
					<?php } else { ?>
						<th width="10%"><?php echo esc_html_e('Name', 'gift-voucher') ?></th>
						<th width="20%"><?php echo esc_html_e('Email', 'gift-voucher') ?></th>
						<th width="40%"><?php echo esc_html_e('Address', 'gift-voucher') ?></th>
						<th width="10%"><?php echo esc_html_e('Postcode', 'gift-voucher') ?></th>
						<th width="10%"><?php echo esc_html_e('Shipping Method', 'gift-voucher') ?></th>
					<?php } ?>
					<th width="10%"><?php echo esc_html_e('Payment Method', 'gift-voucher') ?></th>
					<th width="10%"><?php echo esc_html_e('Transaction ID', 'gift-voucher') ?></th>
					<th width="10%"><?php echo esc_html_e('Expiry Date', 'gift-voucher') ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php echo ($voucher_options->shipping_type == 'shipping_as_email') ? esc_html('Shipping as Email') : esc_html('Shipping as Post'); ?></td>
					<?php if ($voucher_options->shipping_type == 'shipping_as_email') { ?>
						<td><?php echo esc_html($voucher_options->shipping_email); ?></td>
					<?php } else { ?>
						<td><?php echo esc_html($voucher_options->firstname) . ' ' . esc_html($voucher_options->lastname); ?></td>
						<td><?php echo esc_html($voucher_options->email); ?></td>
						<td><?php echo esc_html($voucher_options->address); ?></td>
						<td><?php echo esc_html($voucher_options->postcode); ?></td>
						<td><?php echo esc_html($voucher_options->shipping_method); ?></td>
					<?php } ?>
					<td><?php echo esc_html($voucher_options->pay_method); ?></td>
					<?php if ($voucher_options->pay_method == 'Stripe' && esc_html(get_post_meta($voucher_options->id, 'wpgv_stripe_session_key', true))) { ?>
						<td><span class="" style="width: 150px; display: block; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;" title="<?php echo esc_html(get_post_meta($voucher_options->id, 'wpgv_stripe_session_key', true)) ?>"><?php echo esc_html(get_post_meta($voucher_options->id, 'wpgv_stripe_session_key', true)) ?></span></td>
					<?php } elseif ($voucher_options->pay_method == 'Paypal' && esc_html(get_post_meta($voucher_options->id, 'wpgv_paypal_payment_key', true))) { ?>
						<td><span style="width: 150px; display: block; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;" title="<?php echo esc_html(get_post_meta($voucher_options->id, 'wpgv_paypal_payment_key', true)) ?>"><?php echo esc_html(get_post_meta($voucher_options->id, 'wpgv_paypal_payment_key', true)) ?></span></td>
					<?php } else { ?>
						<td></td>
					<?php } ?>
					</td>
					<td>
						<abbr title="<?php echo esc_html($voucher_options->expiry); ?>"><?php echo esc_html($voucher_options->expiry); ?></abbr>
						<button class="edit-date" id="edit-date" data-activity-id="<?php echo esc_attr($activity->id); ?>"><i class="dashicons dashicons-edit"></i></button>
					</td>
				</tr>
			</tbody>
		</table><br>
		<a href="<?php echo esc_url(admin_url('admin.php')); ?>?page=vouchers-lists" class="button button-primary"><?php echo esc_html_e('Back to Vouchers List', 'gift-voucher') ?></a>
	</div>
</div>

<div class="voucher-modal" id="modal-date" style="display: none;">
	<div class="wp-core-ui-modal-dialog modal-dialog">
		<div class="wp-core-ui-modal-content modal-content">
			<div class="wp-core-ui-modal-header modal-header close-modal-button">
				<h1 class="wp-core-ui-modal-title" id="myModalLabel">
					<?php esc_html_e('Edit date', 'gift-voucher'); ?>
				</h1>

			</div>
			<div class="wp-core-ui-modal-body modal-body">
				<div class="form-group">
					<label for="datepicker" class="voucher-label">
						<?php esc_html_e('Select Date:', 'gift-voucher'); ?>
					</label>
					<input type="text" id="datepicker" class="form-control" value="<?php echo esc_html(gmdate('d.m.Y', strtotime($voucher_options->expiry))); ?>">
				</div>
			</div>
			<div class="wp-core-ui-modal-footer modal-footer">
				<button type="button" class="button" id="close-modal-button">
					<?php esc_html_e('Close', 'gift-voucher'); ?>
				</button>
				<button type="button" class="button button-primary btn-update-date" data-voucher-id="<?php echo esc_attr($voucher_id); ?>">
					<?php esc_html_e('Update Date', 'gift-voucher'); ?>
				</button>
			</div>
		</div>
	</div>
</div>
<div class="voucher-modal" id="modal-note" style="display: none;">
	<div class="wp-core-ui-modal-dialog modal-dialog">
		<div class="wp-core-ui-modal-content modal-content">
			<div class="wp-core-ui-modal-header modal-header close-modal-button">
				<h1 class="wp-core-ui-modal-title" id="myModalLabel">
					<?php esc_html_e('Edit note', 'gift-voucher'); ?>
				</h1>
			</div>
			<div class="wp-core-ui-modal-body modal-body">
				<div class="form-group">
					<label class="voucher-label">
						<?php esc_html_e('Note:', 'gift-voucher'); ?>
					</label>
					<textarea id="data-note"><?php echo esc_attr($voucher_options->note_order); ?></textarea>
				</div>
			</div>
			<div class="wp-core-ui-modal-footer modal-footer">
				<button type="button" class="button" id="close-modal-button">
					<?php esc_html_e('Close', 'gift-voucher'); ?>
				</button>
				<button type="button" class="button button-primary btn-update-note" data-voucher-id="<?php echo esc_attr($voucher_id); ?>">
					<?php esc_html_e('Update note', 'gift-voucher'); ?>
				</button>

			</div>
		</div>
	</div>
</div>
<div class="voucher-modal" id="modal-price" style="display: none;">
	<div class="wp-core-ui-modal-dialog modal-dialog">
		<div class="wp-core-ui-modal-content modal-content">
			<div class="wp-core-ui-modal-header modal-header close-modal-button">
				<h1 class="wp-core-ui-modal-title" id="myModalLabel">
					<?php esc_html_e('Edit price', 'gift-voucher'); ?>
				</h1>
			</div>
			<div class="wp-core-ui-modal-body modal-body">
				<div class="form-group">
					<label class="voucher-label">
						<?php esc_html_e('Price:', 'gift-voucher'); ?>
					</label>
					<input type="number" id="data-price" value="<?php echo esc_attr(intval($activity->amount)); ?>" />
				</div>
			</div>
			<div class="wp-core-ui-modal-footer modal-footer">
				<button type="button" class="button" id="close-modal-button">
					<?php esc_html_e('Close', 'gift-voucher'); ?>
				</button>
				<button type="button" class="button button-primary btn-update-price" data-activity-id="<?php echo esc_attr($activity->id); ?>" data-voucher-id="<?php echo esc_attr($voucher_id); ?>">
					<?php esc_html_e('Update price', 'gift-voucher'); ?>
				</button>
			</div>
		</div>
	</div>
</div>
<script>
	jQuery(document).ready(function($) {
		$('#edit-date').on('click', function() {
			$('#modal-date').css('display', 'block');
		});
		$('#close-modal-button, .close-modal-button').on('click', function() {
			$('#modal-date').css('display', 'none');
		});
		$('.btn-update-date').on('click', function() {
			var voucher_id = $(this).data('voucher-id');
			var new_date = $('#datepicker').val();

			$.ajax({
				type: 'POST',
				url: ajaxurl,
				data: {
					action: 'update_voucher_date',
					voucher_id: voucher_id,
					new_date: new_date
				},
				success: function(response) {
					if (response.success) {
						alert('Date updated successfully');
						$('#modal-date').css('display', 'none');
						location.reload();
					} else {
						alert('Date update failed');
					}
				}
			});
		}); // code date

		$('#edit-note').on('click', function() {
			console.log("123");
			$('#modal-note').css('display', 'block');
		});
		$('#close-modal-button, .close-modal-button').on('click', function() {
			$('#modal-note').css('display', 'none');
		});
		$('.btn-update-note').on('click', function() {
			var voucher_id = $(this).data('voucher-id');
			var data_note = $('#data-note').val();

			$.ajax({
				type: 'POST',
				url: ajaxurl,
				data: {
					action: 'update_voucher_note',
					voucher_id: voucher_id,
					data_note: data_note
				},
				success: function(response) {
					if (response.success) {
						alert('Note updated successfully');
						$('#modal-note').css('display', 'none');
						location.reload();
					} else {
						alert('Note update failed');
					}
				}
			});
		}); // code note

		// code price
		$('#edit-price').on('click', function() {
			$('#modal-price').css('display', 'block');
		});
		$('#close-modal-button, .close-modal-button').on('click', function() {
			$('#modal-price').css('display', 'none');
		});
		$('.btn-update-price').on('click', function() {
			var activity_id = $(this).data('activity-id');
			var voucher_id = $(this).data('voucher-id');
			var data_price = $('#data-price').val();


			$.ajax({
				type: 'POST',
				url: ajaxurl,
				data: {
					action: 'update_voucher_price',
					activity_id: activity_id,
					data_price: data_price,
					voucher_id: voucher_id
				},
				success: function(response) {
					if (response.success) {
						alert('Price updated successfully');
						$('#modal-price').css('display', 'none');
						location.reload();
					} else {
						alert('Price update failed');
					}
				}
			});
		}); // code price

		$('.voucher-modal').on('click', function(e) {
			if (e.target === this) {
				$(this).css('display', 'none');
			}
		});


	});
</script>