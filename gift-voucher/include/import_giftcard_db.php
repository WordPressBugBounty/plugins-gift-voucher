<?php

/**
 * Import a gift card database export (JSON) produced by this plugin.
 *
 * Rendered as the "Import Database" tab of the Gift Voucher Orders screen.
 *
 * @package Gift_Voucher
 */

if (!defined('ABSPATH')) exit;  // Exit if accessed directly

/**
 * Import modes offered to the administrator.
 *
 * @return array
 */
function wpgv_db_import_modes()
{
	return array(
		'merge'      => __('Merge — update gift cards that already exist and add the missing ones.', 'gift-voucher'),
		'insert_new' => __('Add only — skip every gift card whose ID already exists here.', 'gift-voucher'),
	);
}

/**
 * Handle the submitted import form.
 *
 * Called while the Gift Voucher Orders screen renders, so it only returns data;
 * rendering is left to wpgv_render_giftcard_db_import_panel().
 *
 * @return array|null Result summary, or null when nothing was submitted.
 */
function wpgv_handle_giftcard_db_import()
{
	if (!isset($_POST['wpgv_import_db_submit'])) {
		return null;
	}

	if (!current_user_can('manage_options')) {
		return wpgv_db_import_error(__('You are not allowed to import gift card data.', 'gift-voucher'));
	}

	check_admin_referer('wpgv_import_giftcard_db');

	if (empty($_POST['wpgv_import_db_confirm'])) {
		return wpgv_db_import_error(__('Please confirm that you have a database backup before importing.', 'gift-voucher'));
	}

	$mode = isset($_POST['wpgv_import_db_mode']) ? sanitize_key(wp_unslash($_POST['wpgv_import_db_mode'])) : 'merge';
	if (!array_key_exists($mode, wpgv_db_import_modes())) {
		$mode = 'merge';
	}

	$remap_references = !empty($_POST['wpgv_import_db_remap']);

	$payload = wpgv_db_import_read_uploaded_payload();
	if (is_wp_error($payload)) {
		return wpgv_db_import_error($payload->get_error_message());
	}

	return wpgv_db_import_run($payload, $mode, $remap_references);
}

/**
 * Build an error result.
 *
 * @param string $message Message to display.
 * @return array
 */
function wpgv_db_import_error($message)
{
	return array(
		'status'  => 'error',
		'message' => $message,
	);
}

/**
 * Validate the uploaded file and decode it.
 *
 * @return array|WP_Error Decoded payload or an error.
 */
