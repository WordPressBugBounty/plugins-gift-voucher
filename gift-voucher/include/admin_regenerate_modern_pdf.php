<?php
if (!defined('ABSPATH')) exit;

/**
 * Determine voucher template type for regeneration.
 *
 * Supported mapping:
 * - order_type = gift_voucher_product:
 *   + product_id && order_id && template_id == 0 => standard_grid
 *   + product_id && order_id && template_id > 0 => modern
 * - order_type = items => standard_list
 * - order_type = vouchers:
 *   + template_id post_type == voucher_template => modern
 *   + template_id row in giftvouchers_template => standard_list
 */
function wpgv_get_voucher_template_kind($voucher_data)
{
    if (!$voucher_data) {
        return array('kind' => 'unknown', 'template_id' => 0);
    }

    // The stored answer wins. template_id alone is ambiguous - it addresses two
    // different id spaces that can collide - so guessing is a last resort now.
    if (!empty($voucher_data->template_kind)) {
        return array(
            'kind'        => (string) $voucher_data->template_kind,
            'template_id' => intval($voucher_data->template_id),
        );
    }

    return wpgv_get_voucher_template_kind_by_guess($voucher_data);
}

/**
 * Legacy resolution by inspection, kept as the fallback for rows the backfill
 * could not settle. Do not call directly; go through wpgv_get_voucher_template_kind().
 */
function wpgv_get_voucher_template_kind_by_guess($voucher_data)
{
    if (!$voucher_data || empty($voucher_data->order_type)) {
        return array('kind' => 'unknown', 'template_id' => 0);
    }

    if (!empty($voucher_data->id) && function_exists('wpgv_get_saved_voucher_pdf_context')) {
        $saved_context = wpgv_get_saved_voucher_pdf_context($voucher_data->id);
        if ($saved_context['kind'] !== 'unknown' && intval($saved_context['template_id']) > 0) {
            return $saved_context;
        }
    }

    $order_type  = $voucher_data->order_type;
    $product_id  = intval($voucher_data->product_id);
    $order_id    = intval($voucher_data->order_id);
    $item_id     = intval($voucher_data->item_id);
    $template_id = intval($voucher_data->template_id);

    if ($order_type === 'gift_voucher_product') {
        if ($product_id > 0 && $order_id > 0 && $template_id === 0) {
            return array('kind' => 'standard_grid', 'template_id' => $product_id);
        }
        if ($product_id > 0 && $order_id > 0 && $template_id > 0) {
            return array('kind' => 'modern', 'template_id' => $template_id);
        }
        // fallback
        if ($product_id > 0 && $order_id > 0) {
            return array('kind' => 'standard_grid', 'template_id' => $product_id);
        }
    }

    if ($order_type === 'items') {
        return array('kind' => 'standard_list', 'template_id' => $template_id ?: $product_id ?: $item_id);
    }

    if ($order_type === 'vouchers') {
        if ($template_id > 0) {
            $post_type = get_post_type($template_id);
            if ($post_type === 'voucher_template') {
                return array('kind' => 'modern', 'template_id' => $template_id);
            }

            global $wpdb;
            $template_table = $wpdb->prefix . 'giftvouchers_template';
            $standard_template_id = intval($wpdb->get_var($wpdb->prepare("SELECT id FROM $template_table WHERE id = %d", $template_id)));
            if ($standard_template_id > 0) {
                return array('kind' => 'standard_list', 'template_id' => $template_id);
            }
        }
        if ($item_id > 0) {
            return array('kind' => 'standard_list', 'template_id' => $item_id);
        }
    }

    return array('kind' => 'unknown', 'template_id' => $template_id);
}

/**
 * Returns voucher order data + resolved template data for the admin regenerate-PDF modal.
 * The JS uses this to render the Konva canvas with real order values before capturing.
 */
