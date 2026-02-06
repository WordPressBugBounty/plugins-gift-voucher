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

(function () {
	var container = document.getElementById('wpgv-quotes-list');
	var addBtn = document.getElementById('wpgv-add-quote');
	if (!container || !addBtn) { return; }
	addBtn.addEventListener('click', function () {
		var div = document.createElement('div');
		div.className = 'wpgv-quote-row';
		div.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:6px;';
		div.innerHTML = '<input type="text" name="wpgv_quotes_item[]" value="" class="regular-text" style="flex:1;" />' +
			'<button class="button wpgv-remove-quote" type="button">&times;</button>';
		container.appendChild(div);
	});
	container.addEventListener('click', function (e) {
		if (e.target && e.target.classList.contains('wpgv-remove-quote')) {
			var row = e.target.closest('.wpgv-quote-row');
			if (row) { row.remove(); }
		}
	});
})();