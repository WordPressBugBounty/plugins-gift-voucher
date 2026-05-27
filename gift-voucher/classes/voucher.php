<?php

if (!defined('ABSPATH')) exit;  // Exit if accessed directly

if (!class_exists('WPGV_Voucher_List')) :

	/**
	 * WPGV_Voucher_List Class
	 */
	class WPGV_Voucher_List extends WP_List_Table
	{

		/** Class constructor */
		public function __construct()
		{
			parent::__construct(array(
				'singular' => __('Voucher Order', 'gift-voucher'), //singular name of the listed records
				'plural'   => __('Voucher Orders', 'gift-voucher'), //plural name of the listed records
				'ajax'     => true //does this table support ajax?
			));
		}

		protected static function is_items_view()
		{
			return isset($_GET['items']) && sanitize_text_field(wp_unslash($_GET['items'])) === '1';
		}

		protected static function is_woocommerce_view()
		{
			return isset($_GET['woocommerce']) && sanitize_text_field(wp_unslash($_GET['woocommerce'])) === '1';
		}

		protected static function get_current_order_type_filter()
		{
			if (self::is_items_view()) {
				return 'items';
			}

			if (self::is_woocommerce_view()) {
				return 'gift_voucher_product';
			}

			return 'vouchers';
		}

		protected static function get_current_view_args()
		{
			if (self::is_items_view()) {
				return array('items' => '1');
			}

			if (self::is_woocommerce_view()) {
				return array('woocommerce' => '1');
			}

			return array();
		}

		protected static function get_admin_page_url($page, $args = array())
		{
			$query_args = array_merge(
				array('page' => $page),
				self::get_current_view_args(),
				$args
			);

			return add_query_arg($query_args, admin_url('admin.php'));
		}

		/**
		 * Retrieve vouchers data from the database
		 *
		 * @param int $per_page
		 * @param int $page_number
		 *
		 * @return mixed
		 */
		public static function get_vouchers($per_page = 20, $page_number = 1)
		{
			global $wpdb;
			$page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : 'vouchers-lists';
			$search = isset($_GET['search']) ? '%' . $wpdb->esc_like(sanitize_text_field(wp_unslash($_GET['search']))) . '%' : '';
			$voucher_code = isset($_GET['voucher_code']) ? sanitize_text_field(wp_unslash($_GET['voucher_code'])) : '';
			$search_email = '';
			if ($voucher_code && filter_var($voucher_code, FILTER_VALIDATE_EMAIL)) {
				$search_email = $voucher_code;
				$voucher_code = '1';
			}

			$where_clause = $wpdb->prepare(" WHERE `order_type` = %s ", self::get_current_order_type_filter());

			if ($page == 'vouchers-lists') {
				if ($search && $voucher_code) {
					$where_clause .= $wpdb->prepare(" AND (`couponcode` LIKE %s OR `email` LIKE %s OR `shipping_email` LIKE %s) ", $voucher_code, $search_email, $search_email);
				}
			} elseif ($page == 'redeem-voucher') {
				$where_clause .= $wpdb->prepare(" AND (`couponcode` = %s OR `email` LIKE %s OR `shipping_email` LIKE %s) ", $voucher_code, $search_email, $search_email);
			}

			$result = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}giftvouchers_list {$where_clause} ORDER BY `id` DESC LIMIT %d OFFSET %d",
					$per_page,
					($page_number - 1) * $per_page
				),
				'ARRAY_A'
			);

			return $result;
		}






		/**
		 * Set as used a voucher record.
		 *
		 * @param int $id voucher id
		 */

		public static function used_voucher($id)
		{
			global $wpdb;

			$wpdb->update(
				"{$wpdb->prefix}giftvouchers_list",
				array('status' => 'used'),
				array('id' => $id)
			);

			$amount = $wpdb->get_var($wpdb->prepare("SELECT amount FROM `{$wpdb->prefix}giftvouchers_list` WHERE `id` = %d", $id));
			WPGV_Gift_Voucher_Activity::record($id, 'transaction', '-' . $amount, 'Voucher used completely.');
		}

		/**
		 * Set as paid a voucher record.
		 *
		 * @param int $id voucher id
		 */

		public static function paid_voucher($id)
		{
			global $wpdb;
			$wpdb->update(
				$wpdb->prefix . 'giftvouchers_list',
				array('payment_status' => 'Paid'),
				array('id' => $id)
			);
			$results = $wpdb->get_results($wpdb->prepare("SELECT * FROM `{$wpdb->prefix}giftvouchers_list` WHERE `id` = %d", $id));
			if ($results) {
				$result = $results[0];
				WPGV_Gift_Voucher_Activity::record($id, 'transaction', $result->amount, 'Voucher payment received.');
			}
		}

		public static function send_mail($id)
		{
			global $wpdb;
			$setting_table 	= $wpdb->prefix . 'giftvouchers_setting';
			$setting_options = $wpdb->get_row(
				$wpdb->prepare("SELECT * FROM {$wpdb->prefix}%s WHERE id = %d", 'giftvouchers_settings', 1)
			);

			$result = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}giftvouchers_list WHERE id = %d", $id));

			$upload = wp_upload_dir();
			$upload_dir = $upload['basedir'];
			$attachments[0] = wpgv_get_voucher_pdf_path($result->voucherpdf_link);
			$headers = 'Content-type: text/html;charset=utf-8' . "\r\n";
			$headers .= 'From: ' . $setting_options->sender_name . ' <' . $setting_options->sender_email . '>' . "\r\n";
			$headers .= 'Reply-to: ' . $setting_options->sender_name . ' <' . $setting_options->sender_email . '>' . "\r\n";

			$emailsubject = get_option('wpgv_emailsubject') ? get_option('wpgv_emailsubject') : 'Order Confirmation - Your Order with {company_name} (Voucher Order No: {order_number} ) has been successfully placed!';
			if (isset($_GET['per_invoice']) && absint($_GET['per_invoice']) == 1) {
				$emailbody = get_option('wpgv_emailbodyperinvoice') ? get_option('wpgv_emailbodyperinvoice') : '<p>Dear <strong>{customer_name}</strong>,</p><p>Order successfully placed.</p><p>We are pleased to confirm your order no {order_number}</p><p>Thank you for shopping with <strong>{company_name}</strong>!</p><p>You can download the voucher from {pdf_link}.</p><p>You will pay us directly into bank. Our bank details are below:</p><p><strong>Account Number: </strong>XXXXXXXXXXXX<br /><strong>Bank Code: </strong>XXXXXXXX</p><p>- For any clarifications please feel free to email us at {sender_email}.</p><p><strong>Warm Regards, <br /></strong> <strong>{company_name}<br />{website_url}</strong></p>';
			} else {
				$emailbody = get_option('wpgv_emailbody') ? get_option('wpgv_emailbody') : '<p>Dear <strong>{customer_name}</strong>,</p><p>Order successfully placed.</p><p>We are pleased to confirm your order no {order_number}</p><p>Thank you for shopping with <strong>{company_name}</strong>!</p><p>You can download the voucher from {pdf_link}.</p><p>- For any clarifications please feel free to email us at {sender_email}.</p><p><strong>Warm Regards, <br /></strong> <strong>{company_name}<br />{website_url}</strong></p>';
			}

			$recipientemailsubject = get_option('wpgv_recipientemailsubject') ? get_option('wpgv_recipientemailsubject') : 'Gift Voucher - Your have received voucher from {company_name}';
			$recipientemailbody = get_option('wpgv_recipientemailbody') ? get_option('wpgv_recipientemailbody') : '<p>Dear <strong>{recipient_name}</strong>,</p><p>You have received gift voucher from <strong>{customer_name}</strong>.</p><p>You can download the voucher from {pdf_link}.</p><p>- For any clarifications please feel free to email us at {sender_email}.</p><p><strong>Warm Regards, <br /></strong> <strong>{company_name}<br />{website_url}</strong></p>';

			$email = ($result->shipping_email != '') ? $result->shipping_email : $result->email;

			$emailto = $result->from_name . '<' . $email . '>';
			$recipientemailsubject = wpgv_mailvarstr($recipientemailsubject, $setting_options, $result);
			$recipientemailbody = wpgv_mailvarstr($recipientemailbody, $setting_options, $result);

			$recipientmail_sent = wp_mail($emailto, $recipientemailsubject, $recipientemailbody, $headers, $attachments);

			$attachments[1] = wpgv_get_voucher_pdf_path($voucher_options->voucherpdf_link, '-receipt');

			/* Buyer Mail */
			$buyersub = wpgv_mailvarstr($emailsubject, $setting_options, $result);
			$buyermsg = wpgv_mailvarstr($emailbody, $setting_options, $result);
			$buyerto = $result->from_name . '<' . $result->email . '>';
			$mail_sent = wp_mail($buyerto, $buyersub, $buyermsg, $headers, $attachments);
		}

		/**
		 * Delete a voucher record.
		 *
		 * @param int $id voucher id
		 */
		public static function delete_voucher($id)
		{
			global $wpdb;

			$wpdb->delete(
				"{$wpdb->prefix}giftvouchers_list",
				array('id' => $id),
				array('%d')
			);
		}

		/**
		 * Returns the count of records in the database.
		 *
		 * @return null|string
		 */
		public static function record_count()
		{
			global $wpdb;

			$page         = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
			$search       = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
			$voucher_code = isset($_GET['voucher_code']) ? sanitize_text_field(wp_unslash($_GET['voucher_code'])) : '';
			$search_email = '';
			$search_code_like = '';
			$search_email_like = '';

			if ($voucher_code !== '') {
				$search_code_like = '%' . $wpdb->esc_like($voucher_code) . '%';

				if (filter_var($voucher_code, FILTER_VALIDATE_EMAIL)) {
					$search_email = $voucher_code;
					$search_email_like = '%' . $wpdb->esc_like($voucher_code) . '%';
				}
			}

			$order_type = self::get_current_order_type_filter();
			$where_clause = $wpdb->prepare(" WHERE `order_type` = %s ", $order_type);

			if ($page === 'vouchers-lists' && $search && $voucher_code !== '') {
				$where_clause .= $wpdb->prepare(
					" AND (`couponcode` LIKE %s OR `email` LIKE %s OR `shipping_email` LIKE %s) ",
					$search_code_like,
					$search_email_like,
					$search_email_like
				);
			} elseif ($page === 'redeem-voucher' && $voucher_code !== '') {
				$where_clause .= $wpdb->prepare(
					" AND (`couponcode` = %s OR `email` LIKE %s OR `shipping_email` LIKE %s) ",
					$voucher_code,
					$search_email,
					$search_email
				);
			}

			$result = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}giftvouchers_list {$where_clause}");

			return $result;
		}



		/** Text displayed when no voucher data is available */
		public function no_items()
		{
			if (self::is_items_view()) {
				esc_html_e('No purchased gift items yet.', 'gift-voucher');
				return;
			}

			if (self::is_woocommerce_view()) {
				esc_html_e('No WooCommerce gift voucher orders yet.', 'gift-voucher');
				return;
			}

			esc_html_e('No purchased voucher codes yet.', 'gift-voucher');
		}

		/**
		 * Render a column when no column specific method exist.
		 *
		 * @param array $item
		 * @param string $column_id
		 *
		 * @return mixed
		 */
		public function column_default($item, $column_id)
		{
			switch ($column_id) {
				case 'couponcode':
				case 'voucheradd_time':
				case 'voucher_info':
					return $item[$column_id];
				case 'buyer_info':
					return $item[$column_id];
				case 'mark_used':
					return $item[$column_id];
				case 'receipt':
					return $item[$column_id];
				default:
					return print_r($item, true); //Show the whole array for troubleshooting purposes
			}
		}

		/**
		 *  Associative array of columns
		 *
		 * @return array
		 */
		function get_columns()
		{
			$columns = array(
				'cb'              => '<input type="checkbox" />',
				'id'              => esc_html__('Order id', 'gift-voucher'),
				'couponcode'      => esc_html__('Voucher Code', 'gift-voucher'),
				'voucher_info'    => esc_html__('Voucher Information', 'gift-voucher'),
				'buyer_info'      => esc_html__('Buyer\'s Information', 'gift-voucher'),
				'action'          => esc_html__('Action', 'gift-voucher'),
				'receipt'         => esc_html__('Voucher', 'gift-voucher'),
				'voucheradd_time' => esc_html__('Order Date', 'gift-voucher'),
			);

			return $columns;
		}


		/**
		 * Render the bulk used checkbox
		 *
		 * @param array $item
		 *
		 * @return string
		 */
		function column_cb($item)
		{
			return sprintf(
				'<input type="checkbox" name="voucher_code[]" value="%s" />',
				sanitize_text_field(esc_attr($item['id']))
			);
		}

		/**
		 * Method for name column
		 *
		 * @param array $item an array of DB data
		 *
		 * @return string
		 */
		function column_id($item)
		{
			$gift_voucher = new WPGV_Gift_Voucher($item['couponcode']);
			$title = '<strong>' . esc_attr($item['id']) . '</strong>';
			$delete = wp_create_nonce('delete_voucher');
			$form = '';
			$actions = [
				'order_detail' => sprintf(
					'<a href="%s">%s</a>',
					esc_url(self::get_admin_page_url('view-voucher-details', array(
						'action' => 'view_voucher',
						'voucher_id' => absint($item['id']),
					))),
					esc_html(__('View Details', 'gift-voucher'))
				),
				'delete' => sprintf(
					'<a class="" href="%s">%s</a>',
					esc_url(self::get_admin_page_url('vouchers-lists', array(
						'action' => 'delete',
						'voucher' => absint($item['id']),
						'_wpdelete' => $delete,
					))),
					esc_html(__('Delete Voucher', 'gift-voucher'))
				),
			];
			$actions = $this->row_actions($actions);
			$arr = array(
				'div' => array(
					'class' => array(),
				),
				'span' => array(
					'class' => array(),
				),
				'a' => array(
					'href' => array(),
				),
				'strong' => array(),
			);
			// echo $form;
			// echo wp_kses_post_post($actions, $arr);
			return $title . wp_kses_post($actions, $arr) . $form;
		}

		/**
		 * Method for name column
		 *
		 * @param array $item an array of DB data
		 *
		 * @return string
		 */
		function column_couponcode($item)
		{
			$gift_voucher = new WPGV_Gift_Voucher($item['couponcode']);
			$couponcode = '<strong>' . esc_attr($item['couponcode']) . '</strong>';
			$remainingbalance = '';

			if ($item['payment_status'] == 'Paid') {
				$remainingbalance = __('Remaining Balance:', 'gift-voucher') . ' ' . wpgv_price_format($gift_voucher->get_balance());
			}

			return $couponcode . $remainingbalance;
		}

		/**
		 * Method for voucher information
		 *
		 * @param array $item an array of DB data
		 *
		 * @return string
		 */
		function column_voucher_info($item)
		{
			global $wpdb;
			$table_name = $wpdb->prefix . 'giftvouchers_setting';
			$options = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", 1));
?>
			<table style="width: 100%;">
				<tr>
					<th width="40%;" style="font-weight:bold;"><?php echo esc_html__('Buying For', 'gift-voucher'); ?>:</th>
					<td width="60%;"><?php echo ($item['buying_for'] == 'yourself') ? esc_html__('Yourself', 'gift-voucher') : esc_html__('Someone Else', 'gift-voucher'); ?></td>
				</tr>
				<tr>
					<th width="40%;" style="font-weight:bold;"><?php echo esc_html__('Buyer Name', 'gift-voucher'); ?>:</th>
					<td width="60%;"><?php echo esc_html($item['from_name']); ?></td>
				</tr>
				<?php if ($item['buying_for'] != 'yourself') { ?>
					<tr>
						<th width="40%;" style="font-weight:bold;"><?php echo esc_html__('Recipient Name', 'gift-voucher'); ?>:</th>
						<td width="60%;"><?php echo esc_html($item['to_name']); ?></td>
					</tr>
				<?php } ?>
				<tr>
					<th width="22%;" style="font-weight:bold;"><?php echo esc_html__('Voucher Value', 'gift-voucher'); ?>:</th>
					<td width="60%;"><?php echo esc_html(wpgv_price_format($item['amount'])); ?></td>
				</tr>
				<tr>
					<th width="22%;" style="font-weight:bold;"><?php echo esc_html__('Total Payable Amount', 'gift-voucher'); ?>:</th>
					<td width="77%;"><?php echo esc_html(get_post_meta($item['id'], 'wpgv_total_payable_amount', true)); ?></td>
				</tr>
				<tr>
					<th width="22%;" style="font-weight:bold;"><?php echo esc_html__('Message', 'gift-voucher'); ?>:</th>
					<td width="77%;"><?php echo esc_html($item['message']); ?></td>
				</tr>
			</table>
		<?php
		}



		/**
		 * Method for buyer information
		 *
		 * @param array $item an array of DB data
		 *
		 * @return string
		 */
		function column_buyer_info($item)
		{
		?>
			<table style="width: 100%;">
				<tr>
					<th width="45%;" style="font-weight:bold;"><?php echo esc_html__('Shipping', 'gift-voucher') ?>:</th>
					<td width="55%;"><?php echo ($item['shipping_type'] == 'shipping_as_email') ? esc_html__('Shipping as Email', 'gift-voucher') : esc_html__('Shipping as Post', 'gift-voucher'); ?></td>
				</tr>
				<?php if ($item['shipping_type'] == 'shipping_as_email') : ?>
					<tr>
						<th style="font-weight:bold;"><?php echo esc_html__('Recipient Email', 'gift-voucher') ?>:</th>
						<td><?php echo esc_html($item['shipping_email']); ?></td>
					</tr>
					<tr>
						<th style="font-weight:bold;"><?php echo esc_html__('Buyer Email', 'gift-voucher') ?>:</th>
						<td><?php echo esc_html($item['email']); ?></td>
					</tr>
				<?php else : ?>
					<tr>
						<th style="font-weight:bold;"><?php echo esc_html__('Name', 'gift-voucher') ?>:</th>
						<td><?php echo esc_html($item['firstname']) . ' ' . esc_html($item['lastname']); ?></td>
					</tr>
					<tr>
						<th style="font-weight:bold;"><?php echo esc_html__('Buyer Email', 'gift-voucher') ?>:</th>
						<td><?php echo esc_html($item['email']); ?></td>
					</tr>
					<tr>
						<th style="font-weight:bold;"><?php echo esc_html__('Address', 'gift-voucher') ?>:</th>
						<td><?php echo esc_html($item['address']); ?></td>
					</tr>
					<tr>
						<th style="font-weight:bold;"><?php echo esc_html__('Postcode', 'gift-voucher') ?>:</th>
						<td><?php echo esc_html($item['postcode']); ?></td>
					</tr>
					<tr>
						<th style="font-weight:bold;"><?php echo esc_html__('Shipping Method', 'gift-voucher') ?>:</th>
						<td><?php echo esc_html($item['shipping_method']); ?></td>
					</tr>
				<?php endif; ?>
				<tr>
					<th style="font-weight:bold;"><?php echo esc_html__('Payment Method', 'gift-voucher') ?>:</th>
					<td><?php echo esc_html($item['pay_method']); ?></td>
				</tr>
				<?php if ($item['pay_method'] == 'Stripe' && esc_html(get_post_meta($item['id'], 'wpgv_stripe_session_key', true))) { ?>
					<tr>
						<th style="font-weight:bold;"><?php echo esc_html__('Stripe Session ID', 'gift-voucher') ?>:</th>
						<td><span style="width: 150px; display: block; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;" title="<?php echo esc_html(get_post_meta($item['id'], 'wpgv_stripe_session_key', true)) ?>"><?php echo esc_html(get_post_meta($item['id'], 'wpgv_stripe_session_key', true)) ?></span></td>
					</tr>
					<tr>
						<th style="font-weight:bold;"><?php echo esc_html__('Stripe Publishable Key', 'gift-voucher') ?>:</th>
						<td><span style="width: 150px; display: block; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;" title="<?php echo esc_html(get_post_meta($item['id'], 'wpgv_stripe_mode_for_transaction', true)) ?>"><?php echo esc_html(get_post_meta($item['id'], 'wpgv_stripe_mode_for_transaction', true)) ?></span></td>
					</tr>
				<?php } elseif ($item['pay_method'] == 'Paypal' && esc_html(get_post_meta($item['id'], 'wpgv_paypal_payment_key', true))) { ?>
					<tr>
						<th style="font-weight:bold;"><?php echo esc_html__('PayPal PaymentID', 'gift-voucher') ?>:</th>
						<td><span style="width: 150px; display: block; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;" title="<?php echo esc_html(get_post_meta($item['id'], 'wpgv_paypal_payment_key', true)) ?>"><?php echo esc_html(get_post_meta($item['id'], 'wpgv_paypal_payment_key', true)) ?></span></td>
					</tr>
					<tr>
						<th style="font-weight:bold;"><?php echo esc_html__('PayPal Mode', 'gift-voucher') ?>:</th>
						<td><span style="width: 150px; display: block; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;" title="<?php echo esc_html(get_post_meta($item['id'], 'wpgv_paypal_mode_for_transaction', true)) ?>"><?php echo esc_html(get_post_meta($item['id'], 'wpgv_paypal_mode_for_transaction', true)) ?></span></td>
					</tr>
				<?php } ?>
				<tr>
					<th style="font-weight:bold;"><?php echo esc_html__('Payment Status', 'gift-voucher') ?>:</th>
					<td><?php echo esc_html($item['payment_status']); ?></td>
				</tr>
				<tr>
					<th style="font-weight:bold;"><?php echo esc_html__('Expiry', 'gift-voucher') ?>:</th>
					<td><abbr title="<?php echo esc_attr($item['expiry']); ?>"><?php echo esc_html($item['expiry']); ?></abbr></td>
				</tr>
			</table>
		<?php
		}


		/**
		 * Method for mark as used link
		 *
		 * @param array $item an array of DB data
		 *
		 * @return string
		 */
		function column_action($item)
		{

			$used = wp_create_nonce('used_voucher');
			if ($item['status'] == 'unused') {
				$actions = array(
					'used' => sprintf(
						'<a class="used" href="%s">%s</a>',
						esc_url(self::get_admin_page_url('vouchers-lists', array(
							'action' => 'used',
							'voucher' => absint($item['id']),
							'_wpdelete' => $used,
						))),
						esc_html(__('Mark as Used', 'gift-voucher'))
					),
				);
				$mark_used = $this->row_actions($actions, true);
			} else {
				$mark_used = '<span class="vused">' . __('Voucher Used', 'gift-voucher') . '</span>';
			}
			$paid_nonce = wp_create_nonce('mail_voucher');
			$paid = wp_create_nonce('paid_voucher');
			if ($item['payment_status'] != 'Paid') {
				$actions = array(
					'paid' => sprintf(
						'<a class="paid" href="%s">%s</a>',
						esc_url(self::get_admin_page_url('vouchers-lists', array(
							'action' => 'paid',
							'voucher' => absint($item['id']),
							'_wpdelete' => $paid,
						))),
						esc_html(__('Mark as Paid', 'gift-voucher'))
					)
				);
				$mark_paid = $this->row_actions($actions, true);
				$send_mail = '';
			} else {
				$mark_paid = '<span class="vpaid">' . __('Paid', 'gift-voucher') . '</span>';
				$actions = array(
					'paid' => sprintf(
						'<a href="?page=%s&action=%s&voucher=%s&_wpdelete=%s">%s</a>',
						esc_attr($_REQUEST['page']) . (self::is_items_view() ? '&items=1' : (self::is_woocommerce_view() ? '&woocommerce=1' : '')),
						'mail',
						absint($item['id']),
						$paid_nonce, // Nonce đúng action
						__('Send Mail', 'gift-voucher')
					)
				);
				$send_mail = $this->row_actions($actions, true);
			}

			// Regenerate PDF button (modern and standard orders)
			$regenerate_pdf_btn = '';
			$template_info = array('kind' => 'unknown', 'template_id' => 0);
			if (function_exists('wpgv_get_voucher_template_kind')) {
				$template_info = wpgv_get_voucher_template_kind((object) $item);
			}
			$is_modern_order = ($template_info['kind'] === 'modern');
			$is_standard_order = in_array($template_info['kind'], array('standard_list', 'standard_grid'), true);

			if ($item['payment_status'] === 'Paid' && $is_modern_order) {
				$regen_nonce = wp_create_nonce('wpgv_regen_modern_pdf_' . $item['id']);
				$regenerate_pdf_btn = '<div style="margin-top:4px;">'
					. '<button type="button" class="button button-small wpgv-regen-pdf-btn" '
					. 'data-voucher-id="' . absint($item['id']) . '" '
					. 'data-nonce="' . esc_attr($regen_nonce) . '" '
					. 'style="background:#f7b600;color:#000;border-radius:unset;border:none;">'
					. esc_html__('Regenerate PDF', 'gift-voucher')
					. '</button>'
					. '<span class="wpgv-regen-pdf-msg" style="display:none;margin-left:6px;font-size:12px;"></span>'
					. '</div>';
			} elseif ($item['payment_status'] === 'Paid' && $is_standard_order) {
				$regen_nonce = wp_create_nonce('wpgv_regen_standard_pdf_' . $item['id']);
				$regenerate_pdf_btn = '<div style="margin-top:4px;">'
					. '<button type="button" class="button button-small wpgv-regen-standard-pdf-btn" '
					. 'data-voucher-id="' . absint($item['id']) . '" '
					. 'data-nonce="' . esc_attr($regen_nonce) . '" '
					. 'style="background:#f7b600;color:#000;border-radius:unset;border:none;">'
					. esc_html__('Regenerate PDF', 'gift-voucher')
					. '</button>'
					. '<span class="wpgv-regen-standard-pdf-msg" style="display:none;margin-left:6px;font-size:12px;"></span>'
					. '</div>';
			}

			$arr = array(
				'div' => array(
					'class' => array(),
					'style' => array(),
				),
				'span' => array(
					'class' => array(),
					'style' => array(),
				),
				'a' => array(
					'href' => array(),
					'class' => array(),
				),
				'button' => array(
					'type' => array(),
					'class' => array(),
					'data-voucher-id' => array(),
					'data-nonce' => array(),
					'style' => array(),
				),
				'strong' => array(),
			);
			return wp_kses_post($mark_used, $arr) . wp_kses_post($send_mail, $arr) . wp_kses_post($mark_paid, $arr) . wp_kses_post($regenerate_pdf_btn, $arr);
		}

		/**
		 * Method for create receipt
		 *
		 * @param array $item an array of DB data
		 *
		 * @return string
		 */
		function column_voucheradd_time($item)
		{
		?>
			<abbr title="<?php echo esc_attr(gmdate('Y/m/d H:i:s a', strtotime($item['voucheradd_time']))); ?>">
				<?php echo esc_html(gmdate('Y/m/d', strtotime($item['voucheradd_time']))); ?>
			</abbr>

			<?php
		}


		/**
		 * Method for create receipt
		 *
		 * @param array $item an array of DB data
		 *
		 * @return string
		 */
		function column_receipt($item)
		{
			$pdf_url = wpgv_get_voucher_pdf_url(isset($item['voucherpdf_link']) ? $item['voucherpdf_link'] : '');
			if ($pdf_url === '') {
				return '';
			}

			$voucher = '<a href="' . esc_url($pdf_url) . '" title="click to show voucher" target="_blank" rel="noopener noreferrer"><img src="' . esc_url(WPGIFT__PLUGIN_URL . '/assets/img/pdf.png') . '"></a>';

			if ($item['payment_status'] == 'Paid') {
				$receipt_url = wpgv_get_voucher_pdf_url(isset($item['voucherpdf_link']) ? $item['voucherpdf_link'] : '', '-receipt');
				if ($receipt_url !== '') {
					$voucher .= '<br><a href="' . esc_url($receipt_url) . '" title="click to show order receipt" target="_blank" rel="noopener noreferrer"><img src="' . esc_url(WPGIFT__PLUGIN_URL . '/assets/img/pdf.png') . '"></a>';
				}
			}
			return $voucher;
		}

		/**
		 * Returns an associative array containing the bulk action
		 *
		 * @return array
		 */
		public function get_bulk_actions()
		{
			$actions = array(
				'bulk-used' => __('Mark as Used', 'gift-voucher'),
				'bulk-paid' => __('Mark as Paid', 'gift-voucher'),
				'bulk-delete' => __('Delete', 'gift-voucher'),
			);

			return $actions;
		}

		public static function record_count_giftvouchers_list()
		{
			return self::record_count();
		}

		/**
		 * Handles data query and filter, sorting, and pagination.
		 */
		public function prepare_items()
		{
			$this->_column_headers = $this->get_column_info();

			/** Process bulk action */
			$this->process_bulk_action();

			$per_page     = $this->get_items_per_page('vouchers_per_page', 20);
			$current_page = $this->get_pagenum();
			$total_items  = self::record_count_giftvouchers_list();

			$this->set_pagination_args(array(
				'total_items' => $total_items, 	//WE have to calculate the total number of items
				'per_page'    => $per_page 		//WE have to determine how many items to show on a page
			));

			$this->items = self::get_vouchers($per_page, $current_page);
		}

		/**
		 * Handles data for mark as used the bulk action
		 */
		public function process_bulk_action()
		{
			$action = $this->current_action();

			if (in_array($action, ['bulk-used', 'bulk-paid', 'bulk-delete'], true)) {
				if (!empty($_REQUEST['voucher_code']) && is_array($_REQUEST['voucher_code'])) {
					$voucher_codes = array_map('absint', wp_unslash($_REQUEST['voucher_code']));
					foreach ($voucher_codes as $voucher_code) {
						if ('bulk-used' === $action) {
							self::used_voucher($voucher_code);
						} elseif ('bulk-paid' === $action) {
							self::paid_voucher($voucher_code);
						} elseif ('bulk-delete' === $action) {
							self::delete_voucher($voucher_code);
						}
					}
				}
				wp_safe_redirect(esc_url_raw(self::get_admin_page_url('vouchers-lists')));
				exit;
			}
			if (in_array($action, ['used', 'paid', 'mail', 'delete'], true)) {

				if (!isset($_REQUEST['_wpdelete']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['_wpdelete'])), "{$action}_voucher")) {
					wp_die(esc_html__('Invalid request.', 'gift-voucher'));
				}

				$voucher_id = isset($_GET['voucher']) ? absint(wp_unslash($_GET['voucher'])) : 0;
				if ($voucher_id > 0) {
					if ('used' === $action) {
						self::used_voucher($voucher_id);
					} elseif ('paid' === $action) {
						self::paid_voucher($voucher_id);
					} elseif ('mail' === $action) {
						self::send_mail($voucher_id);
					} elseif ('delete' === $action) {
						self::delete_voucher($voucher_id);
					}
				}

				wp_safe_redirect(esc_url_raw(self::get_admin_page_url('vouchers-lists')));
				exit;
			}

			if ('order_detail' === $action) {
				$order_id = isset($_REQUEST['order_id']) ? absint($_REQUEST['order_id']) : 0;

				if ($order_id > 0) {
					global $wpdb;
					$voucher_table_name = $wpdb->prefix . 'giftvouchers_list';
					$order_detail = $wpdb->get_row($wpdb->prepare("SELECT * FROM $voucher_table_name WHERE id = %d", $order_id));

					if ($order_detail) {
			?>
						<div class="admin-modal">
							<div class="admin-custom-modal add-new">
								<span class="close dashicons dashicons-no-alt"></span>
								<h3><?php echo esc_html__('Order Details', 'gift-voucher'); ?> (Order ID: <?php echo esc_attr($order_id); ?>)
									<?php if ($order_detail->status === "unused") : ?>
										<strong style='color:#fff;font-size:14px;background:#ddd;padding:2px 5px;'>Unused</strong>
									<?php elseif ($order_detail->status === "used") : ?>
										<strong style='color:#fff;font-size:14px;display: inline-block;background:#233dcc;padding:2px 5px;'>Used</strong>
									<?php endif; ?>
								</h3>
							</div>
						</div>
<?php
					}
				}
			}
		}
	}

endif;
