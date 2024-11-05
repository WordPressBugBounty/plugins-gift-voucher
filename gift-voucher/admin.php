<?php

if (!defined('ABSPATH')) exit;  // Exit if accessed directly

/**
 * WPGiftVoucherAdminPages Class for add Admin Pages in Menu
 */
class WPGiftVoucherAdminPages
{
	// class instance
	static $instance;

	// Voucher WP_List_Table object
	public $vouchers_obj;

	public function __construct()
	{
		add_filter('set-screen-option', array(__CLASS__, 'set_screen'), 10, 3);
		add_action('admin_menu', array($this, 'plugin_menu'));
		add_action('admin_enqueue_scripts', array($this, 'admin_register_assets'));
	}

	public static function set_screen($status, $option, $value)
	{
		return $value;
	}

	/**
	 * Admin CSS and JS Files
	 */
	function admin_register_assets($hook)
	{
		wp_enqueue_style('wp-color-picker');
		wp_enqueue_style('voucher-style', WPGIFT__PLUGIN_URL . '/assets/css/admin-style.css');
		wp_enqueue_script('voucher-script', WPGIFT__PLUGIN_URL  . '/assets/js/admin-script.js', array('wp-color-picker'), '1.0.0', true);
		wp_localize_script('voucher-script', 'WPGiftAjax', array('ajaxurl' => admin_url('admin-ajax.php')));
	}

	/**
	 * Voucher Menu page
	 */
	public function plugin_menu()
	{
		add_menu_page('Gift Vouchers', 'Gift Vouchers', 'read', 'wpgv-gift-cards', '', 'dashicons-tickets-alt', 25);
		add_submenu_page('wpgv-gift-cards', 'Item Categories', 'Item Categories', 'edit_posts', 'edit-tags.php?taxonomy=wpgv_voucher_category&post_type=wpgv_voucher_product', false);
		add_submenu_page('wpgv-gift-cards', 'Voucher Categories', 'Gift Cards Categories', 'edit_posts', 'edit-tags.php?taxonomy=category_voucher_template&post_type=voucher_template', false);
		$templatehook = add_submenu_page('wpgv-gift-cards', __('Voucher Templates', 'gift-voucher'), __('Voucher Templates', 'gift-voucher'), 'manage_options', 'voucher-templates', array($this, 'voucher_template'));
		add_submenu_page(NULL, __('Add New Template', 'gift-voucher'), __('Add New Template', 'gift-voucher'), 'manage_options', 'new-voucher-template', array($this, 'new_voucher_template'));

		add_submenu_page(NULL, __('View Voucher Details', 'gift-voucher'), __('View Voucher Details', 'gift-voucher'), 'manage_options', 'view-voucher-details', array($this, 'view_voucher_details'));

		add_submenu_page('wpgv-gift-cards', __('Settings', 'gift-voucher'), __('Settings', 'gift-voucher'), 'manage_options', 'voucher-setting', array($this, 'voucher_settings'));
		$hook = add_submenu_page('wpgv-gift-cards', __('Gift Voucher Orders', 'gift-voucher'), __('Gift Voucher Orders', 'gift-voucher'), 'manage_options', 'vouchers-lists', array($this, 'voucher_list'));

		add_action("load-$hook", array($this, 'screen_option_voucher'));
		add_action("load-$templatehook", array($this, 'screen_option_template'));
	}

	/**
	 * Voucher List page
	 */
	public function voucher_list()
	{
		require_once WPGIFT__PLUGIN_DIR . '/include/voucher_list.php';
	}

	/**
	 * Voucher List page
	 */
	public function check_voucher_balance()
	{
		echo '<h3>' . esc_html(__('Check Voucher Balance', 'gift-voucher')) . '</h3>';
		echo do_shortcode(' [wpgv-check-voucher-balance] ');
	}

	/**
	 * Method for view details of an voucher
	 */
	public function view_voucher_details()
	{
		require_once WPGIFT__PLUGIN_DIR . '/include/view_voucher_details.php';
	}

	/**
	 * Voucher settings page
	 */
	public function voucher_settings()
	{
		require_once WPGIFT__PLUGIN_DIR . '/include/voucher_settings.php';
	}

