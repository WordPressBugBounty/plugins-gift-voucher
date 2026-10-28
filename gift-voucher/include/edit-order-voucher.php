<?php


if (!defined('ABSPATH')) exit;  // Exit if accessed directly


// Admin-only handlers: no `nopriv` registration, these are reachable from the
// View Voucher Details screen only (`/include/view_voucher_details.php`).
add_action('wp_ajax_update_voucher_date', 'update_voucher_date');

function update_voucher_date()
{
    // Check nonce to prevent CSRF
    check_ajax_referer('update_voucher_date_action', 'security');

    // Check user permissions. These handlers change the financial state of a
    // voucher, so they require the same capability as the screen that renders
    // their forms (`view_voucher_details.php`).
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('You do not have permission to perform this action.', 'gift-voucher'), 403);
    }

    // Process data if valid
    if (isset($_POST['voucher_id']) && isset($_POST['new_date'])) {
        $voucher_id = absint($_POST['voucher_id']);
        $new_date = sanitize_text_field(wp_unslash($_POST['new_date']));

        if ($voucher_id <= 0) {
            wp_send_json_error('Invalid data', 400);
        }

        global $wpdb;
        $voucher_table = $wpdb->prefix . 'giftvouchers_list';
        $updated = $wpdb->update(
            $voucher_table,
            array('expiry' => $new_date),
            array('id' => $voucher_id),
            array('%s'),
            array('%d')
        );

        if ($updated !== false) {
            wp_send_json_success('Date updated successfully');
        } else {
            wp_send_json_error('Failed to update date');
        }
    } else {
        wp_send_json_error('Invalid data');
    }
    wp_die();
}



add_action('wp_ajax_update_voucher_note', 'update_voucher_note');

function update_voucher_note()
{
    // Check nonce to prevent CSRF
    check_ajax_referer('update_voucher_note_action', 'security');

    // Check user permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('You do not have permission to perform this action.', 'gift-voucher'), 403);
    }

    // Process data if valid
    if (isset($_POST['voucher_id']) && isset($_POST['data_note'])) {
        $voucher_id = absint($_POST['voucher_id']);
        $data_note = sanitize_textarea_field(wp_unslash($_POST['data_note']));

        if ($voucher_id <= 0) {
            wp_send_json_error('Invalid data', 400);
        }

        global $wpdb;
        $voucher_table = $wpdb->prefix . 'giftvouchers_list';
        $updated = $wpdb->update(
            $voucher_table,
            array('note_order' => $data_note),
            array('id' => $voucher_id),
            array('%s'),
            array('%d')
        );

        if ($updated !== false) {
            wp_send_json_success('Note updated successfully');
        } else {
            wp_send_json_error('Failed to update the note');
        }
    } else {
        wp_send_json_error('Invalid data');
    }
    wp_die();
}



add_action('wp_ajax_update_voucher_price', 'update_voucher_price');

function update_voucher_price()
{
    // Check nonce to prevent CSRF
    check_ajax_referer('update_voucher_price_action', 'security');

    // Check user permissions. The voucher balance is SUM(activity.amount), so
    // this handler mints spendable balance - `manage_options` only.
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('You do not have permission to perform this action.', 'gift-voucher'), 403);
    }

    if (isset($_POST['data_price']) && isset($_POST['voucher_id'])) {
        $voucher_id = absint($_POST['voucher_id']);
        $data_price = floatval($_POST['data_price']);

        if ($voucher_id <= 0 || $data_price < 0) {
            wp_send_json_error('Invalid data', 400);
        }

        global $wpdb;
        $giftvouchers_activity = $wpdb->prefix . 'giftvouchers_activity';
        $giftvouchers_list = $wpdb->prefix . 'giftvouchers_list';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name built from $wpdb->prefix.
        $voucher = $wpdb->get_row($wpdb->prepare("SELECT `amount`, `payment_status`, `status` FROM `{$giftvouchers_list}` WHERE `id` = %d", $voucher_id));
        if (!$voucher) {
            wp_send_json_error('Invalid data', 400);
        }

        $wpdb->update(
            $giftvouchers_list,
            array('amount' => $data_price),
            array('id' => $voucher_id),
            array('%s'),
            array('%d')
        );

        // The balance is SUM(activity.amount). Writing the new price into the
        // 'create' row added it on top of the payment already credited, so a paid
        // voucher held both. Now a paid, unused voucher moves by the change in
        // price, never below zero; an unpaid one is credited its new price when
        // it is marked Paid.
        if ($voucher->payment_status === 'Paid' && $voucher->status !== 'used') {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name built from $wpdb->prefix.
            $balance = (float) $wpdb->get_var($wpdb->prepare("SELECT IFNULL(SUM(amount), 0) FROM `{$giftvouchers_activity}` WHERE `voucher_id` = %d", $voucher_id));
            $change = max($data_price - (float) $voucher->amount, -max($balance, 0));
            if (abs($change) >= 0.00001) {
                WPGV_Gift_Voucher_Activity::record($voucher_id, 'transaction', $change, 'Voucher price changed by an administrator.');
            }
        }
        wp_send_json_success('Price updated successfully');
    } else {
        wp_send_json_error('Invalid data');
    }
    wp_die();
}
