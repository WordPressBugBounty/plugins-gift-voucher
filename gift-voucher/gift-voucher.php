<?php

/**
 * Plugin Name: Gift Cards (Gift Vouchers and Packages) (WooCommerce Supported)
 * Description: Let your customers buy gift cards/certificates for your services & products directly on your website.
 * Plugin URI: https://wp-giftcard.com/
 * Author: Codemenschen GmbH
 * Author URI: https://www.codemenschen.at/
 * Version: 4.7.2
 * Text Domain: gift-voucher
 * Domain Path: /languages
 * License: GNU General Public License v2.0 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Plugin Variable: wpgiftv
 *
 * @package         Gift Cards
 * @author          Patrick Fuchshofer
 * @copyright       Copyright (c) 2020
 *
 */

if (!defined('ABSPATH')) exit;  // Exit if accessed directly

// Start an early output buffer to capture any accidental output from included
// libraries (fonts, third-party files with closing PHP tags, etc.). We will
// discard this buffer on 'init' and start a fresh one to avoid leaking any
// characters during plugin activation.
if (!ob_get_level()) {
  ob_start();
  add_action('init', function () {
    // Discard any output generated during plugin file inclusion
    while (ob_get_level()) {
      @ob_end_clean();
    }
    // Start a fresh buffer for normal runtime output handling
    ob_start();
  });
}

define('WPGIFT_VERSION', '4.7.2');
define('WPGIFT__MINIMUM_WP_VERSION', '4.0');
define('WPGIFT__PLUGIN_DIR', untrailingslashit(plugin_dir_path(__FILE__)));
define('WPGIFT__PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));
define('WPGIFT_PLUGIN_ROOT', plugin_dir_path(__FILE__));
define('WPGIFT_SESSION_KEY', 'wpgv-gift-voucher-data');
define('WPGIFT_INSTALL_DATE', 'wpgv-install-date');
define('WPGIFT_ADMIN_NOTICE_KEY', 'wpgv-hide-notice');
define('WPGV_PRODUCT_TYPE_SLUG', 'gift_voucher');
define('WPGV_PRODUCT_TYPE_NAME', 'Gift Voucher');
define('WPGV_DENOMINATION_ATTRIBUTE_SLUG', 'gift-voucher-amount');
define('WPGV_MAX_MESSAGE_CHARACTERS', 500);
define('WPGV_RECIPIENT_LIMIT', 999);
define('WPGV_GIFT_VOUCHER_NUMBER_META_KEY', 'wpgv_gift_voucher_number');
define('WPGV_AMOUNT_META_KEY', 'wpgv_gift_voucher_amount');
define('WPGV_YOUR_NAME_META_KEY', 'wpgv_your_name');
define('WPGV_RECIPIENT_NAME_META_KEY', 'wpgv_recipient_name');
define('WPGV_RECIPIENT_EMAIL_META_KEY', 'wpgv_recipient_email');
define('WPGV_YOUR_EMAIL_META_KEY', 'wpgv_your_email');
define('WPGV_MESSAGE_META_KEY', 'wpgv_message');

if (!class_exists('WP_List_Table')) {
  require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}
function wpgv_db_table_exists($table_name)
{
  global $wpdb;

  return (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table_name)));
}

function wpgv_db_column_exists($table_name, $column_name)
{
  global $wpdb;

  if (!wpgv_db_table_exists($table_name)) {
    return false;
  }

  return (bool) $wpdb->get_var("SHOW COLUMNS FROM `{$table_name}` LIKE '{$column_name}'");
}

function wpgv_db_has_unique_index_for_column($table_name, $column_name)
{
  global $wpdb;

  if (!wpgv_db_table_exists($table_name)) {
    return false;
  }

  $column_name = sanitize_key($column_name);
  if ($column_name === '') {
    return false;
  }

  $query = $wpdb->prepare(
    "SHOW INDEX FROM `{$table_name}` WHERE Column_name = %s AND Non_unique = 0",
    $column_name
  );

  return (bool) $wpdb->get_var($query);
}

function wpgv_couponcode_has_duplicates()
{
  global $wpdb;

  $table_name = $wpdb->prefix . 'giftvouchers_list';
  if (!wpgv_db_table_exists($table_name) || !wpgv_db_column_exists($table_name, 'couponcode')) {
    return false;
  }

  $duplicate_code = $wpdb->get_var(
    "SELECT couponcode
    FROM `{$table_name}`
    GROUP BY couponcode
    HAVING COUNT(*) > 1
    LIMIT 1"
  );

  return !empty($duplicate_code);
}

function wpgv_normalize_decimal_amount($raw_value)
{
  if (is_int($raw_value) || is_float($raw_value)) {
    return round((float) $raw_value, 2);
  }

  $raw_value = sanitize_text_field(wp_unslash((string) $raw_value));
  $raw_value = preg_replace('/\s+/', '', $raw_value);
  $raw_value = preg_replace('/[^0-9,.\-]/', '', $raw_value);

  if ($raw_value === '' || $raw_value === null) {
    return null;
  }

  if (strpos($raw_value, ',') !== false && strpos($raw_value, '.') !== false) {
    $raw_value = str_replace(',', '', $raw_value);
  } elseif (strpos($raw_value, ',') !== false) {
    $raw_value = str_replace(',', '.', $raw_value);
  }

  if (!is_numeric($raw_value)) {
    return null;
  }

  return round((float) $raw_value, 2);
}

function wpgv_get_stripe_amount_minor_units($raw_amount)
{
  $amount = wpgv_normalize_decimal_amount($raw_amount);
  if ($amount === null) {
    return null;
  }

  return (int) round($amount * 100);
}

function wpgv_get_stripe_order_binding_metadata($voucher_id, $order_key, $raw_amount, $currency_code)
{
  $amount_minor = wpgv_get_stripe_amount_minor_units($raw_amount);

  return array(
    'voucher_id'   => (string) absint($voucher_id),
    'order_key'    => sanitize_text_field((string) $order_key),
    'amount_minor' => $amount_minor === null ? '' : (string) $amount_minor,
    'currency'     => strtoupper(sanitize_text_field((string) $currency_code)),
  );
}

function wpgv_normalize_stripe_metadata($metadata)
{
  if ($metadata instanceof \Stripe\StripeObject) {
    $metadata = $metadata->toArray();
  } elseif (is_object($metadata)) {
    $metadata = get_object_vars($metadata);
  }

  if (!is_array($metadata)) {
    return array();
  }

  return array_map(
    static function ($value) {
      return sanitize_text_field((string) $value);
    },
    $metadata
  );
}

function wpgv_stripe_metadata_matches_expected($metadata, $expected_metadata)
{
  $metadata = wpgv_normalize_stripe_metadata($metadata);

  foreach ($expected_metadata as $key => $expected_value) {
    $expected_value = sanitize_text_field((string) $expected_value);
    if ($expected_value === '' || !isset($metadata[$key]) || !hash_equals($expected_value, (string) $metadata[$key])) {
      return false;
    }
  }

  return true;
}

function wpgv_is_stripe_checkout_session_bound_to_voucher($checkout_session, $expected_metadata)
{
  if (!is_object($checkout_session) || !method_exists($checkout_session, 'jsonSerialize')) {
    return false;
  }

  $session_data = $checkout_session->jsonSerialize();
  $expected_amount_minor = isset($expected_metadata['amount_minor']) ? (int) $expected_metadata['amount_minor'] : null;
  $expected_currency = isset($expected_metadata['currency']) ? strtoupper($expected_metadata['currency']) : '';
  $expected_voucher_id = isset($expected_metadata['voucher_id']) ? (string) $expected_metadata['voucher_id'] : '';

  if (($session_data['mode'] ?? '') !== 'payment') {
    return false;
  }

  if (($session_data['payment_status'] ?? '') !== 'paid') {
    return false;
  }

  if (isset($session_data['status']) && $session_data['status'] !== 'complete') {
    return false;
  }

  if (
    isset($session_data['client_reference_id']) &&
    $expected_voucher_id !== '' &&
    (string) $session_data['client_reference_id'] !== $expected_voucher_id
  ) {
    return false;
  }

  if ($expected_amount_minor === null || !isset($session_data['amount_total']) || (int) $session_data['amount_total'] !== $expected_amount_minor) {
    return false;
  }

  if ($expected_currency === '' || strtoupper((string) ($session_data['currency'] ?? '')) !== $expected_currency) {
    return false;
  }

  return wpgv_stripe_metadata_matches_expected($session_data['metadata'] ?? array(), $expected_metadata);
}

function wpgv_is_stripe_payment_intent_bound_to_voucher($payment_intent, $expected_metadata)
{
  if (!is_object($payment_intent)) {
    return false;
  }

  $expected_amount_minor = isset($expected_metadata['amount_minor']) ? (int) $expected_metadata['amount_minor'] : null;
  $expected_currency = isset($expected_metadata['currency']) ? strtoupper($expected_metadata['currency']) : '';

  if (($payment_intent->status ?? '') !== 'succeeded') {
    return false;
  }

  if ($expected_amount_minor === null || !isset($payment_intent->amount) || (int) $payment_intent->amount !== $expected_amount_minor) {
    return false;
  }

  if ($expected_currency === '' || strtoupper((string) ($payment_intent->currency ?? '')) !== $expected_currency) {
    return false;
  }

  return wpgv_stripe_metadata_matches_expected($payment_intent->metadata ?? array(), $expected_metadata);
}

