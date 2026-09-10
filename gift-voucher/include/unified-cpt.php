<?php

if (!defined('ABSPATH')) exit;  // Exit if accessed directly

/**
 * Unified gift-content post type: shared vocabulary.
 *
 * Gift Items, Gift Cards and Voucher Templates are being merged into the single
 * voucher_template post type. They are told apart by a hidden meta key rather
 * than a taxonomy: a taxonomy renders as an editable box on the edit screen, and
 * an admin changing it by accident would silently reclassify the record.
 *
 * See upgrade/01-hop-nhat-item-card-template.md section 4.1.
 */

/** Hidden meta key holding the record's kind. */
define('WPGV_KIND_META', '_wpgv_kind');

/**
 * The only legal kinds.
 *
 * - card     : modern canvas template, used by [wpgv_giftcard]
 * - item     : sellable gift item with a price, used by [wpgv_giftitems]
 * - template : standard template mirrored from the legacy giftvouchers_template table
 *
 * @return string[]
 */
function wpgv_valid_kinds()
{
    return array('card', 'item', 'template');
}

/**
 * Master switch for the merged post type.
 *
 * Turning it off returns every read path to the pre-merge behaviour, which stays
 * possible because the migration never deletes or rewrites the old data.
 * See upgrade/01-hop-nhat-item-card-template.md section 5.2.
 *
 * @return bool
 */
function wpgv_unified_cpt_enabled()
{
    return (bool) get_option('wpgv_unified_cpt_enabled', 0);
}

/**
 * Read a record's kind.
 *
 * Anything that is not one of the three legal values reads as an empty string,
 * so a stray meta row written by other code is never trusted.
 *
 * @param int $post_id
 * @return string One of wpgv_valid_kinds(), or '' when unset or invalid.
 */
function wpgv_get_kind($post_id)
{
    $kind = get_post_meta((int) $post_id, WPGV_KIND_META, true);

    return in_array($kind, wpgv_valid_kinds(), true) ? $kind : '';
}

/**
 * Set a record's kind, rejecting anything outside the allowed set.
 *
 * @param int    $post_id
 * @param string $kind
 * @return bool True when the value was accepted and stored.
 */
function wpgv_set_kind($post_id, $kind)
{
    $post_id = (int) $post_id;

    if ($post_id <= 0 || !in_array($kind, wpgv_valid_kinds(), true)) {
        return false;
    }

    update_post_meta($post_id, WPGV_KIND_META, $kind);

    // update_post_meta() returns false when the value is unchanged, which is not
    // a failure for our purposes - the caller asked for a state, and it holds.
    return wpgv_get_kind($post_id) === $kind;
}

/**
 * Gift item meta stored under bare, unprefixed names.
 *
 * These predate the plugin's naming conventions and can collide with meta from
 * other plugins on the same post. Each now has a _wpgv_ prefixed canonical copy.
 *
 * @return string[]
 */
function wpgv_item_legacy_meta_keys()
{
    return array('description', 'price', 'special_price', 'style1_image', 'style2_image', 'style3_image');
}

/**
 * Read gift item meta, preferring the prefixed key.
 *
 * @param int    $post_id
 * @param string $key     Bare key name, e.g. 'price'.
 * @param mixed  $default Returned when neither copy holds a value.
 * @return mixed
 */
function wpgv_item_meta($post_id, $key, $default = '')
{
    $post_id = (int) $post_id;

    $new = get_post_meta($post_id, '_wpgv_' . $key, true);
    if ($new !== '' && $new !== false) {
        return $new;
    }

    $old = get_post_meta($post_id, $key, true);
    if ($old !== '' && $old !== false) {
        return $old;
    }

    return $default;
}

/**
 * Write gift item meta to BOTH the prefixed and the bare key.
 *
 * Writing only the bare key would leave the prefixed copy stale, and since
 * wpgv_item_meta() prefers the prefixed one, an admin editing a price would keep
 * seeing the old value. The bare key is kept in step rather than dropped because
 * a customer snippet may read get_post_meta($id, 'price') directly.
 *
 * A blank value clears both, so a cleared field cannot resurrect from the copy
 * that was not updated.
 *
 * @param int    $post_id
 * @param string $key   Bare key name.
 * @param mixed  $value
 * @return void
 */
