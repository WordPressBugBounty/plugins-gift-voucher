<?php

if (!defined('ABSPATH')) exit;  // Exit if accessed directly

/**
 * The Gift Card Data box: one Type selector deciding what the record is.
 *
 * One post type now holds gift cards, gift items and standard templates. Before
 * this box existed the create screen offered no way to say which one you wanted,
 * so a new record could only ever become a gift card - gift items could not be
 * created at all.
 *
 * Every panel stays registered as its own meta box and keeps its existing render
 * and save code. This box only writes the kind; a small script shows the panel
 * that matches the selected Type and hides the rest. Nothing about how a gift
 * card or a gift item is stored changed here.
 *
 * See upgrade/01-plan-giai-doan-1.5.md task 2.
 */

/** Kind assigned to a record saved without an explicit choice. */
function wpgv_default_kind()
{
    return 'card';
}

/**
 * Meta box ids belonging to each kind, used to show one panel and hide the rest.
 *
 * @return array<string,string[]>
 */
function wpgv_kind_panel_ids()
{
    return array(
        'card'     => array('customize-template'),
        'item'     => array('wpgv_voucher_amount'),
        'template' => array('wpgv-template-details'),
    );
}

add_action('add_meta_boxes', 'wpgv_register_gift_card_data_box');
/**
 * Register the Type box above the panels.
 *
 * @return void
 */
function wpgv_register_gift_card_data_box()
{
    if (!wpgv_unified_cpt_enabled()) {
        return;
    }

    add_meta_box(
        'wpgv-gift-card-data',
        __('Gift Card Data', 'gift-voucher'),
        'wpgv_render_gift_card_data_box',
        'voucher_template',
        'normal',
        'high'
    );

    add_meta_box(
        'wpgv-template-details',
        __('Gift Voucher Details', 'gift-voucher'),
        'wpgv_render_template_details_box',
        'voucher_template',
        'normal',
        'default'
    );
}

/**
 * Render the standard template panel.
 *
 * Laid out field for field like the gift item's Item Details box, so the two
 * types read the same in admin: Description, Price, Special Price and three
 * style images with the same labels, sizes and buttons.
 *
 * The field names are prefixed (wpgv_template_*) because both boxes sit on the
 * same edit screen - only shown or hidden by Type - and same-named inputs would
 * submit twice. The values themselves go to the same meta keys as an item's, so
 * the price rules in wpgv_resolve_item_amounts() apply to templates unchanged.
 *
 * The publish state carries the template's active flag, so there is no separate
 * Status field to disagree with it.
 *
 * @param WP_Post $post
 * @return void
 */
