(function ($) {

	$('#voucher_bgcolor, #voucher_color').wpColorPicker();

	$('.wpgiftv-row .nav-tab').on('click', function (e) {
		e.preventDefault();
		$('.wpgiftv-row .nav-tab').removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active');
		var tab = $(this).attr('href');
		$('.wpgiftv-row .tab-content').removeClass('tab-content-active');
		$(tab).addClass('tab-content-active');
	});

})(jQuery);

function redeemVoucher(voucher_id) {
	var voucher_amount = jQuery('#voucher_amount' + voucher_id).val();

	var data = {
		'action': 'wpgv_redeem_voucher',
		'voucher_id': voucher_id,
		'voucher_amount': voucher_amount,
	};

	jQuery.post(ajaxurl, data, function (response) {
		alert('Got this from the server: ' + response);
	});
}