function wpgv_sync_item_meta($post_id, $key, $value)
{
    $post_id = (int) $post_id;

    // Matches what the original save loop did - `if (!$value) delete_post_meta()`.
    // absint('') is 0, so an emptied price field arrives here as 0, not ''.
    // Treating only '' as blank stored a literal 0 and left the field showing 0
    // after being cleared.
    if (empty($value)) {
        delete_post_meta($post_id, '_wpgv_' . $key);
        delete_post_meta($post_id, $key);
        return;
    }

    update_post_meta($post_id, '_wpgv_' . $key, $value);
    update_post_meta($post_id, $key, $value);
}

/**
 * Create the prefixed copy for gift items that do not have one yet.
 *
 * Never deletes the bare key. Idempotent, and batched for large catalogues.
 *
 * @param int $batch Maximum posts to process per call.
 * @return int Number of meta rows written in this call.
 */
function wpgv_mirror_item_meta($batch = 200)
{
    $ids = get_posts(wpgv_item_query_args(array(
        'post_status'    => 'any',
        'posts_per_page' => max(1, (int) $batch),
        'fields'         => 'ids',
    )));

    $written = 0;

    foreach ($ids as $id) {
        foreach (wpgv_item_legacy_meta_keys() as $key) {
            $existing = get_post_meta($id, '_wpgv_' . $key, true);
            if ($existing !== '' && $existing !== false) {
                continue;
            }

            $old = get_post_meta($id, $key, true);
            if ($old === '' || $old === false) {
                continue;
            }

            update_post_meta($id, '_wpgv_' . $key, $old);
            $written++;
        }
    }

    return $written;
}

/**
 * The kind a save is settling on, for panel savers to gate themselves against.
 *
 * Reads the submitted Type first and only falls back to what is stored. Panel
 * savers hang off save_post at whatever priority they were registered with -
 * wpt_save_voucher_meta is at 1, ahead of the saver that writes the kind - so a
 * gate reading only the stored value sees an empty kind on a brand new record
 * and skips, which is how a new gift item lost its price on its first save.
 *
 * Order-independent by design: no priority number has to be correct for this to
 * give the right answer.
 *
 * @param int $post_id
 * @return string One of wpgv_valid_kinds(), or '' when neither source has one.
 */
function wpgv_kind_being_saved($post_id)
{
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- every caller verifies its own nonce first.
    if (isset($_POST['wpgv_kind'])) {
        $submitted = sanitize_text_field(wp_unslash($_POST['wpgv_kind']));
        if (in_array($submitted, wpgv_valid_kinds(), true)) {
            return $submitted;
        }
    }

    return wpgv_get_kind($post_id);
}

/**
 * Whether a post holds a gift item.
 *
 * @param int $post_id
 * @return bool
 */
function wpgv_is_item($post_id)
{
    return wpgv_get_kind($post_id) === 'item';
}

/**
 * WP_Query arguments selecting gift items, replacing post_type => wpgv_voucher_product.
 *
 * Falls back to the old post type while the merge has not run, so callers work
 * in both states and the flag stays a real off switch.
 *
 * @param array $extra Additional query arguments, merged over the defaults.
 * @return array
 */
function wpgv_item_query_args($extra = array())
{
    if (!wpgv_unified_cpt_enabled()) {
        return array_merge(array('post_type' => 'wpgv_voucher_product'), $extra);
    }

    $base = array(
        'post_type'  => 'voucher_template',
        'meta_query' => array(
            array('key' => WPGV_KIND_META, 'value' => 'item'),
        ),
    );

    return array_merge($base, $extra);
}

/**
 * Record a migration write that failed.
 *
 * $wpdb->update() and insert() answer false on failure, and every migration
 * step here ignored that. A failed step leaves work behind, so the runner
 * simply tries again on the next admin request - forever, and with nothing
 * anywhere to say why. This leaves a trace in the error log and keeps the last
 * one in an option an administrator can read.
 *
 * @param string $step   Name of the migration step.
 * @param string $detail What it was working on when it failed.
 */
