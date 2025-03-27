<?php


if (!defined('ABSPATH')) exit;  // Exit if accessed directly


add_action('wp_ajax_update_voucher_date', 'update_voucher_date');
add_action('wp_ajax_nopriv_update_voucher_date', 'update_voucher_date');

function update_voucher_date()
{
    // Check nonce to prevent CSRF
    check_ajax_referer('update_voucher_date_action', 'security');

    // Check user permissions
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('You do not have permission to perform this action.');
        wp_die();
    }

    // Process data if valid
    if (isset($_POST['voucher_id']) && isset($_POST['new_date'])) {
        $voucher_id = sanitize_text_field(wp_unslash($_POST['voucher_id']));
        $new_date = sanitize_text_field(wp_unslash($_POST['new_date']));

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
add_action('wp_ajax_nopriv_update_voucher_note', 'update_voucher_note');

function update_voucher_note()
{
    // Check nonce to prevent CSRF
    check_ajax_referer('update_voucher_note_action', 'security');

    // Check user permissions
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('You do not have permission to perform this action.');
        wp_die();
    }

    // Process data if valid
    if (isset($_POST['voucher_id']) && isset($_POST['data_note'])) {
        $voucher_id = sanitize_text_field(wp_unslash($_POST['voucher_id']));
        $data_note = sanitize_textarea_field(wp_unslash($_POST['data_note']));

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
add_action('wp_ajax_nopriv_update_voucher_price', 'update_voucher_price');

function update_voucher_price()
{
    // Check nonce to prevent CSRF
    check_ajax_referer('update_voucher_price_action', 'security');

    // Check user permissions
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('You do not have permission to perform this action.');
        wp_die();
    }

    if (isset($_POST['activity_id']) && isset($_POST['data_price']) && isset($_POST['voucher_id'])) {
        $activity_id = intval($_POST['activity_id']);
        $voucher_id = intval(wp_unslash($_POST['voucher_id']));
        $data_price = floatval($_POST['data_price']);

        global $wpdb;
        $giftvouchers_activity = $wpdb->prefix . 'giftvouchers_activity';
        $giftvouchers_list = $wpdb->prefix . 'giftvouchers_list';

        $wpdb->update(
            $giftvouchers_activity,
            array('amount' => number_format($data_price, 5)),
            array('id' => $activity_id),
            array('%s'),
            array('%d')
        );

        $wpdb->update(
            $giftvouchers_list,
            array('amount' => $data_price),
            array('id' => $voucher_id),
            array('%s'),
            array('%d')
        );
        wp_send_json_success('Price updated successfully');
    } else {
        wp_send_json_error('Invalid data');
    }
    wp_die();
}