function wpgv_render_template_details_box($post)
{
    wp_nonce_field('wpgv_template_details', 'wpgv_template_details_nonce');

    $raw = (string) get_post_meta($post->ID, '_wpgv_legacy_image_style', true);
    $images = $raw ? json_decode($raw, true) : array();
    if (!is_array($images)) {
        $images = array();
    }

    $description = (string) wpgv_item_meta($post->ID, 'description');
    $price = (string) wpgv_item_meta($post->ID, 'price');
    $special_price = (string) wpgv_item_meta($post->ID, 'special_price');

    echo '<p class="post-attributes-label-wrapper"><label class="post-attributes-label" for="wpgv_template_description">' . esc_html__('Description', 'gift-voucher') . ': (20 Words)</label></p>';
    echo '<textarea name="wpgv_template_description" id="wpgv_template_description" class="widefat">' . esc_textarea($description) . '</textarea><div class="dt_hr dt_hr-bottom"></div>';

    echo '<p class="post-attributes-label-wrapper"><label class="post-attributes-label" for="wpgv_template_price">' . esc_html__('Price', 'gift-voucher') . ':</label></p>';
    echo '<input type="number" name="wpgv_template_price" id="wpgv_template_price" class="widefat" value="' . esc_attr($price) . '" step=".01" min="0">';
    echo '<p class="description">' . esc_html__('Leave empty to let customers enter their own amount. Special Price is then ignored.', 'gift-voucher') . '</p><div class="dt_hr dt_hr-bottom"></div>';

    echo '<p class="post-attributes-label-wrapper"><label class="post-attributes-label" for="wpgv_template_special_price">' . esc_html__('Special Price', 'gift-voucher') . ':</label></p>';
    echo '<input type="number" name="wpgv_template_special_price" id="wpgv_template_special_price" class="widefat" value="' . esc_attr($special_price) . '" step=".01" min="0"><div class="dt_hr dt_hr-bottom"></div>';

    $sizes = array('1000px x 760px', '1000px x 1500px', '1000px x 750px');
    for ($i = 0; $i < 3; $i++) {
        $value = isset($images[$i]) ? (string) $images[$i] : '';
        $thumb = $value ? wp_get_attachment_image_src((int) $value, 'voucher-thumb') : false;
        $url = $thumb ? $thumb[0] : '';

        echo '<div class="wpgv-tpl-image">';
        echo '<p class="post-attributes-label-wrapper"><label class="post-attributes-label">'
            . esc_html(sprintf('Image - Style %d (Recommended: %s). ', $i + 1, $sizes[$i]))
            . esc_html__('Supported formats: JPG, PNG only.', 'gift-voucher') . '</label></p>';
        printf('<img class="wpgv-tpl-image-preview" src="%s" width="100" style="display:%s;">', esc_url($url), $url ? 'inline' : 'none');
        printf('<input type="hidden" class="wpgv-tpl-image-id" name="wpgv_template_image[%d]" value="%s">', (int) $i, esc_attr($value));
        echo '<button type="button" class="button wpgv-tpl-image-pick">' . esc_html__('Upload Image', 'gift-voucher') . '</button> ';
        echo '<button type="button" class="button button-primary wpgv-tpl-image-clear" style="display:' . ($value ? 'inline-block' : 'none') . ';">' . esc_html__('Remove Image', 'gift-voucher') . '</button><br>';
        echo '</div>';
    }
    ?>
    <script>
    jQuery(function ($) {
        $('.wpgv-tpl-image-pick').on('click', function (e) {
            e.preventDefault();
            var wrap = $(this).closest('.wpgv-tpl-image');
            var frame = wp.media({
                title: 'Add Voucher Image',
                button: { text: 'Upload Image' },
                multiple: false,
                library: { type: 'image' }
            });
            frame.on('select', function () {
                var a = frame.state().get('selection').first().toJSON();
                // Same rule as the gift item images: JPG and PNG only.
                var mime = a.mime || a.mime_type || '';
                var ext = (a.url || '').split('.').pop().toLowerCase();
                if (['image/jpeg', 'image/png'].indexOf(mime) === -1 && ['jpg', 'jpeg', 'png'].indexOf(ext) === -1) {
                    alert('<?php echo esc_js(__('Only JPG and PNG images are supported for Image - Style. Please choose a JPG or PNG file.', 'gift-voucher')); ?>');
                    return;
                }
                wrap.find('.wpgv-tpl-image-id').val(a.id);
                wrap.find('.wpgv-tpl-image-preview').attr('src', a.url).show();
                wrap.find('.wpgv-tpl-image-clear').show();
            });
            frame.open();
        });
        $('.wpgv-tpl-image-clear').on('click', function () {
            var wrap = $(this).closest('.wpgv-tpl-image');
            wrap.find('.wpgv-tpl-image-id').val('');
            wrap.find('.wpgv-tpl-image-preview').attr('src', '').hide();
            $(this).hide();
        });
    });
    </script>
    <?php
}

add_action('save_post', 'wpgv_save_template_details', 20, 2);
/**
 * Save the standard template panel and write it through to the legacy table.
 *
 * Priority 20 so the kind is already settled by wpgv_save_gift_card_data at 5.
 *
 * @param int     $post_id
 * @param WP_Post $post
 * @return void
 */