	/**
	 * Voucher Template page
	 */
	public function voucher_template()
	{
		require_once WPGIFT__PLUGIN_DIR . '/include/voucher_template.php';
	}

	/**
	 * Add Voucher Template page
	 */
	public function new_voucher_template()
	{
		require_once WPGIFT__PLUGIN_DIR . '/include/new_voucher_template.php';
	}

	/**
	 * Redeem Voucher page
	 */
	public function redeem_voucher()
	{
		require_once WPGIFT__PLUGIN_DIR . '/include/redeem_voucher.php';
	}

	/**
	 * Plugin License page
	 */
	public function wpgv_license_page()
	{
		require_once WPGIFT__PLUGIN_DIR . '/include/wpgv_license_page.php';
	}

	/**
	 * Method for export vouchers in xls
	 */
	function export_orders()
	{
		if (is_admin()) {
			global $wpdb;

			// Check if the POST variable is set
			if (isset($_POST["tbl_name"])) {
				$tablename = sanitize_text_field($_POST["tbl_name"]);

				// Check if the table exists safely
				$safe_tablename = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($tablename)));

				if ($safe_tablename) {
					$filename = "export-orders";
					$rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$safe_tablename}"), ARRAY_A);

					if ($rows) {
						// Clear output buffer
						ob_clean();

						// Set headers for Excel download
						header("Content-Type: application/vnd.ms-excel; charset=utf-8");
						header("Content-Disposition: attachment; filename=" . esc_attr($filename) . "-" . gmdate('d-m-y') . ".xls");
						header("Expires: 0");
						header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
						header("Cache-Control: private", false);

						// Start outputting HTML for the Excel file
						echo "<html><meta http-equiv=\"Content-Type\" content=\"text/html; charset=Windows-1252\"><body><table>";

						// Output the header row
						if (!empty($rows)) {
							echo "<tr>";
							foreach (array_keys($rows[0]) as $col) {
								echo "<th style='border: thin solid; background-color: #83b4d8;'>" . esc_html($col) . "</th>";
							}
							echo "</tr>";
						}

						// Output the data rows
						foreach ($rows as $row) {
							echo "<tr>";
							foreach ($row as $cell) {
								echo "<td style='border: thin solid;'>" . esc_html($cell) . "</td>";
							}
							echo "</tr>";
						}

						echo "</table></body></html>";
					} else {
						echo esc_html(__('Invalid Request.', 'gift-voucher'));
					}
				} else {
					echo esc_html(__('Invalid table name provided.', 'gift-voucher'));
				}
			}
		}
	}


	/**
	 * Screen options for voucher list
	 */
	public function screen_option_voucher()
	{
		$option = 'per_page';
		$args   = array(
			'label'   => __('Gift Vouchers', 'gift-voucher'),
			'default' => 20,
			'option'  => 'vouchers_per_page'
		);

		add_screen_option($option, $args);

		$this->vouchers_obj = new WPGV_Voucher_List();
	}

	/**
	 * Screen options for voucher templates
	 */
	public function screen_option_template()
	{
		$option = 'per_page';
		$args = array(
			'label'   => __('Voucher Templates', 'gift-voucher'),
			'default' => 20,
			'option'  => 'templates_per_page'
		);

		add_screen_option($option, $args);

		$this->vouchers_obj = new WPGV_Voucher_Template();
	}

	/** Singleton instance */
	public static function get_instance()
	{
		if (!isset(self::$instance)) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}

/**
 * Method for get image url by id (Only for Template Page)
 */
function wpgv_get_image_url($ids)
{
	$images = $ids ? json_decode($ids) : [''];
	foreach ($images as $key => $value) {
		if ($value) :
			$image_attributes = wp_get_attachment_image_src($value, 'voucher-thumb');
?>
			<script type="text/javascript">
				jQuery(document).ready(function($) {
					$('.image_src<?php echo esc_html($key); ?>').attr('src', '<?php echo esc_url($image_attributes[0]); ?>').show();
					$('.remove_image<?php echo esc_html($key); ?>').show();
				});
			</script>
<?php
		endif;
	}
}

add_action('admin_post_save_voucher_settings_option', 'process_voucher_settings_options');