function wpgv_migration_note_failure($step, $detail = '')
{
    global $wpdb;

    $message = sprintf(
        'WPGV migration: step "%s" failed on %s. %s',
        $step,
        $detail !== '' ? $detail : 'an unnamed row',
        $wpdb->last_error !== '' ? $wpdb->last_error : 'No database error was reported.'
    );

    update_option('wpgv_migration_last_error', array(
        'time'    => current_time('mysql'),
        'step'    => $step,
        'detail'  => $detail,
        'message' => $message,
    ), false);

    error_log($message);
}

/**
 * Move gift items into the voucher_template post type, in place.
 *
 * Updating post_type by id keeps the post ID, so wp_postmeta and
 * wp_term_relationships - both keyed on object_id - are untouched. That is what
 * makes this migration cheap: no id remapping, so nothing that references a
 * gift item by id has to be rewritten.
 *
 * Switches wpgv_unified_cpt_enabled on once no gift items remain under the old
 * type. The two are coupled on purpose: converting the posts while the flag is
 * off would leave every item query looking at a post type that no longer holds
 * anything, and gift items would disappear from the site.
 *
 * Idempotent, and batched so a large catalogue does not stall a page load.
 *
 * @param int $batch Maximum posts to convert per call.
 * @return int Number of posts converted in this call.
 */
function wpgv_convert_item_posts($batch = 200)
{
    global $wpdb;

    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s ORDER BY ID ASC LIMIT %d",
        'wpgv_voucher_product',
        max(1, (int) $batch)
    ));

    $converted = 0;

    foreach ($ids as $id) {
        $id = (int) $id;

        if (false === $wpdb->update($wpdb->posts, array('post_type' => 'voucher_template'), array('ID' => $id))) {
            wpgv_migration_note_failure('convert_item_posts', 'post ' . $id);
            continue;
        }

        clean_post_cache($id);
        wpgv_set_kind($id, 'item');

        $converted++;
    }

    $remaining = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
        'wpgv_voucher_product'
    ));

    if ($remaining === 0) {
        update_option('wpgv_unified_cpt_enabled', 1);
        // Tightening publicly_queryable does not remove rewrite rules that are
        // already stored, so /voucher_template/<slug>/ would keep resolving.
        // Deferred because this request registered the type with the old flags.
        update_option('wpgv_needs_rewrite_flush', 1);
    }

    return $converted;
}

/**
 * Flush rewrite rules once, after the post types have re-registered with the
 * post-merge flags.
 *
 * Runs on a later request than the conversion on purpose: the request that
 * flipped the flag had already registered the types with the old flags, so
 * flushing then would rebuild the very rules the merge is meant to drop.
 *
 * @return bool True when a flush was performed.
 */
function wpgv_maybe_flush_rewrite_rules()
{
    if (!get_option('wpgv_needs_rewrite_flush', 0)) {
        return false;
    }

    $obj = get_post_type_object('voucher_template');
    if (!$obj || $obj->publicly_queryable !== false) {
        // Registration has not caught up yet; try again next request.
        return false;
    }

    flush_rewrite_rules(false);
    delete_option('wpgv_needs_rewrite_flush');

    return true;
}

/**
 * Undo wpgv_convert_item_posts().
 *
 * The only migration step that cannot reverse itself by simply turning the flag
 * off, because it rewrote a column. Everything else only added rows.
 *
 * @return int Number of posts moved back.
 */
function wpgv_rollback_item_posts()
{
    global $wpdb;

    $ids = get_posts(array(
        'post_type'      => 'voucher_template',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => array(
            array('key' => WPGV_KIND_META, 'value' => 'item'),
        ),
    ));

    $moved = 0;

    foreach ($ids as $id) {
        if (false === $wpdb->update($wpdb->posts, array('post_type' => 'wpgv_voucher_product'), array('ID' => (int) $id))) {
            wpgv_migration_note_failure('rollback_item_posts', 'post ' . (int) $id);
            continue;
        }

        clean_post_cache((int) $id);
        $moved++;
    }

    update_option('wpgv_unified_cpt_enabled', 0);

    return $moved;
}

/** The taxonomy every category now lives in. */
define('WPGV_CATEGORY_TAXONOMY', 'category_voucher_template');

/** The gift item taxonomy that was folded into it. */
define('WPGV_LEGACY_ITEM_TAXONOMY', 'wpgv_voucher_category');