function wpgv_get_public_voucher_value_limits($setting_options)
{
  $min_amount = isset($setting_options->voucher_min_value) && $setting_options->voucher_min_value !== ''
    ? max(0, (float) $setting_options->voucher_min_value)
    : 1.0;
  $max_amount = isset($setting_options->voucher_max_value) && $setting_options->voucher_max_value !== ''
    ? max(0, (float) $setting_options->voucher_max_value)
    : 10000.0;

  if ($max_amount > 0 && $max_amount < $min_amount) {
    $max_amount = $min_amount;
  }

  return array($min_amount, $max_amount);
}

function wpgv_validate_public_voucher_amount($raw_value, $setting_options)
{
  $amount = wpgv_normalize_decimal_amount($raw_value);
  if ($amount === null) {
    return new WP_Error('wpgv_invalid_amount', __('Invalid voucher amount supplied.', 'gift-voucher'));
  }

  list($min_amount, $max_amount) = wpgv_get_public_voucher_value_limits($setting_options);

  if ($amount <= 0) {
    return new WP_Error('wpgv_invalid_amount', __('Voucher amount must be greater than zero.', 'gift-voucher'));
  }

  if ($min_amount > 0 && $amount < $min_amount) {
    return new WP_Error(
      'wpgv_amount_too_low',
      sprintf(
        /* translators: %s: minimum voucher value */
        __('Voucher amount must be at least %s.', 'gift-voucher'),
        wpgv_price_format($min_amount)
      )
    );
  }

  if ($max_amount > 0 && $amount > $max_amount) {
    return new WP_Error(
      'wpgv_amount_too_high',
      sprintf(
        /* translators: %s: maximum voucher value */
        __('Voucher amount must not exceed %s.', 'gift-voucher'),
        wpgv_price_format($max_amount)
      )
    );
  }

  return $amount;
}

function wpgv_get_configured_extra_charge($option_name)
{
  $extra_charge = get_option($option_name);
  $amount = wpgv_normalize_decimal_amount($extra_charge);

  return ($amount === null || $amount < 0) ? 0.0 : $amount;
}

function wpgv_get_shipping_charge_amount($shipping_type, $shipping_method, $setting_options)
{
  $shipping_type = sanitize_text_field((string) $shipping_type);
  $shipping_method = sanitize_text_field((string) $shipping_method);

  if ($shipping_type !== 'shipping_as_post') {
    return 0.0;
  }

  if ($shipping_method === '') {
    return new WP_Error('wpgv_invalid_shipping_method', __('Invalid shipping method selected.', 'gift-voucher'));
  }

  $configured_methods = isset($setting_options->shipping_method) ? (string) $setting_options->shipping_method : '';
  foreach (explode(',', $configured_methods) as $method) {
    if ($method === '') {
      continue;
    }

    $parts = explode(':', $method, 2);
    $configured_amount = wpgv_normalize_decimal_amount($parts[0] ?? '');
    $configured_label = trim(stripslashes($parts[1] ?? ''));

    if ($configured_label !== '' && hash_equals($configured_label, $shipping_method)) {
      return ($configured_amount === null || $configured_amount < 0) ? 0.0 : $configured_amount;
    }
  }

  return new WP_Error('wpgv_invalid_shipping_method', __('Invalid shipping method selected.', 'gift-voucher'));
}

function wpgv_couponcode_exists($couponcode, $exclude_id = 0)
{
  global $wpdb;

  $couponcode = sanitize_text_field((string) $couponcode);
  if ($couponcode === '') {
    return false;
  }

  $table_name = $wpdb->prefix . 'giftvouchers_list';
  if ($exclude_id > 0) {
    $existing_id = $wpdb->get_var(
      $wpdb->prepare(
        "SELECT id FROM `{$table_name}` WHERE couponcode = %s AND id != %d LIMIT 1",
        $couponcode,
        absint($exclude_id)
      )
    );
  } else {
    $existing_id = $wpdb->get_var(
      $wpdb->prepare(
        "SELECT id FROM `{$table_name}` WHERE couponcode = %s LIMIT 1",
        $couponcode
      )
    );
  }

  return !empty($existing_id);
}

function wpgv_generate_unique_couponcode($max_attempts = 50)
{
  $max_attempts = max(1, (int) $max_attempts);

  for ($attempt = 0; $attempt < $max_attempts; $attempt++) {
    try {
      $couponcode = (string) random_int(1000000000000000, 9999999999999999);
    } catch (Exception $exception) {
      return new WP_Error('wpgv_couponcode_rng_failed', __('Unable to generate a secure voucher code.', 'gift-voucher'));
    }

    if (!wpgv_couponcode_exists($couponcode)) {
      return $couponcode;
    }
  }

  return new WP_Error('wpgv_couponcode_generation_failed', __('Unable to generate a unique voucher code.', 'gift-voucher'));
}

function wpgv_sanitize_voucher_pdf_basename($value)
{
  $value = sanitize_file_name((string) $value);
  $value = preg_replace('/[^A-Za-z0-9_-]/', '', $value);

  return trim((string) $value, '._-');
}

function wpgv_get_voucher_pdf_filename($voucherpdf_link, $suffix = '')
{
  $voucherpdf_link = wpgv_sanitize_voucher_pdf_basename($voucherpdf_link);
  $suffix = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $suffix);

  if ($voucherpdf_link === '') {
    return '';
  }

  return $voucherpdf_link . $suffix . '.pdf';
}

function wpgv_get_voucher_pdf_url($voucherpdf_link, $suffix = '')
{
  $filename = wpgv_get_voucher_pdf_filename($voucherpdf_link, $suffix);
  if ($filename === '') {
    return '';
  }

  $upload = wp_get_upload_dir();

  return trailingslashit($upload['baseurl']) . 'voucherpdfuploads/' . rawurlencode($filename);
}

function wpgv_get_voucher_pdf_path($voucherpdf_link, $suffix = '')
{
  $filename = wpgv_get_voucher_pdf_filename($voucherpdf_link, $suffix);
  if ($filename === '') {
    return '';
  }

  return wpgv_pdf_get_upload_path($filename);
}

function wpgv_get_voucher_row_by_id($voucher_id)
{
  global $wpdb;

  $voucher_id = absint($voucher_id);
  if ($voucher_id <= 0) {
    return null;
  }

  return $wpdb->get_row(
    $wpdb->prepare(
      "SELECT * FROM `{$wpdb->prefix}giftvouchers_list` WHERE id = %d",
      $voucher_id
    )
  );
}

function wpgv_current_user_can_view_voucher_details($voucher_id)
{
  $voucher = wpgv_get_voucher_row_by_id($voucher_id);
  if (!$voucher) {
    return false;
  }

  if (current_user_can('manage_options') || current_user_can('manage_woocommerce')) {
    return true;
  }

  if (!is_user_logged_in()) {
    return false;
  }

  $current_user = wp_get_current_user();
  $current_email = isset($current_user->user_email) ? strtolower(trim((string) $current_user->user_email)) : '';
  if ($current_email === '') {
    return false;
  }

  $owner_emails = array_filter(array(
    strtolower(trim((string) $voucher->email)),
    strtolower(trim((string) $voucher->shipping_email)),
  ));

  return in_array($current_email, $owner_emails, true);
}

function wpgv_is_woocommerce_enable()
{
  global $wpdb;
  // Defensive: during activation/upgrade the plugin tables may not exist yet.
  // Check for the settings table before querying it to avoid warnings / output.
  $table_name = $wpdb->prefix . 'giftvouchers_setting';

  if (!wpgv_db_table_exists($table_name)) {
    // Table doesn't exist yet (activation / install context). Treat WooCommerce as disabled so
    // the conditional requires below won't include admin front-end files that may produce output.
    return false;
  }

  $options = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", 1));
  if (!$options) {
    return false;
  }

  return !empty($options->is_woocommerce_enable);
}

// Load translations and plugin files on init to avoid early translation notice (WP 6.7+)
add_action('init', function() {
  load_plugin_textdomain('gift-voucher', false, dirname(plugin_basename(__FILE__)) . '/languages');

  require_once(WPGIFT__PLUGIN_DIR . '/vendor/autoload.php');
  require_once(WPGIFT__PLUGIN_DIR . '/vendor/sofort/payment/sofortLibSofortueberweisung.inc.php');
  require_once(WPGIFT__PLUGIN_DIR . '/admin.php');
  require_once(WPGIFT__PLUGIN_DIR . '/front.php');
  require_once(WPGIFT__PLUGIN_DIR . '/giftitems.php');
  require_once(WPGIFT__PLUGIN_DIR . '/include/pdf-wrapper.php');
  require_once(WPGIFT__PLUGIN_DIR . '/classes/voucher.php');
  require_once(WPGIFT__PLUGIN_DIR . '/classes/template.php');
  require_once(WPGIFT__PLUGIN_DIR . '/classes/page_template.php');
  require_once(WPGIFT__PLUGIN_DIR . '/include/wpgv_voucher_pdf.php');
  require_once(WPGIFT__PLUGIN_DIR . '/include/wpgv_item_pdf.php');
  require_once(WPGIFT__PLUGIN_DIR . '/include/voucher_posttype.php');
  // If we are including this file during 'init' after priority 0 executed, the
  // post type registration hooks added in voucher_posttype.php won't have run
  // on this request. Register them immediately if needed so admin pages work.
  if (!post_type_exists('wpgv_voucher_product') && function_exists('wpgv_voucher_product_function')) {
    wpgv_voucher_product_function();
  }
  if (!taxonomy_exists('wpgv_voucher_category') && function_exists('wpgv_voucher_category_function')) {
    wpgv_voucher_category_function();
  }
  if (!post_type_exists('voucher_template') && function_exists('codemenschen_voucher_template')) {
    codemenschen_voucher_template();
  }
  if (!taxonomy_exists('category_voucher_template') && function_exists('codemenschen_voucher_template_category')) {
    codemenschen_voucher_template_category();
  }

  require_once(WPGIFT__PLUGIN_DIR . '/include/voucher_metabox.php');
  require_once(WPGIFT__PLUGIN_DIR . '/include/voucher-shortcodes.php');
  require_once(WPGIFT__PLUGIN_DIR . '/classes/wpgv-gift-voucher.php');
  require_once(WPGIFT__PLUGIN_DIR . '/classes/wpgv-gift-voucher-activity.php');
  require_once(WPGIFT__PLUGIN_DIR . '/giftcard.php');
  require_once(WPGIFT__PLUGIN_DIR . '/include/wpgv_giftcard_pdf.php');
  require_once(WPGIFT__PLUGIN_DIR . '/include/edit-order-voucher.php');
  require_once(WPGIFT__PLUGIN_DIR . '/include/receipt-functions.php');
  require_once(WPGIFT__PLUGIN_DIR . '/include/admin_regenerate_modern_pdf.php');
  require_once(WPGIFT__PLUGIN_DIR . '/include/admin_regenerate_standard_pdf.php');

  if (wpgv_is_woocommerce_enable()) {
    require_once(WPGIFT__PLUGIN_DIR . '/classes/wpgv-voucher-product-list.php');
    require_once(WPGIFT__PLUGIN_DIR . '/include/wc_wpgv_voucher_pdf.php');
    require_once(WPGIFT__PLUGIN_DIR . '/classes/wpgv-check-plugin-active.php');
  }
}, 1);

