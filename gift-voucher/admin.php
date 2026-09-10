<?php

if (!defined('ABSPATH')) exit;  // Exit if accessed directly

if (!function_exists('wpgv_is_plugin_admin_screen')) {
	/**
	 * Check whether the current admin request belongs to this plugin's own screens.
	 *
	 * @param WP_Screen|null $screen Optional screen object.
	 * @return bool
	 */
	function wpgv_is_plugin_admin_screen($screen = null)
	{
		if (!is_admin()) {
			return false;
		}

		if (null === $screen && function_exists('get_current_screen')) {
			$screen = get_current_screen();
		}

		$current_page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
		$current_post_type = isset($_GET['post_type']) ? sanitize_key(wp_unslash($_GET['post_type'])) : '';
		$current_taxonomy = isset($_GET['taxonomy']) ? sanitize_key(wp_unslash($_GET['taxonomy'])) : '';

		$allowed_pages = array(
			'wpgv-gift-cards',
			'voucher-templates',
			'new-voucher-template',
			'view-voucher-details',
			'voucher-setting',
			'vouchers-lists',
		);
		$allowed_post_types = array(
			'voucher_template',
			'wpgv_voucher_product',
		);
		$allowed_taxonomies = array(
			'wpgv_voucher_category',
			'category_voucher_template',
		);

		if ($current_page && in_array($current_page, $allowed_pages, true)) {
			return true;
		}

		if ($current_post_type && in_array($current_post_type, $allowed_post_types, true)) {
			return true;
		}

		if ($current_taxonomy && in_array($current_taxonomy, $allowed_taxonomies, true)) {
			return true;
		}

		if (!$screen) {
			return false;
		}

		if (!empty($screen->post_type) && in_array($screen->post_type, $allowed_post_types, true)) {
			return true;
		}

		if (!empty($screen->taxonomy) && in_array($screen->taxonomy, $allowed_taxonomies, true)) {
			return true;
		}

		if (!empty($screen->id)) {
			foreach ($allowed_pages as $allowed_page) {
				if (false !== strpos($screen->id, $allowed_page)) {
					return true;
				}
			}
		}

		return false;
	}
}

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
		// Deliberately NOT admin_menu. Core derives a plugin page's parent by
		// scanning $submenu, so a row removed there makes get_admin_page_parent()
		// return an empty string, the hookname resolve to admin_page_<slug> instead
		// of the registered gift-cards_page_<slug>, and the access check in
		// wp-admin/includes/menu.php answer 403 for a page that still exists.
		//
		// admin_head runs after that check and before menu-header.php draws the
		// menu, so the row is gone from the sidebar while the page stays reachable.
		add_action('admin_head', array($this, 'remove_hidden_submenus'));
		add_action('admin_enqueue_scripts', array($this, 'admin_register_assets'));
	}

	public static function set_screen($status, $option, $value)
	{
		return $value;
	}

	/**
	 * Drop submenu rows for pages that are only meaningful when reached with a
	 * parameter, while leaving the pages themselves registered and working.
	 *
	 * This replaces a CSS rule that set display:none on those rows. CSS only
	 * hides them visually - the markup, and the working link, stayed in the page.
	 *
	 * - view-voucher-details needs a voucher_id; it is reached from the View
	 *   Details row action on the Orders list (classes/voucher.php).
	 * - new-voucher-template is the Add form of the legacy template screen and is
	 *   reached from that screen's own button.
	 *
	 * Runs late so every submenu is registered before anything is removed.
	 */
	public function remove_hidden_submenus()
	{
		remove_submenu_page('wpgv-gift-cards', 'view-voucher-details');
		remove_submenu_page('wpgv-gift-cards', 'new-voucher-template');

		// Standard templates are edited in the unified list once the merge is on.
		// The old screen wrote straight to giftvouchers_template while the front
		// end read the mirrored post, so edits made there never took effect.
		// The page stays registered so an existing bookmark does not 404.
		if (wpgv_unified_cpt_enabled()) {
			remove_submenu_page('wpgv-gift-cards', 'voucher-templates');
		}
	}

	/**
	 * Admin CSS and JS Files
	 */
	function admin_register_assets($hook)
	{
		if (!wpgv_is_plugin_admin_screen()) {
			return;
		}

		wp_enqueue_style('wp-color-picker');
		wp_enqueue_style('voucher-style', WPGIFT__PLUGIN_URL . '/assets/css/admin-style.css');
		wp_enqueue_script('konva-js', WPGIFT__PLUGIN_URL . '/assets/js/konva.min.js', array('jquery'), 'v8.0.4', true);
		wp_enqueue_script('voucher-script', WPGIFT__PLUGIN_URL  . '/assets/js/admin-script.js', array('wp-color-picker', 'konva-js'), '1.0.0', true);
		wp_localize_script('voucher-script', 'WPGiftAjax', array(
			'ajaxurl' => admin_url('admin-ajax.php'),
			'redeemNonce' => wp_create_nonce('wpgv_redeem_voucher'),
			'messages' => array(
				'invalidAmount' => __('Please enter a valid voucher amount.', 'gift-voucher'),
				'requestFailed' => __('Request failed. Please try again.', 'gift-voucher'),
				'successPrefix' => __('Voucher updated:', 'gift-voucher'),
			),
		));
	}

	/**
	 * Voucher Menu page
	 */
	public function plugin_menu()
	{
		add_menu_page('Gift Cards', 'Gift Cards', 'read', 'wpgv-gift-cards', '', 'dashicons-tickets-alt', 25);
		// Gift item categories were folded into the gift card taxonomy, so there is
		// one Categories row rather than one per kind.
		if (!wpgv_unified_cpt_enabled()) {
			add_submenu_page('wpgv-gift-cards', 'Item Categories', 'Item Categories', 'edit_posts', 'edit-tags.php?taxonomy=wpgv_voucher_category&post_type=wpgv_voucher_product', '');
		}
		// A post type registered with show_in_menu pointing at a parent slug only
		// gets its list row, never an Add New one, so it is added explicitly.
		if (wpgv_unified_cpt_enabled()) {
			add_submenu_page('wpgv-gift-cards', __('Add New Gift Card', 'gift-voucher'), __('Add New Gift Card', 'gift-voucher'), 'edit_pages', 'post-new.php?post_type=voucher_template', '');
		}
		add_submenu_page('wpgv-gift-cards', __('Categories', 'gift-voucher'), __('Categories', 'gift-voucher'), 'edit_posts', 'edit-tags.php?taxonomy=category_voucher_template&post_type=voucher_template', '');
		$templatehook = add_submenu_page('wpgv-gift-cards', __('Voucher Templates', 'gift-voucher'), __('Voucher Templates', 'gift-voucher'), 'manage_options', 'voucher-templates', array($this, 'voucher_template'));
		add_submenu_page('wpgv-gift-cards', __('Add New Template', 'gift-voucher'), __('Add New Template', 'gift-voucher'), 'manage_options', 'new-voucher-template', array($this, 'new_voucher_template'));

		// Create page for viewing voucher details (will be hidden from sidebar but accessible via URL/action buttons)
		add_submenu_page('wpgv-gift-cards', __('View Voucher Details', 'gift-voucher'), __('View Voucher Details', 'gift-voucher'), 'manage_options', 'view-voucher-details', array($this, 'view_voucher_details'));

		$hook = add_submenu_page('wpgv-gift-cards', __('Orders', 'gift-voucher'), __('Orders', 'gift-voucher'), 'manage_options', 'vouchers-lists', array($this, 'voucher_list'));
		add_submenu_page('wpgv-gift-cards', __('Settings', 'gift-voucher'), __('Settings', 'gift-voucher'), 'manage_options', 'voucher-setting', array($this, 'voucher_settings'));

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
