<?php

if (!defined('ABSPATH')) exit;  // Exit if accessed directly

use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;

// Function for Voucher Payment Successful Shortcode
function wpgv_voucher_successful_shortcode()
{
	global $wpdb;
	$return = '';

	$voucher_table 	= $wpdb->prefix . 'giftvouchers_list';
	$setting_table 	= $wpdb->prefix . 'giftvouchers_setting';
	$setting_options = $wpdb->get_row($wpdb->prepare("SELECT * FROM $setting_table WHERE id = %d", 1));
	if (isset($_GET['voucheritem'])) {
		$voucheritem = absint($_GET['voucheritem']);
		$voucher_options = $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM $voucher_table WHERE id = %d", $voucheritem)
		);
		$check_send_mail = $voucher_options->check_send_mail;

		if ((strtotime($voucher_options->voucheradd_time) + 3600) < strtotime(current_time('mysql'))) {
			return '<div class="error"><p>' . esc_html_e('This URL is invalid. You can not access this page directly.', 'gift-voucher') . '</p></div>';
		}
		if (isset($_GET['per_invoice']) && absint($_GET['per_invoice']) == 1) {
		} else {

			$voucherrow = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}giftvouchers_list WHERE id = %d AND pay_method <> 'Per Invoice'",
					$voucheritem
				)
			);

			if ($voucherrow) {
				$wpdb->update(
					$voucher_table,
					array(
						'payment_status' 	=> 'Paid',
						'voucheradd_time'	=> current_time('mysql')
					),
					array('id' => $voucheritem),
					array(
						'%s'
					),
					array('%d')
				);
				if (isset($_GET['token']) && isset($_GET['PayerID']) && $voucherrow->payment_status != 'Paid') {
					require_once(WPGIFT__PLUGIN_DIR . '/vendor/autoload.php');
					require_once(WPGIFT__PLUGIN_DIR . '/include/PayPalAuth.php');
					session_start();
					$client = PayPalAuth::client();
					// Here, OrdersCaptureRequest() creates a POST request to /v2/checkout/orders
					// $response->result->id gives the orderId of the order created above
					$request = new OrdersCaptureRequest($_SESSION["paypal_order_id"]);
					$request->prefer('return=representation');
					$result_getId = null;
					try {
						// Call API with your client and get a response for your call

						$response = $client->execute($request);

						$result_getId = $response->result->id;
					} catch (HttpException $ex) {

						echo esc_html($ex->statusCode);
						print_r($ex->getMessage());
					}

					update_post_meta($voucheritem, 'wpgv_paypal_payment_key', $result_getId, true);
					update_post_meta($voucheritem, 'wpgv_paypal_mode_for_transaction', (!$setting_options->test_mode) ? 'Livemode' : 'Testmode', true);
				}
				WPGV_Gift_Voucher_Activity::record($voucheritem, 'firsttransact', $voucherrow->amount, 'Voucher payment recieved.');
			}
		}

		$customer_receipt = (get_option('wpgv_customer_receipt') != '') ? get_option('wpgv_customer_receipt') : 0;

		if (isset($_GET['per_invoice']) && absint($_GET['per_invoice']) == 1 && $customer_receipt == 0) {
			// Mail not send

			$upload = wp_upload_dir();
			$upload_dir = $upload['basedir'];
			$attachments[0] = $upload_dir . '/voucherpdfuploads/' . $voucher_options->voucherpdf_link . '.pdf';
			$attachments[1] = $upload_dir . '/voucherpdfuploads/' . $voucher_options->voucherpdf_link . '-receipt.pdf';

			$adminemailsubject = get_option('wpgv_adminemailsubject') ? get_option('wpgv_adminemailsubject') : 'New Voucher Order Received from {customer_name}  (Order No: {order_number})!';
			$adminemailbody = get_option('wpgv_adminemailbody') ? get_option('wpgv_adminemailbody') : '<p>Hello, New Voucher Order received.</p><p><strong>Order Id:</strong> {order_number}</p><p><strong>Name:</strong> {customer_name}<br /><strong>Email:</strong> {customer_email}<br /><strong>Address:</strong> {customer_address}<br /><strong>Postcode:</strong> {customer_postcode}</p>';

			$toadmin = $setting_options->sender_name . ' <' . $setting_options->sender_email . '>';
			$subadmin = wpgv_mailvarstr($adminemailsubject, $setting_options, $voucher_options);
			$bodyadmin = wpgv_mailvarstr($adminemailbody, $setting_options, $voucher_options);
			$headersadmin = 'Content-type: text/html;charset=utf-8' . "\r\n";
			$headersadmin .= 'From: ' . $setting_options->sender_name . ' <' . $setting_options->sender_email . '>' . "\r\n";
			$headersadmin .= 'Reply-to: ' . $voucher_options->from_name . ' <' . $voucher_options->email . '>' . "\r\n";

			if ($check_send_mail === 'unsent') {
				wp_mail($toadmin, $subadmin, $bodyadmin, $headersadmin, $attachments);
			}

			$successpagemessage = get_option('wpgv_successpagemessage') ? get_option('wpgv_successpagemessage') : 'We have got your order! <br>Please complete payment process and contact us for further details';
			$return .= '<div class="success">' . sprintf(stripslashes($successpagemessage), $voucher_options->email) . '</div>';

			if ($setting_options->bank_info != '') {
				$return .= $setting_options->bank_info;
			}
		} else {

			$emailsubject = get_option('wpgv_emailsubject') ? get_option('wpgv_emailsubject') : 'Order Confirmation - Your Order with {company_name} (Voucher Order No: {order_number} ) has been successfully placed!';
			$recipientemailsubject = get_option('wpgv_recipientemailsubject') ? get_option('wpgv_recipientemailsubject') : 'Gift Voucher - Your have received voucher from {company_name}';
			$recipientemailbody = get_option('wpgv_recipientemailbody') ? get_option('wpgv_recipientemailbody') : '<p>Dear <strong>{recipient_name}</strong>,</p><p>You have received gift voucher from <strong>{customer_name}</strong>.</p><p>You can download the voucher from {pdf_link}.</p><p>- For any clarifications please feel free to email us at {sender_email}.</p><p><strong>Warm Regards, <br /></strong> <strong>{company_name}<br />{website_url}</strong></p>';
			if (isset($_GET['per_invoice']) && absint($_GET['per_invoice']) == 1) {
				$emailbody = get_option('wpgv_emailbodyperinvoice') ? get_option('wpgv_emailbodyperinvoice') : '<p>Dear <strong>{customer_name}</strong>,</p><p>Order successfully placed.</p><p>We are pleased to confirm your order no {order_number}</p><p>Thank you for shopping with <strong>{company_name}</strong>!</p><p>You can download the voucher from {pdf_link}.</p><p>You will pay us directly into bank. Our bank details are below:</p><p><strong>Account Number: </strong>XXXXXXXXXXXX<br /><strong>Bank Code: </strong>XXXXXXXX</p><p>- For any clarifications please feel free to email us at {sender_email}.</p><p><strong>Warm Regards, <br /></strong> <strong>{company_name}<br />{website_url}</strong></p>';
			} else {
				$emailbody = get_option('wpgv_emailbody') ? get_option('wpgv_emailbody') : '<p>Dear <strong>{customer_name}</strong>,</p><p>Order successfully placed.</p><p>We are pleased to confirm your order no {order_number}</p><p>Thank you for shopping with <strong>{company_name}</strong>!</p><p>You can download the voucher from {pdf_link}.</p><p>- For any clarifications please feel free to email us at {sender_email}.</p><p><strong>Warm Regards, <br /></strong> <strong>{company_name}<br />{website_url}</strong></p>';
			}

			$adminemailsubject = get_option('wpgv_adminemailsubject') ? get_option('wpgv_adminemailsubject') : 'New Voucher Order Received from {customer_name}  (Order No: {order_number})!';
			$adminemailbody = get_option('wpgv_adminemailbody') ? get_option('wpgv_adminemailbody') : '<p>Hello, New Voucher Order received.</p><p><strong>Order Id:</strong> {order_number}</p><p><strong>Name:</strong> {customer_name}<br /><strong>Email:</strong> {customer_email}<br /><strong>Address:</strong> {customer_address}<br /><strong>Postcode:</strong> {customer_postcode}</p>';

			$upload = wp_upload_dir();
			$upload_dir = $upload['basedir'];
			$attachments[0] = $upload_dir . '/voucherpdfuploads/' . $voucher_options->voucherpdf_link . '.pdf';
			$headers = 'Content-type: text/html;charset=utf-8' . "\r\n";
			$headers .= 'From: ' . $setting_options->sender_name . ' <' . $setting_options->sender_email . '>' . "\r\n";
			$headers .= 'Reply-to: ' . $setting_options->sender_name . ' <' . $setting_options->sender_email . '>' . "\r\n";

			/* Recipient Mail */
			if ($voucher_options->shipping_type != 'shipping_as_post') {
				$recipientsub = wpgv_mailvarstr($recipientemailsubject, $setting_options, $voucher_options);
				$recipientmsg = wpgv_mailvarstr($recipientemailbody, $setting_options, $voucher_options);
				$recipientto = $voucher_options->to_name . '<' . $voucher_options->shipping_email . '>';
				if ($voucher_options->buying_for == 'yourself') {
					$recipientto = $voucher_options->from_name . '<' . $voucher_options->email . '>';
				}
				if ($check_send_mail === 'unsent') {
					wp_mail($recipientto, $recipientsub, $recipientmsg, $headers, $attachments);
				}
			}

			$attachments[1] = $upload_dir . '/voucherpdfuploads/' . $voucher_options->voucherpdf_link . '-receipt.pdf';

			/* Buyer Mail */
			$buyersub = wpgv_mailvarstr($emailsubject, $setting_options, $voucher_options);
			$buyermsg = wpgv_mailvarstr($emailbody, $setting_options, $voucher_options);
			$buyerto = $voucher_options->from_name . '<' . $voucher_options->email . '>';
			$mail_sent = false;
			if ($check_send_mail === 'unsent') {
				$mail_sent = wp_mail($buyerto, $buyersub, $buyermsg, $headers, $attachments);
			}
			$successpagemessage = get_option('wpgv_successpagemessage') ? get_option('wpgv_successpagemessage') : 'We have got your order! <br>E-Mail Sent Successfully to %s';


			if (isset($_GET['per_invoice']) && absint($_GET['per_invoice']) == 1) {
				$return .= $setting_options->bank_info;
			}
			if ($mail_sent == 1) {
				$return .= '<div class="success">' . sprintf(stripslashes($successpagemessage), $voucher_options->email) . '</div>';
				$toadmin = $setting_options->sender_name . ' <' . $setting_options->sender_email . '>';
				$subadmin = wpgv_mailvarstr($adminemailsubject, $setting_options, $voucher_options);
				$bodyadmin = wpgv_mailvarstr($adminemailbody, $setting_options, $voucher_options);
				$headersadmin = 'Content-type: text/html;charset=utf-8' . "\r\n";
				$headersadmin .= 'From: ' . $setting_options->sender_name . ' <' . $setting_options->sender_email . '>' . "\r\n";
				$headersadmin .= 'Reply-to: ' . $voucher_options->from_name . ' <' . $voucher_options->email . '>' . "\r\n";
				if ($check_send_mail === 'unsent') {
					wp_mail($toadmin, $subadmin, $bodyadmin, $headersadmin, $attachments);
					update_check_send_mail('sent');
				}
			} else {
				if ($check_send_mail === 'unsent') {
					$return .= '<div class="error"><p>' . __('Some Error Occurred From Sending this Email! <br>(Reload and Retry Again!) or Contact Us', 'gift-voucher') . '</p></div>';
				} elseif ($check_send_mail === 'sent') {
					$return .= '<div class="success">' . sprintf(stripslashes($successpagemessage), $voucher_options->email) . '</div>';
				}
			}
		}
	} else {
		return '<div class="error"><p>' . esc_html_e('This URL is invalid. You can not access this page directly.', 'gift-voucher') . '</p></div>';
	}
	return $return;
}
add_shortcode('wpgv_giftvouchersuccesspage', 'wpgv_voucher_successful_shortcode');

