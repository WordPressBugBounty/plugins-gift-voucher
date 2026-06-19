<?php

if (!defined('ABSPATH')) exit;  // Exit if accessed directly

global $wpdb;
$voucher_table 	= $wpdb->prefix . 'giftvouchers_list';
$setting_table 	= $wpdb->prefix . 'giftvouchers_setting';
$template_table = $wpdb->prefix . 'giftvouchers_template';
$activity_table = $wpdb->prefix . 'giftvouchers_activity';

if (!current_user_can('manage_options')) {
	wp_die('You are not allowed to be on this page.');
}

if (!function_exists('wpgv_get_voucher_timeline_action_label')) {
	function wpgv_get_voucher_timeline_action_label($action)
	{
		$labels = array(
			'create'        => __('Voucher created', 'gift-voucher'),
			'firsttransact' => __('Initial payment recorded', 'gift-voucher'),
			'transaction'   => __('Voucher balance adjusted', 'gift-voucher'),
			'deactivate'    => __('Voucher deactivated', 'gift-voucher'),
			'reactivate'    => __('Voucher reactivated', 'gift-voucher'),
			'note'          => __('Admin note', 'gift-voucher'),
			'email'         => __('Delivery email status', 'gift-voucher'),
			'payment'       => __('Payment status', 'gift-voucher'),
			'status'        => __('Voucher status', 'gift-voucher'),
		);

		return isset($labels[$action]) ? $labels[$action] : ucwords(str_replace('_', ' ', sanitize_key($action)));
	}
}

if (!function_exists('wpgv_get_voucher_timeline_events')) {
	function wpgv_get_voucher_timeline_events($voucher_id)
	{
		global $wpdb;

		$voucher_id     = absint($voucher_id);
		$voucher_table  = $wpdb->prefix . 'giftvouchers_list';
		$activity_table = $wpdb->prefix . 'giftvouchers_activity';
		$events         = array();

		if (!$voucher_id) {
			return $events;
		}

		$voucher = $wpdb->get_row($wpdb->prepare("SELECT * FROM $voucher_table WHERE id = %d", $voucher_id));
		if (!$voucher) {
			return $events;
		}

		$created_time = !empty($voucher->voucheradd_time) ? $voucher->voucheradd_time : current_time('mysql');

		$events[] = array(
			'timestamp' => $created_time,
			'type'      => 'create',
			'label'     => wpgv_get_voucher_timeline_action_label('create'),
			'amount'    => $voucher->amount,
			'actor'     => $voucher->from_name,
			'note'      => sprintf(__('Order type: %s', 'gift-voucher'), $voucher->order_type),
			'source'    => 'voucher',
			'sequence'  => 10,
		);

		if (!empty($voucher->payment_status)) {
			$payment_note = trim(sprintf(
				__('Payment method: %1$s. Status: %2$s.', 'gift-voucher'),
				$voucher->pay_method,
				$voucher->payment_status
			));

			$events[] = array(
				'timestamp' => $created_time,
				'type'      => 'payment',
				'label'     => wpgv_get_voucher_timeline_action_label('payment'),
				'amount'    => $voucher->amount,
				'actor'     => '',
				'note'      => $payment_note,
				'source'    => 'voucher',
				'sequence'  => 20,
			);
		}

		if (!empty($voucher->check_send_mail)) {
			$events[] = array(
				'timestamp' => $created_time,
				'type'      => 'email',
				'label'     => wpgv_get_voucher_timeline_action_label('email'),
				'amount'    => null,
				'actor'     => '',
				'note'      => sprintf(__('Email delivery is %s.', 'gift-voucher'), $voucher->check_send_mail),
				'source'    => 'voucher',
				'sequence'  => 30,
			);
		}

		if (!empty($voucher->status)) {
			$events[] = array(
				'timestamp' => $created_time,
				'type'      => 'status',
				'label'     => wpgv_get_voucher_timeline_action_label('status'),
				'amount'    => null,
				'actor'     => '',
				'note'      => sprintf(__('Current voucher status: %s.', 'gift-voucher'), $voucher->status),
				'source'    => 'voucher',
				'sequence'  => 40,
			);
		}

		$activity_rows = $wpdb->get_results($wpdb->prepare(
			"SELECT activity.id, activity.activity_date, activity.action, activity.amount, activity.note, activity.user_id, users.display_name, users.user_login
			FROM $activity_table AS activity
			LEFT JOIN {$wpdb->users} AS users ON users.ID = activity.user_id
			WHERE activity.voucher_id = %d
			ORDER BY activity.activity_date ASC, activity.id ASC",
			$voucher_id
		));

		foreach ($activity_rows as $row) {
			$actor = '';
			if (!empty($row->display_name)) {
				$actor = $row->display_name;
			} elseif (!empty($row->user_login)) {
				$actor = $row->user_login;
			} elseif (!empty($row->user_id)) {
				$actor = sprintf(__('User #%d', 'gift-voucher'), absint($row->user_id));
			}

			$label = wpgv_get_voucher_timeline_action_label($row->action);
			if ($row->action === 'transaction') {
				if ((float) $row->amount < 0) {
					$label = __('Voucher redeemed/debited', 'gift-voucher');
				} elseif ((float) $row->amount > 0) {
					$label = __('Voucher credited', 'gift-voucher');
				}
			}

			$events[] = array(
				'timestamp' => $row->activity_date,
				'type'      => $row->action,
				'label'     => $label,
				'amount'    => $row->amount,
				'actor'     => $actor,
				'note'      => $row->note,
				'source'    => 'activity',
				'sequence'  => 100 + absint($row->id),
			);
		}

		usort($events, function ($a, $b) {
			$a_time = strtotime($a['timestamp']);
			$b_time = strtotime($b['timestamp']);

			if ($a_time === $b_time) {
				return $a['sequence'] <=> $b['sequence'];
			}

			return $a_time <=> $b_time;
		});

		return $events;
	}
}

