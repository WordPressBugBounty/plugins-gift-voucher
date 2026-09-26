<?php

if (!defined('ABSPATH')) exit;

/**
 * Sample records so the plugin's pages have something to show on a new site.
 *
 * Creating the pages was never the hard part. A shop owner who installs the
 * plugin, presses the button and opens Gift Cards finds an empty form: the
 * shortcode is there, but there is no gift card, gift item or voucher template
 * for it to offer, and nothing on screen says what to do next. These records
 * give each of the three shortcodes something real to render, so the first
 * impression is a working page rather than a blank one.
 *
 * Every record is stamped with WPGV_DEMO_META. That is what makes pressing the
 * button twice safe, and what lets the owner clear the samples out later
 * without hunting for which records were theirs.
 */

/** Marks a record as sample content this plugin created. */
define('WPGV_DEMO_META', '_wpgv_demo_content');

/**
 * Term ids of the sample categories, creating them on first use.
 *
 * Both shortcodes read categories, but from opposite ends: [wpgv_giftitems]
 * lists items grouped by category, and [wpgv_giftcard] offers the categories
 * that hold cards. One shared category would leave one of the two lists
 * looking broken, so there is one per kind.
 *
 * @return array Kind => term id.
 */
function wpgv_demo_category_ids()
{
    $wanted = array(
        'card' => __('Celebrations', 'gift-voucher'),
        'item' => __('Wellness', 'gift-voucher'),
    );

    $ids = array();

    foreach ($wanted as $kind => $name) {
        $existing = get_terms(array(
            'taxonomy'   => WPGV_CATEGORY_TAXONOMY,
            'hide_empty' => false,
            'meta_key'   => WPGV_DEMO_META,
            'meta_value' => $kind,
        ));

        if (!is_wp_error($existing) && $existing) {
            $ids[$kind] = (int) $existing[0]->term_id;
            continue;
        }

        // A term of that name may already exist without our stamp - someone
        // else's category. Leave it alone and make our own rather than adopting
        // theirs, or removing the samples later would take their category too.
        $created = wp_insert_term($name, WPGV_CATEGORY_TAXONOMY);

        if (is_wp_error($created)) {
            $term = get_term_by('name', $name, WPGV_CATEGORY_TAXONOMY);
            if (!$term) {
                continue;
            }
            $created = array('term_id' => $term->term_id);
        }

        $ids[$kind] = (int) $created['term_id'];
        update_term_meta($ids[$kind], WPGV_DEMO_META, $kind);
    }

    return $ids;
}

/**
 * The sample record of a given kind and slug, or 0.
 *
 * @param string $slug Stable identifier stored in the demo stamp.
 * @return int
 */
function wpgv_find_demo_post($slug)
{
    $found = get_posts(array(
        'post_type'   => 'voucher_template',
        'post_status' => 'any',
        'numberposts' => 1,
        'fields'      => 'ids',
        'meta_key'    => WPGV_DEMO_META,
        'meta_value'  => $slug,
    ));

    return $found ? (int) $found[0] : 0;
}

/**
 * Every template filename the site is configured to offer, landscape first.
 *
 * Read from settings rather than hard-coded: a card whose style is not in the
 * configured lists never appears on the form, so a hard-coded name would give a
 * sample card that silently shows nothing.
 *
 * @return array Filenames, in the order the settings list them.
 */
function wpgv_demo_template_styles()
{
    global $wpdb;

    $row = $wpdb->get_row(
        "SELECT landscape_mode_templates, portrait_mode_templates FROM `{$wpdb->prefix}giftvouchers_setting` WHERE id = 1"
    );

    if (!$row) {
        return array();
    }

    $styles = array();

    foreach (array($row->landscape_mode_templates, $row->portrait_mode_templates) as $list) {
        foreach (array_map('trim', explode(',', (string) $list)) as $style) {
            if ($style !== '' && !in_array($style, $styles, true)) {
                $styles[] = $style;
            }
        }
    }

    return $styles;
}

/**
 * A readable name for the sample card built on a given template.
 *
 * The six templates the plugin ships each get a name that describes the
 * picture, so the gift card list reads as six distinct cards rather than six
 * copies of one. A template added to settings later still gets a usable name.
 *
 * @param string $style    Template filename.
 * @param int    $position 1-based position, used only for unknown templates.
 * @return string
 */
function wpgv_demo_card_title($style, $position)
{
    $names = array(
        'template-voucher-lanscape-4.png'  => __('Elegant Gift Voucher', 'gift-voucher'),
        'template-voucher-lanscape-8.png'  => __('Navy and Gold Gift Voucher', 'gift-voucher'),
        'template-voucher-lanscape-10.png' => __("Valentine's Day Gift Card", 'gift-voucher'),
        'template-voucher-portail-1.png'   => __('A Gift For You', 'gift-voucher'),
        'template-voucher-portail-2.png'   => __('Evergreen Gift Voucher', 'gift-voucher'),
        'template-voucher-portail-6.png'   => __('Red Ribbon Gift Voucher', 'gift-voucher'),
    );

    if (isset($names[$style])) {
        return $names[$style];
    }

    /* translators: %d: position of the sample gift card */
    return sprintf(__('Sample Gift Card %d', 'gift-voucher'), (int) $position);
}

/**
 * Create the sample records, skipping any that already exist.
 *
 * @return array Slug => post id, for everything that exists afterwards.
 */
