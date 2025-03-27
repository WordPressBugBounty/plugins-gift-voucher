<?php

if (!defined('ABSPATH')) exit;  // Exit if accessed directly

global $wpdb;
$setting_table_name = $wpdb->prefix . 'giftvouchers_setting';

if (!current_user_can('manage_options')) {
	wp_die('You are not allowed to be on this page.');
}

if (isset($_POST['submit'])) {
	// Check if our nonce is set.
	if (!isset($_POST['voucher_settings_verify_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['voucher_settings_verify_nonce'])), 'voucher_settings_verify')) {
		wp_die('Security check');
	}

	$is_woocommerce_enable = isset($_POST['is_woocommerce_enable']) ? sanitize_text_field(wp_unslash($_POST['is_woocommerce_enable'])) : '';
	$is_style_choose_enable = isset($_POST['is_style_choose_enable']) ? sanitize_text_field(wp_unslash($_POST['is_style_choose_enable'])) : '';
	$company_name = isset($_POST['company_name']) ? sanitize_text_field(wp_unslash($_POST['company_name'])) : '';
	$sofort_configure_key = isset($_POST['sofort_configure_key']) ? sanitize_text_field(wp_unslash($_POST['sofort_configure_key'])) : '';
	$reason_for_payment = isset($_POST['reason_for_payment']) ? sanitize_text_field(wp_unslash($_POST['reason_for_payment'])) : '';
	$sender_name = isset($_POST['sender_name']) ? sanitize_text_field(wp_unslash($_POST['sender_name'])) : '';
	$sender_email = isset($_POST['sender_email']) ? sanitize_email(wp_unslash($_POST['sender_email'])) : '';
	$currency_code = isset($_POST['currency_code']) ? sanitize_text_field(wp_unslash($_POST['currency_code'])) : '';
	$currency = isset($_POST['currency']) ? sanitize_text_field(wp_unslash($_POST['currency'])) : '';
	$paypal = isset($_POST['paypal']) ? sanitize_text_field(wp_unslash($_POST['paypal'])) : '';
	$sofort = isset($_POST['sofort']) ? sanitize_text_field(wp_unslash($_POST['sofort'])) : '';
	$stripe = isset($_POST['stripe']) ? sanitize_text_field(wp_unslash($_POST['stripe'])) : '';
	$paypal_client_id = isset($_POST['paypal_client_id']) ? sanitize_text_field(wp_unslash($_POST['paypal_client_id'])) : '';
	$paypal_secret_key = isset($_POST['paypal_secret_key']) ? sanitize_text_field(wp_unslash($_POST['paypal_secret_key'])) : '';
	$stripe_publishable_key = isset($_POST['stripe_publishable_key']) ? sanitize_text_field(wp_unslash($_POST['stripe_publishable_key'])) : '';
	$stripe_webhook_key = isset($_POST['stripe_webhook_key']) ? sanitize_text_field(wp_unslash($_POST['stripe_webhook_key'])) : '';
	$stripe_secret_key = isset($_POST['stripe_secret_key']) ? sanitize_text_field(wp_unslash($_POST['stripe_secret_key'])) : '';
	$voucher_bgcolor = isset($_POST['voucher_bgcolor']) ? sanitize_text_field(wp_unslash($_POST['voucher_bgcolor'])) : '';
	$voucher_brcolor = isset($_POST['voucher_brcolor']) ? sanitize_text_field(wp_unslash($_POST['voucher_brcolor'])) : '';
	$voucher_color  = isset($_POST['voucher_color']) ? sanitize_text_field(wp_unslash($_POST['voucher_color'])) : '';

	$voucher_bgcolor = ltrim($voucher_bgcolor, '#');
	$voucher_brcolor = ltrim($voucher_brcolor, '#');
	$voucher_color   = ltrim($voucher_color, '#');

	$template_col = isset($_POST['template_col']) ? sanitize_text_field(wp_unslash($_POST['template_col'])) : '';
	$voucher_min_value = isset($_POST['voucher_min_value']) ? sanitize_text_field(wp_unslash($_POST['voucher_min_value'])) : '';
	$voucher_max_value = isset($_POST['voucher_max_value']) ? sanitize_text_field(wp_unslash($_POST['voucher_max_value'])) : '';
	$voucher_expiry_type = isset($_POST['voucher_expiry_type']) ? sanitize_text_field(wp_unslash($_POST['voucher_expiry_type'])) : '';
	$voucher_expiry = isset($_POST['voucher_expiry']) ? sanitize_text_field(wp_unslash($_POST['voucher_expiry'])) : '';
	$voucher_terms_note = isset($_POST['voucher_terms_note']) ? sanitize_text_field(wp_unslash($_POST['voucher_terms_note'])) : '';
	$currency_position = isset($_POST['currency_position']) ? sanitize_text_field(wp_unslash($_POST['currency_position'])) : '';
	$test_mode = isset($_POST['test_mode']) ? sanitize_text_field(wp_unslash($_POST['test_mode'])) : '';
	$per_invoice = isset($_POST['per_invoice']) ? sanitize_text_field(wp_unslash($_POST['per_invoice'])) : '';
	$custom_loader = isset($_POST['custom_loader']) ? sanitize_text_field(wp_unslash($_POST['custom_loader'])) : '';
	$buying_for = isset($_POST['buying_for']) ? sanitize_text_field(wp_unslash($_POST['buying_for'])) : '';
	$hide_price_voucher = isset($_POST['hide_price_voucher']) ? sanitize_text_field(wp_unslash($_POST['hide_price_voucher'])) : '';
	$hide_price_item = isset($_POST['hide_price_item']) ? sanitize_text_field(wp_unslash($_POST['hide_price_item'])) : '';
	$hide_expiry = isset($_POST['hide_expiry']) ? sanitize_text_field(wp_unslash($_POST['hide_expiry'])) : '';
	$expiry_date_format = isset($_POST['expiry_date_format']) ? sanitize_text_field(wp_unslash($_POST['expiry_date_format'])) : '';
	$post_shipping = isset($_POST['post_shipping']) ? sanitize_text_field(wp_unslash($_POST['post_shipping'])) : '';
	$preview_button = isset($_POST['preview_button']) ? sanitize_text_field(wp_unslash($_POST['preview_button'])) : '';
	$enable_pdf_saving = isset($_POST['enable_pdf_saving']) ? sanitize_text_field(wp_unslash($_POST['enable_pdf_saving'])) : '';
	$shipping_method = isset($_POST['shipping_method']) ? sanitize_text_field(wp_unslash($_POST['shipping_method'])) : '';
	$wpgvtermstext = isset($_POST['wpgvtermstext']) ? sanitize_text_field(wp_unslash($_POST['wpgvtermstext'])) : '';
	$bank_info = isset($_POST['bank_info']) ? sanitize_text_field(wp_unslash($_POST['bank_info'])) : '';
	$email_subject = isset($_POST['email_subject']) ? sanitize_text_field(wp_unslash($_POST['email_subject'])) : '';

	$email_body = isset($_POST['email_body']) ? wp_kses_post(wp_unslash($_POST['email_body'])) : '';

	$email_body_per_invoice = isset($_POST['email_body_per_invoice']) ? wp_kses_post(wp_unslash($_POST['email_body_per_invoice'])) : '';
	$recipient_email_body   = isset($_POST['recipient_email_body']) ? wp_kses_post(wp_unslash($_POST['recipient_email_body'])) : '';
	$admin_email_body       = isset($_POST['admin_email_body']) ? wp_kses_post(wp_unslash($_POST['admin_email_body'])) : '';

	$recipient_email_subject = isset($_POST['recipient_email_subject']) ? sanitize_text_field(wp_unslash($_POST['recipient_email_subject'])) : '';
	$admin_email_subject     = isset($_POST['admin_email_subject']) ? sanitize_text_field(wp_unslash($_POST['admin_email_subject'])) : '';

	$demo_image_voucher = isset($_POST['demo_image_voucher']) ? sanitize_text_field(wp_unslash($_POST['demo_image_voucher'])) : '';
	$demo_image_item = isset($_POST['demo_image_item']) ? sanitize_text_field(wp_unslash($_POST['demo_image_item'])) : '';
	$cancelpagemessage = isset($_POST['cancelpagemessage']) ? wp_kses_post(wp_unslash($_POST['cancelpagemessage'])) : '';
	$successpagemessage = isset($_POST['successpagemessage']) ? wp_kses_post(wp_unslash($_POST['successpagemessage'])) : '';
	$wpgv_custom_css = isset($_POST['wpgv_custom_css']) ? sanitize_text_field(wp_unslash($_POST['wpgv_custom_css'])) : '';
	$pdf_footer_url = isset($_POST['pdf_footer_url']) ? sanitize_text_field(wp_unslash($_POST['pdf_footer_url'])) : '';
	$pdf_footer_email = isset($_POST['pdf_footer_email']) ? sanitize_email(wp_unslash($_POST['pdf_footer_email'])) : '';
	$leftside_notice = isset($_POST['leftside_notice']) ? sanitize_text_field(wp_unslash($_POST['leftside_notice'])) : '';
	$stripe_alternative_text = isset($_POST['stripe_alternative_text']) ? sanitize_text_field(wp_unslash($_POST['stripe_alternative_text'])) : '';
	$customer_receipt = isset($_POST['customer_receipt']) ? sanitize_text_field(wp_unslash($_POST['customer_receipt'])) : '';
	$invoice_mail_enable = isset($_POST['invoice_mail_enable']) ? sanitize_text_field(wp_unslash($_POST['invoice_mail_enable'])) : '';

	$voucher_styles = array();
	if (isset($_POST['voucher_style']) && is_array($_POST['voucher_style'])) {
		$voucher_styles = array_map('sanitize_text_field', wp_unslash($_POST['voucher_style']));
	}

	$check = $wpdb->update(
		$setting_table_name,
		array(
			'is_woocommerce_enable'   => $is_woocommerce_enable,
			'is_style_choose_enable'  => $is_style_choose_enable,
			'voucher_style'           => json_encode($voucher_styles),
			'company_name'           => $company_name,
			'sofort_configure_key'   => $sofort_configure_key,
			'reason_for_payment'     => $reason_for_payment,
			'sender_name'            => $sender_name,
			'sender_email'           => $sender_email,
			'paypal'                 => $paypal,
			'sofort'                 => $sofort,
			'stripe'                 => $stripe,
			'stripe_publishable_key' => $stripe_publishable_key,
			'stripe_secret_key'      => $stripe_secret_key,
			'currency_code'          => $currency_code,
			'currency'               => $currency,
			'voucher_bgcolor'        => $voucher_bgcolor,
			'voucher_color'          => $voucher_color,
			'template_col'           => $template_col,
			'voucher_min_value'      => $voucher_min_value,
			'voucher_max_value'      => $voucher_max_value,
			'voucher_expiry_type'    => $voucher_expiry_type,
			'voucher_expiry'         => $voucher_expiry,
			'voucher_terms_note'     => $voucher_terms_note,
			'currency_position'      => $currency_position,
			'test_mode'              => $test_mode,
			'per_invoice'            => $per_invoice,
			'bank_info'              => $bank_info,
			'custom_loader'          => $custom_loader,
			'post_shipping'          => $post_shipping,
			'shipping_method'        => $shipping_method,
			'preview_button'         => $preview_button,
			'pdf_footer_url'         => $pdf_footer_url,
			'pdf_footer_email'       => $pdf_footer_email
		),
		array('id' => 1)
	);

	update_option('wpgv_paypal_client_id', $paypal_client_id);
	update_option('wpgv_paypal_secret_key', $paypal_secret_key);
	update_option('wpgv_stripe_webhook_key', $stripe_webhook_key);
	update_option('wpgv_termstext', $wpgvtermstext);
	update_option('wpgv_buying_for', $buying_for);

	update_option('wpgv_hide_price_voucher', $hide_price_voucher);
	update_option('wpgv_hide_price_item', $hide_price_item);
	update_option('wpgv_voucher_border_color', $voucher_brcolor);

	update_option('wpgv_hide_expiry', $hide_expiry);
	update_option('wpgv_expiry_date_format', $expiry_date_format);
	update_option('wpgv_emailsubject', $email_subject);
	update_option('wpgv_emailbody', stripslashes(wp_filter_post_kses(addslashes($email_body))));
	update_option('wpgv_emailbodyperinvoice', stripslashes(wp_filter_post_kses(addslashes($email_body_per_invoice))));
	update_option('wpgv_recipientemailsubject', $recipient_email_subject);
	update_option('wpgv_recipientemailbody', stripslashes(wp_filter_post_kses(addslashes($recipient_email_body))));
	update_option('wpgv_adminemailsubject', $admin_email_subject);
	update_option('wpgv_adminemailbody', stripslashes(wp_filter_post_kses(addslashes($admin_email_body))));
	update_option('wpgv_demoimageurl_voucher', $demo_image_voucher);
	update_option('wpgv_demoimageurl_item', $demo_image_item);
	update_option('wpgv_successpagemessage', $successpagemessage);
	update_option('wpgv_cancelpagemessage', $cancelpagemessage);
	update_option('wpgv_enable_pdf_saving', $enable_pdf_saving);
	update_option('wpgv_custom_css', $wpgv_custom_css);
	update_option('wpgv_stripe_alternative_text', $stripe_alternative_text);
	update_option('wpgv_customer_receipt', $customer_receipt);
	update_option('wpgv_invoice_mail_enable', $invoice_mail_enable);
	update_option('wpgv_leftside_notice', $leftside_notice);

	if ($stripe && !get_option('wpgv_stripesuccesspage')) {
		$stripeSuccessPage = array(
			'post_title'    => 'Stripe Payment Success Page',
			'post_content'  => '[wpgv_stripesuccesspage]',
			'post_status'   => 'publish',
			'post_author'   => get_current_user_id(),
			'post_type'     => 'page',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		);
		$stripeSuccessPage_id = wp_insert_post($stripeSuccessPage, '');
		update_option('wpgv_stripesuccesspage', $stripeSuccessPage_id);
	}
	$settype = 'updated';
	$setmessage = __('Your Settings Saved Successfully.', 'gift-voucher');
	add_settings_error(
		'wooenc_settings_updated',
		esc_attr('settings_updated'),
		$setmessage,
		$settype
	);
}
$wpgv_buying_for = get_option('wpgv_buying_for') ? get_option('wpgv_buying_for') : 'both';
$wpgv_hide_price_voucher = get_option('wpgv_hide_price_voucher') ? get_option('wpgv_hide_price_voucher') : 0;
$wpgv_hide_price_item = get_option('wpgv_hide_price_item') ? get_option('wpgv_hide_price_item') : 0;
$voucher_brcolor = get_option('wpgv_voucher_border_color') ? get_option('wpgv_voucher_border_color') : '81c6a9';

$wpgv_enable_pdf_saving = get_option('wpgv_enable_pdf_saving') ? get_option('wpgv_enable_pdf_saving') : 0;
$wpgv_customer_receipt = get_option('wpgv_customer_receipt') ? get_option('wpgv_customer_receipt') : 0;
$wpgv_invoice_mail_enable = (get_option('wpgv_invoice_mail_enable') != '') ? get_option('wpgv_invoice_mail_enable') : 1;
$wpgv_leftside_notice = get_option('wpgv_leftside_notice');
$default_leftside_notice = esc_html__('Cash payment is not possible. The terms and conditions apply.', 'gift-voucher');
$leftside_notice = !empty($wpgv_leftside_notice) ? $wpgv_leftside_notice : $default_leftside_notice;
$wpgv_hide_expiry = get_option('wpgv_hide_expiry') ? get_option('wpgv_hide_expiry') : 'yes';
$wpgv_expiry_date_format = get_option('wpgv_expiry_date_format') ? get_option('wpgv_expiry_date_format') : 'd.m.Y';
$wpgv_termstext = get_option('wpgv_termstext') ? get_option('wpgv_termstext') : 'I hereby accept the terms and conditions, the revocation of the privacy policy and confirm that all information is correct.';
$wpgv_custom_css = get_option('wpgv_custom_css') ? get_option('wpgv_custom_css') : '';
$stripepageurl = get_option('wpgv_stripesuccesspage') ? get_page_link(get_option('wpgv_stripesuccesspage')) : '';
$emailsubject = get_option('wpgv_emailsubject') ? get_option('wpgv_emailsubject') : 'Order Confirmation - Your Order with {company_name} (Voucher Order No: {order_number} ) has been successfully placed!';
$emailbody = get_option('wpgv_emailbody') ? get_option('wpgv_emailbody') : '<p>Dear <strong>{customer_name}</strong>,</p><p>Order successfully placed.</p><p>We are pleased to confirm your order no {order_number}</p><p>Thank you for shopping with <strong>{company_name}</strong>!</p><p>You can download the voucher from {pdf_link}.</p><p>- For any clarifications please feel free to email us at {sender_email}.</p><p><strong>Warm Regards, <br /></strong> <strong>{company_name}<br />{website_url}</strong></p>';
$emailbodyperinvoice = get_option('wpgv_emailbodyperinvoice') ? get_option('wpgv_emailbodyperinvoice') : '<p>Dear <strong>{customer_name}</strong>,</p><p>Order successfully placed.</p><p>We are pleased to confirm your order no {order_number}</p><p>Thank you for shopping with <strong>{company_name}</strong>!</p><p>You can download the voucher from {pdf_link}.</p><p>You will pay us directly into bank. Our bank details are below:</p><p><strong>Account Number: </strong>XXXXXXXXXXXX<br /><strong>Bank Code: </strong>XXXXXXXX</p><p>- For any clarifications please feel free to email us at {sender_email}.</p><p><strong>Warm Regards, <br /></strong> <strong>{company_name}<br />{website_url}</strong></p>';
$recipientemailsubject = get_option('wpgv_recipientemailsubject') ? get_option('wpgv_recipientemailsubject') : 'Gift Voucher - Your have received voucher from {company_name}';
$recipientemailbody = get_option('wpgv_recipientemailbody') ? get_option('wpgv_recipientemailbody') : '<p>Dear <strong>{recipient_name}</strong>,</p><p>You have received gift voucher from <strong>{customer_name}</strong>.</p><p>You can download the voucher from {pdf_link}.</p><p>- For any clarifications please feel free to email us at {sender_email}.</p><p><strong>Warm Regards, <br /></strong> <strong>{company_name}<br />{website_url}</strong></p>';
$adminemailsubject = get_option('wpgv_adminemailsubject') ? get_option('wpgv_adminemailsubject') : 'New Voucher Order Received from {customer_name}  (Order No: {order_number})!';
$adminemailbody = get_option('wpgv_adminemailbody') ? get_option('wpgv_adminemailbody') : '<p>Hello, New Voucher Order received.</p><p><strong>Order Id:</strong> {order_number}</p><p><strong>Name:</strong> {customer_name}<br /><strong>Email:</strong> {customer_email}<br /><strong>Amount:</strong> {amount}</p>';
$demoimageurl_voucher = get_option('wpgv_demoimageurl_voucher') ? get_option('wpgv_demoimageurl_voucher') : WPGIFT__PLUGIN_URL . '/assets/img/demo.png';
$demoimageurl_item = get_option('wpgv_demoimageurl_item') ? get_option('wpgv_demoimageurl_item') : WPGIFT__PLUGIN_URL . '/assets/img/demo.png';
$cancelpagemessage = get_option('wpgv_cancelpagemessage') ? get_option('wpgv_cancelpagemessage') : 'You cancelled your order. Please place your order again from <a href="' . esc_url(get_site_url() . '/gift-voucher') . '">here</a>.';
$successpagemessage = get_option('wpgv_successpagemessage') ? get_option('wpgv_successpagemessage') : 'We have got your order! <br>E-Mail Sent Successfully to %s.<br>This link will be invalid after 1 hour.';
$wpgv_stripe_alternative_text = get_option('wpgv_stripe_alternative_text') ? get_option('wpgv_stripe_alternative_text') : 'Stripe';
$wpgv_paypal_client_id = get_option('wpgv_paypal_client_id') ? get_option('wpgv_paypal_client_id') : '';
$wpgv_paypal_secret_key = get_option('wpgv_paypal_secret_key') ? get_option('wpgv_paypal_secret_key') : '';
$wpgv_stripe_webhook_key = get_option('wpgv_stripe_webhook_key') ? get_option('wpgv_stripe_webhook_key') : '';
$options = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$setting_table_name} WHERE id = %d", 1));
$voucher_styles = $options->voucher_style ? json_decode($options->voucher_style) : [''];

$url = admin_url('admin.php?page=voucher-setting&action=create_default_pages');
$url = wp_nonce_url($url, 'create_default_pages_action');

?>
<?php
if (isset($_GET['action']) && $_GET['action'] == 'create_default_pages') {
	if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'create_default_pages_action')) {
?>
		<div class="wrap wpgiftv-settings">
			<h1><?php esc_html_e('Pages Created', 'gift-voucher'); ?></h1>
			<p><?php esc_html_e('Created total 6 plugin pages. These pages can be viewed in Pages Menu:', 'gift-voucher'); ?></p>
			<?php
			$createdpages = wpgv_create_plugin_pages();
			foreach ($createdpages[0] as $page) {
				$permalink = get_permalink($page);
				echo '<a href="' . esc_url($permalink) . '">' . esc_html($permalink) . '</a>';
				echo '<br>';
			}

			?>
			<p><a href="<?php echo esc_url(admin_url('admin.php?page=voucher-setting')); ?>" class="button button-primary"><?php echo esc_html__('Back to plugin settings', 'gift-voucher') ?></a></p>
			<p><?php echo esc_html__('If you read about those pages, click on the', 'gift-voucher'); ?> <a href="<?php echo esc_url('https://www.wp-giftcard.com/docs/documentation/plugin-pages/'); ?>" target="_blank"><?php echo esc_html__('link', 'gift-voucher'); ?></a> <?php echo esc_html__('for documentation.', 'gift-voucher'); ?></p>
		</div>
	<?php } else {
		wp_die('Security check failed');
	}
} else { ?>
	<div class="wrap wpgiftv-settings">
		<h1><?php echo esc_html_e('Settings', 'gift-voucher'); ?></h1>
		<hr>
		<?php settings_errors(); ?>
		<div class="image-banner" style="margin-bottom: 10px;">
			<a href="<?php echo esc_url("https://wp-sofa.chat/") ?>" target="_blank"><img src="<?php echo esc_url(WPGIFT__PLUGIN_URL . '/assets/img/banner-7.png'); ?>" style="width: 100%;"></a>
		</div>
		<div class="wpgiftv-row">
			<div class="wpgiftv-col75">
				<div class="white-box">
					<a class="button button-large button-primary alignright" href="<?php echo esc_url($url); ?>">
						<?php echo esc_html__('Create Plugin\'s Default Pages', 'gift-voucher'); ?>
					</a>
					<div class="nav-tab-wrapper">
						<a class="nav-tab nav-tab-active" href="#general"><?php echo esc_html_e('General Settings', 'gift-voucher') ?></a>
						<a class="nav-tab" href="#payment"><?php echo esc_html_e('Payment Settings', 'gift-voucher') ?></a>
						<a class="nav-tab" href="#email"><?php echo esc_html_e('Email Settings', 'gift-voucher') ?></a>
						<a class="nav-tab" href="#custom"><?php echo esc_html_e('Custom CSS', 'gift-voucher') ?></a>
					</div>
					<form method="post" name="voucher-settings" id="voucher-settings" action="<?php echo esc_url(admin_url('admin.php')); ?>?page=voucher-setting">
						<input type="hidden" name="action" value="<?php echo esc_attr('save_voucher_settings_option'); ?>" />
						<?php wp_nonce_field('voucher_settings_verify', 'voucher_settings_verify_nonce'); ?>
						<table class="form-table tab-content tab-content-active" id="general">
							<tbody>
								<tr>
									<th colspan="2" style="padding-bottom:0;padding-top: 0;">
										<h3><?php echo esc_html_e('General Settings', 'gift-voucher'); ?></h3>
									</th>
								</tr>
								<tr>
									<th scope="row">
										<label for="is_woocommerce_enable"><?php echo esc_html_e('WooCommerce', 'gift-voucher'); ?></label>
										<p class="description"><?php echo esc_html__('If enable then customers can redeem their vouchers on WooCommerce checkout', 'gift-voucher') ?></p>
									</th>
									<td>
										<select name="is_woocommerce_enable" id="is_woocommerce_enable" class="regular-text">
											<option value="<?php echo esc_attr('1'); ?>" <?php selected($options->is_woocommerce_enable, 1); ?>>
												<?php echo esc_html__('Enable', 'gift-voucher'); ?>
											</option>
											<option value="<?php echo esc_attr('0'); ?>" <?php selected($options->is_woocommerce_enable, 0); ?>>
												<?php echo esc_html__('Disable', 'gift-voucher'); ?>
											</option>
										</select>

									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="is_style_choose_enable"><?php echo esc_html_e('Can customers choose voucher styles?', 'gift-voucher'); ?></label>
										<p class="description"><?php echo esc_html__('If enable then customers can choose the voucher styles from bottom styles you enabled', 'gift-voucher') ?></p>
									</th>
									<td>
										<select name="is_style_choose_enable" id="is_style_choose_enable" class="regular-text">
											<option value="<?php echo esc_attr('1'); ?>" <?php selected($options->is_style_choose_enable, 1); ?>>
												<?php echo esc_html__('Yes', 'gift-voucher'); ?>
											</option>
											<option value="<?php echo esc_attr('0'); ?>" <?php selected($options->is_style_choose_enable, 0); ?>>
												<?php echo esc_html__('No', 'gift-voucher'); ?>
											</option>
										</select>

									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="voucher_style"><?php esc_html_e('Voucher Style', 'gift-voucher'); ?> (<?php esc_html_e('Can select multiple', 'gift-voucher'); ?>)</label>
										<p class="description">
											<?php esc_html_e('Demo:', 'gift-voucher'); ?>
											<a href="<?php echo esc_url(WPGIFT__PLUGIN_URL . '/assets/img/style1.png'); ?>" target="_blank"><?php esc_html_e('Style 1', 'gift-voucher'); ?></a>,
											<a href="<?php echo esc_url(WPGIFT__PLUGIN_URL . '/assets/img/style2.png'); ?>" target="_blank"><?php esc_html_e('Style 2', 'gift-voucher'); ?></a>,
											<a href="<?php echo esc_url(WPGIFT__PLUGIN_URL . '/assets/img/style3.png'); ?>" target="_blank"><?php esc_html_e('Style 3', 'gift-voucher'); ?></a>
										</p>
									</th>
									<td>
										<select name="voucher_style[]" id="voucher_style" multiple="multiple" class="regular-text">
											<option value="<?php echo esc_attr('0'); ?>" <?php echo in_array(0, $voucher_styles) ? 'selected' : ''; ?>>
												<?php esc_html_e('Style 1', 'gift-voucher'); ?>
											</option>
											<option value="<?php echo esc_attr('1'); ?>" <?php echo in_array(1, $voucher_styles) ? 'selected' : ''; ?>>
												<?php esc_html_e('Style 2', 'gift-voucher'); ?>
											</option>
											<option value="<?php echo esc_attr('2'); ?>" <?php echo in_array(2, $voucher_styles) ? 'selected' : ''; ?>>
												<?php esc_html_e('Style 3', 'gift-voucher'); ?>
											</option>
										</select>
									</td>

								</tr>

								<tr>
									<th scope="row">
										<label for="company_name">
											<?php esc_html_e('Company Name', 'gift-voucher'); ?>
											<span class="description">(<?php esc_html_e('required', 'gift-voucher'); ?>)</span>
										</label>
									</th>
									<td>
										<input name="company_name" type="text" id="company_name" value="<?php echo esc_html(stripslashes($options->company_name)); ?>" class="regular-text" aria-required="true" required="required">
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="currency_code">
											<?php esc_html_e('Currency Code', 'gift-voucher'); ?>
											<span class="description">(<?php esc_html_e('required', 'gift-voucher'); ?>)</span>
										</label>
										<p class="description">
											<a href="<?php echo esc_url('https://developer.paypal.com/docs/integration/direct/rest/currency-codes/'); ?>" target="_blank">
												<?php esc_html_e('Click Here', 'gift-voucher'); ?>
											</a>
											<?php esc_html_e('to check valid currency codes', 'gift-voucher'); ?>
										</p>
									</th>

									<td>
										<input name="currency_code" type="text" id="currency_code" value="<?php echo esc_html($options->currency_code); ?>" class="regular-text" aria-required="true" required="required">
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="currency">
											<?php esc_html_e('Currency Symbol', 'gift-voucher'); ?>
											<span class="description">(<?php esc_html_e('required', 'gift-voucher'); ?>)</span>
										</label>

									</th>
									<td>
										<input name="currency" type="text" id="currency" value="<?php echo esc_html($options->currency); ?>" class="regular-text" aria-required="true" required="required">
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="currency_position"><?php echo esc_html_e('Currency Position', 'gift-voucher'); ?> </label>
									</th>
									<td>
										<select name="currency_position" class="regular-text" id="currency_position">
											<option value="<?php echo esc_attr('Left'); ?>" <?php selected($options->currency_position, 'Left'); ?>>
												<?php esc_html_e('Left', 'gift-voucher'); ?>
											</option>
											<option value="<?php echo esc_attr('Right'); ?>" <?php selected($options->currency_position, 'Right'); ?>>
												<?php esc_html_e('Right', 'gift-voucher'); ?>
											</option>
										</select>

									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="voucher_bgcolor">
											<?php esc_html_e('Voucher Background Color', 'gift-voucher'); ?>
											<span class="description">(<?php esc_html_e('required', 'gift-voucher'); ?>)</span>
										</label>
									</th>
									<td>
										<div>
											<input name="voucher_bgcolor" type="text" id="voucher_bgcolor" value="#<?php echo esc_html($options->voucher_bgcolor); ?>" class="regular-text" aria-required="true">
											<span class="description"> <?php esc_html_e('Background Color', 'gift-voucher'); ?></span>
										</div>
										<div>
											<input name="voucher_brcolor" type="text" id="voucher_bgcolor" value="#<?php echo esc_html($voucher_brcolor); ?>" class="regular-text" aria-required="true">
											<span class="description"> <?php esc_html_e('Border & Button Color', 'gift-voucher'); ?></span>
										</div>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="voucher_color">
											<?php esc_html_e('Voucher Text Color', 'gift-voucher'); ?>
											<span class="description">(<?php esc_html_e('required', 'gift-voucher'); ?>)</span>
										</label>

									</th>
									<td>
										<input name="voucher_color" type="text" id="voucher_color" value="#<?php echo esc_html($options->voucher_color); ?>" class="regular-text" aria-required="true">
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="template_col"><?php echo esc_html_e('Templates Columns', 'gift-voucher'); ?></label>
										<p class="description"><?php echo esc_html_e('How many templates show in a row. (Gift Voucher Shortcode)', 'gift-voucher'); ?></p>
									</th>
									<td>
										<select name="template_col" class="regular-text" id="template_col">
											<option value="<?php echo esc_attr('3'); ?>" <?php echo ($options->template_col == 3) ? esc_attr('selected') : ''; ?>>3</option>
											<option value="<?php echo esc_attr('4'); ?>" <?php echo ($options->template_col == 4) ? esc_attr('selected') : ''; ?>>4</option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="voucher_min_value"><?php echo esc_html_e('Minimum Voucher Value', 'gift-voucher'); ?></label>
										<p class="description"><?php echo esc_html_e('Leave 0 if no minimum value', 'gift-voucher'); ?></p>
									</th>
									<td>
										<input name="voucher_min_value" type="number" step="0.01" id="voucher_min_value" value="<?php echo esc_html($options->voucher_min_value); ?>" class="regular-text" aria-required="true">
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="voucher_max_value"><?php echo esc_html_e('Maximum Voucher Value', 'gift-voucher'); ?></label>
									</th>
									<td>
										<input name="voucher_max_value" type="number" step="0.01" id="voucher_max_value" value="<?php echo esc_html($options->voucher_max_value); ?>" class="regular-text" aria-required="true">
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="hide_expiry"><?php echo esc_html_e('Add expiry in voucher', 'gift-voucher'); ?></label>
									</th>
									<td>
										<select name="hide_expiry" id="hide_expiry" class="regular-text">
											<option value="<?php echo esc_attr('yes'); ?>" <?php selected($wpgv_hide_expiry, 'yes'); ?>>
												<?php esc_html_e('Yes', 'gift-voucher'); ?>
											</option>
											<option value="<?php echo esc_attr('no'); ?>" <?php selected($wpgv_hide_expiry, 'no'); ?>>
												<?php esc_html_e('No', 'gift-voucher'); ?>
											</option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="voucher_expiry_type"><?php echo esc_html_e('Voucher Expiry Type', 'gift-voucher'); ?></label>
										<p class="description"><?php echo esc_html_e('Select the type of voucher expiration?', 'gift-voucher'); ?></p>
									</th>
									<td>
										<select name="voucher_expiry_type" class="regular-text" id="template_col">
											<option value="<?php echo esc_attr('days'); ?>" <?php selected($options->voucher_expiry_type, 'days'); ?>>
												<?php esc_html_e('Days', 'gift-voucher'); ?>
											</option>
											<option value="<?php echo esc_attr('fixed'); ?>" <?php selected($options->voucher_expiry_type, 'fixed'); ?>>
												<?php esc_html_e('Fixed Date', 'gift-voucher'); ?>
											</option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="voucher_expiry"><?php echo esc_html_e('Voucher Expiry Value', 'gift-voucher'); ?></label>
										<p class="description"><?php echo esc_html_e('Example: (Days: 60, Fixed Date: 20.05.2018)', 'gift-voucher'); ?></p>
									</th>
									<td>
										<input name="voucher_expiry" type="text" id="voucher_expiry" value="<?php echo esc_html($options->voucher_expiry); ?>" class="regular-text" aria-required="true">
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="expiry_date_format"><?php echo esc_html_e('Expiry date format', 'gift-voucher'); ?></label>
									</th>
									<td>
										<input name="expiry_date_format" type="text" id="expiry_date_format" value="<?php echo esc_html($wpgv_expiry_date_format); ?>" class="regular-text" aria-required="true">
										<p class="description">
											<a href="<?php echo esc_url('http://php.net/manual/en/function.date.php#refsect1-function.date-parameters'); ?>" target="_blank">
												<?php esc_html_e('Click Here', 'gift-voucher'); ?>
											</a>
											<?php esc_html_e('to check valid date formats', 'gift-voucher'); ?>
										</p>
									</td>
								</tr>

								<tr>
									<th colspan="2">
										<hr>
									</th>
								</tr>
								<tr>
									<td style="padding: 0px;">
										<h3><?php esc_html_e('Gift Voucher', 'gift-voucher'); ?></h3>
									</td>
								</tr>

								<tr>
									<th scope="row">
										<label for="hide_price_voucher"><?php echo esc_html_e('Hide price from voucher', 'gift-voucher'); ?></label>
									</th>
									<td>
										<select name="hide_price_voucher" class="regular-text" id="hide_price_voucher">
											<option value="<?php echo esc_attr('1'); ?>" <?php selected($wpgv_hide_price_voucher, 1); ?>>
												<?php esc_html_e('Yes', 'gift-voucher'); ?>
											</option>
											<option value="<?php echo esc_attr('0'); ?>" <?php selected($wpgv_hide_price_voucher, 0); ?>>
												<?php esc_html_e('No', 'gift-voucher'); ?>
											</option>
										</select>
									</td>
								</tr>

								<tr>
									<th scope="row">
										<label for="demo_image_voucher">
											<?php esc_html_e('Add Your Custom Demo Image', 'gift-voucher'); ?>
										</label>
										<p class="description">
											<?php esc_html_e('Default Image - check', 'gift-voucher'); ?>
											<a href="<?php echo esc_url(WPGIFT__PLUGIN_URL . '/assets/img/demo.png'); ?>" target="_blank">
												<?php esc_html_e('here', 'gift-voucher'); ?>
											</a>
										</p>
									</th>
									<td>
										<input name="demo_image_voucher" type="text" id="demo_image_voucher" value="<?php echo esc_html($demoimageurl_voucher); ?>" class="regular-text">
									</td>
								</tr>

								<tr>
									<th colspan="2">
										<hr>
									</th>
								</tr>
								<tr>
									<td style="padding: 0px;">
										<h3><?php esc_html_e('Gift Items', 'gift-voucher'); ?></h3>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="hide_price_item"><?php echo esc_html_e('Hide price from Items', 'gift-voucher'); ?></label>
									</th>
									<td>
										<select name="hide_price_item" class="regular-text" id="hide_price_item">
											<option value="<?php echo esc_attr('1'); ?>" <?php selected($wpgv_hide_price_item, 1); ?>>
												<?php esc_html_e('Yes', 'gift-voucher'); ?>
											</option>
											<option value="<?php echo esc_attr('0'); ?>" <?php selected($wpgv_hide_price_item, 0); ?>>
												<?php esc_html_e('No', 'gift-voucher'); ?>
											</option>
										</select>
									</td>
								</tr>

								<tr>
									<th scope="row">
										<label for="demo_image_item">
											<?php esc_html_e('Add Your Custom Demo Image', 'gift-voucher'); ?>
										</label>
										<p class="description">
											<?php esc_html_e('Default Image - check', 'gift-voucher'); ?>
											<a href="<?php echo esc_url(WPGIFT__PLUGIN_URL . '/assets/img/demo.png'); ?>" target="_blank">
												<?php esc_html_e('here', 'gift-voucher'); ?>
											</a>
										</p>
									</th>
									<td>
										<input name="demo_image_item" type="text" id="demo_image_item" value="<?php echo esc_html($demoimageurl_item); ?>" class="regular-text">
									</td>
								</tr>

								<tr>
									<th colspan="2">
										<hr>
									</th>
								</tr>

								<tr>
									<th scope="row">
										<label for="admin_email_body"><?php echo esc_html_e('Terms and Condition Checkbox Text', 'gift-voucher'); ?></label>
									</th>
									<td>
										<?php wp_editor((stripslashes($wpgv_termstext)), 'wpgvtermstext', array('wpautop' => true, 'media_buttons' => false, 'textarea_rows' => 5)); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="voucher_terms_note"><?php echo esc_html_e('Voucher Terms Note', 'gift-voucher'); ?></label>
										<p class="description"><?php echo esc_html_e('Terms note in voucher order page', 'gift-voucher'); ?></p>
									</th>
									<td>
										<textarea name="voucher_terms_note" id="voucher_terms_note" class="regular-text" aria-required="true" rows="4"><?php echo esc_html(stripslashes($options->voucher_terms_note)); ?></textarea>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="buying_for"><?php echo esc_html_e('Buying for', 'gift-voucher'); ?></label>
									</th>
									<td>
										<select name="buying_for" class="regular-text" id="buying_for">
											<option value="<?php echo esc_attr('both'); ?>" <?php selected($wpgv_buying_for, 'both'); ?>>
												<?php esc_html_e('Both', 'gift-voucher'); ?>
											</option>
											<option value="<?php echo esc_attr('someone_else'); ?>" <?php selected($wpgv_buying_for, 'someone_else'); ?>>
												<?php esc_html_e('Someone Else', 'gift-voucher'); ?>
											</option>
											<option value="<?php echo esc_attr('yourself'); ?>" <?php selected($wpgv_buying_for, 'yourself'); ?>>
												<?php esc_html_e('Yourself', 'gift-voucher'); ?>
											</option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="post_shipping"><?php echo esc_html_e('Post Shipping', 'gift-voucher'); ?></label>
									</th>
									<td>
										<select name="post_shipping" class="regular-text" id="post_shipping">
											<option value="<?php echo esc_attr('1'); ?>" <?php selected($options->post_shipping, 1); ?>>
												<?php esc_html_e('Yes', 'gift-voucher'); ?>
											</option>
											<option value="<?php echo esc_attr('0'); ?>" <?php selected($options->post_shipping, 0); ?>>
												<?php esc_html_e('No', 'gift-voucher'); ?>
											</option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="shipping_method"><?php echo esc_html_e('Shipping Method for Post Shipping', 'gift-voucher'); ?></label>
										<p class="description">
											<?php esc_html_e('Method Format -> value : name', 'gift-voucher'); ?>
										</p>
									</th>
									<td>
										<textarea name="shipping_method" type="text" id="shipping_method" class="regular-text" rows="4"><?php echo esc_html(stripslashes($options->shipping_method)); ?></textarea>
										<p class="description"><?php echo esc_html_e('Multiple methods seperate by comma(,)', 'gift-voucher'); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="preview_button"><?php echo esc_html_e('Voucher preview Button', 'gift-voucher'); ?></label>
										<p class="description">
											<?php esc_html_e('If enabled, the preview button will show in the voucher booking forms', 'gift-voucher'); ?>
										</p>

									</th>
									<td>
										<select name="preview_button" class="regular-text" id="preview_button">
											<option value="<?php echo esc_attr('1'); ?>" <?php selected($options->preview_button, 1); ?>>
												<?php esc_html_e('Enable', 'gift-voucher'); ?>
											</option>
											<option value="<?php echo esc_attr('0'); ?>" <?php selected($options->preview_button, 0); ?>>
												<?php esc_html_e('Disable', 'gift-voucher'); ?>
											</option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="enable_pdf_saving">
											<?php esc_html_e('Change PDF Save Option', 'gift-voucher'); ?>
										</label>
										<p class="description">
											<?php esc_html_e('If you are getting an error on checkout, then enable this PDF saving option.', 'gift-voucher'); ?>
										</p>
									</th>
									<td>
										<select name="enable_pdf_saving" class="regular-text" id="enable_pdf_saving">
											<option value="<?php echo esc_attr('1'); ?>" <?php selected($wpgv_enable_pdf_saving, 1); ?>>
												<?php esc_html_e('Enable', 'gift-voucher'); ?>
											</option>
											<option value="<?php echo esc_attr('0'); ?>" <?php selected($wpgv_enable_pdf_saving, 0); ?>>
												<?php esc_html_e('Disable', 'gift-voucher'); ?>
											</option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="custom_loader">
											<?php esc_html_e('Add Your Custom Loader URL', 'gift-voucher'); ?>
										</label>
										<p class="description">
											<?php esc_html_e('Default - check', 'gift-voucher'); ?>
											<a href="<?php echo esc_url(WPGIFT__PLUGIN_URL . '/assets/img/loader.gif'); ?>" target="_blank">
												<?php esc_html_e('here', 'gift-voucher'); ?>
											</a>
										</p>
									</th>
									<td>
										<input name="custom_loader" type="text" id="custom_loader" value="<?php echo esc_html($options->custom_loader); ?>" class="regular-text">
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="successpagemessage"><?php echo esc_html_e('Successful Page Message', 'gift-voucher'); ?></label>
										<p class="description"><?php echo esc_html_e('Message appear after payment successful.', 'gift-voucher'); ?></p>
									</th>
									<td>
										<?php wp_editor((stripslashes($successpagemessage)), 'successpagemessage', array('wpautop' => false, 'media_buttons' => false, 'textarea_rows' => 5)); ?>
										<p>%s - <?php echo esc_html_e('Display the email address of the customer', 'gift-voucher'); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="cancelpagemessage"><?php echo esc_html_e('Order Cancellation Message', 'gift-voucher'); ?></label>
										<p class="description"><?php echo esc_html_e('Message appear after order cancelled', 'gift-voucher'); ?></p>
									</th>
									<td>
										<?php wp_editor((stripslashes($cancelpagemessage)), 'cancelpagemessage', array('wpautop' => false, 'media_buttons' => false, 'textarea_rows' => 5)); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="pdf_footer_url"><?php echo esc_html_e('Website URL on PDF in Footer', 'gift-voucher'); ?></label>
									</th>
									<td>
										<input name="pdf_footer_url" type="text" id="pdf_footer_url" value="<?php echo esc_attr($options->pdf_footer_url); ?>" class="regular-text">
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="pdf_footer_email"><?php echo esc_html_e('Email on PDF in Footer', 'gift-voucher'); ?></label>
									</th>
									<td>
										<input name="pdf_footer_email" type="text" id="pdf_footer_email" value="<?php echo esc_attr($options->pdf_footer_email); ?>" class="regular-text">
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="leftside_notice"><?php echo esc_html_e('Left side Voucher notice', 'gift-voucher'); ?></label>
									</th>
									<td>
										<input name="leftside_notice" type="text" id="leftside_notice" class="regular-text" maxlength="80" value="<?php echo esc_attr($leftside_notice); ?>">
									</td>
								</tr>
							</tbody>
						</table>
						<table id="payment" class="form-table tab-content">
							<tbody>
								<tr>
									<th colspan="2" style="padding-bottom:0">
										<h3><?php echo esc_html_e('Payment Settings', 'gift-voucher'); ?></h3>
									</th>
								</tr>
								<tr>
									<th scope="row">
										<label for="paypal"><?php echo esc_html_e('Paypal Enable', 'gift-voucher'); ?></label>
									</th>
									<td>
										<select name="paypal" class="regular-text" id="paypal">
											<option value="<?php echo esc_attr('1'); ?>" <?php selected($options->paypal, 1); ?>>
												<?php esc_html_e('Yes', 'gift-voucher'); ?>
											</option>
											<option value="<?php echo esc_attr('0'); ?>" <?php selected($options->paypal, 0); ?>>
												<?php esc_html_e('No', 'gift-voucher'); ?>
											</option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="test_mode"><?php echo esc_html_e('Paypal Testmode', 'gift-voucher'); ?></label>
									</th>
									<td>
										<select name="test_mode" class="regular-text" id="test_mode">
											<option value="<?php echo esc_attr('1'); ?>" <?php selected($options->test_mode, 1); ?>>
												<?php esc_html_e('Yes', 'gift-voucher'); ?>
											</option>
											<option value="<?php echo esc_attr('0'); ?>" <?php selected($options->test_mode, 0); ?>>
												<?php esc_html_e('No', 'gift-voucher'); ?>
											</option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="paypal_client_id" style="float: left;"><?php echo esc_html_e('PayPal Client ID', 'gift-voucher'); ?></label>
										<div class="wpgv_tooltip">
											<img src="<?php echo esc_url(WPGIFT__PLUGIN_URL . '/assets/img/info-icon.png'); ?>" class="wpgv_info">
											<span class="wpgv_tooltiptext">
												<?php esc_html_e('Credentials will be different for both Test mode and Live mode.', 'gift-voucher'); ?>
											</span>
										</div>
										<p class="description" style="width: 100%; float: left;"><?php echo esc_html_e('Read the documentation of how to create PayPal live client ID.', 'gift-voucher'); ?>
											<br><a href="<?php echo esc_url('https://www.wp-giftcard.com/docs/documentation/plugin-settings/payment-settings/') ?>" target="_blank">Click Here</a>
										</p>
									</th>
									<td>
										<input name="paypal_client_id" type="text" id="paypal_client_id" value="<?php echo esc_attr($wpgv_paypal_client_id); ?>" class="regular-text">
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="paypal_secret_key"><?php echo esc_html_e('PayPal Secret Key', 'gift-voucher'); ?></label>
									</th>
									<td>
										<input name="paypal_secret_key" type="text" id="paypal_secret_key" value="<?php echo esc_html($wpgv_paypal_secret_key); ?>" class="regular-text">
									</td>
								</tr>
								<tr>
									<th colspan="2">
										<hr>
									</th>
								</tr>
								<tr>
									<th scope="row">
										<label for="stripe"><?php echo esc_html_e('Stripe Enable', 'gift-voucher'); ?></label>
									</th>
									<td>
										<select name="stripe" class="regular-text" id="stripe">
											<option value="<?php echo esc_attr('1'); ?>" <?php selected($options->stripe, 1); ?>>
												<?php esc_html_e('Yes', 'gift-voucher'); ?>
											</option>
											<option value="<?php echo esc_attr('0'); ?>" <?php selected($options->stripe, 0); ?>>
												<?php esc_html_e('No', 'gift-voucher'); ?>
											</option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="stripe_alternative_text"><?php echo esc_html_e('Stripe Text', 'gift-voucher'); ?></label>
										<p class="description"><?php echo esc_html_e('This will show on frontend Form', 'gift-voucher'); ?></p>
									</th>
									<td>
										<input name="stripe_alternative_text" type="text" id="stripe_alternative_text" value="<?php echo esc_html(stripslashes($wpgv_stripe_alternative_text)); ?>" class="regular-text">
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="stripe_publishable_key"><?php echo esc_html_e('Stripe Publishable key', 'gift-voucher'); ?></label>
										<p class="description">
											<?php esc_html_e('Collect the Publishable API key from the link below.', 'gift-voucher'); ?><br>
											<a href="<?php echo esc_url('https://dashboard.stripe.com/account/apikeys'); ?>" target="_blank">
												<?php esc_html_e('Click Here', 'gift-voucher'); ?>
											</a>
										</p>
									</th>
									<td>
										<input name="stripe_publishable_key" type="text" id="stripe_publishable_key" value="<?php echo esc_html($options->stripe_publishable_key); ?>" class="regular-text">
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="stripe_secret_key"><?php echo esc_html_e('Stripe Secret Key', 'gift-voucher'); ?></label>
										<p class="description">
											<?php esc_html_e('Collect the Secret API key from the link below.', 'gift-voucher'); ?><br>
											<a href="<?php echo esc_url('https://dashboard.stripe.com/account/apikeys'); ?>" target="_blank">
												<?php esc_html_e('Click Here', 'gift-voucher'); ?>
											</a>
										</p>

									</th>
									<td>
										<input name="stripe_secret_key" type="text" id="stripe_secret_key" value="<?php echo esc_html($options->stripe_secret_key); ?>" class="regular-text">
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="stripe_webhook_url"><?php echo esc_html_e('Stripe Webhook URL', 'gift-voucher'); ?></label>
									</th>
									<td>
										<input name="stripe_webhook_url" type="text" id="stripe_webhook_url" value="<?php echo esc_url(WPGIFT__PLUGIN_URL . '/include/stripewebhook.php'); ?>" class="regular-text" readonly>
										<p class="description"><?php esc_html_e('Copy this url and paste in Stripe Webhook Endpoint URL.', 'gift-voucher'); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="stripe_webhook_key"><?php echo esc_html_e('Stripe Webhook Signing secret key', 'gift-voucher'); ?></label>
										<p class="description">
											<?php esc_html_e('Collect the Webhook Signing secret key from the link below.', 'gift-voucher'); ?><br>
											<a href="<?php echo esc_url('https://dashboard.stripe.com/account/webhooks'); ?>" target="_blank">
												<?php esc_html_e('Click Here', 'gift-voucher'); ?>
											</a>
										</p>
									</th>
									<td>
										<input name="stripe_webhook_key" type="text" id="stripe_webhook_key" value="<?php echo esc_html($wpgv_stripe_webhook_key); ?>" class="regular-text">
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="stripe_checkoutpage"><?php echo esc_html_e('Stripe Checkout Page', 'gift-voucher'); ?></label>
									</th>
									<td>
										<input name="stripe_checkoutpage" type="text" id="stripe_checkoutpage" value="<?php echo esc_html($stripepageurl); ?>" class="regular-text" readonly>
										<p class="description"><?php echo esc_html_e('This page is automatically created for you when you enable stripe payment method.', 'gift-voucher'); ?></p>
									</td>
								</tr>
								<tr>
									<th colspan="2">
										<hr>
									</th>
								</tr>
								<tr>
									<th scope="row">
										<label for="sofort"><?php echo esc_html_e('Sofort Enable', 'gift-voucher'); ?></label>
									</th>
									<td>
										<select name="sofort" class="regular-text" id="sofort">
											<option value="<?php echo esc_attr('1'); ?>" <?php selected($options->sofort, 1); ?>>
												<?php esc_html_e('Yes', 'gift-voucher'); ?>
											</option>
											<option value="<?php echo esc_attr('0'); ?>" <?php selected($options->sofort, 0); ?>>
												<?php esc_html_e('No', 'gift-voucher'); ?>
											</option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="sofort_configure_key"><?php echo esc_html_e('Sofort Configuration Key', 'gift-voucher'); ?></label>
										<p class="description"><?php echo esc_html_e('Enter your configuration key. you only can create a new configuration key by creating a new Gateway project in your account at sofort.com.', 'gift-voucher'); ?></p>
									</th>
									<td>
										<input name="sofort_configure_key" type="text" id="sofort_configure_key" value="<?php echo esc_html($options->sofort_configure_key); ?>" class="regular-text" aria-describedby="paypal-description">
										<p class="description"><?php echo esc_html_e('This key is used for Sofort Payment.', 'gift-voucher'); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="reason_for_payment"><?php echo esc_html_e('Reason for Payment', 'gift-voucher'); ?></label>
										<p class="description"><?php echo esc_html_e('Reason for payment from Sofort.', 'gift-voucher'); ?></p>
									</th>
									<td>
										<input name="reason_for_payment" type="text" id="reason_for_payment" value="<?php echo esc_html($options->reason_for_payment); ?>" class="regular-text" aria-describedby="paypal-description">
									</td>
								</tr>
								<tr>
									<th colspan="2">
										<hr>
									</th>
								</tr>
								<tr>
									<th scope="row">
										<label for="per_invoice"><?php echo esc_html_e('Bank Transfer Enable', 'gift-voucher'); ?></label>
										<p class="description"><?php echo esc_html_e('With this payment method user don\'t have to pay immediately, They can directly transfer amount to your bank.', 'gift-voucher'); ?></p>
									</th>
									<td>
										<select name="per_invoice" class="regular-text" id="per_invoice">
											<option value="<?php echo esc_attr('1'); ?>" <?php selected($options->per_invoice, 1); ?>>
												<?php esc_html_e('Yes', 'gift-voucher'); ?>
											</option>
											<option value="<?php echo esc_attr('0'); ?>" <?php selected($options->per_invoice, 0); ?>>
												<?php esc_html_e('No', 'gift-voucher'); ?>
											</option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="per_invoice"><?php echo esc_html_e('Send Direct Mail', 'gift-voucher'); ?></label>
									</th>
									<td>
										<select name="invoice_mail_enable" id="invoice_mail_enable" class="regular-text">
											<option value="<?php echo esc_attr('1'); ?>" <?php selected($wpgv_invoice_mail_enable, 1); ?>>
												<?php esc_html_e('Yes', 'gift-voucher'); ?>
											</option>
											<option value="<?php echo esc_attr('0'); ?>" <?php selected($wpgv_invoice_mail_enable, 0); ?>>
												<?php esc_html_e('No', 'gift-voucher'); ?>
											</option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="bank_info"><?php echo esc_html_e('Bank Details', 'gift-voucher'); ?></label>
										<p class="description"><?php echo esc_html_e('This details will show to user who would pay as per invoice.', 'gift-voucher'); ?></p>
									</th>
									<td>
										<?php wp_editor((stripslashes($options->bank_info)), 'bank_info', array('wpautop' => false, 'media_buttons' => false, 'textarea_rows' => 5)); ?>
									</td>
								</tr>
							</tbody>
						</table>
						<table id="email" class="form-table tab-content">
							<tbody>
								<tr>
									<th colspan="2" style="padding-bottom:0">
										<h3><?php echo esc_html_e('Email Settings', 'gift-voucher'); ?></h3>
									</th>
								</tr>
								<tr>
									<th scope="row">
										<label for="sender_name"><?php echo esc_html_e('Sender Name', 'gift-voucher'); ?></label>
										<p class="description"><?php echo esc_html_e('For emails send by this plugin.', 'gift-voucher'); ?></p>
									</th>
									<td>
										<input name="sender_name" type="text" id="sender_name" value="<?php echo esc_html($options->sender_name); ?>" class="regular-text" aria-describedby="sendername-description">
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="sender_email"><?php echo esc_html_e('Sender Email', 'gift-voucher'); ?></label>
										<p class="description"><?php echo esc_html_e('For emails send by this plugin.', 'gift-voucher'); ?></p>
									</th>
									<td>
										<input name="sender_email" type="email" id="sender_email" value="<?php echo esc_html($options->sender_email); ?>" class="regular-text" aria-describedby="senderemail-description">
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="customer_receipt"><?php echo esc_html_e('Send Customer Receipt', 'gift-voucher'); ?></label>
									</th>
									<td>
										<select name="customer_receipt" id="customer_receipt" class="regular-text">
											<option value="<?php echo esc_attr('1'); ?>" <?php selected($wpgv_customer_receipt, 1); ?>>
												<?php esc_html_e('Enable', 'gift-voucher'); ?>
											</option>
											<option value="<?php echo esc_attr('0'); ?>" <?php selected($wpgv_customer_receipt, 0); ?>>
												<?php esc_html_e('Disable', 'gift-voucher'); ?>
											</option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="email_subject"><?php echo esc_html_e('Buyer Email Subject', 'gift-voucher'); ?></label>
										<p class="description"><?php echo esc_html_e('Subject for emails send to customers.', 'gift-voucher'); ?></p>
									</th>
									<td>
										<?php wp_editor((stripslashes($emailsubject)), 'email_subject', array('media_buttons' => false, 'textarea_rows' => 5)); ?>
										<p class="description">{company_name} {website_url} {sender_email} {sender_name} {order_number} {order_type} {amount} {customer_name} {recipient_name} {customer_email} {customer_address} {customer_postcode} {coupon_code} {pdf_link} {payment_method} {payment_status} {receipt_link}</p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="email_body"><?php echo esc_html_e('Buyer Email Body', 'gift-voucher'); ?></label>
										<p class="description"><?php echo esc_html_e('Body message for emails send to customers.', 'gift-voucher'); ?></p>
									</th>
									<td>
										<?php wp_editor((stripslashes($emailbody)), 'email_body', array('wpautop' => false, 'media_buttons' => false)); ?>
										<p class="description">{company_name} {website_url} {sender_email} {sender_name} {order_number} {order_type} {amount} {customer_name} {recipient_name} {customer_email} {customer_address} {customer_postcode} {coupon_code} {pdf_link} {payment_method} {payment_status} {receipt_link}</p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="email_body"><?php echo esc_html_e('Buyer Email Body for Bank Transfer', 'gift-voucher'); ?></label>
										<p class="description"><?php echo esc_html_e('This email body is used when customer select payment as per bank transfer.', 'gift-voucher'); ?></p>
									</th>
									<td>
										<?php wp_editor((stripslashes($emailbodyperinvoice)), 'email_body_per_invoice', array('wpautop' => false, 'media_buttons' => false)); ?>
										<p class="description">{company_name} {website_url} {sender_email} {sender_name} {order_number} {order_type} {amount} {customer_name} {recipient_name} {customer_email} {customer_address} {customer_postcode} {coupon_code} {pdf_link} {payment_method} {payment_status} {receipt_link}</p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="email_subject"><?php echo esc_html_e('Recipient Email Subject', 'gift-voucher'); ?></label>
										<p class="description"><?php echo esc_html_e('Subject for emails send to recipient.', 'gift-voucher'); ?></p>
									</th>
									<td>
										<?php wp_editor((stripslashes($recipientemailsubject)), 'recipient_email_subject', array('media_buttons' => false, 'textarea_rows' => 5)); ?>
										<p class="description">{company_name} {website_url} {sender_email} {sender_name} {order_number} {order_type} {amount} {customer_name} {recipient_name} {customer_email} {customer_address} {customer_postcode} {coupon_code} {pdf_link} {payment_method} {payment_status} {receipt_link}</p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="email_body"><?php echo esc_html_e('Recipient Email Body', 'gift-voucher'); ?></label>
										<p class="description"><?php echo esc_html_e('Body message for emails send to recipient.', 'gift-voucher'); ?></p>
									</th>
									<td>
										<?php wp_editor((stripslashes($recipientemailbody)), 'recipient_email_body', array('wpautop' => false, 'media_buttons' => false)); ?>
										<p class="description">{company_name} {website_url} {sender_email} {sender_name} {order_number} {order_type} {amount} {customer_name} {recipient_name} {customer_email} {customer_address} {customer_postcode} {coupon_code} {pdf_link} {payment_method} {payment_status} {receipt_link}</p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="admin_email_subject"><?php echo esc_html_e('Admin Email Subject', 'gift-voucher'); ?></label>
										<p class="description"><?php echo esc_html_e('Subject for emails send to customers.', 'gift-voucher'); ?></p>
									</th>
									<td>
										<?php wp_editor(($adminemailsubject), 'admin_email_subject', array('media_buttons' => false, 'textarea_rows' => 5)); ?>
										<p class="description">{company_name} {website_url} {sender_email} {sender_name} {order_number} {order_type} {amount} {customer_name} {recipient_name} {customer_email} {customer_address} {customer_postcode} {coupon_code} {pdf_link} {payment_method} {payment_status} {receipt_link}</p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="admin_email_body"><?php echo esc_html_e('Admin Email Body', 'gift-voucher'); ?></label>
										<p class="description"><?php echo esc_html_e('Body message for emails send to customers.', 'gift-voucher'); ?></p>
									</th>
									<td>
										<?php wp_editor((stripslashes($adminemailbody)), 'admin_email_body', array('wpautop' => false, 'media_buttons' => false)); ?>
										<p class="description">{company_name} {website_url} {sender_email} {sender_name} {order_number} {order_type} {amount} {customer_name} {recipient_name} {customer_email} {customer_address} {customer_postcode} {coupon_code} {pdf_link} {payment_method} {payment_status} {receipt_link}</p>
									</td>
								</tr>
							</tbody>
						</table>
						<table id="custom" class="form-table tab-content">
							<tbody>
								<tr>
									<th colspan="2" style="padding-bottom:0">
										<h3><?php echo esc_html_e('Custom CSS', 'gift-voucher'); ?></h3>
									</th>
								</tr>
								<tr>
									<td colspan="2">
										<textarea name="wpgv_custom_css" id="wpgv_custom_css" class="regular-text" aria-required="true" rows="4" style="width: 100%;height: 200px;"><?php echo esc_html(stripslashes($wpgv_custom_css)); ?></textarea>
									</td>
								</tr>
							</tbody>
						</table>
						<p class="submit"><?php submit_button(__('Save Settings', 'gift-voucher'), 'primary', 'submit', false); ?></p>
					</form>
				</div>
			</div>

			<div class="wpgiftv-col25">
				<div class="white-box rating-box">
					<h2><?php esc_html_e('Rate Our Plugin', 'gift-voucher'); ?></h2>
					<div class="star-ratings">
						<span class="dashicons dashicons-star-filled"></span>
						<span class="dashicons dashicons-star-filled"></span>
						<span class="dashicons dashicons-star-filled"></span>
						<span class="dashicons dashicons-star-filled"></span>
						<span class="dashicons dashicons-star-filled"></span>
					</div>
					<p><?php esc_html_e('Did WordPress Gift Voucher Plugin help you out? Please leave a 5-star review. Thank you!', 'gift-voucher'); ?></p>
					<a href="<?php echo esc_url('https://wordpress.org/support/plugin/gift-voucher/reviews/#new-post'); ?>" target="_blank" class="button button-primary">
						<?php esc_html_e('Write a review', 'gift-voucher'); ?>
					</a>
				</div>

				<div class="white-box">
					<h2><?php esc_html_e('Gift Cards (Gift Vouchers and Packages)', 'gift-voucher'); ?></h2>
					<h4><?php esc_html_e('Changelog', 'gift-voucher'); ?></h4>
					<p><?php esc_html_e("See what's new in", 'gift-voucher'); ?>
						<a href="<?php echo esc_url('https://wordpress.org/plugins/gift-voucher/#developers'); ?>" target="_blank">
							<?php
							// translators: %s is the plugin version.
							echo esc_html(sprintf(__('version %s', 'gift-voucher'), WPGIFT_VERSION));
							?>
						</a>.
					</p>
					<h4><?php esc_html_e('Resources', 'gift-voucher'); ?></h4>
					<ul>
						<li><a href="<?php echo esc_url('https://www.wp-giftcard.com/'); ?>" target="_blank"><i aria-hidden="true" class="dashicons dashicons-external"></i> <?php esc_html_e('Website', 'gift-voucher'); ?></a></li>
						<li><a href="<?php echo esc_url('https://www.wp-giftcard.com/docs/documentation/'); ?>" target="_blank"><i aria-hidden="true" class="dashicons dashicons-external"></i> <?php esc_html_e('Documentation', 'gift-voucher'); ?></a></li>
						<li><a href="<?php echo esc_url('https://wp-sofa.chat/'); ?>" target="_blank"><i aria-hidden="true" class="dashicons dashicons-external"></i> <?php esc_html_e('Support', 'gift-voucher'); ?></a></li>
						<li><a href="<?php echo esc_url('https://www.wp-giftcard.com/'); ?>" target="_blank"><i aria-hidden="true" class="dashicons dashicons-external"></i> <?php esc_html_e('Pro', 'gift-voucher'); ?></a></li>
					</ul>
				</div>

				<div class="white-box">
					<h2><?php esc_html_e('Having Issues?', 'gift-voucher'); ?></h2>
					<p><?php esc_html_e('Need a helping hand? Please ask for help on the Support forum. Be sure to mention your WordPress version and give as much additional information as possible.', 'gift-voucher'); ?>
						<a href="<?php echo esc_url('https://wp-sofa.chat/'); ?>" target="_blank"><?php esc_html_e('Support forum', 'gift-voucher'); ?></a>.
					</p>
					<a href="<?php echo esc_url('https://wp-sofa.chat/'); ?>" class="button button-primary" target="_blank">
						<?php esc_html_e('Submit your question', 'gift-voucher'); ?>
					</a>
				</div>

				<div class="white-box">
					<h2><?php esc_html_e('Customization Service', 'gift-voucher'); ?></h2>
					<p><?php esc_html_e('We are a European Company. To hire our agency to help you with this plugin installation or any other customization or requirements please contact us through our site contact form or email us directly.', 'gift-voucher'); ?>
						<a href="<?php echo esc_url('https://wp-sofa.chat/'); ?>" target="_blank"><?php esc_html_e('contact form', 'gift-voucher'); ?></a>
						<?php esc_html_e('or email', 'gift-voucher'); ?>
						<a href="mailto:gdpr@codemenschen.at">gdpr@codemenschen.at</a>
						<?php esc_html_e('directly.', 'gift-voucher'); ?>
					</p>
					<a href="<?php echo esc_url('https://wp-sofa.chat/'); ?>" class="button button-primary" target="_blank">
						<?php esc_html_e('Hire Us Now', 'gift-voucher'); ?>
					</a>
				</div>

				<div class="image-banner" style="margin-bottom: 10px;">
					<a href="<?php echo esc_url('https://wp-sofa.chat/'); ?>" target="_blank">
						<img src="<?php echo esc_url(WPGIFT__PLUGIN_URL . '/assets/img/banner-8.png'); ?>" style="width: 100%;">
					</a>
				</div>

				<div class="image-banner" style="margin-bottom: 10px;">
					<a href="<?php echo esc_url('https://wp-sofa.chat/'); ?>" target="_blank">
						<img src="<?php echo esc_url(WPGIFT__PLUGIN_URL . '/assets/img/banner-9.png'); ?>" style="width: 100%;">
					</a>
				</div>
			</div>

		</div>
		<span class="wpgiftv-disclaimer">
			<?php
			// translators: %s is the plugin name in bold.
			printf(esc_html__('Thank you for using %s.', 'gift-voucher'), '<b>WordPress Gift Voucher</b>');
			?>
		</span>

	</div>
<?php }