if (!function_exists('wpgv_voucher_has_activity_rows')) {
	function wpgv_voucher_has_activity_rows($voucher_id)
	{
		global $wpdb;

		$voucher_id     = absint($voucher_id);
		$activity_table = $wpdb->prefix . 'giftvouchers_activity';

		if (!$voucher_id) {
			return false;
		}

		$count = $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM $activity_table WHERE voucher_id = %d",
			$voucher_id
		));

		return !empty($count);
	}
}

if (!function_exists('wpgv_add_voucher_timeline_note')) {
	function wpgv_add_voucher_timeline_note($voucher_id, $note)
	{
		global $wpdb;

		$voucher_id     = absint($voucher_id);
		$activity_table = $wpdb->prefix . 'giftvouchers_activity';
		$note           = sanitize_textarea_field($note);

		if (!$voucher_id || $note === '') {
			return false;
		}

		return (bool) $wpdb->insert(
			$activity_table,
			array(
				'voucher_id'    => $voucher_id,
				'action'        => 'note',
				'amount'        => 0,
				'note'          => $note,
				'user_id'       => get_current_user_id(),
				'activity_date' => current_time('mysql'),
			),
			array('%d', '%s', '%f', '%s', '%d', '%s')
		);
	}
}

if (!function_exists('wpgv_render_voucher_timeline')) {
	function wpgv_render_voucher_timeline($voucher_id)
	{
		$events              = wpgv_get_voucher_timeline_events($voucher_id);
		$has_activity_events = wpgv_voucher_has_activity_rows($voucher_id);
		?>
		<h2 class="hndle ui-sortable-handle"><span><?php esc_html_e('Voucher Timeline', 'gift-voucher'); ?></span></h2>
		<?php if (!$has_activity_events) : ?>
			<p class="description wpgv-voucher-timeline-empty">
				<?php esc_html_e('No stored activity rows were found for this voucher yet. Showing current voucher state only.', 'gift-voucher'); ?>
			</p>
		<?php endif; ?>
		<table class="widefat wpgv-voucher-timeline">
			<thead>
				<tr>
					<th width="18%"><?php esc_html_e('Date', 'gift-voucher'); ?></th>
					<th width="20%"><?php esc_html_e('Event', 'gift-voucher'); ?></th>
					<th width="12%"><?php esc_html_e('Amount', 'gift-voucher'); ?></th>
					<th width="15%"><?php esc_html_e('Actor', 'gift-voucher'); ?></th>
					<th width="35%"><?php esc_html_e('Note', 'gift-voucher'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($events)) : ?>
					<tr>
						<td colspan="5"><?php esc_html_e('No timeline activity found for this voucher.', 'gift-voucher'); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ($events as $event) : ?>
						<tr class="<?php echo esc_attr('wpgv-timeline-event-' . sanitize_html_class($event['type'])); ?>">
							<td>
								<?php if (!empty($event['timestamp'])) : ?>
									<abbr title="<?php echo esc_attr(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($event['timestamp']))); ?>">
										<?php echo esc_html(date_i18n(get_option('date_format'), strtotime($event['timestamp']))); ?>
									</abbr>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html($event['label']); ?></td>
							<td>
								<?php
								if ($event['amount'] !== null && $event['amount'] !== '' && (float) $event['amount'] !== 0.0) {
									echo esc_html(wpgv_price_format($event['amount']));
								} else {
									echo '&mdash;';
								}
								?>
							</td>
							<td><?php echo !empty($event['actor']) ? esc_html($event['actor']) : '&mdash;'; ?></td>
							<td><?php echo !empty($event['note']) ? esc_html($event['note']) : '&mdash;'; ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table><br>
		<?php
	}
}

