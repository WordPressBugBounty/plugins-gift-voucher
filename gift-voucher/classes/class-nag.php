<?php

if (!defined('ABSPATH')) exit;

class WPGIFT_Nag
{

	/**
	 * Setup the class
	 */
	public function setup()
	{

		// catch nag hide
		$this->catch_hide_notice();

		// bind nag
		$this->bind();
	}

	/**
	 * Catch the hide nag request
	 */
	private function catch_hide_notice()
	{

		if (isset($_GET[WPGIFT_ADMIN_NOTICE_KEY]) && current_user_can('install_plugins')) {
			// Require a valid nonce for any action that modifies state / user meta
			// (prevents CSRF / accidental external triggers).
			if (empty($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'wpgv_hide_notice')) {
				// Bad request — stop processing. Use wp_die with a generic message.
				wp_die( esc_html__('Invalid request.', 'gift-voucher') );
			}
			// Add user meta
			global $current_user;
			add_user_meta($current_user->ID, WPGIFT_ADMIN_NOTICE_KEY, '1', true);

			// Build redirect URL
			$query_params = $this->get_admin_querystring_array();
			unset($query_params[WPGIFT_ADMIN_NOTICE_KEY]);
			$query_string = http_build_query($query_params);
			if ($query_string != '') {
				$query_string = '?' . $query_string;
			}

			$redirect_url = "";
			$host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
			$path = isset($_SERVER['PHP_SELF']) ? sanitize_text_field(wp_unslash($_SERVER['PHP_SELF'])) : '';

			if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
				$redirect_url = sanitize_url('https://' . $host . $path . $query_string);
			} else {
				$redirect_url = sanitize_url('http://' . $host . $path . $query_string);
			}
			// Redirect: use wp_safe_redirect so redirect targets are validated by WP.
			// We pass the raw URL to the safe redirect helper and still call exit() afterwards.
			wp_safe_redirect( esc_url_raw( $redirect_url ) );
			exit;
		}
	}

	/**
	 * Bind nag message
	 */
	private function bind()
	{
		// Is admin notice hidden?
		$current_user = wp_get_current_user();
		$hide_notice  = get_user_meta($current_user->ID, WPGIFT_ADMIN_NOTICE_KEY, true);

		// Check if we need to display the notice
		if (current_user_can('install_plugins') && '' == $hide_notice) {
			// Get installation date
			$datetime_install = $this->get_install_date();
			$datetime_past    = new DateTime('-10 days');

			if ($datetime_past >= $datetime_install) {
				// 10 or more days ago, show admin notice
				add_action('admin_notices', array($this, 'display_admin_notice'));
			}
		}
	}

	/**
	 * Get the install data
	 *
	 * @return DateTime
	 */
	private function get_install_date()
	{
		$date_string = get_site_option(WPGIFT_INSTALL_DATE, '');
		if ($date_string == '') {
			// There is no install date, plugin was installed before version 1.2.0. Add it now.
			$date_string = self::insert_install_date();
		}

		return new DateTime($date_string);
	}

	/**
	 * Parse the admin query string
	 *
	 * @return array
	 */
	private function get_admin_querystring_array()
	{

		$query_string = isset($_SERVER['QUERY_STRING']) ? sanitize_text_field(wp_unslash($_SERVER['QUERY_STRING'])) : '';

		$params = array();

		if (!empty($query_string)) {
			wp_parse_str($query_string, $params);
		}

		$array_params = array();
		foreach ($params as $key => $value) {
			$sanitize_value = esc_html($value);
			$array_params[esc_html($key)] = $sanitize_value;
		}
		return $array_params;
	}

	/**
	 * Insert the install date
	 *
	 * @return string
	 */
	public static function insert_install_date()
	{
		$datetime_now = new DateTime();
		$date_string  = $datetime_now->format('Y-m-d');
		add_site_option(WPGIFT_INSTALL_DATE, $date_string, '', 'no');

		return $date_string;
	}

	/**
	 * Display the admin notice
	 */
	public function display_admin_notice()
	{

		$query_params = $this->get_admin_querystring_array();
			// Build a nonce protected URL that will toggle the hide-notice cookie.
			$redirect_params = array_merge($query_params, array(WPGIFT_ADMIN_NOTICE_KEY => '1'));
			$raw_url = add_query_arg($redirect_params, admin_url('admin.php'));
			// Add nonce for 'wpgv_hide_notice' action to the URL
			$url_with_nonce = wp_nonce_url($raw_url, 'wpgv_hide_notice');
			$query_string = esc_html($url_with_nonce);

			// Output admin notice (HTML escape everything). The hide link uses
			// the same nonce action we verify above.
			printf(
				'<div class="notice notice-info is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
				esc_html__('Thanks for using Gift Cards plugin. You can hide this message if you like.', 'gift-voucher'),
				esc_url($url_with_nonce),
				esc_html__('Hide this notice', 'gift-voucher')
			);
	}
}
