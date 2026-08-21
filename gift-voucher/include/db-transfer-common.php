<?php

/**
 * Shared helpers for the gift card database export/import (JSON) feature.
 *
 * @package Gift_Voucher
 */

if (!defined('ABSPATH')) exit;  // Exit if accessed directly

/**
 * Payload format version. Bump when the JSON structure changes in a way that
 * older importers cannot understand.
 */
define('WPGV_DB_TRANSFER_FORMAT', 1);

/**
 * Rows read/written per chunk so large sites do not exhaust memory.
 */
define('WPGV_DB_TRANSFER_CHUNK', 500);

/**
 * Tables covered by the export/import.
 *
 * Deliberately excludes `giftvouchers_setting` (site configuration must not be
 * overwritten by an import) and `giftvouchers_template`/template CPTs.
 *
 * @return array Map of payload key => real table name.
 */
function wpgv_db_transfer_tables()
{
	global $wpdb;

	return array(
		'giftvouchers_list'     => $wpdb->prefix . 'giftvouchers_list',
		'giftvouchers_activity' => $wpdb->prefix . 'giftvouchers_activity',
	);
}

/**
 * Column names of a table on this installation.
 *
 * Used to drop unknown columns coming from a payload created by a different
 * plugin edition, so an import never fails with "Unknown column".
 *
 * @param string $table_name Full table name.
 * @return array List of column names.
 */
function wpgv_db_transfer_get_columns($table_name)
{
	global $wpdb;

	static $cache = array();

	if (isset($cache[$table_name])) {
		return $cache[$table_name];
	}

	if (!wpgv_db_table_exists($table_name)) {
		$cache[$table_name] = array();

		return $cache[$table_name];
	}

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names cannot be placeholders; this one is built from $wpdb->prefix.
	$columns = $wpdb->get_col("SHOW COLUMNS FROM `{$table_name}`", 0);
	$cache[$table_name] = is_array($columns) ? $columns : array();

	return $cache[$table_name];
}

/**
 * Keep only the keys that exist as columns on this installation.
 *
 * @param mixed $row     Row from the payload.
 * @param array $columns Local column names.
 * @return array Filtered row.
 */
function wpgv_db_transfer_filter_row($row, $columns)
{
	if (!is_array($row) || empty($columns)) {
		return array();
	}

	$row = array_intersect_key($row, array_flip($columns));

	foreach ($row as $key => $value) {
		if (is_array($value) || is_object($value)) {
			unset($row[$key]);
		}
	}

	return $row;
}

/**
 * Largest import payload accepted, in bytes.
 *
 * @return int
 */
function wpgv_db_transfer_max_import_bytes()
{
	$server_limit = function_exists('wp_max_upload_size') ? (int) wp_max_upload_size() : 0;
	$plugin_limit = (int) apply_filters('wpgv_db_transfer_max_import_bytes', 25 * MB_IN_BYTES);

	if ($plugin_limit <= 0) {
		$plugin_limit = 25 * MB_IN_BYTES;
	}

	if ($server_limit > 0) {
		return min($server_limit, $plugin_limit);
	}

	return $plugin_limit;
}

/**
 * Voucher columns that reference a template, product or item by ID.
 *
 * Those IDs are meaningless on another installation, so they are described in
 * the payload and can be remapped by title on import.
 *
 * @return array
 */
function wpgv_db_transfer_reference_columns()
{
	return array('template_id', 'item_id', 'product_id');
}

/**
 * Describe one referenced ID so the importer can look it up again by name.
 *
 * An ID can resolve to a post (modern templates, gift items, WooCommerce
 * products) and/or to a row of the legacy `giftvouchers_template` table. Both
 * are recorded when present.
 *
 * @param int $id Referenced ID.
 * @return array|null Description, or null when the ID resolves to nothing.
 */
function wpgv_db_transfer_describe_reference($id)
{
	global $wpdb;

	$id = absint($id);
	if ($id <= 0) {
		return null;
	}

	$description = array();

	$post = get_post($id);
	if ($post instanceof WP_Post) {
		$description['post_type']  = $post->post_type;
		$description['post_title'] = $post->post_title;
	}

	$template_table = $wpdb->prefix . 'giftvouchers_template';
	if (wpgv_db_table_exists($template_table)) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names cannot be placeholders; this one is built from $wpdb->prefix.
		$title = $wpdb->get_var($wpdb->prepare("SELECT title FROM `{$template_table}` WHERE id = %d", $id));
		if (null !== $title) {
			$description['template_row_title'] = (string) $title;
		}
	}

	return empty($description) ? null : $description;
}

/**
 * Find the local ID matching a reference description.
 *
 * @param int   $original_id Referenced ID as stored in the payload.
 * @param array $description Description recorded at export time.
 * @return int Local ID, or 0 when nothing matches.
 */
function wpgv_db_transfer_resolve_reference($original_id, $description)
{
	global $wpdb;

	$original_id = absint($original_id);
	if ($original_id <= 0 || !is_array($description)) {
		return 0;
	}

	// The same ID pointing at the same thing locally: nothing to remap.
	if (!empty($description['post_type'])) {
		$post = get_post($original_id);
		if (
			$post instanceof WP_Post
			&& $post->post_type === $description['post_type']
			&& $post->post_title === (isset($description['post_title']) ? $description['post_title'] : '')
		) {
			return $original_id;
		}
	}

	if (isset($description['template_row_title'])) {
		$template_table = $wpdb->prefix . 'giftvouchers_template';
		if (wpgv_db_table_exists($template_table)) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names cannot be placeholders; this one is built from $wpdb->prefix.
			$title = $wpdb->get_var($wpdb->prepare("SELECT title FROM `{$template_table}` WHERE id = %d", $original_id));
			if (null !== $title && (string) $title === $description['template_row_title']) {
				return $original_id;
			}
		}
	}

	// Same title, different ID: remap to the local post.
	if (!empty($description['post_type']) && !empty($description['post_title'])) {
		$matches = get_posts(array(
			'post_type'        => $description['post_type'],
			'title'            => $description['post_title'],
			'post_status'      => 'any',
			'posts_per_page'   => 2,
			'fields'           => 'ids',
			'suppress_filters' => true,
			'no_found_rows'    => true,
		));

		// Only remap when the match is unambiguous.
		if (is_array($matches) && 1 === count($matches)) {
			return absint($matches[0]);
		}
	}

	if (!empty($description['template_row_title'])) {
		$template_table = $wpdb->prefix . 'giftvouchers_template';
		if (wpgv_db_table_exists($template_table)) {
			$ids = $wpdb->get_col($wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names cannot be placeholders; this one is built from $wpdb->prefix.
				"SELECT id FROM `{$template_table}` WHERE title = %s LIMIT 2",
				$description['template_row_title']
			));
			if (is_array($ids) && 1 === count($ids)) {
				return absint($ids[0]);
			}
		}
	}

	return 0;
}

/**
 * URL of the export endpoint, nonce included.
 *
 * @return string
 */
function wpgv_db_transfer_export_url()
{
	return wp_nonce_url(
		admin_url('admin-post.php?action=wpgv_export_giftcard_db'),
		'wpgv_export_giftcard_db'
	);
}

/**
 * URL of the import screen.
 *
 * @return string
 */
function wpgv_db_transfer_import_url()
{
	return add_query_arg(
		array(
			'page' => 'vouchers-lists',
			'tab'  => 'import-db',
		),
		admin_url('admin.php')
	);
}