$voucher_id = isset($_REQUEST['voucher_id']) ? absint(wp_unslash($_REQUEST['voucher_id'])) : 0;
$voucher_options = $wpdb->get_row($wpdb->prepare("SELECT * FROM $voucher_table WHERE id = %d", $voucher_id));
$setting_options = $wpdb->get_row("SELECT * FROM $setting_table WHERE id = 1");
$activity = $wpdb->get_row($wpdb->prepare("SELECT * FROM $activity_table WHERE voucher_id = %d AND action = %s", $voucher_id, 'create'));
$return_args = array('page' => 'vouchers-lists');
$detail_args = array(
	'page'       => 'view-voucher-details',
	'action'     => 'view_voucher',
	'voucher_id' => $voucher_id,
);

if (!$voucher_options) {
	wp_die(esc_html__('Voucher not found.', 'gift-voucher'));
}

if (isset($_GET['items']) && sanitize_text_field(wp_unslash($_GET['items'])) === '1') {
	$return_args['items'] = '1';
	$detail_args['items'] = '1';
} elseif (isset($_GET['woocommerce']) && sanitize_text_field(wp_unslash($_GET['woocommerce'])) === '1') {
	$return_args['woocommerce'] = '1';
	$detail_args['woocommerce'] = '1';
}

$return_to_list_url = add_query_arg($return_args, admin_url('admin.php'));
$voucher_detail_url = add_query_arg($detail_args, admin_url('admin.php'));

if (
	isset($_POST['wpgv_voucher_timeline_note_submit']) &&
	check_admin_referer('wpgv_add_voucher_timeline_note_' . $voucher_id, 'wpgv_voucher_timeline_note_nonce')
) {
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You are not allowed to add voucher notes.', 'gift-voucher'));
	}

	$posted_voucher_id = isset($_POST['wpgv_voucher_timeline_note_voucher_id']) ? absint(wp_unslash($_POST['wpgv_voucher_timeline_note_voucher_id'])) : 0;
	$posted_note       = isset($_POST['wpgv_voucher_timeline_note']) ? wp_unslash($_POST['wpgv_voucher_timeline_note']) : '';
	$notice            = 'error';

	if ($posted_voucher_id === $voucher_id && wpgv_add_voucher_timeline_note($voucher_id, $posted_note)) {
		$notice = 'added';
	}

	wp_safe_redirect(add_query_arg('wpgv_timeline_note', $notice, $voucher_detail_url));
	exit;
}

