<?php
add_action('wp_ajax_update_voucher_date', 'update_voucher_date');
add_action('wp_ajax_nopriv_update_voucher_date', 'update_voucher_date');

function update_voucher_date()
{
    if (isset($_POST['voucher_id']) && isset($_POST['new_date'])) {
        $voucher_id = sanitize_text_field($_POST['voucher_id']);
        $new_date = sanitize_text_field($_POST['new_date']);

        global $wpdb;
        $voucher_table = $wpdb->prefix . 'giftvouchers_list';
        $wpdb->update(
            $voucher_table,
            array('expiry' => $new_date),
            array('id' => $voucher_id),
            array('%s'),
            array('%d')
        );
        wp_send_json_success('Date updated successfully');
    } else {
        wp_send_json_error('Invalid data');
    }
    wp_die();
}

add_action('wp_ajax_update_voucher_note', 'update_voucher_note');
add_action('wp_ajax_nopriv_update_voucher_note', 'update_voucher_note');

function update_voucher_note()
{
    if (isset($_POST['voucher_id']) && isset($_POST['data_note'])) {
        $voucher_id = sanitize_text_field($_POST['voucher_id']);
        $data_note = sanitize_text_field($_POST['data_note']);

        global $wpdb;
        $voucher_table = $wpdb->prefix . 'giftvouchers_list';
        $wpdb->update(
            $voucher_table,
            array('note_order' => $data_note),
            array('id' => $voucher_id),
            array('%s'),
            array('%d')
        );
        wp_send_json_success('Note updated successfully');
    } else {
        wp_send_json_error('Invalid data');
    }
    wp_die();
}


add_action('wp_ajax_update_voucher_price', 'update_voucher_price');
add_action('wp_ajax_nopriv_update_voucher_price', 'update_voucher_price');

function update_voucher_price()
{
    if (isset($_POST['activity_id']) && isset($_POST['data_price']) && isset($_POST['voucher_id'])) {
        $activity_id = sanitize_text_field($_POST['activity_id']);
        $data_price = floatval($_POST['data_price']);
        $voucher_id = sanitize_text_field($_POST['voucher_id']);

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

function check_and_add_custom_column_on_plugin_activation()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'giftvouchers_list';
    $column_name = 'note_order';
    $column_exists = $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE '$column_name'");
    if ($column_exists !== $column_name) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN $column_name varchar(255) NOT NULL");
    }
}

register_activation_hook(__FILE__, 'check_and_add_custom_column_on_plugin_activation');