/**
 * Fold the gift item taxonomy into the gift card one.
 *
 * Rewrites wp_term_taxonomy.taxonomy in place. term_taxonomy_id is untouched and
 * wp_term_relationships is keyed on it, so every existing assignment survives
 * without being rewritten - the same property that made changing post_type in
 * place safe. Term meta is keyed on term_id, which is also unchanged.
 *
 * Slugs cannot collide: wp_terms.slug is unique across the whole table, so two
 * taxonomies could never already hold the same slug.
 *
 * Idempotent.
 *
 * @return int Number of rows moved in this call.
 */
function wpgv_merge_item_taxonomy()
{
    global $wpdb;

    $term_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s",
        WPGV_LEGACY_ITEM_TAXONOMY
    ));

    $moved = $wpdb->update(
        $wpdb->term_taxonomy,
        array('taxonomy' => WPGV_CATEGORY_TAXONOMY),
        array('taxonomy' => WPGV_LEGACY_ITEM_TAXONOMY)
    );

    if (false === $moved) {
        wpgv_migration_note_failure('merge_item_taxonomy', WPGV_LEGACY_ITEM_TAXONOMY);
        $moved = 0;
    }

    $moved = (int) $moved;

    if ($moved > 0) {
        wpgv_clear_taxonomy_caches($term_ids);
    }

    return $moved;
}

/**
 * Categories that actually hold records of one kind.
 *
 * The two taxonomies became one, so a plain term query now returns gift card
 * categories to the gift item form and vice versa. Each buying flow only ever
 * listed its own categories, so each is filtered back to the terms that contain
 * at least one record of its kind.
 *
 * A category with nothing in it is left out either way: the item form renders a
 * heading per category, and an empty one produced a heading with no products.
 *
 * @param string $kind One of wpgv_valid_kinds().
 * @param array  $args Extra get_terms() arguments.
 * @return WP_Term[]
 */
function wpgv_get_categories_for_kind($kind, $args = array())
{
    if (!in_array($kind, wpgv_valid_kinds(), true)) {
        return array();
    }

    $terms = get_terms(array_merge(array(
        'taxonomy'   => WPGV_CATEGORY_TAXONOMY,
        'hide_empty' => false,
    ), $args));

    if (is_wp_error($terms) || !$terms) {
        return array();
    }

    $matching = array();

    foreach ($terms as $term) {
        $found = get_posts(array(
            'post_type'      => array('voucher_template', 'wpgv_voucher_product'),
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'tax_query'      => array(
                array(
                    'taxonomy' => WPGV_CATEGORY_TAXONOMY,
                    'field'    => 'term_id',
                    'terms'    => (int) $term->term_id,
                ),
            ),
            'meta_query'     => array(
                array('key' => WPGV_KIND_META, 'value' => $kind),
            ),
        ));

        if ($found) {
            $matching[] = $term;
        }
    }

    return $matching;
}

/**
 * Clear only the term caches this plugin invalidated.
 *
 * Deliberately not wp_cache_flush(): that empties every other plugin's cache
 * too, and firing admin_menu straight after one was enough to fatal WooCommerce
 * while this was being developed. Nothing here needs that blast radius.
 *
 * @param int[] $term_ids
 * @return void
 */
function wpgv_clear_taxonomy_caches($term_ids)
{
    $term_ids = array_map('intval', (array) $term_ids);

    clean_term_cache($term_ids, WPGV_LEGACY_ITEM_TAXONOMY, false);
    clean_term_cache($term_ids, WPGV_CATEGORY_TAXONOMY, false);

    // Invalidate cached get_terms() queries without touching other groups.
    wp_cache_set('last_changed', microtime(), 'terms');
}

/**
 * Undo wpgv_merge_item_taxonomy().
 *
 * Only moves back terms that are attached to a gift item, since the target
 * taxonomy also holds the gift card categories that were always there.
 *
 * @return int Number of rows moved back.
 */