if ($voucher_options->order_type == 'vouchers') {
	$template_id = absint($voucher_options->template_id);
	$template_options = $wpdb->get_row(
		$wpdb->prepare("SELECT * FROM $template_table WHERE id = %d", $template_id)
	);
	$image_style = (is_object($template_options) && !empty($template_options->image_style)) ? $template_options->image_style : '';
	$images = !empty($image_style) ? json_decode($image_style, true) : array();
	$image_id = (is_array($images) && isset($images[0])) ? absint($images[0]) : 0;
	$image_attributes = $image_id ? wp_get_attachment_image_src($image_id, 'voucher-medium') : false;
} elseif ($voucher_options->order_type == 'gift_voucher_product') {
	$product_id = absint($voucher_options->product_id);
	$image_attributes = $product_id ? wp_get_attachment_image_src(get_post_thumbnail_id($product_id), 'voucher-medium') : false;
} else {
	$item_id = absint($voucher_options->item_id);
	$style_image = absint(get_post_meta($item_id, 'style1_image', true));
	$image_attributes = $style_image ? wp_get_attachment_image_src($style_image, 'voucher-medium') : false;
}

?>
<div class="wrap">
	<h1><?php echo esc_html_e('Voucher Order ID', 'gift-voucher') ?>: <?php echo esc_html($voucher_options->id); ?></h1>
	<p class="description"><?php echo esc_html_e('Here you can find detailed information for a voucher code.', 'gift-voucher') ?></p><br>
	<?php if (isset($_GET['wpgv_timeline_note']) && sanitize_key(wp_unslash($_GET['wpgv_timeline_note'])) === 'added') : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e('Timeline note added.', 'gift-voucher'); ?></p>
		</div>
	<?php elseif (isset($_GET['wpgv_timeline_note']) && sanitize_key(wp_unslash($_GET['wpgv_timeline_note'])) === 'error') : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e('Timeline note could not be added. Please enter a note and try again.', 'gift-voucher'); ?></p>
		</div>
	<?php endif; ?>
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
						<p><?php echo isset($voucher_options->note_order) ? esc_attr($voucher_options->note_order) : ''; ?></p>
						<button class="edit-note" id="edit-note" data-voucher-id="<?php echo esc_attr($voucher_options->id); ?>">
							<i class="dashicons dashicons-edit"></i>
						</button>
					</td>

					<td>
						<?php
						$pdf_url = wpgv_get_voucher_pdf_url($voucher_options->voucherpdf_link);
						if ($pdf_url !== '') {
							echo '<a href="' . esc_url($pdf_url) . '" title="click to show order receipt" target="_blank" rel="noopener noreferrer"><img src="' . esc_url(WPGIFT__PLUGIN_URL . '/assets/img/pdf.png') . '" width="50"></a>';
						}
						?>
					</td>
				</tr>
			</tbody>
		</table><br>
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
					<td><?php echo ($voucher_options->buying_for == 'yourself') ? esc_html__('Yourself', 'gift-voucher') : esc_html__('Someone Else', 'gift-voucher'); ?></td>

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
					<td><?php echo ($voucher_options->shipping_type == 'shipping_as_email') ? esc_html__('Shipping as Email', 'gift-voucher') : esc_html__('Shipping as Post', 'gift-voucher'); ?></td>
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
						<button class="edit-date" id="edit-date" data-activity-id="<?php echo esc_attr(is_object($activity) ? $activity->id : 0); ?>"><i class="dashicons dashicons-edit"></i></button>
					</td>
				</tr>
			</tbody>
		</table><br>
		<h2 class="hndle ui-sortable-handle"><span><?php esc_html_e('Add Internal Timeline Note', 'gift-voucher'); ?></span></h2>
		<form class="wpgv-voucher-timeline-note-form" method="post" action="<?php echo esc_url($voucher_detail_url); ?>">
			<?php wp_nonce_field('wpgv_add_voucher_timeline_note_' . $voucher_id, 'wpgv_voucher_timeline_note_nonce'); ?>
			<input type="hidden" name="wpgv_voucher_timeline_note_voucher_id" value="<?php echo esc_attr($voucher_id); ?>">
			<textarea name="wpgv_voucher_timeline_note" rows="4" class="large-text" required></textarea>
			<p class="submit">
				<button type="submit" name="wpgv_voucher_timeline_note_submit" value="1" class="button button-primary">
					<?php esc_html_e('Add Timeline Note', 'gift-voucher'); ?>
				</button>
			</p>
		</form>
		<?php wpgv_render_voucher_timeline($voucher_id); ?>
		<a href="<?php echo esc_url($return_to_list_url); ?>" class="button button-primary"><?php echo esc_html_e('Back to Vouchers List', 'gift-voucher') ?></a>
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
				<?php wp_nonce_field('update_voucher_date_action', 'update_voucher_date_nonce'); ?>
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
					<textarea id="data-note"><?php echo esc_attr(isset($voucher_options->note_order) ? $voucher_options->note_order : ''); ?></textarea>
					<?php wp_nonce_field('update_voucher_note_action', 'update_voucher_note_nonce'); ?>
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
				<?php wp_nonce_field('update_voucher_price_action', 'update_voucher_price_nonce'); ?>
				<div class="form-group">
					<label class="voucher-label">
						<?php esc_html_e('Price:', 'gift-voucher'); ?>
					</label>
					<input type="number" id="data-price" value="<?php echo esc_attr(is_object($activity) ? intval($activity->amount) : intval($voucher_options->amount)); ?>" />
				</div>
			</div>
			<div class="wp-core-ui-modal-footer modal-footer">
				<button type="button" class="button" id="close-modal-button">
					<?php esc_html_e('Close', 'gift-voucher'); ?>
				</button>
				<button type="button" class="button button-primary btn-update-price" data-activity-id="<?php echo esc_attr(is_object($activity) ? $activity->id : 0); ?>" data-voucher-id="<?php echo esc_attr($voucher_id); ?>">
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
			var nonce = $('#update_voucher_date_nonce').val();

			$.ajax({
				type: 'POST',
				url: ajaxurl,
				data: {
					action: 'update_voucher_date',
					voucher_id: voucher_id,
					new_date: new_date,
					security: nonce
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
		});


		$('#edit-note').on('click', function() {
			$('#modal-note').css('display', 'block');
		});
		$('#close-modal-button, .close-modal-button').on('click', function() {
			$('#modal-note').css('display', 'none');
		});
		$('.btn-update-note').on('click', function() {
			var voucher_id = $(this).data('voucher-id');
			var data_note = $('#data-note').val();
			var nonce = $('#update_voucher_note_nonce').val();

			$.ajax({
				type: 'POST',
				url: ajaxurl,
				data: {
					action: 'update_voucher_note',
					voucher_id: voucher_id,
					data_note: data_note,
					security: nonce
				},
				success: function(response) {
					if (response.success) {
						alert('Note updated successfully');
						$('#modal-note').css('display', 'none');
						location.reload();
					} else {
						alert(response.data || 'Note update failed');
					}
				}
			});
		});


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
			var nonce = $('#update_voucher_price_nonce').val();

			$.ajax({
				type: 'POST',
				url: ajaxurl,
				data: {
					action: 'update_voucher_price',
					activity_id: activity_id,
					data_price: data_price,
					voucher_id: voucher_id,
					security: nonce
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
