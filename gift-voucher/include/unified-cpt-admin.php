<?php

if (!defined('ABSPATH')) exit;  // Exit if accessed directly

/**
 * Admin list screen for the unified post type.
 *
 * One post type now holds gift items, gift cards and standard templates, so the
 * list needs a way to narrow down to one kind and a column showing which is
 * which. See upgrade/01-hop-nhat-item-card-template.md section 4.5.
 */

/**
 * Human-readable label per kind.
 *
 * @return array<string,string>
 */
function wpgv_kind_labels()
{
    return array(
        'item'     => __('Gift Items', 'gift-voucher'),
        'card'     => __('Gift Cards', 'gift-voucher'),
        'template' => __('Voucher Templates', 'gift-voucher'),
    );
}

add_action('restrict_manage_posts', 'wpgv_kind_filter_dropdown');
/**
 * Render the kind dropdown above the list table.
 *
 * @param string $post_type
 * @return void
 */
function wpgv_kind_filter_dropdown($post_type)
{
    if ($post_type !== 'voucher_template') {
        return;
    }

    $current = isset($_GET['wpgv_kind']) ? sanitize_text_field(wp_unslash($_GET['wpgv_kind'])) : '';

    echo '<select name="wpgv_kind"><option value="">'
        . esc_html__('All types', 'gift-voucher') . '</option>';

    foreach (wpgv_kind_labels() as $value => $label) {
        printf(
            '<option value="%s"%s>%s</option>',
            esc_attr($value),
            selected($current, $value, false),
            esc_html($label)
        );
    }

    echo '</select>';
}

add_action('pre_get_posts', 'wpgv_kind_filter_query');
/**
 * Narrow the list query to one kind.
 *
 * An unrecognised value is ignored rather than applied, so a mistyped URL shows
 * the full list instead of an empty one that reads like data loss.
 *
 * @param WP_Query $query
 * @return void
 */
function wpgv_kind_filter_query($query)
{
    if (!$query->is_main_query() || $query->get('post_type') !== 'voucher_template') {
        return;
    }

    if (empty($_GET['wpgv_kind'])) {
        return;
    }

    $kind = sanitize_text_field(wp_unslash($_GET['wpgv_kind']));
    if (!in_array($kind, wpgv_valid_kinds(), true)) {
        return;
    }

    $query->set('meta_query', array(
        array('key' => WPGV_KIND_META, 'value' => $kind),
    ));
}

add_filter('manage_voucher_template_posts_columns', 'wpgv_kind_column');
/**
 * Add the Type column.
 *
 * @param array $columns
 * @return array
 */
function wpgv_kind_column($columns)
{
    $columns['wpgv_kind'] = __('Type', 'gift-voucher');

    return $columns;
}

add_action('manage_voucher_template_posts_custom_column', 'wpgv_kind_column_content', 10, 2);
/**
 * Print the Type cell.
 *
 * @param string $column
 * @param int    $post_id
 * @return void
 */
function wpgv_kind_column_content($column, $post_id)
{
    if ($column !== 'wpgv_kind') {
        return;
    }

    $kind = wpgv_get_kind($post_id);
    if ($kind === '') {
        echo '&mdash;';
        return;
    }

    $labels = wpgv_kind_labels();
    echo esc_html(isset($labels[$kind]) ? $labels[$kind] : $kind);
}