// Function for Voucher Payment Cancel Shortcode
function wpgv_voucher_cancel_shortcode()
{
	global $wpdb;
	$return = '';
	$voucher_table 	= $wpdb->prefix . 'giftvouchers_list';
	if (isset($_GET['voucheritem'])) {
		$cancelpagemessage = get_option('wpgv_cancelpagemessage') ? get_option('wpgv_cancelpagemessage') : 'You cancelled your order. Please place your order again from <a href="' . esc_url(get_site_url() . "/gift-voucher") . '">here</a>.';
		$voucheritem = sanitize_text_field($_GET['voucheritem']);
		$wpdb->delete($voucher_table, array('id' => $voucheritem), array('%d'));
		$return .= stripslashes($cancelpagemessage);
	}
	return $return;
}
add_shortcode('wpgv_giftvouchercancelpage', 'wpgv_voucher_cancel_shortcode');

//Function for Stripe Success Page
function wpgv_stripe_success_page_shortcode()
{
	global $wpdb;
	$voucher_table 	= $wpdb->prefix . 'giftvouchers_list';
	$setting_table 	= $wpdb->prefix . 'giftvouchers_setting';
	$setting_options = $wpdb->get_row("SELECT * FROM $setting_table WHERE id = 1");

	//check whether stripe token is not empty
	if (!empty($_GET['sessionid'])) {
		$orderid = absint($_GET['voucheritem']);

		$voucher_options = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $voucher_table WHERE id = %d",
				$orderid
			)
		);
		$check_send_mail = $voucher_options->check_send_mail;
		if ((strtotime($voucher_options->voucheradd_time) + 3600) < strtotime(current_time('mysql'))) {
			return '<div class="error"><p>' . esc_html_e('This URL is invalid. You can not access this page directly.', 'gift-voucher') . '</p></div>';
		}

		//include Stripe PHP library
		// if(!class_exists('\Stripe\Checkout\Session')) {
		// 	require_once( WPGIFT__PLUGIN_DIR .'/library/stripe-php/init.php');
		// }

		//set api key
		$stripe = array(
			"publishable_key" => $setting_options->stripe_publishable_key,
			"secret_key"      => $setting_options->stripe_secret_key,
		);

		\Stripe\Stripe::setApiKey($stripe['secret_key']);
		\Stripe\Stripe::setVerifySslCerts(false);

		$checkout_session = \Stripe\Checkout\Session::retrieve(sanitize_text_field($_GET['sessionid']));

		//retrieve charge details
		$sessionJson = $checkout_session->jsonSerialize();

		\Stripe\PaymentIntent::update(
			$sessionJson['payment_intent'],
			['metadata' => ['order_id' => $orderid]]
		);

		try {
			$payment_intent = \Stripe\PaymentIntent::retrieve($sessionJson['payment_intent']);
			if ($payment_intent->status === 'succeeded') {

				//if order inserted successfully


				$wpdb->update(
					$voucher_table,
					array(
						'payment_status' 	=> 'Paid',
						'voucheradd_time'	=> current_time('mysql')
					),
					array('id' => $orderid, 'pay_method' => 'Stripe'),
					array(
						'%s'
					),
					array('%d', '%s')
				);
				$sessionid = sanitize_text_field($_GET['sessionid']);
				update_post_meta($orderid, 'wpgv_stripe_session_key', $sessionid, true);
				update_post_meta($orderid, 'wpgv_stripe_mode_for_transaction', $setting_options->stripe_publishable_key, true);


				$voucherrow = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM `{$wpdb->prefix}giftvouchers_list` WHERE `id` = %d",
						$orderid
					)
				);
				WPGV_Gift_Voucher_Activity::record($orderid, 'firsttransact', $voucherrow->amount, 'Voucher payment recieved.');

				$emailsubject = get_option('wpgv_emailsubject') ? get_option('wpgv_emailsubject') : 'Order Confirmation - Your Order with {company_name} (Voucher Order No: {order_number} ) has been successfully placed!';
				$emailbody = get_option('wpgv_emailbody') ? get_option('wpgv_emailbody') : '<p>Dear <strong>{customer_name}</strong>,</p><p>Order successfully placed.</p><p>We are pleased to confirm your order no {order_number}</p><p>Thank you for shopping with <strong>{company_name}</strong>!</p><p>You can download the voucher from {pdf_link}.</p><p>- For any clarifications please feel free to email us at {sender_email}.</p><p><strong>Warm Regards, <br /></strong> <strong>{company_name}<br />{website_url}</strong></p>';
				$recipientemailsubject = get_option('wpgv_recipientemailsubject') ? get_option('wpgv_recipientemailsubject') : 'Gift Voucher - Your have received voucher from {company_name}';
				$recipientemailbody = get_option('wpgv_recipientemailbody') ? get_option('wpgv_recipientemailbody') : '<p>Dear <strong>{recipient_name}</strong>,</p><p>You have received gift voucher from <strong>{customer_name}</strong>.</p><p>You can download the voucher from {pdf_link}.</p><p>- For any clarifications please feel free to email us at {sender_email}.</p><p><strong>Warm Regards, <br /></strong> <strong>{company_name}<br />{website_url}</strong></p>';
				$adminemailsubject = get_option('wpgv_adminemailsubject') ? get_option('wpgv_adminemailsubject') : 'New Voucher Order Received from {customer_name}  (Order No: {order_number})!';
				$adminemailbody = get_option('wpgv_adminemailbody') ? get_option('wpgv_adminemailbody') : '<p>Hello, New Voucher Order received.</p><p><strong>Order Id:</strong> {order_number}</p><p><strong>Name:</strong> {customer_name}<br /><strong>Email:</strong> {customer_email}<br /><strong>Address:</strong> {customer_address}<br /><strong>Postcode:</strong> {customer_postcode}</p>';

				$upload = wp_upload_dir();
				$upload_dir = $upload['basedir'];
				$attachments[0] = $upload_dir . '/voucherpdfuploads/' . $voucher_options->voucherpdf_link . '.pdf';
				$headers = 'Content-type: text/html;charset=utf-8' . "\r\n";
				$headers .= 'From: ' . $setting_options->sender_name . ' <' . $setting_options->sender_email . '>' . "\r\n";
				$headers .= 'Reply-to: ' . $setting_options->sender_name . ' <' . $setting_options->sender_email . '>' . "\r\n";

				/* Recipient Mail */
				if ($voucher_options->shipping_type != 'shipping_as_post') {
					$recipientsub = wpgv_mailvarstr($recipientemailsubject, $setting_options, $voucher_options);
					$recipientmsg = wpgv_mailvarstr($recipientemailbody, $setting_options, $voucher_options);
					$recipientto = $voucher_options->to_name . '<' . $voucher_options->shipping_email . '>';
					if ($voucher_options->buying_for == 'yourself') {
						$recipientto = $voucher_options->from_name . '<' . $voucher_options->email . '>';
					}
					if ($check_send_mail === 'unsent') {
						wp_mail($recipientto, $recipientsub, $recipientmsg, $headers, $attachments);
					}
				}

				$attachments[1] = $upload_dir . '/voucherpdfuploads/' . $voucher_options->voucherpdf_link . '-receipt.pdf';

				/* Buyer Mail */
				$buyersub = wpgv_mailvarstr($emailsubject, $setting_options, $voucher_options);
				$buyermsg = wpgv_mailvarstr($emailbody, $setting_options, $voucher_options);
				$buyerto = $voucher_options->from_name . '<' . $voucher_options->email . '>';
				$mail_sent = false;
				if ($check_send_mail === 'unsent') {
					$mail_sent = wp_mail($buyerto, $buyersub, $buyermsg, $headers, $attachments);
				}
				$successpagemessage = get_option('wpgv_successpagemessage') ? get_option('wpgv_successpagemessage') : 'We have got your order! <br>E-Mail Sent Successfully to %s';

				if ($mail_sent == true) {
					$statusMsg = '<div class="success">' . sprintf(stripslashes($successpagemessage), $voucher_options->email) . '</div>';

					$toadmin = $setting_options->sender_name . ' <' . $setting_options->sender_email . '>';
					$subadmin = wpgv_mailvarstr($adminemailsubject, $setting_options, $voucher_options);
					$bodyadmin = wpgv_mailvarstr($adminemailbody, $setting_options, $voucher_options);
					$headersadmin = 'Content-type: text/html;charset=utf-8' . "\r\n";
					$headersadmin .= 'From: ' . $setting_options->sender_name . ' <' . $setting_options->sender_email . '>' . "\r\n";
					$headersadmin .= 'Reply-to: ' . $voucher_options->from_name . ' <' . $voucher_options->email . '>' . "\r\n";
					if ($check_send_mail === 'unsent') {
						wp_mail($toadmin, $subadmin, $bodyadmin, $headersadmin, $attachments);
						update_check_send_mail('sent');
					}
				} else {
					if ($check_send_mail === 'unsent') {
						$statusMsg = '<div class="error"><p>' . esc_html_e('Some Error Occurred From Sending this Email! <br>(Reload and Retry Again!) or Contact Us', 'gift-voucher') . '</p></div>';
					} elseif ($check_send_mail === 'sent') {
						$statusMsg = '<div class="success">' . sprintf(stripslashes($successpagemessage), $voucher_options->email) . '</div>';
					}
				}
			} else {
				$statusMsg = esc_html_e("Transaction has been failed", 'gift-voucher');
			}
		} catch (\Exception $e) {
			$statusMsg = esc_html_e("Transaction has been failed!", 'gift-voucher');
		}
	} else {
		$statusMsg = "Form submission error.......";
	}

	return $statusMsg;
}
add_shortcode('wpgv_stripesuccesspage', 'wpgv_stripe_success_page_shortcode');