function wpgv_admin_get_voucher_regen_data_func()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'gift-voucher')));
    }

    $voucher_id = isset($_POST['voucher_id']) ? intval($_POST['voucher_id']) : 0;
    if (!$voucher_id) {
        wp_send_json_error(array('message' => __('Invalid voucher ID.', 'gift-voucher')));
    }

    check_ajax_referer('wpgv_regen_modern_pdf_' . $voucher_id, 'nonce');

    global $wpdb;
    $voucher_table  = $wpdb->prefix . 'giftvouchers_list';
    $setting_table  = $wpdb->prefix . 'giftvouchers_setting';
    $voucher_data   = $wpdb->get_row($wpdb->prepare("SELECT * FROM $voucher_table WHERE id = %d", $voucher_id));
    $setting_opts   = $wpdb->get_row("SELECT * FROM $setting_table WHERE id = 1");

    if (!$voucher_data) {
        wp_send_json_error(array('message' => __('Voucher not found.', 'gift-voucher')));
    }

    $template_info = wpgv_get_voucher_template_kind($voucher_data);
    if ($template_info['kind'] !== 'modern') {
        wp_send_json_error(array(
            'message' => __('Not a modern order. Use the standard voucher PDF regeneration flow instead.', 'gift-voucher'),
            'kind'    => $template_info['kind'],
        ));
    }

    $template_id = intval($template_info['template_id']) > 0
        ? intval($template_info['template_id'])
        : 0;

    // ── Template picture and Konva JSON ──────────────────────────────────────
    // The same files the [wpgv_giftcard] form draws the card from (getSelectTemplateVoucher()
    // in giftcard.php), so the regenerated card looks like the one the customer bought.
    $select_status  = get_post_meta($template_id, 'wpgv_customize_template_select_template', true);
    $template_style = sanitize_file_name(basename((string) get_post_meta($template_id, 'wpgv_customize_template_template-style', true)));
    $id_bg          = get_post_meta($template_id, 'wpgv_customize_template_id_bg_template', true);
    $json_template  = get_post_meta($template_id, 'wpgv_customize_template_json_template', true);

    $image_url = '';
    $json = '';
    if ($select_status === 'custom') {
        // id_bg_template may be an attachment ID or a URL.
        if (!empty($id_bg)) {
            if (is_numeric($id_bg)) {
                $src = wp_get_attachment_image_src(intval($id_bg), 'large');
                $image_url = $src ? $src[0] : '';
            } else {
                $image_url = $id_bg;
            }
        }
        $json = $json_template;
    } elseif ($template_style !== '') {
        $svg_name  = str_replace('.png', '.svg', $template_style);
        $json_name = str_replace('.png', '.json', $template_style);

        if (file_exists(WPGIFT__PLUGIN_DIR . '/assets/img/templates/svg/' . $svg_name)) {
            $image_url = WPGIFT__PLUGIN_URL . '/assets/img/templates/svg/' . $svg_name;
        } else {
            $image_url = 'https://gift-card-pro.s3.eu-central-1.amazonaws.com/templates/png/' . $template_style;
        }

        $json_file = WPGIFT__PLUGIN_DIR . '/assets/img/templates/json/' . $json_name;
        if (file_exists($json_file)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a file shipped with the plugin.
            $json = file_get_contents($json_file);
        } else {
            $response = wp_remote_get('https://gift-card-pro.s3.eu-central-1.amazonaws.com/templates/version-1.2/json/' . $json_name, array('timeout' => 15));
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $json = wp_remote_retrieve_body($response);
            }
        }
    }

    // ── What goes on the card, as the form puts it there ─────────────────────
    $amount = (float) $voucher_data->amount;
    $amount_text = (floor($amount) == $amount) ? (string) intval($amount) : rtrim(rtrim(sprintf('%.2f', $amount), '0'), '.');
    $currency = isset($setting_opts->currency) ? (string) $setting_opts->currency : '';
    $currency_right = isset($setting_opts->currency_position) && $setting_opts->currency_position !== 'Left';

    wp_send_json_success(array(
        // Order data
        'voucher_id'   => $voucher_id,
        'template_id'  => $template_id,
        'to_name'      => $voucher_data->to_name,
        'from_name'    => $voucher_data->from_name,
        'amount'       => $voucher_data->amount,
        'amount_text'  => $currency_right ? $amount_text . $currency : $currency . $amount_text,
        'couponcode'   => $voucher_data->couponcode,
        'message'      => $voucher_data->message,
        'expiry'       => trim((string) $voucher_data->expiry),
        // Template / canvas data — always send JSON as string for Konva.Node.create()
        'image_url'    => $image_url,
        'json'         => is_array($json) ? wp_json_encode($json) : $json,
        // Settings, with the form's defaults
        'company_name' => !empty($setting_opts->company_name) ? $setting_opts->company_name : get_bloginfo('name'),
        'email'        => !empty($setting_opts->pdf_footer_email) ? $setting_opts->pdf_footer_email : get_option('admin_email'),
        'web'          => !empty($setting_opts->pdf_footer_url) ? $setting_opts->pdf_footer_url : get_site_url(),
        'notice'       => get_option('wpgv_leftside_notice', __('Cash payment is not possible. The terms and conditions apply.', 'gift-voucher')),
        // Labels
        'giftto'       => __('Gift To', 'gift-voucher'),
        'giftfrom'     => __('Gift From', 'gift-voucher'),
        'date_of'      => __('Date', 'gift-voucher'),
        'counpon'      => __('Coupon', 'gift-voucher'),
        'value_text'   => __('Value', 'gift-voucher'),
    ));
}
add_action('wp_ajax_wpgv_admin_get_voucher_regen_data', 'wpgv_admin_get_voucher_regen_data_func');


