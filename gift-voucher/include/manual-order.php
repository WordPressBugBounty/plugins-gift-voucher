<?php
/**
 * Manual orders: an administrator issues a paid voucher without a checkout.
 *
 * Built only from existing helpers (code generation, the standard PDF renderer
 * fed from the order row, the activity ledger and the confirmation mailer), so
 * the public order handlers stay untouched. Nothing here ever deletes data: if
 * a step after the INSERT fails, the order is kept and the problem is noted.
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Templates and items an administrator can issue, with their fixed price if any. */
function wpgv_manual_order_sources()
{
    $sources = array();

    foreach (WPGV_Template_Repository::get_active_standard() as $row) {
        $post_id = wpgv_find_post_by_legacy_template_id((int) $row->id);
        $sources[] = array(
            'type'  => 'vouchers',
            'id'    => (int) $row->id,
            'title' => (string) $row->title,
            'price' => wpgv_manual_order_fixed_price($post_id),
        );
    }

    $items = get_posts(wpgv_item_query_args(array('post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids')));
    foreach ($items as $item_id) {
        $sources[] = array(
            'type'  => 'items',
            'id'    => (int) $item_id,
            'title' => get_the_title($item_id),
            'price' => wpgv_manual_order_fixed_price($item_id),
        );
    }

    return $sources;
}

/** Face value of a priced template or item, or null when the price is open. */
function wpgv_manual_order_fixed_price($post_id)
{
    if (!$post_id || wpgv_item_is_open_price($post_id)) {
        return null;
    }
    $amounts = wpgv_resolve_item_amounts($post_id, '', null);
    return is_wp_error($amounts) ? null : (float) $amounts['face'];
}

/**
 * Expiry text as the order forms store it, without their trailing newline.
 *
 * @param object $settings Row of giftvouchers_setting.
 * @param string $override Optional date (Y-m-d) chosen by the administrator.
 */
function wpgv_manual_order_expiry($settings, $override = '')
{
    $format = get_option('wpgv_expiry_date_format', 'd.m.Y');

    if ($override !== '') {
        return gmdate($format, strtotime($override));
    }
    if (get_option('wpgv_hide_expiry', 'yes') === 'no') {
        return __('No Expiry', 'gift-voucher');
    }
    if (!empty($settings->voucher_expiry_type) && $settings->voucher_expiry_type === 'days') {
        return gmdate($format, strtotime('+' . (int) $settings->voucher_expiry . ' days', time()));
    }
    return trim((string) $settings->voucher_expiry);
}

/**
 * Create a paid order for a template or item.
 *
 * @return array{id:int,warnings:string[]}|WP_Error
 */