function wpgv_db_import_read_uploaded_payload()
{
	// The nonce and capability are verified by the only caller,
	// wpgv_handle_giftcard_db_import(), before this function runs.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in the caller.
	if (empty($_FILES['wpgv_import_file']) || !is_array($_FILES['wpgv_import_file'])) {
		return new WP_Error('wpgv_no_file', __('Please choose a JSON file to import.', 'gift-voucher'));
	}

	// $_FILES is not slash-escaped by wp_magic_quotes(), so it must NOT be run
	// through wp_unslash(): that would strip the backslashes out of the Windows
	// temp path and make the upload unreadable. Members are validated below.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in the caller; individual members are validated below.
	$file = $_FILES['wpgv_import_file'];

	$error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
	if (UPLOAD_ERR_NO_FILE === $error) {
		return new WP_Error('wpgv_no_file', __('Please choose a JSON file to import.', 'gift-voucher'));
	}

	if (UPLOAD_ERR_OK !== $error) {
		return new WP_Error(
			'wpgv_upload_error',
			__('The file could not be uploaded. It may be larger than the server allows.', 'gift-voucher')
		);
	}

	$tmp_name = isset($file['tmp_name']) ? $file['tmp_name'] : '';
	if (!$tmp_name || !is_uploaded_file($tmp_name)) {
		return new WP_Error('wpgv_upload_error', __('The uploaded file could not be read.', 'gift-voucher'));
	}

	$file_name = isset($file['name']) ? sanitize_file_name($file['name']) : '';
	$extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
	if ('json' !== $extension) {
		return new WP_Error('wpgv_bad_extension', __('Only .json files exported by this plugin can be imported.', 'gift-voucher'));
	}

	$size = isset($file['size']) ? (int) $file['size'] : 0;
	$max_bytes = wpgv_db_transfer_max_import_bytes();
	if ($size < 1) {
		return new WP_Error('wpgv_empty_file', __('The uploaded file is empty.', 'gift-voucher'));
	}

	if ($size > $max_bytes) {
		return new WP_Error(
			'wpgv_file_too_large',
			sprintf(
				/* translators: %s: maximum file size, already formatted. */
				__('The file is too large. The maximum accepted size is %s.', 'gift-voucher'),
				size_format($max_bytes)
			)
		);
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a validated PHP upload temp file.
	$contents = file_get_contents($tmp_name);
	if (!is_string($contents) || '' === trim($contents)) {
		return new WP_Error('wpgv_empty_file', __('The uploaded file is empty.', 'gift-voucher'));
	}

	$payload = json_decode(trim($contents), true);
	if (!is_array($payload) || JSON_ERROR_NONE !== json_last_error()) {
		return new WP_Error('wpgv_invalid_json', __('The file is not valid JSON. Please upload a file created by the Export Database tab.', 'gift-voucher'));
	}

	if (!isset($payload['giftvouchers_list']) && !isset($payload['giftvouchers_activity'])) {
		return new WP_Error('wpgv_invalid_structure', __('This JSON file does not contain gift card data. Please upload a file created by the Export Database tab.', 'gift-voucher'));
	}

	$format = isset($payload['meta']['format']) ? (int) $payload['meta']['format'] : 0;
	if ($format > WPGV_DB_TRANSFER_FORMAT) {
		return new WP_Error(
			'wpgv_future_format',
			__('This file was created by a newer version of the plugin. Please update Gift Voucher before importing it.', 'gift-voucher')
		);
	}

	return $payload;
}

/**
 * Write the decoded payload into the local tables.
 *
 * @param array  $payload          Decoded payload.
 * @param string $mode             'merge' or 'insert_new'.
 * @param bool   $remap_references Whether to remap template/item/product IDs by title.
 * @return array Result summary.
 */
function wpgv_db_import_run($payload, $mode, $remap_references)
{
	global $wpdb;

	$tables = wpgv_db_transfer_tables();
	$list_table     = $tables['giftvouchers_list'];
	$activity_table = $tables['giftvouchers_activity'];

	foreach ($tables as $table_name) {
		if (!wpgv_db_table_exists($table_name)) {
			return wpgv_db_import_error(__('The gift card tables are missing. Deactivate and reactivate the plugin, then try again.', 'gift-voucher'));
		}
	}

	$list_columns     = wpgv_db_transfer_get_columns($list_table);
	$activity_columns = wpgv_db_transfer_get_columns($activity_table);

	$stats = array(
		'status'              => 'success',
		'mode'                => $mode,
		'source_site'         => isset($payload['meta']['site_url']) ? (string) $payload['meta']['site_url'] : '',
		'source_version'      => isset($payload['meta']['plugin_version']) ? (string) $payload['meta']['plugin_version'] : '',
		'vouchers_added'      => 0,
		'vouchers_updated'    => 0,
		'vouchers_skipped'    => 0,
		'code_conflicts'      => 0,
		'schema_skipped'      => 0,
		'invalid_skipped'     => 0,
		'db_errors'           => 0,
		'activity_added'      => 0,
		'activity_duplicate'  => 0,
		'activity_orphan'     => 0,
		'references_remapped' => 0,
		'references_broken'   => 0,
		'dropped_columns'     => array(),
	);

	// Existing IDs and coupon codes, kept in sync as rows are written so the
	// UNIQUE index on `couponcode` is never violated mid-import.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names cannot be placeholders; this one is built from $wpdb->prefix.
	$existing_ids = array_flip(array_map('intval', (array) $wpdb->get_col("SELECT id FROM `{$list_table}`")));

	$code_owner = array();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names cannot be placeholders; this one is built from $wpdb->prefix.
	$code_rows  = $wpdb->get_results("SELECT id, couponcode FROM `{$list_table}`", ARRAY_A);
	foreach ((array) $code_rows as $code_row) {
		$code_owner[(string) $code_row['couponcode']] = (int) $code_row['id'];
	}

	$imported_voucher_ids = array();

	$list_rows = isset($payload['giftvouchers_list']) && is_array($payload['giftvouchers_list'])
		? $payload['giftvouchers_list']
		: array();

	foreach ($list_rows as $raw_row) {
		if (!is_array($raw_row)) {
			$stats['invalid_skipped']++;
			continue;
		}

		$references = isset($raw_row['_wpgv_references']) && is_array($raw_row['_wpgv_references'])
			? $raw_row['_wpgv_references']
			: array();

		$stats['dropped_columns'] = array_unique(array_merge(
			$stats['dropped_columns'],
			array_values(array_diff(array_keys($raw_row), $list_columns, array('_wpgv_references')))
		));

		$row = wpgv_db_transfer_filter_row($raw_row, $list_columns);
		if (empty($row)) {
			$stats['schema_skipped']++;
			continue;
		}

		$id = isset($row['id']) ? absint($row['id']) : 0;
		if ($id < 1) {
			$stats['invalid_skipped']++;
			continue;
		}

		$exists = isset($existing_ids[$id]);
		if ($exists && 'insert_new' === $mode) {
			$stats['vouchers_skipped']++;
			continue;
		}

		// A different local gift card already owns this code: importing would
		// destroy it, so leave both alone and report the conflict.
		$code = isset($row['couponcode']) ? (string) $row['couponcode'] : '';
		if ('' !== $code && isset($code_owner[$code]) && $code_owner[$code] !== $id) {
			$stats['code_conflicts']++;
			continue;
		}

		if ($remap_references) {
			$row = wpgv_db_import_remap_row_references($row, $references, $stats);
		} else {
			wpgv_db_import_count_broken_references($row, $stats);
		}

		if ($exists) {
			$result = $wpdb->update($list_table, $row, array('id' => $id));
			if (false === $result) {
				$stats['db_errors']++;
				continue;
			}
			$stats['vouchers_updated']++;
		} else {
			$result = $wpdb->insert($list_table, $row);
			if (false === $result) {
				$stats['db_errors']++;
				continue;
			}
			$stats['vouchers_added']++;
			$existing_ids[$id] = true;
		}

		if ('' !== $code) {
			$code_owner[$code] = $id;
		}
		$imported_voucher_ids[$id] = true;
	}

	$allowed_voucher_ids = $existing_ids + $imported_voucher_ids;

	$activity_rows = isset($payload['giftvouchers_activity']) && is_array($payload['giftvouchers_activity'])
		? $payload['giftvouchers_activity']
		: array();

	foreach ($activity_rows as $raw_row) {
		if (!is_array($raw_row)) {
			$stats['invalid_skipped']++;
			continue;
		}

		$row = wpgv_db_transfer_filter_row($raw_row, $activity_columns);
		if (empty($row)) {
			$stats['schema_skipped']++;
			continue;
		}

		$voucher_id = isset($row['voucher_id']) ? absint($row['voucher_id']) : 0;
		if ($voucher_id < 1 || !isset($allowed_voucher_ids[$voucher_id])) {
			$stats['activity_orphan']++;
			continue;
		}

		// Activity IDs are not referenced anywhere, so they are re-issued
		// locally and rows are deduplicated on their natural key instead. That
		// keeps re-importing the same file idempotent.
		unset($row['id']);

		$duplicate_id = $wpdb->get_var($wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names cannot be placeholders; this one is built from $wpdb->prefix.
			"SELECT id FROM `{$activity_table}`
			 WHERE voucher_id = %d
			   AND COALESCE(`action`, '') = %s
			   AND COALESCE(amount, 0) = %f
			   AND COALESCE(`note`, '') = %s
			   AND COALESCE(activity_date, '') = %s
			 LIMIT 1",
			$voucher_id,
			isset($row['action']) ? (string) $row['action'] : '',
			isset($row['amount']) ? (float) $row['amount'] : 0,
			isset($row['note']) ? (string) $row['note'] : '',
			isset($row['activity_date']) ? (string) $row['activity_date'] : ''
		));

		if ($duplicate_id) {
			$stats['activity_duplicate']++;
			continue;
		}

		if (false === $wpdb->insert($activity_table, $row)) {
			$stats['db_errors']++;
			continue;
		}

		$stats['activity_added']++;
	}

	return $stats;
}