function wpgv_save_template_details($post_id, $post)
{
    if (!isset($_POST['wpgv_template_details_nonce'])) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash($_POST['wpgv_template_details_nonce']));
    if (!wp_verify_nonce($nonce, 'wpgv_template_details')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    $post_id = (int) $post_id;

    if (get_post_type($post_id) !== 'voucher_template' || wp_is_post_revision($post_id)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // This panel exists on every record of the type, so only write for templates.
    if (wpgv_kind_being_saved($post_id) !== 'template') {
        return;
    }

    $submitted = isset($_POST['wpgv_template_image']) && is_array($_POST['wpgv_template_image'])
        ? wp_unslash($_POST['wpgv_template_image'])
        : array();

    $images = array();
    for ($i = 0; $i < 3; $i++) {
        $images[] = isset($submitted[$i]) ? (string) absint($submitted[$i]) : '';
    }
    // Match the legacy column's shape: a JSON array of three attachment ids.
    $images = array_map(function ($v) {
        return $v === '0' ? '' : $v;
    }, $images);

    // JPG and PNG only, as for gift item images: the PDF renderer cannot use the rest.
    foreach ($images as $i => $id) {
        if ($id !== '' && !in_array(get_post_mime_type((int) $id), array('image/jpeg', 'image/png'), true)) {
            $images[$i] = '';
        }
    }

    update_post_meta($post_id, '_wpgv_legacy_image_style', wp_json_encode($images));

    // Description and prices share the gift item's meta keys, so one set of price
    // rules (wpgv_resolve_item_amounts) serves both. Unlike the item box this keeps
    // decimals: absint() there turns 49.90 into 49.
    $text = function ($key) {
        return isset($_POST[$key]) ? trim((string) wp_unslash($_POST[$key])) : '';
    };
    $money = function ($key) use ($text) {
        $amount = wpgv_normalize_decimal_amount($text($key));
        return ($amount === null || $amount <= 0) ? '' : (string) $amount;
    };
    wpgv_sync_item_meta($post_id, 'description', sanitize_textarea_field($text('wpgv_template_description')));
    wpgv_sync_item_meta($post_id, 'price', $money('wpgv_template_price'));
    wpgv_sync_item_meta($post_id, 'special_price', $money('wpgv_template_special_price'));

    wpgv_sync_template_to_legacy_table($post_id);
}

add_action('save_post', 'wpgv_sync_template_title_to_legacy', 30, 2);
/**
 * Keep the legacy row in step when only the title or publish state changed.
 *
 * The panel saver above only runs when its own nonce is present. A quick edit
 * from the list screen, or a status change, submits no meta box at all, so the
 * table would drift out of step without this.
 *
 * @param int     $post_id
 * @param WP_Post $post
 * @return void
 */
function wpgv_sync_template_title_to_legacy($post_id, $post)
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    $post_id = (int) $post_id;

    if (get_post_type($post_id) !== 'voucher_template' || wp_is_post_revision($post_id)) {
        return;
    }

    if (wpgv_get_kind($post_id) !== 'template') {
        return;
    }

    wpgv_sync_template_to_legacy_table($post_id);
}

/**
 * Render the Type selector.
 *
 * @param WP_Post $post
 * @return void
 */