add_action('init', function () {
  WPGiftVoucherAdminPages::get_instance();
});


add_action('admin_init', function () {

  global $wpdb;
  $giftvouchers_list = $wpdb->prefix . 'giftvouchers_list';
  $giftvouchers_setting = $wpdb->prefix . 'giftvouchers_setting';

  if (wpgv_db_table_exists($giftvouchers_list)) {
    if (!wpgv_db_column_exists($giftvouchers_list, 'check_send_mail')) {
      $wpdb->query("ALTER TABLE `{$giftvouchers_list}` ADD check_send_mail VARCHAR(30) NOT NULL DEFAULT 'unsent'");
    }

    if (!wpgv_db_column_exists($giftvouchers_list, 'product_id')) {
      $wpdb->query("ALTER TABLE `{$giftvouchers_list}` ADD product_id BIGINT(20) UNSIGNED DEFAULT NULL");
    }

    if (!wpgv_db_column_exists($giftvouchers_list, 'order_id')) {
      $wpdb->query("ALTER TABLE `{$giftvouchers_list}` ADD order_id BIGINT(20) UNSIGNED DEFAULT NULL");
    }

    if (!wpgv_db_column_exists($giftvouchers_list, 'note_order')) {
      $wpdb->query("ALTER TABLE `{$giftvouchers_list}` ADD note_order VARCHAR(255) NOT NULL");
    }

    if (!wpgv_db_has_unique_index_for_column($giftvouchers_list, 'couponcode') && !wpgv_couponcode_has_duplicates()) {
      $wpdb->query("ALTER TABLE `{$giftvouchers_list}` ADD UNIQUE KEY `wpgv_couponcode_unique` (`couponcode`)");
    }
  }

  if (wpgv_db_table_exists($giftvouchers_setting)) {
    if (!wpgv_db_column_exists($giftvouchers_setting, 'is_order_form_enable')) {
      $wpdb->query("ALTER TABLE `{$giftvouchers_setting}` ADD is_order_form_enable TINYINT(1) DEFAULT 1");
    }

    if (wpgv_db_column_exists($giftvouchers_setting, 'portrait_mode_templates')) {
      $portrait_mode_templates = $wpdb->get_var("SELECT portrait_mode_templates FROM `{$giftvouchers_setting}` LIMIT 1");

      if (empty($portrait_mode_templates) || $portrait_mode_templates === '0') {
        $template_portail = 'template-voucher-portail-1.png, template-voucher-portail-2.png, template-voucher-portail-6.png';

        $wpdb->query($wpdb->prepare("
            UPDATE `{$giftvouchers_setting}`
            SET portrait_mode_templates = %s
            WHERE portrait_mode_templates = '' OR portrait_mode_templates IS NULL OR portrait_mode_templates = '0'
        ", $template_portail));
      }
    }
  }

  // Seed default quotes JSON if not exists
  $existing_quotes = get_option('wpgv_quotes', '');
  if ($existing_quotes === '' || $existing_quotes === false) {
    $default_quotes = array(
      __('Happy Birthday! Wishing you a day filled with joy and laughter.', 'gift-voucher'),
      __('Congratulations on your special day! Enjoy this little treat.', 'gift-voucher'),
      __('Thank you for everything you do. Hope you love this gift!', 'gift-voucher'),
      __('Wishing you all the best—may this gift bring a smile to your face!', 'gift-voucher'),
      __('With love and warm wishes—enjoy every moment!', 'gift-voucher'),
    );
    update_option('wpgv_quotes', wp_json_encode($default_quotes));
  }

  if (!current_user_can('manage_options')) {
    return false;
  }

  require_once(WPGIFT__PLUGIN_DIR . '/classes/class-nag.php');

  // Setup nag
  $nag = new WPGIFT_Nag();
  $nag->setup();
});



add_action('woocommerce_init', 'wpgv_files_loaded', 10, 1);
function wpgv_files_loaded()
{
  global $wpdb;
  $options = $wpdb->get_row("SELECT * FROM `{$wpdb->prefix}giftvouchers_setting` WHERE id = 1");

  if ($options->is_woocommerce_enable) {
    require_once(WPGIFT__PLUGIN_DIR . '/include/redeem-voucher.php');
    require_once(WPGIFT__PLUGIN_DIR . '/classes/wc-order-item-wpgv-gift-voucher.php');
    require_once(WPGIFT__PLUGIN_DIR . '/classes/data-stores/wc-order-item-wpgv-gift-voucher-data-store.php');

    require_once(WPGIFT__PLUGIN_DIR . '/classes/wpgv-gift-voucher-product.php');
    require_once(WPGIFT__PLUGIN_DIR . '/classes/wpgv-wc-product-gift-voucher.php');
    require_once(WPGIFT__PLUGIN_DIR . '/include/wpgv-product-settings.php');

    if (is_admin()) {
      require_once(WPGIFT__PLUGIN_DIR . '/admin/wpgv-gift-voucher-admin.php');
    }
  }
}

add_action('init', 'wpgv_voucher_imagesize_setup');
function wpgv_voucher_imagesize_setup()
{
  add_image_size('voucher-thumb', 300);
  add_image_size('voucher-medium', 450);
}

/** Setting menu link */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wpgv_settings_page_link');
function wpgv_settings_page_link($links)
{
  $links[] = '<a href="' . admin_url('admin.php?page=voucher-setting') . '">' . __('Settings', 'gift-voucher') . '</a>';
  $links[] = '<a href="https://www.wp-giftcard.com/docs/documentation/" target="_blank">' . __('Documentation', 'gift-voucher') . '</a>';
  return $links;
}

function wpgv_front_enqueue()
{

  global $wpdb;
  $setting_options = $wpdb->get_row("SELECT * FROM `{$wpdb->prefix}giftvouchers_setting` WHERE id = 1");


  $translations = array(
    'ajaxurl' => admin_url('admin-ajax.php'),
    'not_equal_email' => __('This email must be different from above email.', 'gift-voucher'),
    'select_template' => __('Please select voucher template', 'gift-voucher'),
    'accept_terms' => __('Please accept the terms and conditions', 'gift-voucher'),
    'finish' => __('Finish', 'gift-voucher'),
    'next' => __('Continue', 'gift-voucher'),
    'previous' => __('Back', 'gift-voucher'),
    'submitted' => __('Submitted!', 'gift-voucher'),
    'error_occur' => __('Error occurred', 'gift-voucher'),
    'total_character' => __('Total Characters', 'gift-voucher'),
    'via_post' => __('Shipping via Post', 'gift-voucher'),
    'via_email' => __('Shipping via Email', 'gift-voucher'),
    'checkemail' => __('Please check email address.', 'gift-voucher'),
    'required' => __('This field is required.', 'gift-voucher'),
    'remote' => __('Please fix this field.', 'gift-voucher'),
    'maxlength' => __('Please enter no more than {0} characters.', 'gift-voucher'),
    'email' => __('Please enter a valid email address.', 'gift-voucher'),
    'max' => __('Please enter a value less than or equal to {0}.', 'gift-voucher'),
    'min' => __('Please enter a value greater than or equal to {0}.', 'gift-voucher'),
    'preview' => __('This is Preview!', 'gift-voucher'),
    'text_value' => __('Value', 'gift-voucher'),
    'nonce'   => wp_create_nonce('wpgv_nonce_action'),
    'gift_voucher_session_nonce' => wp_create_nonce('wpgv_gift_voucher_session'),
  );
  wp_register_style('wpgv-voucher-style',  WPGIFT__PLUGIN_URL . '/assets/css/voucher-style.css');
  wp_register_style('wpgv-item-style',  WPGIFT__PLUGIN_URL . '/assets/css/item-style.css');
  wp_register_style('wpgv-slick-css',  WPGIFT__PLUGIN_URL . '/assets/css/slick.css');
  wp_register_style('wpgv-fontawesome-css',  WPGIFT__PLUGIN_URL . '/assets/css/font-awesome.min.css');
  wp_register_style('wpgv-voucher-template-fonts-css',  WPGIFT__PLUGIN_URL . '/assets/css/voucher-template-fonts.css');
  wp_register_style('wpgv-voucher-template-style-css',  WPGIFT__PLUGIN_URL . '/assets/css/voucher-template-style.css');
  wp_register_script('wpgv-konva-min-js', WPGIFT__PLUGIN_URL . '/assets/js/konva.min.js', array('jquery'), '1.17.0', true);
  wp_register_script('wpgv-jspdf-js', WPGIFT__PLUGIN_URL . '/assets/js/jspdf.debug.js', array('jquery'), '1.5.3', true);
  wp_register_script('wpgv-jquery-validate', WPGIFT__PLUGIN_URL . '/assets/js/jquery.validate.min.js', array('jquery'), '1.17.0', true);
  wp_register_script('wpgv-jquery-steps', WPGIFT__PLUGIN_URL . '/assets/js/jquery.steps.min.js', array('jquery'), '1.1.0', true);
  wp_register_script('wpgv-stripe-js', WPGIFT__PLUGIN_URL . '/assets/js/stripe-v3.js', array('jquery'), '3.0.0', true);
  wp_register_script('wpgv-voucher-script', WPGIFT__PLUGIN_URL  . '/assets/js/voucher-script.js', array('jquery'), '3.3.9.1', true);
  wp_register_script('wpgv-item-script', WPGIFT__PLUGIN_URL  . '/assets/js/item-script.js', array('jquery'), '3.3.9.1', true);
  wp_register_script('wpgv-woocommerce-script', WPGIFT__PLUGIN_URL  . '/assets/js/woocommerce-script.js', array('jquery'), '3.3.9.1', true);
  wp_register_script('wpgv-voucher-product', WPGIFT__PLUGIN_URL  . '/assets/js/wpgv-voucher-product.js', array('jquery'), WPGIFT_VERSION, true);
  wp_register_script('wpgv-slick-script', WPGIFT__PLUGIN_URL  . '/assets/js/slick.min.js', array('jquery'), WPGIFT_VERSION, true);
  wp_register_script('wpgv-voucher-template-script', WPGIFT__PLUGIN_URL  . '/assets/js/voucher-template-script.js', array('jquery'), WPGIFT_VERSION, true);
  if ($setting_options->test_mode) {
    wp_register_script('wpgv-paypal-js', 'https://www.paypal.com/sdk/js?client-id=sb&currency=' . $setting_options->currency_code, array('jquery'), '5.0.0', true);
  } else {
    $wpgv_paypal_client_id = get_option('wpgv_paypal_client_id') ? get_option('wpgv_paypal_client_id') : '';
    wp_register_script('wpgv-paypal-js', 'https://www.paypal.com/sdk/js?client-id=' . $wpgv_paypal_client_id . '&currency=' . $setting_options->currency_code, array('jquery'), '5.0.0', true);
  }
  if (wpgv_is_woocommerce_enable()) {
    $check_plugin = new WPGV_Check_Plugin_Active();
    if ($check_plugin->wpgv_check_woo_active()) {
      wp_localize_script('wpgv-voucher-product', 'wpgv', array(
        'ajaxurl'                       => admin_url('admin-ajax.php', 'relative'),
        'denomination_attribute_slug'   => WPGV_DENOMINATION_ATTRIBUTE_SLUG,
        'decimal_places'                => wc_get_price_decimals(),
        'max_message_characters'        => WPGV_MAX_MESSAGE_CHARACTERS,
        'i18n'                          => array(
          'custom_amount_required_error' => __('Required', 'gift-voucher'),
          // translators: %s is the currency symbol.
          'min_amount_error'          => sprintf(__('Minimum amount is %s', 'gift-voucher'), get_woocommerce_currency_symbol()),
          // translators: %s is the currency symbol.
          'max_amount_error'          => sprintf(__('Maximum amount is %s', 'gift-voucher'), get_woocommerce_currency_symbol()),
          'invalid_recipient_error'   => __('The "To" field should only contain email addresses. The following recipients do not look like valid email addresses:', 'gift-voucher'),
        ),
        'nonces' => array(
          'check_balance'             => wp_create_nonce('wpgv-gift-cards-check-balance'),
          'apply_gift_card'           => wp_create_nonce('wpgv-gift-cards-apply-gift-card'),
          'remove_card'               => wp_create_nonce('wpgv-gift-cards-remove-card'),
        )
      ));
    }
  }
  wp_localize_script('wpgv-voucher-script', 'frontend_ajax_object', $translations);
  wp_localize_script('wpgv-voucher-template-script', 'frontend_ajax_object', $translations);
  wp_localize_script('wpgv-item-script', 'frontend_ajax_object', $translations);
  wp_localize_script('wpgv-woocommerce-script', 'frontend_ajax_object', $translations);
}

add_action('wp_enqueue_scripts', 'wpgv_front_enqueue');

function wpgv_plugin_activation()
{
  global $wpdb;
  global $jal_db_version;

  $giftvouchers_setting = $wpdb->prefix . 'giftvouchers_setting';
  $giftvouchers_list = $wpdb->prefix . 'giftvouchers_list';
  $giftvouchers_template = $wpdb->prefix . 'giftvouchers_template';
  $giftvouchers_activity = $wpdb->prefix . 'giftvouchers_activity';

  $charset_collate = $wpdb->get_charset_collate();

  $giftvouchers_setting_sql = "CREATE TABLE $giftvouchers_setting (
        id int(11) NOT NULL AUTO_INCREMENT,
        is_woocommerce_enable int(1) DEFAULT 0,
        is_style_choose_enable int(1) DEFAULT 0,
        is_order_form_enable int(1) DEFAULT 1,
        voucher_style varchar(100) DEFAULT 0,
        company_name varchar(255) DEFAULT NULL,
        currency_code varchar(10) DEFAULT NULL,
        currency varchar(10) DEFAULT NULL,
        currency_position varchar(10) DEFAULT NULL,
        voucher_bgcolor varchar(6) DEFAULT NULL,
        voucher_color varchar(6) DEFAULT NULL,
        template_col int(2) DEFAULT 3,
        voucher_min_value int(4) DEFAULT NULL,
        voucher_max_value int(6) DEFAULT NULL,
        voucher_expiry_type varchar(6) DEFAULT NULL,
        voucher_expiry varchar(10) DEFAULT NULL,
        voucher_terms_note text DEFAULT NULL,
        custom_loader text DEFAULT NULL,
        pdf_footer_url varchar(255) DEFAULT NULL,
        pdf_footer_email varchar(255) DEFAULT NULL,
        post_shipping int(1) DEFAULT NULL,
        shipping_method text DEFAULT NULL,
        preview_button int(1) DEFAULT 1,
        paypal int(11) DEFAULT NULL,
        sofort int(11) DEFAULT NULL,
        stripe int(11) DEFAULT NULL,
        paypal_email varchar(100) DEFAULT NULL,
        sofort_configure_key varchar(100) DEFAULT NULL,
        reason_for_payment varchar(100) DEFAULT NULL,
        stripe_publishable_key varchar(255) DEFAULT NULL,
        stripe_secret_key varchar(255) DEFAULT NULL,
        sender_name varchar(100) DEFAULT NULL,
        sender_email varchar(100) DEFAULT NULL,
        test_mode int(10) NOT NULL,
        per_invoice int(10) NOT NULL,
        bank_info longtext,
        landscape_mode_templates text DEFAULT NULL,
        portrait_mode_templates text DEFAULT NULL,
        PRIMARY KEY (id)
      ) $charset_collate;";

  $giftvouchers_list_sql = "CREATE TABLE $giftvouchers_list (
        id int(11) NOT NULL AUTO_INCREMENT,
        order_type enum('items', 'vouchers', 'gift_voucher_product') NOT NULL DEFAULT 'vouchers',
        template_id int(11) NOT NULL,
        product_id int(11) NOT NULL,
        order_id int(11) NOT NULL,
        itemcat_id int(11) NOT NULL,
        item_id int(11) NOT NULL,
        buying_for enum('someone_else', 'yourself') NOT NULL DEFAULT 'someone_else',
        from_name varchar(255) NOT NULL,
        to_name varchar(255) NOT NULL,
        amount float NOT NULL,
        message text NOT NULL,
        firstname varchar(255) NOT NULL,
        lastname varchar(255) NOT NULL,
        email varchar(255) NOT NULL,
        address text NOT NULL,
        postcode varchar(30) NOT NULL,
        pay_method varchar(255) NOT NULL,
        shipping_type enum('shipping_as_email', 'shipping_as_post') NOT NULL DEFAULT 'shipping_as_email',
        shipping_email varchar(255) NOT NULL,
        shipping_method varchar(255) NOT NULL,
        expiry varchar(100) NOT NULL,
        couponcode bigint(25) NOT NULL,
        voucherpdf_link text NOT NULL,
        voucheradd_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        status varchar(10) NOT NULL DEFAULT 'unused',
        payment_status varchar(10) NOT NULL DEFAULT 'Not Pay',
        check_send_mail varchar(30) NOT NULL DEFAULT 'unsent',
        PRIMARY KEY (id),
        UNIQUE KEY wpgv_couponcode_unique (couponcode)
      ) $charset_collate;";

  $giftvouchers_template_sql = "CREATE TABLE $giftvouchers_template (
        id int(11) NOT NULL AUTO_INCREMENT,
        title text NOT NULL,
        image int(11) DEFAULT NULL,
        image_style varchar(100) DEFAULT NULL,
        orderno int(11) NOT NULL DEFAULT '0',
        active int(11) NOT NULL DEFAULT '0',
        templateadd_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
      ) $charset_collate;";

  $giftvouchers_activity_sql = "CREATE TABLE $giftvouchers_activity (
        id int(11) NOT NULL AUTO_INCREMENT,
        voucher_id int(11) NOT NULL,
        user_id int(11) NOT NULL,
        action varchar(60) DEFAULT NULL,
        amount decimal(15,6),
        note text NOT NULL,
        activity_date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
      ) $charset_collate;";

  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
  dbDelta($giftvouchers_setting_sql);
  dbDelta($giftvouchers_list_sql);
  dbDelta($giftvouchers_template_sql);
  dbDelta($giftvouchers_activity_sql);

  add_option('jal_db_version', $jal_db_version);

  $demoimageurl = get_option('wpgv_demoimageurl') ? get_option('wpgv_demoimageurl') : WPGIFT__PLUGIN_URL . '/assets/img/demo.png';
  update_option('wpgv_demoimageurl', $demoimageurl);

  $company_name = get_bloginfo('name');
  $paypal_email = get_option('admin_email');
  $template_lanscape = 'template-voucher-lanscape-4.png, template-voucher-lanscape-8.png, template-voucher-lanscape-10.png';
  $template_portail = 'template-voucher-portail-1.png, template-voucher-portail-2.png, template-voucher-portail-6.png';
  if (!$wpdb->get_var("SELECT * FROM $giftvouchers_setting WHERE id = 1")) {
    $wpdb->insert(
      $giftvouchers_setting,
      array(
        'is_woocommerce_enable' => 0,
        'is_style_choose_enable' => 0,
        'is_order_form_enable' => 1,
        'voucher_style'      => 0,
        'company_name'       => $company_name,
        'paypal_email'       => $paypal_email,
        'reason_for_payment' => 'Payment for Gift Cards',
        'sender_name'        => $company_name,
        'sender_email'       => $paypal_email,
        'currency_code'      => 'USD',
        'currency'           => '$',
        'paypal'             => 1,
        'sofort'             => 0,
        'stripe'             => 0,
        'voucher_bgcolor'    => '81c6a9',
        'voucher_color'      => '555555',
        'template_col'       => 4,
        'voucher_min_value'  => 0,
        'voucher_max_value'  => 10000,
        'voucher_expiry_type' => 'days',
        'voucher_expiry'     => 60,
        'voucher_terms_note' => 'Note: The voucher is valid for 60 days and can be redeemed at ' . $company_name . '. A cash payment is not possible.',
        'custom_loader'      => WPGIFT__PLUGIN_URL . '/assets/img/loader.gif',
        'pdf_footer_url'     => get_site_url(),
        'pdf_footer_email'   => $paypal_email,
        'post_shipping'      => 1,
        'shipping_method'    => '5.99 : Express Shipping - $5.99, 3.99 : Standard Shipping - $3.99',
        'preview_button'     => 1,
        'currency_position'  => 'Left',
        'test_mode'          => 0,
        'per_invoice'        => 0,
        'landscape_mode_templates' => $template_lanscape,
        'portrait_mode_templates' => $template_portail,
      ),
      array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%d', '%s', '%s',)
    );
    $wpdb->insert(
      $giftvouchers_template,
      array(
        'title'  => "Demo Template",
        'active' => 1,
      ),
      array('%s', '%d')
    );
  }
  $data_setting = $wpdb->get_row("SELECT * FROM $giftvouchers_setting WHERE id = 1");
  if (empty($data_setting->landscape_mode_templates) || empty($data_setting->portrait_mode_templates)) {
    // Use update() function from $wpdb
    $wpdb->update(
      $giftvouchers_setting,
      array(
        'landscape_mode_templates' => $template_lanscape,
        'portrait_mode_templates' => $template_portail,
      ),
      array('id' => 1),
      array('%s', '%s'),
      array('%d')
    );
  }
  require_once ABSPATH . 'wp-admin/includes/file.php';
  WP_Filesystem();

  $upload = wp_upload_dir();
  $upload_dir = trailingslashit($upload['basedir']) . 'voucherpdfuploads';

  global $wp_filesystem;
  if (!$wp_filesystem->is_dir($upload_dir)) {
    $wp_filesystem->mkdir($upload_dir, 0755);
    $wp_filesystem->put_contents($upload_dir . '/index.html', 'Silence is golden.', 0755);
  }


  if (!wp_next_scheduled('wpgv_check_voucher_status')) {
    wp_schedule_event(time(), 'hourly', 'wpgv_check_voucher_status');
  }
  if (!wp_next_scheduled('wpgv_cleanup_unpaid_cron')) {
    wp_schedule_event(time(), 'hourly', 'wpgv_cleanup_unpaid_cron');
  }
  set_transient('wpgv_activated', 1);
  require_once(WPGIFT__PLUGIN_DIR . '/classes/class-nag.php');
  WPGIFT_Nag::insert_install_date();
}
register_activation_hook(__FILE__, 'wpgv_plugin_activation');

