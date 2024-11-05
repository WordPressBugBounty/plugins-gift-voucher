<?php

// Gift Card

if (!defined('ABSPATH')) exit;  // Exit if accessed directly

use PayPalCheckoutSdk\Orders\OrdersCreateRequest;

function wpgv__doajax_gift_card_pdf_save_func()
{
	global $wpdb;
	$voucher_table 	= $wpdb->prefix . 'giftvouchers_list';
	$setting_options = get_data_settings_voucher();
	//get data ajax
	$buying_for = 'someone_else';
	$idVoucher = sanitize_text_field(base64_decode($_POST['idVoucher']));
	$priceExtraCharges = sanitize_text_field(base64_decode($_POST['priceExtraCharges']));
	$priceVoucher = sanitize_text_field(base64_decode($_POST['priceVoucher']));
	$from = isset($_POST['giftTo']) ? sanitize_text_field(base64_decode($_POST['giftTo'])) : '';
	$for = sanitize_text_field(base64_decode($_POST['giftFrom']));
	$message = sanitize_textarea_field(base64_decode($_POST['giftDescription']));
	$email = sanitize_text_field(base64_decode($_POST['giftEmail']));
	$code = sanitize_text_field(base64_decode($_POST['couponcode']));
	$shipping_email = sanitize_text_field(base64_decode($_POST['recipientEmail']));
	$shipping = sanitize_text_field(base64_decode($_POST['nameShipping']));
	$check_shipping = sanitize_text_field(base64_decode($_POST['type_shipping']));
	$firstname = isset($_POST['fisrtName']) ? sanitize_text_field(base64_decode($_POST['fisrtName'])) : '';
	$lastname = isset($_POST['lastName']) ? sanitize_text_field(base64_decode($_POST['lastName'])) : '';
	$address = isset($_POST['address']) ? sanitize_text_field(base64_decode($_POST['address'])) : '';
	$city = isset($_POST['city']) ? sanitize_text_field(base64_decode($_POST['city'])) : '';
	$pincode = isset($_POST['postcode']) ? sanitize_text_field(base64_decode($_POST['postcode'])) : '';
	$shipping_method = isset($_POST['shippingMethod']) ? sanitize_text_field(base64_decode($_POST['shippingMethod'])) : '';
	$paymentmethod = sanitize_text_field(base64_decode($_POST['pay_method']));
	$typeGiftCard = sanitize_text_field(base64_decode($_POST['typeGiftCard']));
	$send_email_date_time = isset($_POST['send_email_date_time']) ? sanitize_text_field(base64_decode($_POST['send_email_date_time'])) : 'send_instantly';
	$wpgv_customer_receipt = get_option('wpgv_customer_receipt') ? get_option('wpgv_customer_receipt') : 0;
	// check exp
	$wpgv_hide_expiry = get_option('wpgv_hide_expiry') ? get_option('wpgv_hide_expiry') : 'yes';
	$wpgv_expiry_date_format = get_option('wpgv_expiry_date_format') ? get_option('wpgv_expiry_date_format') : 'd.m.Y';
	$wpgv_add_extra_charges = get_option('wpgv_add_extra_charges_voucher') ? get_option('wpgv_add_extra_charges_voucher') : 0;

	$voucher_expiry_value = !empty(esc_html(get_post_meta($voucher_id, 'wpgv_customize_template_voucher_expiry_value', true))) ? esc_html(get_post_meta($voucher_id, 'wpgv_customize_template_voucher_expiry_value', true)) : $setting_options->voucher_expiry; // format day and number
	if ($wpgv_hide_expiry == 'no') {
		$expiry = __('No Expiry', 'gift-voucher');
	} else {
		$expiry = ($setting_options->voucher_expiry_type == 'days') ? gmdate($wpgv_expiry_date_format, strtotime('+' . $voucher_expiry_value . ' days', time())) . PHP_EOL : $voucher_expiry_value;
	}
	//get image
	if ($setting_options->is_style_choose_enable) {
		$voucher_style = sanitize_text_field(base64_decode($_POST['style']));
		$image_attributes = get_attached_file($images[$voucher_style]);
		$image = ($image_attributes) ? $image_attributes : get_option('wpgv_demoimageurl_voucher');
		$stripeimage = (wp_get_attachment_image_src($images[$voucher_style])) ? wp_get_attachment_image_src($images[$voucher_style]) : get_option('wpgv_demoimageurl_voucher');
	} else {
		$voucher_style = 0;
		$image_attributes = get_attached_file($images[0]);
		$image = ($image_attributes) ? $image_attributes : get_option('wpgv_demoimageurl_voucher');
		$stripeimage = (wp_get_attachment_image_src($images[0])) ? wp_get_attachment_image_src($images[0]) : get_option('wpgv_demoimageurl_voucher');
	}
	//updaload image
	// Khởi tạo WP_Filesystem
	require_once(ABSPATH . 'wp-admin/includes/file.php');
	WP_Filesystem();

	global $wp_filesystem;

	$upload = wp_upload_dir();
	$upload_dir = $upload['basedir'] . '/voucherpdfuploads/';

	// Kiểm tra xem thư mục có tồn tại không, nếu không thì tạo nó
	if (!$wp_filesystem->exists($upload_dir)) {
		$wp_filesystem->mkdir($upload_dir, 0755);
	}

	$image = sanitize_text_field(base64_decode($_POST["urlImage"]));
	$image = str_replace('data:image/png;base64,', '', $image);
	$image = str_replace(' ', '+', $image);
	$image = base64_decode($image);

	// Ghi nội dung vào file bằng WP_Filesystem
	$image_path = $upload_dir . "giftcard.png";
	$wp_filesystem->put_contents($image_path, $image, FS_CHMOD_FILE);

	// Lấy kích thước hình ảnh
	$sizeimage = getimagesize($image_path);

	// Nếu cần, bạn có thể lưu URL của thư mục
	$dirUrl = $upload['baseurl'] . '/voucherpdfuploads/';

	$pdf = new PDF();
	if (!empty($sizeimage)) {
		if ($typeGiftCard == 'landscape') {
			$pdf->AddPage("L", 'a4');
		} else {
			$pdf->AddPage('P', 'a4');
		}
		$pdf->Image($upload_dir . "giftcard.png", 0, 0, $pdf->GetPageWidth(), $pdf->GetPageHeight());
	} else {
		$pdf->AddPage("L");
		$pdf->centreImage($upload_dir . "giftcard.png");
	}
	$curr_time = time();
	$upload = wp_upload_dir();
	$upload_dir = $upload['basedir'];
	$upload_dir = $upload_dir . '/voucherpdfuploads/' . $curr_time . $code . '.pdf';
	$pdf->output($upload_dir, 'F');
	$upload_url = $curr_time . $code;
	$wpdb->insert(
		$voucher_table,
		array(
			'order_type'		=> 'vouchers',
			'template_id' 		=> $idVoucher, //
			'buying_for'		=> $buying_for,
			'from_name' 		=> $for,
			'to_name' 			=> $from, //
			'amount'			=> $priceVoucher, //
			'message'			=> $message,
			'shipping_type'		=> $check_shipping,
			'shipping_email'	=> $shipping_email,
			'firstname'			=> $firstname, //
			'lastname'			=> $lastname,
			'email'				=> $email,
			'address'			=> $address,
			'postcode'			=> $pincode, //
			'shipping_method'	=> $shipping_method,
			'pay_method'		=> $paymentmethod,
			'expiry'			=> $expiry,
			'couponcode'		=> $code, //
			'voucherpdf_link'	=> $upload_url,
			'status'			=> 'unused',
			'payment_status'	=> 'Not Pay',
			'voucheradd_time'	=> current_time('mysql'),
			'check_send_mail'	=> 'unsent',
			//'email_send_date_time' => $send_email_date_time
		),
		array('%s', '%d', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
	);
	$lastid = $wpdb->insert_id;
	WPGV_Gift_Voucher_Activity::record($lastid, 'create', '', 'Voucher ordered by ' . $for . ', Message: ' . $message);
	$titleVoucher = get_the_title($idVoucher);
	//shipping as post
	$shipping_charges = 0;
	if ($check_shipping != 'shipping_as_email') {
		$preshipping_methods = explode(',', $setting_options->shipping_method);
		foreach ($preshipping_methods as $method) {
			$preshipping_method = explode(':', $method);
			if (trim(stripslashes($preshipping_method[1])) == trim(stripslashes($shipping_method))) {
				$shipping_charges = trim($preshipping_method[0]);
				break;
			}
		}
	}
	$value = $priceVoucher + $priceExtraCharges + $shipping_charges;
	//Customer Receipt
	if ($wpgv_customer_receipt) {
		$upload_dir = $upload['basedir'];
		$receiptupload_dir = $upload_dir . '/voucherpdfuploads/' . $curr_time . $code . '-receipt.pdf';
		require_once(WPGIFT__PLUGIN_DIR . '/templates/pdfstyles/receipt.php');
		if ($wpgv_enable_pdf_saving) {
			$receipt->Output($receiptupload_dir, 'F');
		} else {
			$receipt->Output('F', $receiptupload_dir);
		}
	}
	$currency = wpgv_price_format($value);
	update_post_meta($lastid, 'wpgv_extra_charges', wpgv_price_format($priceExtraCharges));
	update_post_meta($lastid, 'wpgv_total_payable_amount', $currency);
	$success_url = get_site_url() . '/voucher-payment-successful/?voucheritem=' . $lastid;
	$cancel_url = get_site_url() . '/voucher-payment-cancel/?voucheritem=' . $lastid;
	$notify_url = get_site_url() . '/voucher-payment-successful/?voucheritem=' . $lastid;
	//check payment

	$wpgv_paypal_client_id = get_option('wpgv_paypal_client_id') ? get_option('wpgv_paypal_client_id') : '';
	$wpgv_paypal_secret_key = get_option('wpgv_paypal_secret_key') ? get_option('wpgv_paypal_secret_key') : '';

	if ($paymentmethod == 'Paypal') {
		if (empty($wpgv_paypal_client_id) || empty($wpgv_paypal_secret_key)) {
			$error_message = "PayPal Client ID or Secret Key are both empty.";
			wp_send_json_error(array('message' => $error_message));
		} else {
			require_once(WPGIFT__PLUGIN_DIR . '/vendor/autoload.php');
			require_once(WPGIFT__PLUGIN_DIR . '/include/PayPalAuth.php');

			if (!PayPalAuth::isCredentialsValid()) {
				$error_message = "PayPal Client ID or Secret Key is invalid or the PayPal Mode is not compatible with the provided credentials.";
				wp_send_json_error(array('message' => $error_message));
			} else {
				$client = PayPalAuth::client();
				$request = new OrdersCreateRequest();
				$request->prefer('return=representation');
				$request->body = [
					"intent" => "CAPTURE",
					"purchase_units" => [[
						"reference_id" => $template_options->title,
						"amount" => [
							"value" => $value,
							"currency_code" => $setting_options->currency_code
						]
					]],
					"application_context" => [
						"cancel_url" => $cancel_url,
						"return_url" => $success_url
					]
				];

				try {
					// Call API with your client and get a response for your call
					$response = $client->execute($request);
					session_start();
					$_SESSION["paypal_order_id"] = strval($response->result->id);
					// If call returns body in response, you can get the deserialized version from the result attribute of the response
					$approve_link = '';

					// If call returns body in response, you can get the deserialized version from the result attribute of the response
					foreach ($response->result->links as $link) {
						if ($link->rel == "approve") {
							$approve_link = $link->href;
						}
					}

					// Check if the approve_link is empty
					if (!empty($approve_link)) {
						wp_send_json_success(array('message' => "PayPal valid.", 'approve_link' => $approve_link));
					} else {
						wp_send_json_error(array('message' => "No PayPal approve link found."));
					}
				} catch (HttpException $ex) {
					echo esc_html($ex->statusCode);
					print_r($ex->getMessage());
				}
			}
		}
	} elseif ($paymentmethod == 'Sofort') {

		$Sofortueberweisung = new Sofortueberweisung($setting_options->sofort_configure_key);

		$Sofortueberweisung->setAmount($value);
		$Sofortueberweisung->setCurrencyCode($setting_options->currency_code);

		$Sofortueberweisung->setReason($setting_options->reason_for_payment, $lastid);
		$Sofortueberweisung->setSuccessUrl($success_url, true);
		$Sofortueberweisung->setAbortUrl($cancel_url);
		// $Sofortueberweisung->setNotificationUrl($notify_url);

		$Sofortueberweisung->sendRequest();

		if ($Sofortueberweisung->isError()) {
			//SOFORT-API didn't accept the data
			$error_message = "Sofort is invalid.";
			wp_send_json_error(array('message' => $error_message));
		} else {
			//buyer must be redirected to $paymentUrl else payment cannot be successfully completed!
			$paymentUrl = $Sofortueberweisung->getPaymentUrl();
			wp_send_json_success(array('message' => "Sofort valid.", 'approve_link' => $paymentUrl));
		}
	} elseif ($paymentmethod == 'Stripe') {

		if (empty($setting_options->stripe_publishable_key) || empty($setting_options->stripe_secret_key)) {
			$error_message = "Stripe Publishable key or Stripe Secret Key are both empty.";
			wp_send_json_error(array('message' => $error_message));
		} else {
			$stripeimage = $dirUrl . "giftcard.png";
			$stripesuccesspageurl = get_option('wpgv_stripesuccesspage');

			//set api key
			$stripe = array(
				"publishable_key" => $setting_options->stripe_publishable_key,
				"secret_key"      => $setting_options->stripe_secret_key,
			);

			$camount = ($value) * 100;
			$stripeemail = ($giftEmail) ? $giftEmail : $giftEmail;

			\Stripe\Stripe::setApiKey($stripe['secret_key']);

			$is_stripe_ideal_enable = get_option('wpgv_stripe_ideal');

			// fix new
			if ($is_stripe_ideal_enable == 1) {
				$session = \Stripe\Checkout\Session::create([
					'payment_method_types' => ['card', 'ideal'],
					'line_items' => [[
						'price_data' => [
							'currency' => $setting_options->currency_code,
							'unit_amount' => $camount,
							'product_data' => [
								'name' => $titleVoucher,
								'images' => [wp_sanitize_redirect($stripeimage)],
							],
						],
						'quantity' => 1,
					]],
					'mode' => 'payment',
					'success_url' => get_page_link($stripesuccesspageurl) . '/?voucheritem=' . $lastid . '&sessionid={CHECKOUT_SESSION_ID}',
					'cancel_url' => $cancel_url,
				]);
			} else {
				$session = \Stripe\Checkout\Session::create([
					'payment_method_types' => ['card'],
					'line_items' => [[
						'price_data' => [
							'currency' => $setting_options->currency_code,
							'unit_amount' => $camount,
							'product_data' => [
								'name' => $titleVoucher,
								'images' => [wp_sanitize_redirect($stripeimage)],
							],
						],
						'quantity' => 1,
					]],
					'mode' => 'payment',
					'success_url' => get_page_link($stripesuccesspageurl) . '/?voucheritem=' . $lastid . '&sessionid={CHECKOUT_SESSION_ID}',
					'cancel_url' => $cancel_url,
				]);
			}


			$stripesuccesspageurl = get_option('wpgv_stripesuccesspage');
			$stripeemail = ($giftEmail) ? $giftEmail : $giftEmail;
			wp_send_json_success(array('message' => "Stripe valid.", 'approve_link' => $session->url));
		}
	} elseif ($paymentmethod == 'Per Invoice') {
		wp_send_json_success(array('message' => "Per Invoice valid.", 'approve_link' => $success_url . '&per_invoice=1'));
	}
	die();
}
add_action('wp_ajax_nopriv_wpgv_save_gift_card', 'wpgv__doajax_gift_card_pdf_save_func');
add_action('wp_ajax_wpgv_save_gift_card', 'wpgv__doajax_gift_card_pdf_save_func');
// PDF
class PDF extends FPDF
{
	const DPI = 96;
	const MM_IN_INCH = 25.4;
	const A4_HEIGHT = 297;
	const A4_WIDTH = 210;
	const MAX_WIDTH = 800;
	const MAX_HEIGHT = 500;
	function pixelsToMM($val)
	{
		return $val * self::MM_IN_INCH / self::DPI;
	}
	function resizeToFit($imgFilename)
	{
		list($width, $height) = getimagesize($imgFilename);
		$widthScale = self::MAX_WIDTH / $width;
		$heightScale = self::MAX_HEIGHT / $height;
		$scale = min($widthScale, $heightScale);
		return array(
			round($this->pixelsToMM($scale * $width)),
			round($this->pixelsToMM($scale * $height))
		);
	}
	function centreImage($img)
	{
		list($width, $height) = $this->resizeToFit($img);
		// you will probably want to swap the width/height
		// around depending on the page's orientation
		$this->Image(
			$img,
			(self::A4_HEIGHT - $width) / 2,
			(self::A4_WIDTH - $height) / 2,
			$width,
			$height
		);
	}
}