function wpgv_render_gift_card_data_box($post)
{
    wp_nonce_field('wpgv_gift_card_data', 'wpgv_gift_card_data_nonce');

    $current = wpgv_get_kind($post->ID);
    if ($current === '') {
        $current = wpgv_default_kind();
    }

    $labels = array(
        'card'     => __('Gift Card', 'gift-voucher'),
        'item'     => __('Gift Item', 'gift-voucher'),
        'template' => __('Gift Voucher', 'gift-voucher'),
    );

    $hints = array(
        'card'     => __('Designed card, sold through [wpgv_giftcard]', 'gift-voucher'),
        'item'     => __('Fixed-price product, sold through [wpgv_giftitems]', 'gift-voucher'),
        'template' => __('Standard gift voucher, sold through [wpgv_giftvoucher]', 'gift-voucher'),
    );

    $locked = wpgv_kind_is_locked($post->ID);
    $orders = $locked ? wpgv_count_orders_using($post->ID) : 0;

    echo '<p><label for="wpgv_kind"><strong>' . esc_html__('Type', 'gift-voucher') . '</strong></label> ';
    echo '<select name="wpgv_kind" id="wpgv_kind"' . ($locked ? ' disabled="disabled"' : '') . '>';
    foreach ($labels as $value => $label) {
        printf(
            '<option value="%s"%s>%s</option>',
            esc_attr($value),
            selected($current, $value, false),
            esc_html($label)
        );
    }
    echo '</select> <span id="wpgv-kind-hint" class="description"></span></p>';

    if ($locked) {
        // A disabled select submits nothing, so the current kind still has to
        // reach the server or the saver would see an empty value.
        echo '<input type="hidden" name="wpgv_kind" value="' . esc_attr($current) . '">';

        echo '<p class="description">';
        printf(
            /* translators: %d: number of orders placed with this record */
            esc_html(_n(
                'The type cannot be changed: %d order was placed with this record, and its PDF is rebuilt from it whenever you regenerate it. Changing the type would rebuild that order from the wrong design.',
                'The type cannot be changed: %d orders were placed with this record, and their PDFs are rebuilt from it whenever you regenerate them. Changing the type would rebuild those orders from the wrong design.',
                $orders,
                'gift-voucher'
            )),
            (int) $orders
        );
        echo ' ' . esc_html__('To offer this as a different type, create a new record and leave this one in place so the order history stays intact.', 'gift-voucher');
        echo '</p>';
    } else {
        echo '<p class="description">'
            . esc_html__('The type decides which fields this record uses. Fields of another type stay saved and reappear if you switch back.', 'gift-voucher')
            . '</p>';
    }

    // Panel visibility is a display concern, so it stays in the browser; the
    // fields themselves are all rendered server-side by their own meta boxes.
    //
    // Deferred to DOM ready on purpose. This box renders first, so a script
    // running inline here would look for the other panels before the browser has
    // parsed them and silently hide nothing - which is exactly what happened.
    ?>
    <script>
    jQuery(function ($) {
        var panels = <?php echo wp_json_encode(wpgv_kind_panel_ids()); ?>;
        var hints  = <?php echo wp_json_encode($hints); ?>;
        var $select = $('#wpgv_kind');
        var $hint   = $('#wpgv-kind-hint');
        if (!$select.length) { return; }

        function apply() {
            var kind = $select.val();
            $.each(panels, function (k, ids) {
                $.each(ids, function (i, id) {
                    $('#' + id).toggle(k === kind);
                });
            });
            $hint.text(hints[kind] || '');
        }

        $select.on('change', apply);
        apply();
    });
    </script>
    <?php
}

add_action('save_post', 'wpgv_save_gift_card_data', 5, 2);
/**
 * Store the chosen kind.
 *
 * Priority 5 so the kind is settled before the panel savers run at the default
 * priority and check it.
 *
 * @param int     $post_id
 * @param WP_Post $post
 * @return void
 */
function wpgv_save_gift_card_data($post_id, $post)
{
    if (!isset($_POST['wpgv_gift_card_data_nonce'])) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash($_POST['wpgv_gift_card_data_nonce']));
    if (!wp_verify_nonce($nonce, 'wpgv_gift_card_data')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    $post_id = (int) $post_id;

    if (get_post_type($post_id) !== 'voucher_template' || wp_is_post_revision($post_id)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Enforced here, not only by disabling the select. A disabled control is a
    // courtesy to the person using the screen, never a guarantee about what
    // arrives in the request.
    if (wpgv_kind_is_locked($post_id)) {
        return;
    }

    $kind = isset($_POST['wpgv_kind'])
        ? sanitize_text_field(wp_unslash($_POST['wpgv_kind']))
        : '';

    if (!in_array($kind, wpgv_valid_kinds(), true)) {
        // An unrecognised value is ignored rather than applied. A record that has
        // no kind yet still needs one, so it falls back to the default.
        if (wpgv_get_kind($post_id) !== '') {
            return;
        }
        $kind = wpgv_default_kind();
    }

    wpgv_set_kind($post_id, $kind);
}
