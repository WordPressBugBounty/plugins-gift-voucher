<?php

if (!defined('ABSPATH')) exit;  // Exit if accessed directly

/**
 * Voucher PDFs are handed out by the plugin, not by the web server.
 *
 * An .htaccess in voucherpdfuploads sends every *.pdf request to WordPress
 * (index.php?wpgv_pdf=<file>). The plugin then serves a voucher PDF only when
 * its order is Paid, or to an administrator, so the voucher of an unpaid order
 * can not be opened by its link. Receipts stay open: the buyer pays against
 * them. Links already sent by email keep working, because the URL does not
 * change. Apache and LiteSpeed read the .htaccess; on nginx nothing changes.
 */

define('WPGV_PDF_ACCESS_MARKER', 'Gift Voucher PDF access');

/** The .htaccess lines; the target follows the site's own path. */
function wpgv_pdf_access_rules()
{
    $home_path = wp_parse_url(home_url('/'), PHP_URL_PATH);
    $home_path = '/' . trim((string) $home_path, '/');
    $home_path = ($home_path === '/') ? '/' : $home_path . '/';

    return array(
        'Options -Indexes',
        '<IfModule mod_rewrite.c>',
        'RewriteEngine On',
        'RewriteRule ^(.+\.pdf)$ ' . $home_path . 'index.php?wpgv_pdf=$1 [NC,L]',
        '</IfModule>',
    );
}

/**
 * Write the rules into voucherpdfuploads/.htaccess between our markers, keeping
 * anything else in that file. Runs again only when the rules change.
 */
function wpgv_pdf_access_install()
{
    $rules = wpgv_pdf_access_rules();
    $hash = md5(implode("\n", $rules));
    if (get_option('wpgv_pdf_access_rules') === $hash) {
        return;
    }

    $dir = wpgv_pdf_access_dir();
    if (!is_dir($dir) && !wp_mkdir_p($dir)) {
        return;
    }
    if (!file_exists($dir . 'index.html')) {
        @file_put_contents($dir . 'index.html', 'Silence is golden.'); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    }

    require_once ABSPATH . 'wp-admin/includes/misc.php';
    if (insert_with_markers($dir . '.htaccess', WPGV_PDF_ACCESS_MARKER, $rules)) {
        update_option('wpgv_pdf_access_rules', $hash);
    }
}
add_action('admin_init', 'wpgv_pdf_access_install');

/** Take our lines out again, so PDF links work as before while the plugin is off. */
function wpgv_pdf_access_uninstall()
{
    $file = wpgv_pdf_access_dir() . '.htaccess';
    if (file_exists($file)) {
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        insert_with_markers($file, WPGV_PDF_ACCESS_MARKER, array());
    }
    delete_option('wpgv_pdf_access_rules');
}

function wpgv_pdf_access_dir()
{
    $upload = wp_upload_dir(null, false);
    return trailingslashit($upload['basedir']) . 'voucherpdfuploads/';
}

/**
 * May the current visitor open this file?
 *
 * @param string $filename A file name inside voucherpdfuploads.
 */
function wpgv_pdf_access_allowed($filename)
{
    if (current_user_can('manage_options')) {
        return true;
    }

    $is_receipt = (substr($filename, -12) === '-receipt.pdf');
    $link = substr($filename, 0, $is_receipt ? -12 : -4);

    global $wpdb;
    $statuses = $wpdb->get_col($wpdb->prepare(
        "SELECT `payment_status` FROM `{$wpdb->prefix}giftvouchers_list` WHERE `voucherpdf_link` = %s",
        $link
    ));

    // A file that belongs to no order (a deleted one, a leftover) is for the admin only.
    if (!$statuses) {
        return false;
    }

    return $is_receipt || in_array('Paid', $statuses, true);
}

function wpgv_pdf_access_serve()
{
    if (!isset($_GET['wpgv_pdf'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a file link, no state change.
        return;
    }

    $filename = basename(sanitize_file_name(wp_unslash($_GET['wpgv_pdf']))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $path = wpgv_pdf_access_dir() . $filename;

    if (!preg_match('/^[A-Za-z0-9_-]+\.pdf$/', $filename) || !is_file($path) || !wpgv_pdf_access_allowed($filename)) {
        wp_die(esc_html__('This file is not available.', 'gift-voucher'), '', array('response' => 404));
    }

    while (ob_get_level()) {
        ob_end_clean();
    }
    nocache_headers();
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($path));
    header('X-Robots-Tag: noindex, nofollow');
    readfile($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
    exit;
}
add_action('init', 'wpgv_pdf_access_serve', 2);
