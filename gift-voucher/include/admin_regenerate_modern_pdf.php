<?php
if (!defined('ABSPATH')) exit;

/**
 * Determine voucher template type for regeneration.
 *
 * Supported mapping (as requested):
 * - order_type = gift_voucher_product:
 *   + product_id && order_id && template_id == 0 => standard_grid (style1)
 *   + product_id && order_id && template_id > 0 => modern (Konva)
 * - order_type = items => standard_list (style1)
 * - order_type = vouchers:
 *   + template_id post_type == voucher_template => modern (Konva)
 *   + template_id post_type == wpgv_voucher_product => standard_grid (style1)
 *   + template_id post_type == post => standard_list (style1)
 */
function wpgv_get_voucher_template_kind($voucher_data)
{
    if (!$voucher_data || empty($voucher_data->order_type)) {
        return array('kind' => 'unknown', 'template_id' => 0);
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
            if ($post_type === 'wpgv_voucher_product') {
                return array('kind' => 'standard_grid', 'template_id' => $template_id);
            }
            if ($post_type === 'post') {
                return array('kind' => 'standard_list', 'template_id' => $template_id);
            }
        }
        if ($item_id > 0) {
            return array('kind' => 'standard_grid', 'template_id' => $item_id);
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
            'message' => __('Not a modern order. Standard vouchers can be regenerated using the standard PDF engine (style1).', 'gift-voucher'),
            'kind'    => $template_info['kind'],
        ));
    }

    $template_id = intval($template_info['template_id']) > 0
        ? intval($template_info['template_id'])
        : 0;

    // ── Resolve template background image URL ────────────────────────────────
    $select_status  = get_post_meta($template_id, 'wpgv_customize_template_select_template', true);
    $template_style = get_post_meta($template_id, 'wpgv_customize_template_template-style', true);
    $bg_result      = get_post_meta($template_id, 'wpgv_customize_template_bg_result', true);
    $id_bg          = get_post_meta($template_id, 'wpgv_customize_template_id_bg_template', true);
    $json_template  = get_post_meta($template_id, 'wpgv_customize_template_json_template', true);

    $image_url = '';
    if ($select_status === 'custom') {
        // Fallback: id_bg_template (may be a URL or attachment ID)
        if (empty($image_url) && !empty($id_bg)) {
            if (is_numeric($id_bg)) {
                $src = wp_get_attachment_image_src(intval($id_bg), 'large');
                $image_url = $src ? $src[0] : '';
            } else {
                $image_url = $id_bg; // already a URL
            }
        }
    } else {
        // Default template — bg is an S3 PNG filename
        if (!empty($template_style)) {
            $title_svg = str_replace('.png', '.svg', $template_style);
            if (function_exists('wpgv_get_template_image_url')) {
                $image_url = wpgv_get_template_image_url($title_svg);
            }
            if (empty($image_url)) {
                $image_url = 'https://gift-card-pro.s3.eu-central-1.amazonaws.com/templates/png/' . $template_style;
            }
        }
    }

    // ── Resolve Konva JSON ────────────────────────────────────────────────────
    $json = '';
    if ($select_status === 'custom') {
        $json = $json_template;
    } else {
        $name_template = str_replace('.png', '.json', $template_style);
        if (function_exists('wpgv_fetch_cached_template_json')) {
            $json = wpgv_fetch_cached_template_json($name_template);
        }
        if (empty($json) && !empty($name_template)) {
            $json_url  = 'https://gift-card-pro.s3.eu-central-1.amazonaws.com/templates/version-1.2/json/' . str_replace('.png', '.json', $template_style);
            $response  = wp_remote_get($json_url, array('timeout' => 15));
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $json = wp_remote_retrieve_body($response);
            }
        }
    }

    // ── Parse label text from JSON ────────────────────────────────────────────
    $giftto = __('Gift To', 'gift-voucher');
    $giftfrom  = __('Gift From', 'gift-voucher');
    $date_of   = __('Date of Expiry', 'gift-voucher');
    $counpon   = __('Coupon Code', 'gift-voucher');
    $value_text = __('Value', 'gift-voucher');

    if (!empty($json)) {
        $json_array = is_array($json) ? $json : json_decode($json, true);
        if (is_array($json_array) && isset($json_array['children'])) {
            foreach ($json_array['children'] as $child) {
                if (!isset($child['children'])) continue;
                foreach ($child['children'] as $item) {
                    if (!isset($item['attrs']['id'])) continue;
                    switch ($item['attrs']['id']) {
                        case 'giftto_label':            $giftto     = __($item['attrs']['text'] ?? $giftto, 'gift-voucher');     break;
                        case 'giftfrom_label':          $giftfrom   = __($item['attrs']['text'] ?? $giftfrom, 'gift-voucher');   break;
                        case 'giftcard_date_gift_label':$date_of    = __($item['attrs']['text'] ?? $date_of, 'gift-voucher');    break;
                        case 'giftcard_counpon_label':  $counpon    = __($item['attrs']['text'] ?? $counpon, 'gift-voucher');    break;
                        case 'giftcard_monney_label':   $value_text = __($item['attrs']['text'] ?? $value_text, 'gift-voucher'); break;
                    }
                }
            }
        }
    }

    // ── Company / settings ────────────────────────────────────────────────────
    $currency     = !empty($setting_opts->currency)         ? $setting_opts->currency         : '';
    $company_name = !empty($setting_opts->company_name)     ? $setting_opts->company_name     : '';
    $company_logo = get_option('wpgv_company_logo', '');
    $web          = !empty($setting_opts->pdf_footer_url)   ? $setting_opts->pdf_footer_url   : '';
    $email        = !empty($setting_opts->pdf_footer_email) ? $setting_opts->pdf_footer_email : '';
    $notice       = get_option('wpgv_leftside_notice', __('Cash payment is not possible. The terms and conditions apply.', 'gift-voucher'));

    wp_send_json_success(array(
        // Order data
        'voucher_id'  => $voucher_id,
        'template_id' => $template_id,
        'to_name'     => $voucher_data->to_name,
        'from_name'   => $voucher_data->from_name,
        'amount'      => $voucher_data->amount,
        'couponcode'  => $voucher_data->couponcode,
        'message'     => $voucher_data->message,
        'expiry'      => $voucher_data->expiry,
        // Template / canvas data — always send JSON as string for Konva.Node.create()
        'image_url'   => $image_url,
        'json'        => is_array($json) ? json_encode($json) : $json,
        // Settings
        'currency'      => $currency,
        'company_name'  => $company_name,
        'company_logo'  => $company_logo,
        'web'           => $web,
        'email'         => $email,
        'notice'        => $notice,
        // Labels parsed from JSON
        'giftto'      => $giftto,
        'giftfrom'    => $giftfrom,
        'date_of'     => $date_of,
        'counpon'     => $counpon,
        'value_text'  => $value_text,
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
            'message' => __('This is not a Giftcard Modern order. For standard vouchers use style1 PDF generation.', 'gift-voucher'),
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