function wpgv_upgrade_completed($upgrader_object, $options)
{
  // The path to our plugin's main file
  $our_plugin = plugin_basename(__FILE__);
  // If an update has taken place and the updated type is plugins and the plugins element exists
  if (isset($options['action'], $options['type']) && $options['action'] == 'update' && $options['type'] == 'plugin' && isset($options['plugins'])) {
    // Iterate through the plugins being updated and check if ours is there
    foreach ($options['plugins'] as $plugin) {
      if ($plugin == $our_plugin) {

        global $wpdb;

        $giftvouchers_setting = $wpdb->prefix . 'giftvouchers_setting';
        $giftvouchers_list = $wpdb->prefix . 'giftvouchers_list';
        $giftvouchers_template = $wpdb->prefix . 'giftvouchers_template';
        $giftvouchers_activity = $wpdb->prefix . 'giftvouchers_activity';

        $charset_collate = $wpdb->get_charset_collate();
        $giftvouchers_activity_sql = "CREATE TABLE IF NOT EXISTS $giftvouchers_activity (
        id int(11) NOT NULL AUTO_INCREMENT,
        voucher_id int(11) NOT NULL,
        user_id int(11) NOT NULL,
        action varchar(60) DEFAULT NULL,
        amount decimal(15,6),
        note text NOT NULL,
        activity_date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
      ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($giftvouchers_activity_sql);

        $table_exists = function ($table) use ($wpdb) {
          return (bool) $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        };

        $column_exists = function ($table, $column) use ($wpdb, $table_exists) {
          if (!$table_exists($table)) {
            return false;
          }

          return (bool) $wpdb->get_row("SHOW COLUMNS FROM `$table` LIKE '$column'");
        };

        $add_column_if_not_exists = function ($table, $column, $sql) use ($column_exists, $wpdb) {
          if (!$column_exists($table, $column)) {
            $wpdb->query($sql);
          }
        };

        $modify_column_if_exists = function ($table, $column, $sql) use ($column_exists, $wpdb) {
          if ($column_exists($table, $column)) {
            $wpdb->query($sql);
          }
        };

        $add_column_if_not_exists($giftvouchers_template, 'image_style', "ALTER TABLE $giftvouchers_template ADD image_style varchar(100) DEFAULT NULL");
        $add_column_if_not_exists($giftvouchers_setting, 'is_woocommerce_enable', "ALTER TABLE $giftvouchers_setting ADD is_woocommerce_enable int(1) DEFAULT 0");
        $add_column_if_not_exists($giftvouchers_setting, 'post_shipping', "ALTER TABLE $giftvouchers_setting ADD post_shipping int(1) DEFAULT 0");
        $add_column_if_not_exists($giftvouchers_setting, 'preview_button', "ALTER TABLE $giftvouchers_setting ADD preview_button int(1) DEFAULT 1");
        $add_column_if_not_exists($giftvouchers_setting, 'pdf_footer_url', "ALTER TABLE $giftvouchers_setting ADD pdf_footer_url varchar(255) DEFAULT NULL");
        $add_column_if_not_exists($giftvouchers_setting, 'pdf_footer_email', "ALTER TABLE $giftvouchers_setting ADD pdf_footer_email varchar(255) DEFAULT NULL");
        $add_column_if_not_exists($giftvouchers_setting, 'is_style_choose_enable', "ALTER TABLE $giftvouchers_setting ADD is_style_choose_enable int(1) DEFAULT 0");
        $add_column_if_not_exists($giftvouchers_setting, 'voucher_style', "ALTER TABLE $giftvouchers_setting ADD voucher_style varchar(100) DEFAULT NULL");
        $add_column_if_not_exists($giftvouchers_setting, 'currency', "ALTER TABLE $giftvouchers_setting ADD currency varchar(10) DEFAULT NULL");
        $add_column_if_not_exists($giftvouchers_setting, 'currency_code', "ALTER TABLE $giftvouchers_setting ADD currency_code varchar(10) DEFAULT NULL");
        $add_column_if_not_exists($giftvouchers_list, 'check_send_mail', "ALTER TABLE $giftvouchers_list ADD check_send_mail varchar(30) NOT NULL DEFAULT 'unsent'");

        $modify_column_if_exists($giftvouchers_setting, 'stripe_publishable_key', "ALTER TABLE $giftvouchers_setting MODIFY COLUMN stripe_publishable_key varchar(255) DEFAULT NULL;");
        $modify_column_if_exists($giftvouchers_setting, 'stripe_secret_key', "ALTER TABLE $giftvouchers_setting MODIFY COLUMN stripe_secret_key varchar(255) DEFAULT NULL;");

        if ($table_exists($giftvouchers_list) && $table_exists($giftvouchers_activity)) {
          $orders = $wpdb->get_results("SELECT id,from_name,amount FROM $giftvouchers_list WHERE id NOT IN (SELECT voucher_id FROM $giftvouchers_activity) AND `status` = 'unused' AND `payment_status` = 'Paid'");

          foreach ($orders as $order) {
            WPGV_Gift_Voucher_Activity::record($order->id, 'create', '', 'Voucher ordered by ' . $order->from_name);
            WPGV_Gift_Voucher_Activity::record($order->id, 'transaction', $order->amount, 'Voucher payment recieved.');
          }
        }

        if ($table_exists($giftvouchers_template) && $column_exists($giftvouchers_template, 'image_style')) {
          $templates = $wpdb->get_results("SELECT id,image FROM $giftvouchers_template WHERE `image_style` IS NULL");
          foreach ($templates as $template) {
            $wpdb->update(
              $giftvouchers_template,
              array(
                'image_style' => '["' . $template->image . '","",""]',
              ),
              array('id' => $template->id)
            );
          }
        }

        $items = get_posts(array('posts_per_page' => -1, 'post_type' => 'wpgv_voucher_product'));
        foreach ($items as $item) {
          update_post_meta($item->ID, 'style1_image', get_post_thumbnail_id($item->ID));
        }

        // Set a transient to record that our plugin has just been updated
        set_transient('wpgv_updated', 1);
      }
    }
  }
}
add_action('upgrader_process_complete', 'wpgv_upgrade_completed', 10, 2);