function update_check_send_mail($new_status)
{
	global $wpdb;

	$voucher_table = $wpdb->prefix . 'giftvouchers_list';
	$wpdb->query($wpdb->prepare("UPDATE $voucher_table SET check_send_mail = %s", $new_status));
}


function wpgv_check_voucher_balance_shortcode()
{
	$voucher_code = '';
	if (isset($_REQUEST['voucher_code'])) {
		$voucher_code = sanitize_text_field($_REQUEST['voucher_code']);
	} ?>
	<form action="" method="post">
		<input type="text" name="voucher_code" autocomplete="off" placeholder="<?php esc_html_e('Search by Gift voucher code', 'gift-voucher'); ?>" value="<?php echo esc_attr($voucher_code) ?>" style="width: 400px;" required>
		<input type="submit" class="button button-primary" value="<?php esc_html_e('Check Balance', 'gift-voucher'); ?>">
	</form>
	<?php
	if ($voucher_code) {
		global $wpdb;
		$gift_voucher = new WPGV_Gift_Voucher($voucher_code);
		if ($gift_voucher->get_id()) {
	?>
			<style type="text/css">
				.wpgv-balance-activity-negative {
					color: #f00;
				}

				.wpgv-balance-activity-table {
					font-size: 14px;
				}

				.wpgv-balance-activity-table td,
				.wpgv-balance-activity-table th {
					padding: 10px;
				}
			</style>
			<h4>
				<strong><?php esc_html_e('Current Voucher Balance:', 'gift-voucher'); ?> <?php wpgv_price_format($gift_voucher->get_balance()); ?></strong>
			</h4>
			<table class="wpgv-balance-activity-table">
				<tr>
					<th><?php echo esc_html('Date', 'gift-voucher'); ?></th>
					<th><?php echo esc_html('Action', 'gift-voucher'); ?></th>
					<th><?php echo esc_html('Note', 'gift-voucher'); ?></th>
					<th><?php echo esc_html('Amount', 'gift-voucher'); ?></th>
					<th><?php echo esc_html('Balance', 'gift-voucher'); ?></th>
				</tr>
				<?php
				$running_balance = $gift_voucher->get_balance();
				foreach ($gift_voucher->get_activity() as $activity) {
				?>
					<tr>
						<td>
							<?php date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($activity->activity_date)); ?>
						</td>
						<td>
							<?php echo esc_html(ucwords($activity->action)); ?>
						</td>
						<td>
							<?php echo esc_html($activity->note); ?>
						</td>
						<td class="wpgv-balance-activity <?php echo ($activity->amount < 0) ? esc_html('wpgv-balance-activity-negative') : ''; ?>">
							<?php
							if ($activity->amount != 0) {
								echo esc_html(wpgv_price_format($activity->amount));
							}
							?>

						</td>
						<td class="wpgv-balance-activity">
							<?php echo esc_html(wpgv_price_format($running_balance)); ?>
						</td>
					</tr>
				<?php
				}
				?>
			</table>
<?php
		} else {
			echo esc_html_e('This is a invalid voucher code.', 'gift-voucher');
		}
	}
}
add_shortcode('wpgv-check-voucher-balance', 'wpgv_check_voucher_balance_shortcode');