/**
 * Point a row's template/item/product IDs at their local equivalents.
 *
 * @param array $row        Filtered voucher row.
 * @param array $references Reference descriptions from the payload.
 * @param array $stats      Result summary, updated by reference.
 * @return array Row with remapped IDs.
 */
function wpgv_db_import_remap_row_references($row, $references, &$stats)
{
	foreach (wpgv_db_transfer_reference_columns() as $column) {
		if (empty($row[$column])) {
			continue;
		}

		$original_id = absint($row[$column]);
		if ($original_id < 1) {
			continue;
		}

		if (empty($references[$column])) {
			// Nothing recorded at export time: keep the ID but flag it when it
			// no longer resolves here.
			if (null === wpgv_db_transfer_describe_reference($original_id)) {
				$stats['references_broken']++;
			}
			continue;
		}

		$resolved = wpgv_db_transfer_resolve_reference($original_id, $references[$column]);

		if ($resolved < 1) {
			$stats['references_broken']++;
			continue;
		}

		if ($resolved !== $original_id) {
			$row[$column] = $resolved;
			$stats['references_remapped']++;
		}
	}

	return $row;
}

/**
 * Count IDs that do not resolve locally, without changing them.
 *
 * @param array $row   Filtered voucher row.
 * @param array $stats Result summary, updated by reference.
 * @return void
 */