function wpgv_display_update_notice()
{
  if (get_transient('wpgv_updated')) {
    $class = 'notice notice-info';
    $message = sprintf(
      'Thanks for Updating <b>Gift Cards</b> plugin. Please see the new plugin settings features from <a href="%s" target="_blank">here</a>. We upgraded PayPal (New Checkout) and Stripe (SCA-ready) payment process so you need to update the fields of these payment methods in the settings page. Please see here the documentation of new payment settings <a href="%s" target="_blank">here</a>.<br><br>We have noticed that you have been using Gift Cards plugin for a long time. We hope you love it, and we would really appreciate it if you would <a href="%s" target="_blank">give us a 5 stars rating</a>.',
      esc_url(admin_url('admin.php') . '?page=voucher-setting'),
      esc_url('https://www.wp-giftcard.com/docs/documentation/plugin-settings/payment-settings/'),
      esc_url('https://wordpress.org/support/plugin/gift-voucher/reviews/#new-post')
    );

    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));
    delete_transient('wpgv_updated');
  }
}
add_action('admin_notices', 'wpgv_display_update_notice');

function wpgv_display_install_notice()
{
  if (get_transient('wpgv_activated')) {
    $class = 'notice notice-info';
    $message = sprintf(
      'Thanks for Installing <b>Gift Cards</b> plugin. Please setup your plugin settings from <a href="%s" target="_blank">here</a>.',
      esc_url(admin_url('admin.php') . '?page=voucher-setting')
    );

    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));
    delete_transient('wpgv_activated');
  }
}
add_action('admin_notices', 'wpgv_display_install_notice');