function wpgv_create_manual_order(array $input)
{
    global $wpdb;

    $list     = $wpdb->prefix . 'giftvouchers_list';
    $settings = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}giftvouchers_setting WHERE id = %d", 1));
    if (!$settings) {
        return new WP_Error('wpgv_manual_settings', __('Gift card settings could not be loaded.', 'gift-voucher'));
    }

    // --- Validate everything before writing anything --------------------------------
    $type      = isset($input['type']) ? (string) $input['type'] : '';
    $source_id = isset($input['source_id']) ? absint($input['source_id']) : 0;
    if (!in_array($type, array('vouchers', 'items'), true)) {
        return new WP_Error('wpgv_manual_type', __('Choose a gift voucher or a gift item.', 'gift-voucher'));
    }
    if ($type === 'vouchers' && !WPGV_Template_Repository::find_standard($source_id)) {
        return new WP_Error('wpgv_manual_source', __('This gift voucher does not exist.', 'gift-voucher'));
    }
    if ($type === 'items' && !wpgv_is_purchasable_gift_item($source_id)) {
        return new WP_Error('wpgv_manual_source', __('This gift item does not exist or is not published.', 'gift-voucher'));
    }

    $amount = wpgv_normalize_decimal_amount(isset($input['amount']) ? (string) $input['amount'] : '');
    if (!is_numeric($amount) || (float) $amount <= 0) {
        return new WP_Error('wpgv_manual_amount', __('Enter a voucher value greater than zero.', 'gift-voucher'));
    }
    $amount = round((float) $amount, 2);

    $buying_for = (isset($input['buying_for']) && $input['buying_for'] === 'yourself') ? 'yourself' : 'someone_else';
    $buyer_name = sanitize_text_field(isset($input['buyer_name']) ? $input['buyer_name'] : '');
    $buyer_email = sanitize_email(isset($input['buyer_email']) ? $input['buyer_email'] : '');
    $recipient_name = $buying_for === 'yourself' ? '' : sanitize_text_field(isset($input['recipient_name']) ? $input['recipient_name'] : '');
    $recipient_email = $buying_for === 'yourself' ? $buyer_email : sanitize_email(isset($input['recipient_email']) ? $input['recipient_email'] : '');
    if ($buyer_name === '' || !is_email($buyer_email)) {
        return new WP_Error('wpgv_manual_buyer', __('Enter the buyer name and a valid buyer email.', 'gift-voucher'));
    }
    if ($buying_for === 'someone_else' && ($recipient_name === '' || !is_email($recipient_email))) {
        return new WP_Error('wpgv_manual_recipient', __('Enter the recipient name and a valid recipient email.', 'gift-voucher'));
    }

    $code = isset($input['code']) ? trim((string) $input['code']) : '';
    if ($code !== '') {
        if (!preg_match('/^[0-9]{6,18}$/', $code)) {
            return new WP_Error('wpgv_manual_code', __('A voucher code must be 6 to 18 digits.', 'gift-voucher'));
        }
        if (wpgv_couponcode_exists($code)) {
            return new WP_Error('wpgv_manual_code_taken', __('This voucher code is already in use.', 'gift-voucher'));
        }
    } else {
        $code = wpgv_generate_unique_couponcode();
        if (is_wp_error($code)) {
            return $code;
        }
    }

    $expiry_override = isset($input['expiry']) ? trim((string) $input['expiry']) : '';
    if ($expiry_override !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry_override)) {
        return new WP_Error('wpgv_manual_expiry', __('Choose a valid expiry date.', 'gift-voucher'));
    }

    $style = isset($input['style']) ? (int) $input['style'] : 0;
    $style = in_array($style, array(0, 1, 2), true) ? $style : 0;

    // --- Write the order --------------------------------------------------------------
    $row = array(
        'order_type'      => $type,
        'buying_for'      => $buying_for,
        'from_name'       => $buyer_name,
        'to_name'         => $recipient_name,
        'amount'          => $amount,
        'message'         => sanitize_textarea_field(isset($input['message']) ? $input['message'] : ''),
        'firstname'       => '',
        'lastname'        => '',
        'email'           => $buyer_email,
        'address'         => '',
        'postcode'        => '',
        'pay_method'      => 'admin',
        'shipping_type'   => 'shipping_as_email',
        'shipping_email'  => $recipient_email,
        'shipping_method' => '',
        'expiry'          => wpgv_manual_order_expiry($settings, $expiry_override),
        'couponcode'      => $code,
        'voucherpdf_link' => wpgv_sanitize_voucher_pdf_basename(time() . $code),
        'status'          => 'unused',
        'payment_status'  => 'Paid',
        'check_send_mail' => 'unsent',
        'voucheradd_time' => current_time('mysql'),
    );
    if ($type === 'vouchers') {
        $row['template_id'] = $source_id;
    } else {
        $terms = wp_get_object_terms($source_id, WPGV_CATEGORY_TAXONOMY, array('fields' => 'ids'));
        $row['item_id'] = $source_id;
        $row['itemcat_id'] = (!is_wp_error($terms) && $terms) ? (int) $terms[0] : 0;
    }
    if ($wpdb->get_var("SHOW COLUMNS FROM `{$list}` LIKE 'template_kind'")) {
        $row['template_kind'] = 'standard_list';
    }

    if (!$wpdb->insert($list, $row)) {
        return new WP_Error('wpgv_manual_insert', __('The order could not be saved.', 'gift-voucher'));
    }
    $id = (int) $wpdb->insert_id;
    $warnings = array();

    wpgv_save_voucher_pdf_context($id, array('kind' => 'standard_list', 'template_id' => $source_id, 'style' => $style));
    wpgv_create_voucher_order_key($id);
    wpgv_update_voucher_meta($id, 'wpgv_total_payable_amount', wpgv_price_format($amount));

    $user = wp_get_current_user();
    WPGV_Gift_Voucher_Activity::record($id, 'create', null, sprintf('Order created manually by %s.', $user && $user->exists() ? $user->user_login : 'an administrator'));
    WPGV_Gift_Voucher_Activity::record($id, 'firsttransact', $amount, 'Voucher value added by an administrator.');

    // --- PDF from the saved row; a failure keeps the order ----------------------------
    $voucher = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$list} WHERE id = %d", $id));
    $args = wpgv_get_standard_pdf_args_for_voucher($voucher, $style);
    $pdf = is_wp_error($args) ? $args : wpgv_pdf_render_standard_document($args);
    if (is_wp_error($pdf) || !wpgv_pdf_output_to_file($pdf, wpgv_get_voucher_pdf_path($voucher->voucherpdf_link))) {
        $warnings[] = __('The voucher PDF could not be created. Use Regenerate PDF on the Orders page.', 'gift-voucher');
        WPGV_Gift_Voucher_Activity::record($id, 'note', null, 'PDF could not be created; use Regenerate PDF.');
        return array('id' => $id, 'warnings' => $warnings);
    }

    if (get_option('wpgv_customer_receipt')) {
        wpgv_generate_receipt_pdf_for_voucher($id);
    }

    if (!empty($input['send_email'])) {
        if (wpgv_send_stripe_voucher_emails_once($voucher, $settings) !== true) {
            $warnings[] = __('The order was created, but the email could not be sent.', 'gift-voucher');
        }
    }

    return array('id' => $id, 'warnings' => $warnings);
}