function wpgv_db_import_count_broken_references($row, &$stats)
{
	foreach (wpgv_db_transfer_reference_columns() as $column) {
		if (empty($row[$column])) {
			continue;
		}

		if (null === wpgv_db_transfer_describe_reference(absint($row[$column]))) {
			$stats['references_broken']++;
		}
	}
}

/**
 * Render the import tab: result notices plus the upload form.
 *
 * @param array|null $result Result returned by wpgv_handle_giftcard_db_import().
 * @return void
 */
function wpgv_render_giftcard_db_import_panel($result = null)
{
	if (is_array($result)) {
		wpgv_render_giftcard_db_import_result($result);
	}

	$modes = wpgv_db_import_modes();
	?>
	<div class="wpgv-import-panel">
		<form method="post" enctype="multipart/form-data" class="wpgv-import-card" action="<?php echo esc_url(wpgv_db_transfer_import_url()); ?>">
			<?php wp_nonce_field('wpgv_import_giftcard_db'); ?>

			<div class="wpgv-import-card__header">
				<h2><?php esc_html_e('Import Gift Card Database', 'gift-voucher'); ?></h2>
				<p><?php esc_html_e('Upload a JSON file created by the Export Database tab to merge its gift cards and activity into this site.', 'gift-voucher'); ?></p>
			</div>

			<div class="wpgv-import-card__body">
				<p>
					<label for="wpgv_import_file"><strong><?php esc_html_e('JSON file', 'gift-voucher'); ?></strong></label><br>
					<input type="file" name="wpgv_import_file" id="wpgv_import_file" accept=".json,application/json" required>
					<span class="description">
						<?php
						printf(
							/* translators: %s: maximum upload size. */
							esc_html__('Maximum size: %s.', 'gift-voucher'),
							esc_html(size_format(wpgv_db_transfer_max_import_bytes()))
						);
						?>
					</span>
				</p>

				<fieldset class="wpgv-import-card__modes">
					<legend><strong><?php esc_html_e('When a gift card already exists here', 'gift-voucher'); ?></strong></legend>
					<?php foreach ($modes as $mode_key => $mode_label) : ?>
						<label>
							<input type="radio" name="wpgv_import_db_mode" value="<?php echo esc_attr($mode_key); ?>" <?php checked('merge', $mode_key); ?>>
							<?php echo esc_html($mode_label); ?>
						</label><br>
					<?php endforeach; ?>
				</fieldset>

				<p>
					<label>
						<input type="checkbox" name="wpgv_import_db_remap" value="1" checked>
						<?php esc_html_e('Try to reconnect gift cards to local templates and items with the same name.', 'gift-voucher'); ?>
					</label>
				</p>

				<div class="notice notice-warning inline wpgv-import-card__warning">
					<p><strong><?php esc_html_e('Before you import:', 'gift-voucher'); ?></strong></p>
					<ul>
						<li><?php esc_html_e('Back up your database. Existing gift cards with the same ID will be overwritten in Merge mode.', 'gift-voucher'); ?></li>
						<li><?php esc_html_e('PDF files are not part of the export. Copy wp-content/uploads/voucherpdfuploads/ across as well, otherwise PDF links will not open.', 'gift-voucher'); ?></li>
						<li><?php esc_html_e('Plugin settings, templates and items are not imported. Set those up on this site first so gift cards can be reconnected to them.', 'gift-voucher'); ?></li>
					</ul>
				</div>

				<p>
					<label>
						<input type="checkbox" name="wpgv_import_db_confirm" value="1" required>
						<?php esc_html_e('I have a backup of this database and want to continue.', 'gift-voucher'); ?>
					</label>
				</p>
			</div>

			<p class="submit">
				<input type="submit" name="wpgv_import_db_submit" class="button button-primary" value="<?php echo esc_attr__('Import Database', 'gift-voucher'); ?>">
				<a class="button" href="<?php echo esc_url(admin_url('admin.php?page=vouchers-lists')); ?>"><?php esc_html_e('Cancel', 'gift-voucher'); ?></a>
			</p>
		</form>
	</div>
	<?php
}