function wpgv_plugin_deactivation()
{
  wp_clear_scheduled_hook('wpgv_check_voucher_status');
  wp_clear_scheduled_hook('wpgv_cleanup_unpaid_cron');
}
register_deactivation_hook(__FILE__, 'wpgv_plugin_deactivation');

add_action('init', 'wpgv_do_output_buffer');
function wpgv_do_output_buffer()
{
  ob_start();
  global $wpdb;
  $giftvouchers_list = $wpdb->prefix . 'giftvouchers_list';

  if (wpgv_db_table_exists($giftvouchers_list) && !wpgv_db_column_exists($giftvouchers_list, 'check_send_mail')) {
    $wpdb->query("ALTER TABLE $giftvouchers_list ADD check_send_mail varchar(30) NOT NULL DEFAULT 'unsent'");
  }
}

// Filter page template
add_filter('page_template', 'wpgv_catch_plugin_template');

// Page template filter callback
function wpgv_catch_plugin_template($template)
{
  if (is_page_template('wpgv_voucher_pdf.php')) {
    $template = WPGIFT__PLUGIN_DIR . '/templates/wpgv_voucher_pdf.php';
  } elseif (is_page_template('wpgv_item_pdf.php')) {
    $template = WPGIFT__PLUGIN_DIR . '/templates/wpgv_item_pdf.php';
  }

  return $template;
}

function wpgv_hex2rgb($color)
{
  if ($color[0] == '#')
    $color = substr($color, 1);

  if (strlen($color) == 6)
    list($r, $g, $b) = array(
      $color[0] . $color[1],
      $color[2] . $color[3],
      $color[4] . $color[5]
    );
  elseif (strlen($color) == 3)
    list($r, $g, $b) = array($color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2]);
  else
    return false;

  $r = hexdec($r);
  $g = hexdec($g);
  $b = hexdec($b);

  return array($r, $g, $b);
}

//conversion pixel -> millimeter at 72 dpi
function wpgv_px2mm($px)
{
  return $px * 25.4 / 72;
}

function wpgv_txtentities($html)
{
  $trans = get_html_translation_table(HTML_ENTITIES);
  $trans = array_flip($trans);
  return strtr($html, $trans);
}

function wpgv_em($word)
{
  if (is_null($word)) {
    return '';
  }

  // Undo slashes safely (use WP helper)
  if (function_exists('wp_unslash')) {
    $word = wp_unslash($word);
  } else {
    $word = stripslashes($word);
  }

  // Remove any HTML tags
  $word = wp_strip_all_tags($word);

  // Decode HTML entities into UTF-8. Use HTML5 flag to support wider entity set.
  $word = html_entity_decode($word, ENT_QUOTES | ENT_HTML5, 'UTF-8');

  // Fix invalid UTF-8 sequences (WP helper if available)
  if (function_exists('wp_check_invalid_utf8')) {
    $word = wp_check_invalid_utf8($word);
  } elseif (function_exists('mb_convert_encoding')) {
    $word = mb_convert_encoding($word, 'UTF-8', 'UTF-8');
  }

  // Normalize Unicode (NFC) if ext-intl is available
  if (function_exists('normalizer_normalize')) {
    $normalized = normalizer_normalize($word, Normalizer::FORM_C);
    if ($normalized !== false) {
      $word = $normalized;
    }
  }

  // Trim whitespace and return; preserve full UTF-8 (including emoji and non-Latin)
  return trim($word);
}

function wpgv_mailvarstr_multiple($string, $setting_options, $voucher_options_results, $voucherpdf_link)
{

  $get_link_pdf = array();
  $get_order_number = array();
  $from_name = null;
  $to_name = null;
  $email = null;
  $amount = null;

  // Normalize input: ensure we can iterate and access an example item safely.
  if (empty($voucher_options_results)) {
    return $string;
  }

  foreach ($voucher_options_results as $get_value) {
    $get_link_pdf[] = wpgv_get_voucher_pdf_url(isset($get_value->voucherpdf_link) ? $get_value->voucherpdf_link : '');
    $get_order_number[] = isset($get_value->id) ? $get_value->id : '';
    $from_name = isset($get_value->from_name) ? $get_value->from_name : '';
    $to_name = isset($get_value->to_name) ? $get_value->to_name : '';
    if (!empty($get_value->email)) {
      $email = $get_value->email;
    } else {
      $email = isset($get_value->shipping_email) ? $get_value->shipping_email : '';
    }
    $amount = isset($get_value->amount) ? $get_value->amount : '';
  }

  // Use the first record as a representative for order-level fields if necessary.
  $first = is_array($voucher_options_results) ? reset($voucher_options_results) : $voucher_options_results;

  $vars = array(
    '{order_type}'        => (!empty($first->order_type)) ? $first->order_type : 'vouchers',
    '{company_name}'      => (!empty($setting_options->company_name)) ? stripslashes($setting_options->company_name) : '',
    '{website_url}'       => get_site_url(),
    '{sender_email}'      => isset($setting_options->sender_email) ? $setting_options->sender_email : '',
    '{sender_name}'       => isset($setting_options->sender_name) ? stripslashes($setting_options->sender_name) : '',
    '{order_number}'      => isset($first->id) ? $first->id : '',
    '{amount}'            => $amount,
    '{customer_name}'     => stripslashes($from_name),
    '{recipient_name}'    => stripslashes($to_name),
    '{customer_email}'    => $email,
    '{customer_address}'  => isset($first->address) ? $first->address : '',
    '{customer_postcode}' => isset($first->postcode) ? $first->postcode : '',
    '{coupon_code}'       => isset($first->couponcode) ? $first->couponcode : '',
    '{payment_method}'    => isset($first->pay_method) ? $first->pay_method : '',
    '{payment_status}'    => isset($first->payment_status) ? $first->payment_status : '',
    '{pdf_link}'          => wpgv_get_voucher_pdf_url($voucherpdf_link),
    '{receipt_link}'      => wpgv_get_voucher_pdf_url(isset($first->voucherpdf_link) ? $first->voucherpdf_link : '', '-receipt'),
  );
  return strtr($string, $vars);
}