add_action('admin_post_wpgv_create_manual_order', 'wpgv_handle_manual_order');

function wpgv_handle_manual_order()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You are not allowed to create orders.', 'gift-voucher'), 403);
    }
    check_admin_referer('wpgv_create_manual_order');

    $source = isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : '';
    list($type, $source_id) = array_pad(explode(':', $source, 2), 2, '');

    $input = array(
        'type'            => $type,
        'source_id'       => $source_id,
        'amount'          => isset($_POST['amount']) ? sanitize_text_field(wp_unslash($_POST['amount'])) : '',
        'buying_for'      => isset($_POST['buying_for']) ? sanitize_key(wp_unslash($_POST['buying_for'])) : 'someone_else',
        'buyer_name'      => isset($_POST['buyer_name']) ? wp_unslash($_POST['buyer_name']) : '',
        'buyer_email'     => isset($_POST['buyer_email']) ? wp_unslash($_POST['buyer_email']) : '',
        'recipient_name'  => isset($_POST['recipient_name']) ? wp_unslash($_POST['recipient_name']) : '',
        'recipient_email' => isset($_POST['recipient_email']) ? wp_unslash($_POST['recipient_email']) : '',
        'message'         => isset($_POST['message']) ? wp_unslash($_POST['message']) : '',
        'style'           => isset($_POST['style']) ? absint($_POST['style']) : 0,
        'code'            => isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '',
        'expiry'          => isset($_POST['expiry']) ? sanitize_text_field(wp_unslash($_POST['expiry'])) : '',
        'send_email'      => !empty($_POST['send_email']),
    );

    $result = wpgv_create_manual_order($input);
    $key = 'wpgv_manual_order_' . get_current_user_id();

    if (is_wp_error($result)) {
        // Keep what was typed for one minute so the form can be shown again filled in.
        set_transient($key, array('input' => $input, 'error' => $result->get_error_message()), MINUTE_IN_SECONDS);
        wp_safe_redirect(admin_url('admin.php?page=wpgv-add-order&wpgv_manual_order=error'));
        exit;
    }

    set_transient($key, array('warnings' => $result['warnings']), MINUTE_IN_SECONDS);
    wp_safe_redirect(admin_url('admin.php?page=view-voucher-details&action=view_voucher&voucher_id=' . $result['id'] . '&wpgv_manual_order=created'));
    exit;
}

add_action('admin_notices', 'wpgv_manual_order_notice');

function wpgv_manual_order_notice()
{
    if (!isset($_GET['wpgv_manual_order']) || sanitize_key(wp_unslash($_GET['wpgv_manual_order'])) !== 'created') {
        return;
    }
    $state = get_transient('wpgv_manual_order_' . get_current_user_id());
    delete_transient('wpgv_manual_order_' . get_current_user_id());
    echo '<div class="notice notice-success"><p>' . esc_html__('Order created. The voucher is paid and ready to use.', 'gift-voucher') . '</p></div>';
    foreach ((array) (isset($state['warnings']) ? $state['warnings'] : array()) as $warning) {
        echo '<div class="notice notice-warning"><p>' . esc_html($warning) . '</p></div>';
    }
}
