<?php

/**
 * Export the gift card database (vouchers + activity) to a JSON file.
 *
 * Runs through admin-post.php so no HTML has been sent yet when the download
 * headers go out.
 *
 * @package Gift_Voucher
 */

if (!defined('ABSPATH')) exit;  // Exit if accessed directly

add_action('admin_post_wpgv_export_giftcard_db', 'wpgv_export_giftcard_db_handler');

/**
 * Stream the export payload as a JSON download.
 *
 * @return void
 */
function wpgv_export_giftcard_db_handler()
{
	if (!current_user_can('manage_options')) {
		wp_die(
			esc_html__('You are not allowed to export the gift card database.', 'gift-voucher'),
			esc_html__('Permission denied', 'gift-voucher'),
			array('response' => 403)
		);
	}

	check_admin_referer('wpgv_export_giftcard_db');

	global $wpdb;

	$tables = wpgv_db_transfer_tables();

	foreach ($tables as $table_name) {
		if (!wpgv_db_table_exists($table_name)) {
			wp_die(
				esc_html__('The gift card tables are missing. Deactivate and reactivate the plugin, then try again.', 'gift-voucher'),
				esc_html__('Export failed', 'gift-voucher'),
				array('response' => 500)
			);
		}
	}

	$counts = array();
	$total  = 0;
	foreach ($tables as $key => $table_name) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names cannot be placeholders; this one is built from $wpdb->prefix.
		$counts[$key] = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table_name}`");
		$total += $counts[$key];
	}

	if ($total < 1) {
		wp_die(
			esc_html__('There is no gift card data to export yet.', 'gift-voucher'),
			esc_html__('Nothing to export', 'gift-voucher'),
			array(
				'response'  => 200,
				'back_link' => true,
			)
		);
	}

	$filename = 'gift-voucher-database-' . gmdate('Y-m-d-His') . '.json';

	nocache_headers();
	header('Content-Type: application/json; charset=utf-8');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	header('X-Content-Type-Options: nosniff');

	// Discard anything a theme or another plugin may have buffered.
	while (ob_get_level() > 0) {
		ob_end_clean();
	}

	echo '{';
	echo '"meta":' . wp_json_encode(wpgv_export_giftcard_db_meta($counts));

	foreach ($tables as $key => $table_name) {
		echo ',' . wp_json_encode($key) . ':';
		wpgv_export_giftcard_db_stream_table($table_name, $key);
	}

	echo '}';

	exit;
}

/**
 * Metadata block describing the payload and the site it came from.
 *
 * @param array $counts Row counts keyed by payload key.
 * @return array
 */
function wpgv_export_giftcard_db_meta($counts)
{
	return array(
		'format'         => WPGV_DB_TRANSFER_FORMAT,
		'plugin'         => 'gift-voucher',
		'plugin_version' => defined('WPGIFT_VERSION') ? WPGIFT_VERSION : '',
		'db_version'     => get_option('jal_db_version'),
		'exported_at'    => gmdate('c'),
		'site_url'       => home_url(),
		'counts'         => $counts,
	);
}

/**
 * Write one table to the output stream as a JSON array, in chunks.
 *
 * Voucher rows carry an extra `_wpgv_references` key describing what their
 * template/item/product IDs pointed at, so the importer can remap those IDs by
 * title on the destination site. The key is stripped again on import because it
 * is not a real column.
 *
 * @param string $table_name Full table name.
 * @param string $key        Payload key.
 * @return void
 */
function wpgv_export_giftcard_db_stream_table($table_name, $key)
{
	global $wpdb;

	$collect_references = ('giftvouchers_list' === $key);
	$reference_columns  = wpgv_db_transfer_reference_columns();

	echo '[';

	$offset = 0;
	$first  = true;

	do {
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names cannot be placeholders; this one is built from $wpdb->prefix.
				"SELECT * FROM `{$table_name}` ORDER BY id ASC LIMIT %d OFFSET %d",
				WPGV_DB_TRANSFER_CHUNK,
				$offset
			),
			ARRAY_A
		);

		if (empty($rows)) {
			break;
		}

		foreach ($rows as $row) {
			if ($collect_references) {
				$references = array();
				foreach ($reference_columns as $column) {
					if (empty($row[$column])) {
						continue;
					}
					$description = wpgv_db_transfer_describe_reference($row[$column]);
					if (null !== $description) {
						$references[$column] = $description;
					}
				}
				if (!empty($references)) {
					$row['_wpgv_references'] = $references;
				}
			}

			echo $first ? '' : ',';
			echo wp_json_encode($row, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
			$first = false;
		}

		$offset += WPGV_DB_TRANSFER_CHUNK;

		// Keep peak memory flat on large exports.
		if (function_exists('flush')) {
			flush();
		}
	} while (count($rows) === WPGV_DB_TRANSFER_CHUNK);

	echo ']';
}