// Provide admin variant fallback if not defined elsewhere. This keeps backward compatibility
if (! function_exists('wpgv_mailvarstr_multiple_admin')) {
  function wpgv_mailvarstr_multiple_admin($string, $setting_options, $voucher_options_results)
  {
    // Try to use the same logic as wpgv_mailvarstr_multiple; admin templates typically do not need a specific pdf link.
    $first = !empty($voucher_options_results) && is_array($voucher_options_results) ? reset($voucher_options_results) : $voucher_options_results;
    $voucherpdf_link = isset($first->voucherpdf_link) ? $first->voucherpdf_link : '';
    return wpgv_mailvarstr_multiple($string, $setting_options, $voucher_options_results, $voucherpdf_link);
  }
}
function wpgv_mailvarstr($string, $setting_options, $voucher_options)
{
  $vars = array(
    '{order_type}'        => ($voucher_options->order_type) ? $voucher_options->order_type : 'vouchers',
    '{company_name}'      => ($setting_options->company_name) ? $setting_options->company_name : '',
    '{website_url}'       => get_site_url(),
    '{sender_email}'      => $setting_options->sender_email,
    '{sender_name}'       => $setting_options->sender_name,
    '{order_number}'      => $voucher_options->id,
    '{amount}'            => $voucher_options->amount,
    '{customer_name}'     => $voucher_options->from_name,
    '{recipient_name}'    => $voucher_options->to_name,
    '{customer_email}'    => ($voucher_options->email) ? $voucher_options->email : $voucher_options->shipping_email,
    '{customer_address}'  => $voucher_options->address,
    '{customer_postcode}' => $voucher_options->postcode,
    '{coupon_code}'       => $voucher_options->couponcode,
    '{payment_method}'    => $voucher_options->pay_method,
    '{payment_status}'    => $voucher_options->payment_status,
    '{pdf_link}'          => wpgv_get_voucher_pdf_url($voucher_options->voucherpdf_link),
    '{receipt_link}'      => wpgv_get_voucher_pdf_url($voucher_options->voucherpdf_link, '-receipt'),
  );

  return strtr($string, $vars);
}

// This function is use for Multisite WP environment
function wpgv_new_blog($blog_id, $user_id, $domain, $path, $site_id, $meta)
{
  if (is_plugin_active_for_network('gift-voucher-pro/gift-voucher-pro.php')) {
    switch_to_blog($blog_id);
    wpgv_plugin_activation();
    restore_current_blog();
  }
}

add_action('wpmu_new_blog', 'wpgv_new_blog', 10, 6);

add_action('wpgv_check_voucher_status', 'do_wpgv_check_voucher_status');

function do_wpgv_check_voucher_status()
{
  global $wpdb;
  $setting_table_name = $wpdb->prefix . 'giftvouchers_setting';
  $options = $wpdb->get_row("SELECT * FROM $setting_table_name WHERE id = 1");
  if ($options->is_woocommerce_enable) {
    $vouchers = $wpdb->get_results("SELECT id, couponcode FROM {$wpdb->prefix}giftvouchers_list WHERE `status` = 'unused' AND `payment_status` = 'Paid'");
    foreach ($vouchers as $voucher) {
      $gift_voucher = new WPGV_Gift_Voucher($voucher->couponcode);
      $balance = $gift_voucher->get_balance();
      if (empty($balance) || ($balance == 0)) {
        $wpdb->update(
          "{$wpdb->prefix}giftvouchers_list",
          array('id' => $voucher->id, 'status' => 'used'),
          array('id' => $voucher->id)
        );
      }
    }
  }
}

add_action('wp_ajax_wpgv_redeem_voucher', 'wpgv_redeem_voucher');

function wpgv_redeem_voucher()
{
  global $wpdb;

  if (!current_user_can('manage_options')) {
    wp_send_json_error(array('message' => __('You are not allowed to redeem vouchers.', 'gift-voucher')), 403);
  }

  check_ajax_referer('wpgv_redeem_voucher', 'nonce');

  $setting_table_name = $wpdb->prefix . 'giftvouchers_setting';
  $setting_options = $wpdb->get_row("SELECT * FROM $setting_table_name WHERE id = 1");
  $voucher_id = isset($_POST['voucher_id']) ? absint(wp_unslash($_POST['voucher_id'])) : 0;
  $raw_voucher_amount = isset($_POST['voucher_amount']) ? sanitize_text_field(wp_unslash($_POST['voucher_amount'])) : '';
  $normalized_amount = preg_replace('/[^0-9.,]/', '', $raw_voucher_amount);

  if ($voucher_id <= 0) {
    wp_send_json_error(array('message' => __('Invalid voucher ID.', 'gift-voucher')), 400);
  }

  if ($normalized_amount === '') {
    wp_send_json_error(array('message' => __('Voucher amount is required.', 'gift-voucher')), 400);
  }

  if (strpos($normalized_amount, ',') !== false && strpos($normalized_amount, '.') !== false) {
    $normalized_amount = str_replace(',', '', $normalized_amount);
  } else {
    $normalized_amount = str_replace(',', '.', $normalized_amount);
  }

  $voucher_amount = round((float) $normalized_amount, 2);

  if ($voucher_amount <= 0) {
    wp_send_json_error(array('message' => __('Voucher amount must be greater than zero.', 'gift-voucher')), 400);
  }

  $gift_voucher = WPGV_Gift_Voucher::get_by_id($voucher_id);
  if (!$gift_voucher || !$gift_voucher->get_id()) {
    wp_send_json_error(array('message' => __('Gift Voucher does not exist.', 'gift-voucher')), 404);
  }

  if ($gift_voucher->get_payment_status() !== 'Paid') {
    wp_send_json_error(array('message' => __('Only paid vouchers can be redeemed.', 'gift-voucher')), 400);
  }

  if ($gift_voucher->get_active() === 'used') {
    wp_send_json_error(array('message' => __('This voucher is already marked as used.', 'gift-voucher')), 400);
  }

  if ($gift_voucher->has_expired()) {
    wp_send_json_error(array('message' => __('This voucher has expired and cannot be redeemed.', 'gift-voucher')), 400);
  }

  $current_balance = (float) $gift_voucher->get_balance();
  if ($current_balance <= 0) {
    wp_send_json_error(array('message' => __('This voucher has no remaining balance.', 'gift-voucher')), 400);
  }

  if ($voucher_amount > $current_balance) {
    wp_send_json_error(array('message' => sprintf(
      /* translators: %s: current balance */
      __('Redeem amount exceeds current balance of %s.', 'gift-voucher'),
      wpgv_price_format($current_balance)
    )), 400);
  }

  $result = WPGV_Gift_Voucher_Activity::record(
    $voucher_id,
    'transaction',
    -$voucher_amount,
    'Voucher amount ' . $setting_options->currency . $voucher_amount . ' used directly by administrator.'
  );

  if (!$result) {
    wp_send_json_error(array('message' => __('Unable to record voucher redemption.', 'gift-voucher')), 500);
  }

  $updated_voucher = WPGV_Gift_Voucher::get_by_id($voucher_id);
  $updated_balance = $updated_voucher ? (float) $updated_voucher->get_balance() : max(0, $current_balance - $voucher_amount);

  wp_send_json_success(array(
    'message' => __('Successful', 'gift-voucher'),
    'balance' => $updated_balance,
  ));
}

function wpgv_price_format($price)
{
  global $wpdb;
  $setting_options = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}giftvouchers_setting WHERE id = %d", 1));

  $price = html_entity_decode(wp_strip_all_tags(stripslashes($price)), ENT_NOQUOTES, 'UTF-8');
  // Keep UTF-8 for front-end and general usage.
  // Do not convert to windows-1252 here because this function is used
  // in both HTML (UTF-8) and PDF contexts. PDF-specific conversion
  // is handled by `wpgv_text_to_pdf_safe` when rendering PDFs.
  if (function_exists('wp_check_invalid_utf8')) {
    $price = wp_check_invalid_utf8($price);
  } elseif (function_exists('mb_convert_encoding')) {
    $price = mb_convert_encoding($price, 'UTF-8', 'UTF-8');
  }
  // number format new
  $wpgv_select_number_format = get_option('wpgv_select_number_format') ? get_option('wpgv_select_number_format') : '';
  if ($wpgv_select_number_format == "comma") {
    $price = number_format((float)$price, 2, '.', ',');
  } elseif ($wpgv_select_number_format == "dot") {
    $price = number_format((float)$price, 2, ',', '.');
  }
  $currency = ($setting_options->currency_position == 'Left') ? $setting_options->currency . ' ' . $price : $price . ' ' . $setting_options->currency;
  return $currency;
}

function wpgv_find_existing_public_page($post_title, $post_content = '', $page_template = '')
{
  global $wpdb;

  $posts_table = $wpdb->posts;
  $postmeta_table = $wpdb->postmeta;

  $post_title = sanitize_text_field($post_title);
  $post_content = is_string($post_content) ? trim($post_content) : '';
  $page_template = sanitize_text_field($page_template);

  if ($page_template !== '' && $post_title !== '') {
    $existing_id = $wpdb->get_var(
      $wpdb->prepare(
        "SELECT p.ID
        FROM $posts_table p
        INNER JOIN $postmeta_table pm ON p.ID = pm.post_id
        WHERE p.post_type = 'page'
          AND p.post_status = 'publish'
          AND p.post_title = %s
          AND pm.meta_key = '_wp_page_template'
          AND pm.meta_value = %s
        LIMIT 1",
        $post_title,
        $page_template
      )
    );

    if ($existing_id) {
      return absint($existing_id);
    }
  }

  if ($post_content !== '') {
    $existing_id = $wpdb->get_var(
      $wpdb->prepare(
        "SELECT ID
        FROM $posts_table
        WHERE post_type = 'page'
          AND post_status = 'publish'
          AND post_content = %s
        LIMIT 1",
        $post_content
      )
    );

    if ($existing_id) {
      return absint($existing_id);
    }
  }

  if ($post_title !== '') {
    $existing_id = $wpdb->get_var(
      $wpdb->prepare(
        "SELECT ID
        FROM $posts_table
        WHERE post_type = 'page'
          AND post_status = 'publish'
          AND post_title = %s
        LIMIT 1",
        $post_title
      )
    );

    if ($existing_id) {
      return absint($existing_id);
    }
  }

  return 0;
}

