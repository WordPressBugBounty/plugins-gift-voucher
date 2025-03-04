<?php

$license = get_option('wpgv_license_key');
$status  = get_option('wpgv_license_status');
?>
<div class="wrap">
	<h2><?php echo esc_html(__('Plugin License Options', 'gift-voucher')); ?></h2>
	<form method="post" action="options.php">

		<?php settings_fields('wpgv_license'); ?>

		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row" valign="top">
						<?php echo esc_html(__('License Key', 'gift-voucher')); ?>
					</th>
					<td>
						<input id="wpgv_license_key" name="wpgv_license_key" type="text" class="regular-text" value="<?php echo esc_attr($license); ?>" />
						<label class="description" for="wpgv_license_key"><?php echo esc_html(__('Enter your license key', 'gift-voucher')); ?></label>
					</td>
				</tr>
				<?php if (false !== $license) { ?>
					<tr valign="top">
						<th scope="row" valign="top">
							<?php echo esc_html(__('Activate License', 'gift-voucher')); ?>
						</th>
						<td>
							<?php if ($status !== false && $status == 'valid') { ?>
								<span style="color:green;"><?php echo esc_html(__('active', 'gift-voucher')); ?></span>
								<?php wp_nonce_field('wpgv_nonce', 'wpgv_nonce'); ?>
								<input type="submit" class="button-secondary" name="wpgv_license_deactivate" value="<?php echo esc_html(__('Deactivate License', 'gift-voucher')); ?>" />
							<?php } else {
								wp_nonce_field('wpgv_nonce', 'wpgv_nonce'); ?>
								<input type="submit" class="button-secondary" name="wpgv_license_activate" value="<?php echo esc_html(__('Activate License', 'gift-voucher')); ?>" />
							<?php } ?>
						</td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
		<?php submit_button(); ?>

	</form>