<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!current_user_can('manage_options')) {
    wp_die(esc_html__('You are not allowed to be on this page.', 'gift-voucher'), 403);
}

$wpgv_state = get_transient('wpgv_manual_order_' . get_current_user_id());
$wpgv_old = (isset($_GET['wpgv_manual_order']) && isset($wpgv_state['input'])) ? $wpgv_state['input'] : array();
if (isset($wpgv_state['error'])) {
    delete_transient('wpgv_manual_order_' . get_current_user_id());
}
$wpgv_val = function ($key, $default = '') use ($wpgv_old) {
    return isset($wpgv_old[$key]) ? $wpgv_old[$key] : $default;
};
$wpgv_sources = wpgv_manual_order_sources();
$wpgv_selected = $wpgv_old ? $wpgv_val('type') . ':' . $wpgv_val('source_id') : '';
?>
<div class="wrap">
    <h1><?php esc_html_e('Add Order', 'gift-voucher'); ?></h1>
    <p><?php esc_html_e('Issue a paid voucher without a checkout, for example for a sale at the counter, a present or a refund.', 'gift-voucher'); ?></p>

    <?php if (isset($wpgv_state['error'])) : ?>
        <div class="notice notice-error"><p><?php echo esc_html($wpgv_state['error']); ?></p></div>
    <?php endif; ?>

    <?php if (!$wpgv_sources) : ?>
        <div class="notice notice-warning"><p><?php esc_html_e('Create a gift voucher or a gift item first.', 'gift-voucher'); ?></p></div>
    <?php else : ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="wpgv-manual-order">
        <input type="hidden" name="action" value="wpgv_create_manual_order">
        <?php wp_nonce_field('wpgv_create_manual_order'); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="wpgv_manual_source"><?php esc_html_e('Gift voucher or item', 'gift-voucher'); ?></label></th>
                <td>
                    <select name="source" id="wpgv_manual_source" required>
                        <option value=""><?php esc_html_e('Choose…', 'gift-voucher'); ?></option>
                        <?php foreach (array('vouchers' => __('Gift Vouchers', 'gift-voucher'), 'items' => __('Gift Items', 'gift-voucher')) as $wpgv_type => $wpgv_group) : ?>
                            <optgroup label="<?php echo esc_attr($wpgv_group); ?>">
                                <?php foreach ($wpgv_sources as $wpgv_s) : if ($wpgv_s['type'] !== $wpgv_type) { continue; } $wpgv_key = $wpgv_s['type'] . ':' . $wpgv_s['id']; ?>
                                    <option value="<?php echo esc_attr($wpgv_key); ?>" data-price="<?php echo esc_attr(null === $wpgv_s['price'] ? '' : $wpgv_s['price']); ?>" <?php selected($wpgv_selected, $wpgv_key); ?>><?php echo esc_html($wpgv_s['title']); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="wpgv_manual_amount"><?php esc_html_e('Voucher value', 'gift-voucher'); ?></label></th>
                <td><input type="number" step="0.01" min="0.01" name="amount" id="wpgv_manual_amount" value="<?php echo esc_attr($wpgv_val('amount')); ?>" required>
                    <p class="description"><?php esc_html_e('Filled in from a priced voucher or item. You can change it.', 'gift-voucher'); ?></p></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('For', 'gift-voucher'); ?></th>
                <td>
                    <label><input type="radio" name="buying_for" value="someone_else" <?php checked($wpgv_val('buying_for', 'someone_else'), 'someone_else'); ?>> <?php esc_html_e('Someone else', 'gift-voucher'); ?></label>
                    &nbsp; <label><input type="radio" name="buying_for" value="yourself" <?php checked($wpgv_val('buying_for'), 'yourself'); ?>> <?php esc_html_e('The buyer', 'gift-voucher'); ?></label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="wpgv_manual_buyer_name"><?php esc_html_e('Buyer name', 'gift-voucher'); ?></label></th>
                <td><input type="text" class="regular-text" name="buyer_name" id="wpgv_manual_buyer_name" value="<?php echo esc_attr($wpgv_val('buyer_name')); ?>" required></td>
            </tr>
            <tr>
                <th scope="row"><label for="wpgv_manual_buyer_email"><?php esc_html_e('Buyer email', 'gift-voucher'); ?></label></th>
                <td><input type="email" class="regular-text" name="buyer_email" id="wpgv_manual_buyer_email" value="<?php echo esc_attr($wpgv_val('buyer_email')); ?>" required></td>
            </tr>
            <tr class="wpgv-manual-recipient">
                <th scope="row"><label for="wpgv_manual_recipient_name"><?php esc_html_e('Recipient name', 'gift-voucher'); ?></label></th>
                <td><input type="text" class="regular-text" name="recipient_name" id="wpgv_manual_recipient_name" value="<?php echo esc_attr($wpgv_val('recipient_name')); ?>"></td>
            </tr>
            <tr class="wpgv-manual-recipient">
                <th scope="row"><label for="wpgv_manual_recipient_email"><?php esc_html_e('Recipient email', 'gift-voucher'); ?></label></th>
                <td><input type="email" class="regular-text" name="recipient_email" id="wpgv_manual_recipient_email" value="<?php echo esc_attr($wpgv_val('recipient_email')); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="wpgv_manual_message"><?php esc_html_e('Personal message', 'gift-voucher'); ?></label></th>
                <td><textarea name="message" id="wpgv_manual_message" rows="3" class="large-text" maxlength="250"><?php echo esc_textarea($wpgv_val('message')); ?></textarea></td>
            </tr>
            <tr>
                <th scope="row"><label for="wpgv_manual_style"><?php esc_html_e('PDF style', 'gift-voucher'); ?></label></th>
                <td><select name="style" id="wpgv_manual_style">
                    <?php foreach (array(0, 1, 2) as $wpgv_style) : ?>
                        <option value="<?php echo esc_attr($wpgv_style); ?>" <?php selected((int) $wpgv_val('style', 0), $wpgv_style); ?>><?php echo esc_html(sprintf(__('Style %d', 'gift-voucher'), $wpgv_style + 1)); ?></option>
                    <?php endforeach; ?>
                </select></td>
            </tr>
            <tr>
                <th scope="row"><label for="wpgv_manual_code"><?php esc_html_e('Voucher code', 'gift-voucher'); ?></label></th>
                <td><input type="text" inputmode="numeric" pattern="[0-9]{6,18}" name="code" id="wpgv_manual_code" value="<?php echo esc_attr($wpgv_val('code')); ?>">
                    <p class="description"><?php esc_html_e('Leave empty to create a code. Your own code must be 6 to 18 digits.', 'gift-voucher'); ?></p></td>
            </tr>
            <tr>
                <th scope="row"><label for="wpgv_manual_expiry"><?php esc_html_e('Expiry date', 'gift-voucher'); ?></label></th>
                <td><input type="date" name="expiry" id="wpgv_manual_expiry" value="<?php echo esc_attr($wpgv_val('expiry')); ?>">
                    <p class="description"><?php esc_html_e('Leave empty to use the expiry from Settings.', 'gift-voucher'); ?></p></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Email', 'gift-voucher'); ?></th>
                <td><label><input type="checkbox" name="send_email" id="wpgv_manual_send_email" value="1" <?php checked(!$wpgv_old || !empty($wpgv_old['send_email'])); ?>> <?php esc_html_e('Email the voucher now to the recipient, the buyer and the shop', 'gift-voucher'); ?></label></td>
            </tr>
        </table>
        <p class="submit"><button type="submit" class="button button-primary" id="wpgv_manual_submit"><?php esc_html_e('Create paid order', 'gift-voucher'); ?></button></p>
    </form>
    <?php endif; ?>
</div>