function wpgv_create_or_reuse_public_page($page_args, $page_template = '')
{
  $post_title = isset($page_args['post_title']) ? $page_args['post_title'] : '';
  $post_content = isset($page_args['post_content']) ? $page_args['post_content'] : '';

  $page_id = wpgv_find_existing_public_page($post_title, $post_content, $page_template);

  if (!$page_id) {
    $page_id = wp_insert_post($page_args, '');
  }

  $page_id = absint($page_id);

  if ($page_id && $page_template !== '') {
    update_post_meta($page_id, '_wp_page_template', $page_template);
  }

  return $page_id;
}

function wpgv_create_plugin_pages()
{

  // Create Pages
  $giftCardPage = array(
    'post_title'    => 'Gift Cards',
    'post_content'  => '[wpgv_giftcard]',
    'post_status'   => 'publish',
    'post_author'   => strval(wp_get_current_user()->ID),
    'post_type'     => 'page',
  );
  $voucherPage = array(
    'post_title'    => 'Gift Voucher',
    'post_content'  => '[wpgv_giftvoucher]',
    'post_status'   => 'publish',
    'post_author'   => strval(wp_get_current_user()->ID),
    'post_type'     => 'page',
  );
  $giftItemsPage = array(
    'post_title'    => 'Gift Items',
    'post_content'  => '[wpgv_giftitems]',
    'post_status'   => 'publish',
    'post_author'   => strval(wp_get_current_user()->ID),
    'post_type'     => 'page',
  );
  $voucherPDFPage = array(
    'post_title'    => 'Voucher PDF Preview',
    'post_content'  => ' ',
    'post_status'   => 'publish',
    'post_author'   => strval(wp_get_current_user()->ID),
    'post_type'     => 'page',
    'comment_status' => 'closed',
    'ping_status'    => 'closed',
  );
  $giftItemPDFPage = array(
    'post_title'    => 'Gift Item PDF Preview',
    'post_content'  => ' ',
    'post_status'   => 'publish',
    'post_author'   => strval(wp_get_current_user()->ID),
    'post_type'     => 'page',
    'comment_status' => 'closed',
    'ping_status'    => 'closed',
  );
  $voucherSuccessPage = array(
    'post_title'    => 'Voucher Payment Successful',
    'post_content'  => '[wpgv_giftvouchersuccesspage]',
    'post_status'   => 'publish',
    'post_author'   => strval(wp_get_current_user()->ID),
    'post_type'     => 'page',
    'comment_status' => 'closed',
    'ping_status'    => 'closed',
  );
  $voucherCancelPage = array(
    'post_title'    => 'Voucher Payment Cancel',
    'post_content'  => '[wpgv_giftvouchercancelpage]',
    'post_status'   => 'publish',
    'post_author'   => strval(wp_get_current_user()->ID),
    'post_type'     => 'page',
    'comment_status' => 'closed',
    'ping_status'    => 'closed',
  );
  $stripeSuccessPage = array(
    'post_title'    => 'Stripe Payment Success Page',
    'post_content'  => '[wpgv_stripesuccesspage]',
    'post_status'   => 'publish',
    'post_author'   => strval(wp_get_current_user()->ID),
    'post_type'     => 'page',
    'comment_status' => 'closed',
    'ping_status'    => 'closed',
  );
  $voucherBalancePage = array(
    'post_title'    => 'Voucher Balance Check',
    'post_content'  => '[wpgv-check-voucher-balance]',
    'post_status'   => 'publish',
    'post_author'   => strval(wp_get_current_user()->ID),
    'post_type'     => 'page',
    'comment_status' => 'closed',
    'ping_status'    => 'closed',
  );
  $lastpageIds[0] = wpgv_create_or_reuse_public_page($voucherPage);
  $lastpageIds[1] = wpgv_create_or_reuse_public_page($giftItemsPage);
  $lastpageIds[2] = wpgv_create_or_reuse_public_page($voucherPDFPage, 'wpgv_voucher_pdf.php');
  $lastpageIds[3] = wpgv_create_or_reuse_public_page($giftItemPDFPage, 'wpgv_item_pdf.php');
  $lastpageIds[4] = wpgv_create_or_reuse_public_page($voucherSuccessPage);
  $lastpageIds[5] = wpgv_create_or_reuse_public_page($voucherCancelPage);
  $lastpageIds[6] = wpgv_create_or_reuse_public_page($giftCardPage);
  $lastpageIds[7] = wpgv_create_or_reuse_public_page($stripeSuccessPage);
  $lastpageIds[8] = wpgv_create_or_reuse_public_page($voucherBalancePage);

  if (!empty($lastpageIds[7])) {
    update_option('wpgv_stripesuccesspage', $lastpageIds[7]);
  }

  $lastCategoryID = wp_insert_term(
    'Demo Category',
    'wpgv_voucher_category',
    array(
      'description' => 'Demo Category Description',
      'slug'        => 'demo-category',
    )
  );
  $demoItem = array(
    'post_title'    => 'Demo Item',
    'post_status'   => 'publish',
    'post_author'   => get_current_user_id(),
    'post_type'     => 'wpgv_voucher_product',
  );
  $lastItemID = wp_insert_post($demoItem);
  add_post_meta($lastItemID, 'description', 'Demo Description');
  add_post_meta($lastItemID, 'price', '100');
  add_post_meta($lastItemID, 'special_price', '80');
  if (!isset($lastCategoryID)) {
    wp_set_object_terms($lastItemID, $lastCategoryID, 'wpgv_voucher_category');
  }

  if (!$lastpageIds[2])
    wp_die('Error creating template page');

  if (!$lastpageIds[3])
    wp_die('Error creating template page');

  return array($lastpageIds);
}

function wpgv_display_testmode_notice()
{

  global $wpdb;
  $setting_table  = $wpdb->prefix . 'giftvouchers_setting';
  $setting_options = $wpdb->get_row("SELECT * FROM $setting_table WHERE id = 1");

  $wpgv_paypal_client_id = get_option('wpgv_paypal_client_id') ? get_option('wpgv_paypal_client_id') : '';
  $wpgv_paypal_secret_key = get_option('wpgv_paypal_secret_key') ? get_option('wpgv_paypal_secret_key') : '';

  if ($setting_options->paypal && $setting_options->test_mode) {
    $class = 'notice notice-info';
    $message = sprintf(
      'PayPal Testmode has enabled in the <a href="%s" target="_blank">plugin settings</a> in <b>Gift Cards</b> plugin.',
      esc_url(admin_url('admin.php') . '?page=voucher-setting#payment')
    );
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));
  }

  if ($setting_options->paypal && (!$wpgv_paypal_client_id || !$wpgv_paypal_secret_key)) {
    $class = 'notice notice-info';
    $message = sprintf(
      'PayPal has enabled but empty client id or secret key in the <a href="%s" target="_blank">plugin settings</a> in <b>Gift Cards</b> plugin.',
      esc_url(admin_url('admin.php') . '?page=voucher-setting#payment')
    );
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));
  }

  if ($setting_options->stripe && (!$setting_options->stripe_publishable_key || !$setting_options->stripe_secret_key)) {
    $class = 'notice notice-info';
    $message = sprintf(
      'Stripe has enabled but empty Publishable key or Secret key in the <a href="%s" target="_blank">plugin settings</a> in <b>Gift Cards</b> plugin.',
      esc_url(admin_url('admin.php') . '?page=voucher-setting#payment')
    );
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));
  }

  if ($setting_options->sofort && !$setting_options->sofort_configure_key) {
    $class = 'notice notice-info';
    $message = sprintf(
      'Sofort has enabled but empty Configuration Key in the <a href="%s" target="_blank">plugin settings</a> in <b>Gift Cards</b> plugin.',
      esc_url(admin_url('admin.php') . '?page=voucher-setting#payment')
    );
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));
  }
}
add_action('admin_notices', 'wpgv_display_testmode_notice');


// Apply gift card code if valid and update cart totals
function wpgv_handle_gift_voucher_application($err, $err_code, $coupon)
{
  // WooCommerce deprecated direct property access on coupon objects.
  $coupon_code = is_object($coupon) && method_exists($coupon, 'get_code')
    ? $coupon->get_code()
    : (is_object($coupon) && isset($coupon->code) ? $coupon->code : '');

  if ($coupon_code === '') {
    return $err;
  }

  $gift_voucher = new WPGV_Gift_Voucher($coupon_code);

  if (!$gift_voucher->get_id() || $gift_voucher->get_payment_status() !== 'Paid') {
    return $err;
  }

  $balance = $gift_voucher->get_balance();

  if (empty($balance) || $balance <= 0) {
    wc_add_notice(__('This gift voucher has a zero balance.', 'gift-voucher'), 'error');
    return;
  }

  if ($gift_voucher->has_expired()) {
    wc_add_notice(__('Your voucher has expired.', 'gift-voucher'), 'error');
    return;
  }

  if (!WC()->session->has_session()) {
    WC()->session->set_customer_session_cookie(true);
  }

  $session_data = (array) WC()->session->get(WPGIFT_SESSION_KEY);
  $session_data['gift_voucher'][$coupon_code] = 0;
  WC()->session->set(WPGIFT_SESSION_KEY, $session_data);

  wc_add_notice(__('Gift voucher applied successfully.', 'gift-voucher'), 'success');

  WC()->cart->calculate_totals();

  if (is_checkout() && !is_admin()) {
    wc_enqueue_js("jQuery('body').trigger('update_checkout');");
  }
}
add_action('woocommerce_coupon_error', 'wpgv_handle_gift_voucher_application', 10, 3);
