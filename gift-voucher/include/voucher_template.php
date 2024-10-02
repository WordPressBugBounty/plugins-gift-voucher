<?php

if( !defined( 'ABSPATH' ) ) exit;  // Exit if accessed directly

global $wpdb;
$template_table = $wpdb->prefix . 'giftvouchers_template';
?>
<div class="wrap">
	<h1><?php echo esc_html_e('Voucher Templates', 'gift-voucher' ); ?> <a class="page-title-action" id="add_new_template" href="<?php echo esc_url(admin_url( 'admin.php' )); ?>?page=new-voucher-template"><?php echo esc_html_e('Add New Template', 'gift-voucher' ) ?></a></h1>
	<div class="content">
		<div id="post-body" class="metabox-holder">
			<div id="post-body-content">
				<div class="meta-box-sortables ui-sortable">
					<form method="post">
						<?php
						$this->vouchers_obj->prepare_items();
						$this->vouchers_obj->display(); 
						?>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>