<?php

/**
 * Plugin Name: Gift Cards (Gift Vouchers and Packages) (WooCommerce Supported)
 * Description: Let your customers buy gift cards/certificates for your services & products directly on your website.
 * Plugin URI: https://wp-giftcard.com/
 * Author: Codemenschen GmbH
 * Author URI: https://www.codemenschen.at/
 * Version: 4.4.6
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

define('WPGIFT_VERSION', '4.4.6');
define('WPGIFT__MINIMUM_WP_VERSION', '4.0');
define('WPGIFT__PLUGIN_DIR', untrailingslashit(plugin_dir_path(__FILE__)));
define('WPGIFT__PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));

define('WPGIFT_SESSION_KEY', 'wpgv-gift-voucher-data');
define('WPGIFT_INSTALL_DATE', 'wpgv-install-date');
define('WPGIFT_ADMIN_NOTICE_KEY', 'wpgv-hide-notice');

if (!class_exists('WP_List_Table')) {
  require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

function wpgiftv_plugin_init()
{
  $langOK = load_plugin_textdomain('gift-voucher', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'wpgiftv_plugin_init');

require_once(WPGIFT__PLUGIN_DIR . '/vendor/autoload.php');
require_once(WPGIFT__PLUGIN_DIR . '/vendor/sofort/payment/sofortLibSofortueberweisung.inc.php');
require_once(WPGIFT__PLUGIN_DIR . '/classes/rotation.php');
require_once(WPGIFT__PLUGIN_DIR . '/admin.php');
require_once(WPGIFT__PLUGIN_DIR . '/front.php');
require_once(WPGIFT__PLUGIN_DIR . '/giftitems.php');
require_once(WPGIFT__PLUGIN_DIR . '/classes/fpdf.php');
require_once(WPGIFT__PLUGIN_DIR . '/classes/voucher.php');
require_once(WPGIFT__PLUGIN_DIR . '/classes/template.php');
require_once(WPGIFT__PLUGIN_DIR . '/classes/page_template.php');
require_once(WPGIFT__PLUGIN_DIR . '/include/wpgv_voucher_pdf.php');
require_once(WPGIFT__PLUGIN_DIR . '/include/wpgv_item_pdf.php');
require_once(WPGIFT__PLUGIN_DIR . '/include/voucher_posttype.php');
require_once(WPGIFT__PLUGIN_DIR . '/include/voucher_metabox.php');
require_once(WPGIFT__PLUGIN_DIR . '/include/voucher-shortcodes.php');
require_once(WPGIFT__PLUGIN_DIR . '/classes/wpgv-gift-voucher.php');
require_once(WPGIFT__PLUGIN_DIR . '/classes/wpgv-gift-voucher-activity.php');
require_once(WPGIFT__PLUGIN_DIR . '/giftcard.php');
require_once(WPGIFT__PLUGIN_DIR . '/include/wpgv_giftcard_pdf.php');
require_once(WPGIFT__PLUGIN_DIR . '/include/edit-order-voucher.php');

add_action('plugins_loaded', function () {
  WPGiftVoucherAdminPages::get_instance();
});


add_action('admin_init', function () {

  global $wpdb;
  $giftvouchers_list = $wpdb->prefix . 'giftvouchers_list';

  $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `$giftvouchers_list` LIKE 'check_send_mail'");
  if (empty($column_exists)) {

    $wpdb->query("ALTER TABLE $giftvouchers_list ADD check_send_mail varchar(30) NOT NULL DEFAULT 'unsent'");
  }

  // Check if user is an administrator
  if (!current_user_can('manage_options')) {
    return false;
  }

  // include nag class
  require_once(WPGIFT__PLUGIN_DIR . '/classes/class-nag.php');

  // setup nag
  $nag = new WPGIFT_Nag();
  $nag->setup();
});

add_action('woocommerce_init', 'wpgv_files_loaded', 10, 1);
function wpgv_files_loaded()
{
  global $wpdb;
  $setting_table_name = $wpdb->prefix . 'giftvouchers_setting';
  $options = $wpdb->get_row("SELECT * FROM $setting_table_name WHERE id = 1");
  if ($options->is_woocommerce_enable) {
    require_once(WPGIFT__PLUGIN_DIR . '/include/redeem-voucher.php');
    require_once(WPGIFT__PLUGIN_DIR . '/classes/wc-order-item-wpgv-gift-voucher.php');
    require_once(WPGIFT__PLUGIN_DIR . '/classes/data-stores/wc-order-item-wpgv-gift-voucher-data-store.php');
  }
}

add_action('plugins_loaded', 'wpgv_voucher_imagesize_setup');
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
  $setting_table  = $wpdb->prefix . 'giftvouchers_setting';
  $setting_options = $wpdb->get_row("SELECT * FROM $setting_table WHERE id = 1");

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
  wp_register_script('wpgv-stripe-js', 'https://js.stripe.com/v3/', array('jquery'), '3.0.0', true);
  wp_register_script('wpgv-voucher-script', WPGIFT__PLUGIN_URL  . '/assets/js/voucher-script.js', array('jquery'), '3.3.9.1', true);
  wp_register_script('wpgv-item-script', WPGIFT__PLUGIN_URL  . '/assets/js/item-script.js', array('jquery'), '3.3.9.1', true);
  wp_register_script('wpgv-woocommerce-script', WPGIFT__PLUGIN_URL  . '/assets/js/woocommerce-script.js', array('jquery'), '3.3.9.1', true);
  wp_register_script('wpgv-slick-script', WPGIFT__PLUGIN_URL  . '/assets/js/slick.min.js', array('jquery'), WPGIFT_VERSION, true);
  wp_register_script('wpgv-voucher-template-script', WPGIFT__PLUGIN_URL  . '/assets/js/voucher-template-script.js', array('jquery'), WPGIFT_VERSION, true);
  if ($setting_options->test_mode) {
    wp_register_script(
      'wpgv-paypal-js',
      'https://www.paypal.com/sdk/js?client-id=sb&currency=' . $setting_options->currency_code,
      array('jquery'),
      '1.0.0',
      true
    );
  } else {
    $wpgv_paypal_client_id = get_option('wpgv_paypal_client_id') ? get_option('wpgv_paypal_client_id') : '';
    wp_register_script(
      'wpgv-paypal-js',
      'https://www.paypal.com/sdk/js?client-id=' . $wpgv_paypal_client_id . '&currency=' . $setting_options->currency_code,
      array('jquery'),
      '1.0.0',
      true
    );
  }

  wp_localize_script('wpgv-voucher-script', 'frontend_ajax_object', $translations);
  wp_localize_script('wpgv-voucher-template-script', 'frontend_ajax_object', $translations);
  wp_localize_script('wpgv-item-script', 'frontend_ajax_object', $translations);
  wp_localize_script('wpgv-woocommerce-script', 'frontend_ajax_object', $translations);
}

add_action('wp_enqueue_scripts', 'wpgv_front_enqueue');


function enqueue_jquery_ui_datepicker()
{
  wp_enqueue_style('jquery-ui', WPGIFT__PLUGIN_URL . '/assets/css/jquery-ui.css');
  wp_enqueue_script('jquery-ui', WPGIFT__PLUGIN_URL . '/assets/js/jquery-ui.js');

  wp_add_inline_script('jquery-ui', '
        jQuery(document).ready(function($) {
            $("#datepicker").datepicker({
                dateFormat: "dd.mm.yy"
            });
        });
    ');
}

add_action('admin_enqueue_scripts', 'enqueue_jquery_ui_datepicker');


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
        order_type enum('items', 'vouchers') NOT NULL DEFAULT 'vouchers',
        template_id int(11) NOT NULL,
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
        PRIMARY KEY (id)
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
  if (empty($data_setting->landscape_mode_templates)) {
    // Use update() function from $wpdb
    $wpdb->update(
      $giftvouchers_setting,
      array(
        'landscape_mode_templates' => $template_landscape,
        'portrait_mode_templates' => $template_portail,
      ),
      array('id' => 1),
      array('%s', '%s'),
      array('%d')
    );
  }
  require_once(ABSPATH . 'wp-admin/includes/file.php');
  WP_Filesystem();

  global $wp_filesystem;

  // Lấy thông tin thư mục tải lên
  $upload = wp_upload_dir();
  $upload_dir = $upload['basedir'] . '/voucherpdfuploads';

  // Kiểm tra xem thư mục có tồn tại không
  if (!$wp_filesystem->exists($upload_dir)) {
    // Tạo thư mục nếu nó không tồn tại
    $wp_filesystem->mkdir($upload_dir, 0755);

    // Tạo file index.html và ghi nội dung vào đó
    $file_path = $upload_dir . '/index.html';
    $wp_filesystem->put_contents($file_path, "Silence is golden.", FS_CHMOD_FILE);
  }

  if (!wp_next_scheduled('wpgv_check_voucher_status')) {
    wp_schedule_event(time(), 'hourly', 'wpgv_check_voucher_status');
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
  if ($options['action'] == 'update' && $options['type'] == 'plugin' && isset($options['plugins'])) {
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

        $wpdb->query("ALTER TABLE $giftvouchers_template ADD image_style varchar(100) DEFAULT NULL");
        $wpdb->query("ALTER TABLE $giftvouchers_setting ADD is_woocommerce_enable int(1) DEFAULT 0");
        $wpdb->query("ALTER TABLE $giftvouchers_setting ADD post_shipping int(1) DEFAULT 0");
        $wpdb->query("ALTER TABLE $giftvouchers_setting ADD preview_button int(1) DEFAULT 1");
        $wpdb->query("ALTER TABLE $giftvouchers_setting ADD pdf_footer_url varchar(255) DEFAULT NULL");
        $wpdb->query("ALTER TABLE $giftvouchers_setting ADD is_woocommerce_enable varchar(255) DEFAULT NULL");
        $wpdb->query("ALTER TABLE $giftvouchers_setting ADD is_style_choose_enable int(1) DEFAULT 0");
        $wpdb->query("ALTER TABLE $giftvouchers_setting ADD voucher_style varchar(100) DEFAULT NULL");
        $wpdb->query("ALTER TABLE $giftvouchers_setting ADD currency varchar(10) DEFAULT NULL");

        $wpdb->query("ALTER TABLE $giftvouchers_list ADD check_send_mail varchar(30) NOT NULL DEFAULT 'unsent'");

        $wpdb->query("ALTER TABLE $giftvouchers_setting ADD currency_code varchar(10) DEFAULT NULL");

        $wpdb->query("ALTER TABLE $giftvouchers_setting MODIFY COLUMN stripe_publishable_key varchar(255) DEFAULT NULL;");
        $wpdb->query("ALTER TABLE $giftvouchers_setting MODIFY COLUMN stripe_secret_key varchar(255) DEFAULT NULL;");

        $orders = $wpdb->get_results("SELECT id,from_name,amount FROM $giftvouchers_list WHERE id NOT IN (SELECT voucher_id FROM $giftvouchers_activity) AND `status` = 'unused' AND `payment_status` = 'Paid'");

        foreach ($orders as $order) {
          WPGV_Gift_Voucher_Activity::record($order->id, 'create', '', 'Voucher ordered by ' . $order->from_name);
          WPGV_Gift_Voucher_Activity::record($order->id, 'transaction', $order->amount, 'Voucher payment recieved.');
        }

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
      'Thanks for Updating <b>Gift Cards</b> plugin. Please see the new plugin settings features from <a href="%s" target="_blank">here</a>. We upgraded PayPal (New Checkout) and Stripe (SCA-ready) payment process so you need to update the fields of these payment methods in the settings page. Please see here the documentation of new payment settings <a href="%s" target="_blank">here</a>.<br><br>We have noticed that you have been using Gift Cards plugin from long time. We hope you love it, and we would really appreciate it if you would <a href="%s" target="_blank">give us a 5 stars rating</a>.',
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
      'Thanks for Installing <b>Gift Cards</b> plugin. Please set up your plugin settings from <a href="%s" target="_blank">here</a>.',
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
}
register_deactivation_hook(__FILE__, 'wpgv_plugin_deactivation');

add_action('init', 'wpgv_do_output_buffer');
function wpgv_do_output_buffer()
{
  ob_start();
  global $wpdb;
  $giftvouchers_list = $wpdb->prefix . 'giftvouchers_list';

  $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `$giftvouchers_list` LIKE 'check_send_mail'");
  if (empty($column_exists)) {

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

  $clean_html = wp_strip_all_tags($html);
  $trans = get_html_translation_table(HTML_ENTITIES);
  $trans = array_flip($trans);

  return strtr($clean_html, $trans);
}


function wpgv_em($word)
{
  $word = html_entity_decode(wp_strip_all_tags(stripslashes($word)), ENT_NOQUOTES, 'UTF-8');
  $word = iconv('UTF-8', 'windows-1252', $word);
  return $word;
}


function wpgv_mailvarstr($string, $setting_options, $voucher_options)
{
  $url_upload = wp_get_upload_dir();
  $baseurl = $url_upload['baseurl'];
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
    '{pdf_link}'          => $baseurl . '/voucherpdfuploads/' . $voucher_options->voucherpdf_link . '.pdf',
    '{receipt_link}'      => $baseurl . '/voucherpdfuploads/' . $voucher_options->voucherpdf_link . '-receipt.pdf',
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
  $setting_table_name = $wpdb->prefix . 'giftvouchers_setting';
  $setting_options = $wpdb->get_row("SELECT * FROM $setting_table_name WHERE id = 1");

  $voucher_id = wp_strip_all_tags(sanitize_text_field($_POST['voucher_id']));
  $voucher_amount = wp_strip_all_tags(sanitize_text_field($_POST['voucher_amount']));

  WPGV_Gift_Voucher_Activity::record($voucher_id, 'transaction', '-' . $voucher_amount, 'Voucher amount ' . $setting_options->currency . $voucher_amount . ' used directly by administrator.');

  echo 'Successful';
  wp_die(); // this is required to terminate immediately and return a proper response
}


function wpgv_price_format($price)
{
  global $wpdb;
  $setting_table_name = $wpdb->prefix . 'giftvouchers_setting';
  $setting_options = $wpdb->get_row("SELECT * FROM $setting_table_name WHERE id = 1");

  $price = html_entity_decode(wp_strip_all_tags(stripslashes($price)), ENT_NOQUOTES, 'UTF-8');
  $price = iconv('UTF-8', 'windows-1252', $price);
  $price = number_format((float)$price, 2, ',', '.');

  $currency = ($setting_options->currency_position == 'Left')
    ? $setting_options->currency . ' ' . $price
    : $price . ' ' . $setting_options->currency;

  return $currency;
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
  $lastpageIds[0] = wp_insert_post($voucherPage, '');
  $lastpageIds[1] = wp_insert_post($giftItemsPage, '');
  $lastpageIds[2] = wp_insert_post($voucherPDFPage, '');
  $lastpageIds[3] = wp_insert_post($giftItemPDFPage, '');
  $lastpageIds[4] = wp_insert_post($voucherSuccessPage, '');
  $lastpageIds[5] = wp_insert_post($voucherCancelPage, '');
  $lastpageIds[6] = wp_insert_post($giftCardPage, '');

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
  else
    update_post_meta($lastpageIds[2], '_wp_page_template', 'wpgv_voucher_pdf.php');

  if (!$lastpageIds[3])
    wp_die('Error creating template page');
  else
    update_post_meta($lastpageIds[3], '_wp_page_template', 'wpgv_item_pdf.php');

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
      'PayPal Testmode has been enabled in the <a href="%s" target="_blank">plugin settings</a> in <b>Gift Cards</b> plugin.',
      esc_url(admin_url('admin.php') . '?page=voucher-setting#payment')
    );
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));
  }

  if ($setting_options->paypal && (!$wpgv_paypal_client_id || !$wpgv_paypal_secret_key)) {
    $class = 'notice notice-info';
    $message = sprintf(
      'PayPal has been enabled but the client ID or secret key is empty in the <a href="%s" target="_blank">plugin settings</a> in <b>Gift Cards</b> plugin.',
      esc_url(admin_url('admin.php') . '?page=voucher-setting#payment')
    );
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));
  }

  if ($setting_options->stripe && (!$setting_options->stripe_publishable_key || !$setting_options->stripe_secret_key)) {
    $class = 'notice notice-info';
    $message = sprintf(
      'Stripe has been enabled but the Publishable key or Secret key is empty in the <a href="%s" target="_blank">plugin settings</a> in <b>Gift Cards</b> plugin.',
      esc_url(admin_url('admin.php') . '?page=voucher-setting#payment')
    );
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));
  }

  if ($setting_options->sofort && (!$setting_options->sofort_configure_key)) {
    $class = 'notice notice-info';
    $message = sprintf(
      'Sofort has been enabled but the Configuration Key is empty in the <a href="%s" target="_blank">plugin settings</a> in <b>Gift Cards</b> plugin.',
      esc_url(admin_url('admin.php') . '?page=voucher-setting#payment')
    );
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));
  }
}
add_action('admin_notices', 'wpgv_display_testmode_notice');