/**
 * Admin AJAX: receive canvas PNG (base64), write to temp file, generate PDF.
 * Overwrites existing file using voucherpdf_link as filename.
 */
function wpgv_admin_regenerate_modern_pdf_func()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'gift-voucher')));
    }

    $voucher_id = isset($_POST['voucher_id']) ? intval($_POST['voucher_id']) : 0;
    if (!$voucher_id) {
        wp_send_json_error(array('message' => __('Invalid voucher ID.', 'gift-voucher')));
    }

    check_ajax_referer('wpgv_regen_modern_pdf_' . $voucher_id, 'nonce');

    global $wpdb;
    $voucher_table = $wpdb->prefix . 'giftvouchers_list';
    $voucher_data  = $wpdb->get_row($wpdb->prepare("SELECT * FROM $voucher_table WHERE id = %d", $voucher_id));

    if (!$voucher_data) {
        wp_send_json_error(array('message' => __('Voucher not found.', 'gift-voucher')));
    }
    if ($voucher_data->payment_status !== 'Paid') {
        wp_send_json_error(array('message' => __('Voucher is not paid.', 'gift-voucher')));
    }

    $template_info = wpgv_get_voucher_template_kind($voucher_data);
    if ($template_info['kind'] !== 'modern') {
        wp_send_json_error(array(
            'message' => __('This is not a modern gift card order. Use the standard voucher PDF regeneration flow instead.', 'gift-voucher'),
            'kind'    => $template_info['kind'],
        ));
    }

    $template_id = intval($template_info['template_id']) > 0
        ? intval($template_info['template_id'])
        : 0;

    // ── Receive canvas base64 from browser Konva render ───────────────────────
    $canvas_data     = isset($_POST['canvas_data']) ? $_POST['canvas_data'] : '';
    $canvas_file_path = '';

    if (!empty($canvas_data) && preg_match('/^data:image\/(png|jpeg);base64,/', $canvas_data)) {
        $upload   = wp_upload_dir();
        $temp_dir = $upload['basedir'] . '/voucherpdfuploads/temp/';
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        $raw     = substr($canvas_data, strpos($canvas_data, ',') + 1);
        $decoded = base64_decode($raw);
        if ($decoded !== false && strlen($decoded) > 0) {
            $temp_file = $temp_dir . 'regen_admin_' . $voucher_id . '_' . time() . '.png';
            if (file_put_contents($temp_file, $decoded) !== false) {
                $canvas_file_path = $temp_file;
            }
        }
    }

    if (empty($canvas_file_path)) {
        wp_send_json_error(array('message' => __('Canvas data missing or invalid. Please try again.', 'gift-voucher')));
    }

    require_once WPGIFT__PLUGIN_DIR . '/include/wpgv_giftcard_modern_pdf_helpers.php';

    $pdf_file = wpgv_generate_modern_giftcard_pdf(
        $voucher_id,
        $voucher_data,
        $template_id,
        null,
        $canvas_file_path,
        $voucher_data->voucherpdf_link
    );

    // Clean up temp canvas
    if (file_exists($canvas_file_path)) {
        @unlink($canvas_file_path);
    }

    $ok = false;
    if ($pdf_file && file_exists($pdf_file)) {
        $ok = true;

        // If customer receipt is enabled, regenerate receipt too
        if (get_option('wpgv_customer_receipt')) {
            require_once WPGIFT__PLUGIN_DIR . '/include/receipt-functions.php';
            if (function_exists('wpgv_generate_receipt_pdf_for_voucher')) {
                wpgv_generate_receipt_pdf_for_voucher($voucher_id);
            }
        }
    }

    if ($ok) {
        wp_send_json_success(array('message' => __('PDF regenerated successfully.', 'gift-voucher')));
    } else {
        wp_send_json_error(array('message' => __('Failed to generate PDF. Check server logs.', 'gift-voucher')));
    }
}
add_action('wp_ajax_wpgv_admin_regenerate_modern_pdf', 'wpgv_admin_regenerate_modern_pdf_func');
