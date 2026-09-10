<?php

if (!defined('ABSPATH')) exit;  // Exit if accessed directly

/**
 * Single read path for standard voucher templates (the [wpgv_giftvoucher] flow).
 *
 * Returns objects shaped exactly like rows of the legacy giftvouchers_template
 * table, so the rendering code in front.php did not have to change when this
 * layer was introduced. A later task gives the repository a CPT source behind
 * the same interface.
 *
 * Identity note: ->id stays in the LEGACY id space on purpose. front.php:328
 * renders it into the template_id radio input, and wpgv_voucher_pdf.php:164
 * stores that value on the order, where wpgv_voucher_pdf.php:56 resolves it
 * against this same table. Handing out post ids here would silently move every
 * new order into a different id space, which is a separate migration with its
 * own risk. See upgrade/01-hop-nhat-item-card-template.md section 4.3.
 */
class WPGV_Template_Repository
{
    /**
     * Every active standard template.
     *
     * Ordered by id only. The table carries an `orderno` column, but the query
     * this replaced had no ORDER BY at all, and sorting by `orderno` would
     * reorder the buying form on any site that has set it. Making the existing
     * order explicit is in scope here; changing it is not.
     *
     * @return object[] Rows with id, title, image, image_style, orderno, active.
     */
    public static function get_active_standard()
    {
        if (wpgv_unified_cpt_enabled()) {
            $ids = get_posts(array(
                'post_type'      => 'voucher_template',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    array('key' => WPGV_KIND_META, 'value' => 'template'),
                ),
                'meta_key'       => WPGV_LEGACY_TEMPLATE_META,
                'orderby'        => 'meta_value_num',
                'order'          => 'ASC',
            ));

            $out = array();
            foreach ($ids as $id) {
                $out[] = self::shape_from_post($id);
            }

            return $out;
        }

        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$wpdb->prefix}giftvouchers_template`
             WHERE active = %d
             ORDER BY id ASC",
            1
        ));

        return $rows ? $rows : array();
    }

    /**
     * Rebuild a legacy-shaped row from a mirrored post.
     *
     * ->id is the LEGACY id, never the post id, for the reason given in the
     * class docblock: it is what ends up in giftvouchers_list.template_id.
     *
     * @param int $post_id
     * @return object
     */
    private static function shape_from_post($post_id)
    {
        $post_id = (int) $post_id;

        return (object) array(
            'id'          => (int) get_post_meta($post_id, WPGV_LEGACY_TEMPLATE_META, true),
            'title'       => get_the_title($post_id),
            'image'       => (int) get_post_meta($post_id, '_wpgv_legacy_image', true),
            'image_style' => (string) get_post_meta($post_id, '_wpgv_legacy_image_style', true),
            'orderno'     => (int) get_post_field('menu_order', $post_id),
            'active'      => get_post_status($post_id) === 'publish' ? 1 : 0,
        );
    }

    /**
     * One standard template by its legacy id.
     *
     * @param int $id
     * @return object|null Null for a missing or non-positive id, so callers can
     *                     reject bad input instead of dereferencing null later.
     */
    public static function find_standard($id)
    {
        global $wpdb;

        $id = (int) $id;

        if ($id <= 0) {
            return null;
        }

        if (wpgv_unified_cpt_enabled()) {
            $post_id = wpgv_find_post_by_legacy_template_id($id);
            if ($post_id) {
                return self::shape_from_post($post_id);
            }
            // Fall through to the table: a row not mirrored yet must still resolve.
            // This is the third of the three resolution layers in spec section 5.1.
        }

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$wpdb->prefix}giftvouchers_template` WHERE id = %d",
            $id
        ));

        return $row ? $row : null;
    }
}