function wpgv_rollback_item_taxonomy()
{
    global $wpdb;

    $item_ids = get_posts(array(
        'post_type'      => array('voucher_template', 'wpgv_voucher_product'),
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => array(
            array('key' => WPGV_KIND_META, 'value' => 'item'),
        ),
    ));

    if (!$item_ids) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($item_ids), '%d'));

    $tt_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT tr.term_taxonomy_id
         FROM {$wpdb->term_relationships} tr
         JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
         WHERE tr.object_id IN ({$placeholders}) AND tt.taxonomy = %s",
        array_merge($item_ids, array(WPGV_CATEGORY_TAXONOMY))
    ));

    $moved = 0;
    foreach ($tt_ids as $tt_id) {
        $one = $wpdb->update(
            $wpdb->term_taxonomy,
            array('taxonomy' => WPGV_LEGACY_ITEM_TAXONOMY),
            array('term_taxonomy_id' => (int) $tt_id)
        );

        if (false === $one) {
            wpgv_migration_note_failure('rollback_item_taxonomy', 'term_taxonomy_id ' . (int) $tt_id);
            continue;
        }

        $moved += (int) $one;
    }

    if ($moved > 0) {
        wpgv_clear_taxonomy_caches($tt_ids);
    }

    return $moved;
}

/**
 * Write a standard template back to the legacy giftvouchers_template table.
 *
 * The CPT is now the only place a standard template is edited, but the table
 * still supplies the id every order carries in giftvouchers_list.template_id, so
 * it remains the identity allocator rather than a discarded store. A record with
 * no legacy row yet gets one, which is how a template created in the CPT obtains
 * an id the ordering flow can use.
 *
 * Only ever INSERTs or UPDATEs. No row is ever removed, so an order can always
 * resolve the template it was bought with.
 *
 * @param int $post_id
 * @return int The legacy row id, or 0 when the post is not a standard template.
 */
function wpgv_sync_template_to_legacy_table($post_id)
{
    global $wpdb;

    $post_id = (int) $post_id;
    if ($post_id <= 0 || wpgv_get_kind($post_id) !== 'template') {
        return 0;
    }

    $table = $wpdb->prefix . 'giftvouchers_template';
    if (!function_exists('wpgv_db_table_exists') || !wpgv_db_table_exists($table)) {
        return 0;
    }

    $data = array(
        'title'       => (string) get_the_title($post_id),
        // The publish state is the single source of truth for active, rather than
        // a second field that could disagree with it.
        'active'      => get_post_status($post_id) === 'publish' ? 1 : 0,
        'orderno'     => (int) get_post_field('menu_order', $post_id),
        'image'       => (int) get_post_meta($post_id, '_wpgv_legacy_image', true),
        'image_style' => (string) get_post_meta($post_id, '_wpgv_legacy_image_style', true),
    );

    $legacy_id = (int) get_post_meta($post_id, WPGV_LEGACY_TEMPLATE_META, true);

    if ($legacy_id > 0) {
        $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM `{$table}` WHERE id = %d", $legacy_id));
        if ($exists > 0) {
            if (false === $wpdb->update($table, $data, array('id' => $legacy_id))) {
                wpgv_migration_note_failure('sync_template_to_legacy_table', 'legacy id ' . $legacy_id);
            }

            return $legacy_id;
        }
    }

    if (false === $wpdb->insert($table, $data)) {
        wpgv_migration_note_failure('sync_template_to_legacy_table', 'post ' . $post_id);
        return 0;
    }

    $legacy_id = (int) $wpdb->insert_id;

    if ($legacy_id > 0) {
        update_post_meta($post_id, WPGV_LEGACY_TEMPLATE_META, $legacy_id);
    }

    return $legacy_id;
}

/**
 * How many orders were placed against a record.
 *
 * A record is reachable from giftvouchers_list three different ways, because the
 * three kinds were three separate stores before the merge:
 *
 * - gift item      : item_id holds the post id
 * - gift card      : template_id holds the post id, template_kind is 'modern'
 * - standard tpl   : template_id holds the LEGACY table id, template_kind is
 *                    'standard_list', so it is matched through the mirrored post's
 *                    _wpgv_legacy_template_id
 *
 * @param int $post_id
 * @return int
 */