/**
 * Render the notices summarising an import run.
 *
 * @param array $result Result summary.
 * @return void
 */
function wpgv_render_giftcard_db_import_result($result)
{
	if (isset($result['status']) && 'error' === $result['status']) {
		echo '<div class="notice notice-error"><p>' . esc_html($result['message']) . '</p></div>';

		return;
	}

	$has_problems = $result['code_conflicts'] > 0
		|| $result['schema_skipped'] > 0
		|| $result['invalid_skipped'] > 0
		|| $result['db_errors'] > 0
		|| $result['activity_orphan'] > 0
		|| $result['references_broken'] > 0;

	$notice_class = $has_problems ? 'notice-warning' : 'notice-success';

	echo '<div class="notice ' . esc_attr($notice_class) . '"><p><strong>';
	esc_html_e('Import finished.', 'gift-voucher');
	echo '</strong></p><ul style="list-style:disc;margin-left:20px;">';

	$lines = array(
		sprintf(
			/* translators: 1: number of gift cards added, 2: number updated, 3: number skipped. */
			__('Gift cards: %1$d added, %2$d updated, %3$d skipped.', 'gift-voucher'),
			(int) $result['vouchers_added'],
			(int) $result['vouchers_updated'],
			(int) $result['vouchers_skipped']
		),
		sprintf(
			/* translators: 1: activity rows added, 2: duplicates skipped. */
			__('Activity entries: %1$d added, %2$d already present.', 'gift-voucher'),
			(int) $result['activity_added'],
			(int) $result['activity_duplicate']
		),
	);

	if ($result['references_remapped'] > 0) {
		$lines[] = sprintf(
			/* translators: %d: number of reconnected references. */
			__('%d template, item or product reference was reconnected to this site.', 'gift-voucher'),
			(int) $result['references_remapped']
		);
	}

	if ($result['code_conflicts'] > 0) {
		$lines[] = sprintf(
			/* translators: %d: number of conflicting gift cards. */
			__('%d gift card was skipped because its code is already used by a different gift card here.', 'gift-voucher'),
			(int) $result['code_conflicts']
		);
	}

	if ($result['references_broken'] > 0) {
		$lines[] = sprintf(
			/* translators: %d: number of unresolved references. */
			__('%d gift card reference could not be matched to a template, item or product on this site. Those gift cards may not render a PDF until you reassign them.', 'gift-voucher'),
			(int) $result['references_broken']
		);
	}

	if ($result['activity_orphan'] > 0) {
		$lines[] = sprintf(
			/* translators: %d: number of orphan activity rows. */
			__('%d activity entry was skipped because its gift card does not exist here.', 'gift-voucher'),
			(int) $result['activity_orphan']
		);
	}

	if ($result['schema_skipped'] > 0 || $result['invalid_skipped'] > 0) {
		$lines[] = sprintf(
			/* translators: %d: number of unreadable rows. */
			__('%d row was skipped because it could not be read.', 'gift-voucher'),
			(int) $result['schema_skipped'] + (int) $result['invalid_skipped']
		);
	}

	if ($result['db_errors'] > 0) {
		$lines[] = sprintf(
			/* translators: %d: number of failed database writes. */
			__('%d row could not be written to the database.', 'gift-voucher'),
			(int) $result['db_errors']
		);
	}

	if (!empty($result['dropped_columns'])) {
		$lines[] = sprintf(
			/* translators: %s: comma separated column names. */
			__('These fields exist in the file but not on this site, so they were ignored: %s.', 'gift-voucher'),
			implode(', ', array_map('sanitize_key', $result['dropped_columns']))
		);
	}

	foreach ($lines as $line) {
		echo '<li>' . esc_html($line) . '</li>';
	}

	echo '</ul></div>';
}