function wpgv_create_demo_content()
{
    $author = get_current_user_id();
    if (!$author) {
        $admins = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
        $author = $admins ? (int) $admins[0] : 0;
    }

    $categories = wpgv_demo_category_ids();
    $made = array();

    /** Create one sample post unless its slug is already present. */
    $ensure = function ($slug, $title, $kind) use ($author, &$made) {
        $existing = wpgv_find_demo_post($slug);

        if ($existing) {
            $made[$slug] = $existing;
            return $existing;
        }

        $id = wp_insert_post(array(
            'post_type'   => 'voucher_template',
            'post_title'  => $title,
            'post_status' => 'publish',
            'post_author' => $author,
        ));

        if (is_wp_error($id) || !$id) {
            return 0;
        }

        update_post_meta($id, WPGV_DEMO_META, $slug);
        wpgv_set_kind($id, $kind);
        $made[$slug] = (int) $id;

        return (int) $id;
    };

    // --- One gift card per configured template ------------------------------
    //
    // A card is what puts a template on the form. With one per template, both
    // halves of the Format switch show every design the plugin ships, which is
    // the fastest way for a new shop owner to see what they can offer.

    $position = 0;

    foreach (wpgv_demo_template_styles() as $style) {
        $position++;

        // Keyed by the template file, so pressing the button again finds the
        // card it already made for that design instead of adding another.
        $slug = 'card-' . sanitize_key(preg_replace('/\.png$/i', '', $style));

        $id = $ensure($slug, wpgv_demo_card_title($style, $position), 'card');
        if (!$id) {
            continue;
        }

        update_post_meta($id, 'wpgv_customize_template_template-style', $style);
        update_post_meta($id, 'wpgv_customize_template_status', 'Active');
        update_post_meta($id, 'wpgv_customize_template_voucher_expiry_value', 60);

        if (!empty($categories['card'])) {
            wp_set_object_terms($id, array((int) $categories['card']), WPGV_CATEGORY_TAXONOMY);
        }
    }

    // --- One gift item -------------------------------------------------------
    //
    // Priced above its special price so the promotion rule is visible from the
    // start: the buyer is charged 40 while the voucher is worth 50.

    $item = $ensure('item', __('Relaxing Massage', 'gift-voucher'), 'item');

    if ($item) {
        wpgv_sync_item_meta($item, 'description', __('A one hour massage at our studio. Sample content - edit or delete it.', 'gift-voucher'));
        wpgv_sync_item_meta($item, 'price', 50);
        wpgv_sync_item_meta($item, 'special_price', 40);

        if (!empty($categories['item'])) {
            wp_set_object_terms($item, array((int) $categories['item']), WPGV_CATEGORY_TAXONOMY);
        }
    }

    // --- One voucher template ------------------------------------------------

    $template = $ensure('template', __('Classic Voucher', 'gift-voucher'), 'template');

    if ($template && function_exists('wpgv_sync_template_to_legacy_table')) {
        // Reuse the row a previous sample template was given, if it is still
        // there. The row survives deletion of the post on purpose - it is the
        // id orders are filed under - so without this, create, delete, create
        // would leave a new dead row behind every time.
        $remembered = (int) get_option('wpgv_demo_legacy_template_id');

        if ($remembered > 0 && !get_post_meta($template, WPGV_LEGACY_TEMPLATE_META, true)) {
            global $wpdb;
            $still_there = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM `{$wpdb->prefix}giftvouchers_template` WHERE id = %d",
                $remembered
            ));

            if ($still_there) {
                update_post_meta($template, WPGV_LEGACY_TEMPLATE_META, $remembered);
            }
        }

        $legacy_id = wpgv_sync_template_to_legacy_table($template);

        if ($legacy_id > 0) {
            update_option('wpgv_demo_legacy_template_id', $legacy_id, false);
        }
    }

    return $made;
}

/**
 * Remove the sample records.
 *
 * Posts and terms go; the row in giftvouchers_template stays. That table hands
 * out the ids orders are filed under, and upgrade/README.md holds it to
 * additions only - a deleted row would strand any order placed against the
 * sample template while the owner was trying it out.
 *
 * @return int How many posts were removed.
 */
function wpgv_delete_demo_content()
{
    $removed = 0;

    foreach (get_posts(array(
        'post_type'   => 'voucher_template',
        'post_status' => 'any',
        'numberposts' => -1,
        'fields'      => 'ids',
        'meta_key'    => WPGV_DEMO_META,
    )) as $id) {
        if (wp_delete_post((int) $id, true)) {
            $removed++;
        }
    }

    // The legacy row needs deciding on rather than simply left behind. Left
    // live it is still a template the front end can offer with nothing behind
    // it, and wpgv_migrate_legacy_templates() mirrors every row it finds -
    // active or not - so the row would come back as an ordinary record.
    //
    // Deleting it is only safe while no order points at it: that column is the
    // id an order's PDF is rebuilt from, so a row still in use is deactivated
    // instead, which hides it without stranding anything.
    $legacy_id = (int) get_option('wpgv_demo_legacy_template_id');

    if ($legacy_id > 0) {
        global $wpdb;

        $in_use = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$wpdb->prefix}giftvouchers_list` WHERE template_id = %d",
            $legacy_id
        ));

        if ($in_use > 0) {
            $wpdb->update(
                $wpdb->prefix . 'giftvouchers_template',
                array('active' => 0),
                array('id' => $legacy_id)
            );
        } else {
            $wpdb->delete($wpdb->prefix . 'giftvouchers_template', array('id' => $legacy_id));
            delete_option('wpgv_demo_legacy_template_id');
        }
    }

    foreach (get_terms(array(
        'taxonomy'   => WPGV_CATEGORY_TAXONOMY,
        'hide_empty' => false,
        'meta_key'   => WPGV_DEMO_META,
    )) as $term) {
        if (!is_wp_error($term)) {
            wp_delete_term((int) $term->term_id, WPGV_CATEGORY_TAXONOMY);
        }
    }

    return $removed;
}