function wpgv_count_orders_using($post_id)
{
    global $wpdb;

    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return 0;
    }

    $list = $wpdb->prefix . 'giftvouchers_list';

    if (!function_exists('wpgv_db_table_exists') || !wpgv_db_table_exists($list)) {
        return 0;
    }

    $total = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM `{$list}` WHERE item_id = %d",
        $post_id
    ));

    $total += (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM `{$list}` WHERE template_id = %d AND template_kind = %s",
        $post_id,
        'modern'
    ));

    $legacy_id = (int) get_post_meta($post_id, WPGV_LEGACY_TEMPLATE_META, true);
    if ($legacy_id > 0) {
        $total += (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$list}` WHERE template_id = %d AND template_kind = %s",
            $legacy_id,
            'standard_list'
        ));
    }

    return $total;
}

/**
 * Whether a record's kind may still be changed.
 *
 * Locked once any order references it: those orders recorded a template_kind at
 * purchase time and their PDFs are rebuilt from this record, so changing the kind
 * would rebuild them from the wrong design.
 *
 * @param int $post_id
 * @return bool
 */
function wpgv_kind_is_locked($post_id)
{
    return wpgv_count_orders_using($post_id) > 0;
}

/**
 * Whether a post id may be bought through the [wpgv_giftitems] flow.
 *
 * Single source of truth for "is this a gift item". The public item handler had
 * no type check at all, so any post id was accepted; see
 * docs-ai/fix/12-item-flow-inconsistencies.md.
 *
 * Kept in one function on purpose: when gift items move into the
 * voucher_template post type, only the condition here changes.
 *
 * @param int $post_id
 * @return bool
 */
function wpgv_is_purchasable_gift_item($post_id)
{
    $post_id = (int) $post_id;

    if ($post_id <= 0) {
        return false;
    }

    if (get_post_status($post_id) !== 'publish') {
        return false;
    }

    if (wpgv_unified_cpt_enabled()) {
        // After the merge, gift cards and standard templates share this post
        // type, so the post type alone no longer identifies an item.
        return get_post_type($post_id) === 'voucher_template' && wpgv_is_item($post_id);
    }

    return get_post_type($post_id) === 'wpgv_voucher_product';
}

/**
 * Bumped whenever a new migration step is added, so an existing site runs it.
 */
define('WPGV_MIGRATION_VERSION', '4.8.0');

/** True when there is nothing left to migrate on this site. */
function wpgv_migrations_are_current()
{
    return get_option('wpgv_migration_version', '') === WPGV_MIGRATION_VERSION;
}

/**
 * Run whatever migration work is still outstanding.
 *
 * Three things this exists to prevent, all of which the steps had when they were
 * wired straight into admin_init:
 *
 * 1. Running forever. Every step queried on every admin page load long after
 *    there was nothing to do - about 20 queries a request on a nearly empty
 *    site, and more as content grows. The version marker stops that.
 * 2. Running as the wrong user. admin_init fires for a Subscriber opening
 *    profile.php, so their request performed the schema change and the data
 *    rewrites. Now it takes an administrator.
 * 3. Running twice at once. Two overlapping requests could both pass a "has this
 *    been migrated yet" check and both write. A short lock serialises them.
 *
 * The marker is only written once every step reports no work left, so a batched
 * migration keeps going across page loads until it is genuinely finished.
 *
 * @return bool True when this call did any work.
 */
function wpgv_run_pending_migrations()
{
    if (wpgv_migrations_are_current()) {
        return false;
    }

    // Schema and data changes are an administrator's business, not a side effect
    // of whoever happens to load an admin page first.
    if (!current_user_can('manage_options')) {
        return false;
    }

    if (get_transient('wpgv_migration_lock')) {
        return false;
    }

    // Short enough that a fatal mid-migration cannot wedge the site for long.
    set_transient('wpgv_migration_lock', 1, 60);

    $remaining = 0;

    $remaining += (int) wpgv_upgrade_template_kind_column();
    $remaining += (int) wpgv_stamp_existing_cards();
    $remaining += (int) wpgv_migrate_legacy_templates();
    $remaining += (int) wpgv_convert_item_posts();
    $remaining += (int) wpgv_mirror_item_meta();
    $remaining += (int) wpgv_merge_item_taxonomy();

    wpgv_maybe_flush_rewrite_rules();

    // Only finished when a full pass found nothing to do. A batched step that
    // still has rows left reports a non-zero count, so the next admin request
    // picks up where this one stopped.
    if ($remaining === 0) {
        update_option('wpgv_migration_version', WPGV_MIGRATION_VERSION);
    }

    delete_transient('wpgv_migration_lock');

    return $remaining > 0;
}

/** Meta key holding the giftvouchers_template row id a post was mirrored from. */
define('WPGV_LEGACY_TEMPLATE_META', '_wpgv_legacy_template_id');

/**
 * Find the post mirrored from a given legacy template row.
 *
 * @param int $legacy_id Row id from giftvouchers_template.
 * @return int|null Post id, or null when that row has not been mirrored yet.
 */
function wpgv_find_post_by_legacy_template_id($legacy_id)
{
    $legacy_id = (int) $legacy_id;

    if ($legacy_id <= 0) {
        return null;
    }

    $ids = get_posts(array(
        'post_type'      => 'voucher_template',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => array(
            array('key' => WPGV_LEGACY_TEMPLATE_META, 'value' => $legacy_id),
        ),
    ));

    return $ids ? (int) $ids[0] : null;
}

/**
 * Mirror rows of the legacy giftvouchers_template table into the CPT.
 *
 * The table is never modified, dropped or read-locked - it stays the identity
 * source for existing orders and becomes permanently read-only. This only adds
 * a post per row so the templates can be managed from the unified admin list.
 *
 * All meta is written through wp_insert_post()'s meta_input so a post never
 * exists without its kind; otherwise a crash between insert and update would
 * leave a post that wpgv_stamp_existing_cards() would later mislabel as a card.
 *
 * Idempotent: rows that already have a mirror post are skipped.
 *
 * @param int $batch Maximum rows to process per call.
 * @return int Number of posts created in this call.
 */
function wpgv_migrate_legacy_templates($batch = 50)
{
    global $wpdb;

    $table = $wpdb->prefix . 'giftvouchers_template';

    if (!function_exists('wpgv_db_table_exists') || !wpgv_db_table_exists($table)) {
        return 0;
    }

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM `{$table}` ORDER BY id ASC LIMIT %d",
        max(1, (int) $batch)
    ));

    if (!$rows) {
        return 0;
    }

    $created = 0;

    foreach ($rows as $row) {
        if (wpgv_find_post_by_legacy_template_id((int) $row->id)) {
            continue;
        }

        $post_id = wp_insert_post(array(
            'post_type'   => 'voucher_template',
            'post_title'  => (string) $row->title,
            'post_status' => ((int) $row->active === 1) ? 'publish' : 'draft',
            'menu_order'  => (int) $row->orderno,
            'meta_input'  => array(
                WPGV_KIND_META             => 'template',
                WPGV_LEGACY_TEMPLATE_META  => (int) $row->id,
                '_wpgv_legacy_image'       => (int) $row->image,
                '_wpgv_legacy_image_style' => (string) $row->image_style,
            ),
        ), true);

        if (is_wp_error($post_id) || !$post_id) {
            continue;
        }

        $created++;
    }

    return $created;
}

/**
 * Mark every existing voucher_template post as a card.
 *
 * Runs before any other content is merged in, so at this point every post of
 * that type is by definition a modern gift card. Idempotent: posts that already
 * carry a kind are skipped, so a record explicitly set to something else is not
 * overwritten.
 *
 * @param int $batch Maximum posts to stamp per call.
 * @return int Number of posts stamped in this call.
 */
function wpgv_stamp_existing_cards($batch = 200)
{
    $ids = get_posts(array(
        'post_type'      => 'voucher_template',
        'post_status'    => 'any',
        'posts_per_page' => max(1, (int) $batch),
        'fields'         => 'ids',
        // Catch both a missing meta row and one holding a value outside the
        // allowed set, so the postcondition "every post has a valid kind" holds.
        'meta_query'     => array(
            'relation' => 'OR',
            array('key' => WPGV_KIND_META, 'compare' => 'NOT EXISTS'),
            array('key' => WPGV_KIND_META, 'value' => wpgv_valid_kinds(), 'compare' => 'NOT IN'),
        ),
    ));

    $stamped = 0;

    foreach ($ids as $id) {
        if (wpgv_get_kind($id) !== '') {
            continue;
        }

        if (wpgv_set_kind($id, 'card')) {
            $stamped++;
        }
    }

    return $stamped;
}
