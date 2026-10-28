(function ($) {
	$(function () {
		$(document).on('click', '.wpgv-secret-toggle', function () {
			var $button = $(this);
			var $input = $('#' + $button.attr('aria-controls'));
			var showing = $input.attr('type') === 'text';
			var label = showing ? 'Hide webhook signing secret' : 'Show webhook signing secret';

			$input.attr('type', showing ? 'password' : 'text');
			$button.attr('aria-label', label).attr('title', label);
			$button.find('.dashicons')
				.toggleClass('dashicons-visibility', showing)
				.toggleClass('dashicons-hidden', !showing);
			$button.find('.screen-reader-text').text(label);
		});
	});

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
	var config = window.WPGiftAjax || {};
	var messages = config.messages || {};
	var requestUrl = config.ajaxurl || window.ajaxurl;

	if (!voucher_amount || isNaN(parseFloat(voucher_amount)) || parseFloat(voucher_amount) <= 0) {
		alert(messages.invalidAmount || 'Please enter a valid voucher amount.');
		return;
	}

	var data = {
		'action': 'wpgv_redeem_voucher',
		'voucher_id': voucher_id,
		'voucher_amount': voucher_amount,
		'nonce': config.redeemNonce || '',
	};

	jQuery.post(requestUrl, data)
		.done(function (response) {
			if (response && response.success) {
				alert((messages.successPrefix || 'Voucher updated:') + ' ' + response.data.message);
				window.location.reload();
				return;
			}

			var errorMessage = response && response.data && response.data.message
				? response.data.message
				: (messages.requestFailed || 'Request failed. Please try again.');
			alert(errorMessage);
		})
		.fail(function (xhr) {
			var response = xhr && xhr.responseJSON ? xhr.responseJSON : null;
			var errorMessage = response && response.data && response.data.message
				? response.data.message
				: (messages.requestFailed || 'Request failed. Please try again.');
			alert(errorMessage);
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

// Regenerate Modern Giftcard PDF — Purchase History admin page
// Full Konva canvas render → PNG capture → server PDF write
jQuery(document).on('click', '.wpgv-regen-pdf-btn', function () {
	var $btn = jQuery(this);
	var $msg = $btn.siblings('.wpgv-regen-pdf-msg');
	var voucherId = $btn.data('voucher-id');
	var nonce = $btn.data('nonce');

	$btn.prop('disabled', true).text('Loading...');
	$msg.hide().text('');

	// Step 1: fetch all order + template data from server
	jQuery.ajax({
		url: ajaxurl,
		type: 'POST',
		data: { action: 'wpgv_admin_get_voucher_regen_data', voucher_id: voucherId, nonce: nonce },
		success: function (resp) {
			if (!resp || !resp.success) {
				var errMsg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Failed to load order data.';
				$msg.css('color', 'red').text(errMsg).show();
				$btn.prop('disabled', false).text('Regenerate PDF');
				return;
			}
			$btn.text('Rendering...');
			wpgv_regen_render_canvas(resp.data, voucherId, nonce, $btn, $msg);
		},
		error: function () {
			$msg.css('color', 'red').text('Request failed. Please try again.').show();
			$btn.prop('disabled', false).text('Regenerate PDF');
		}
	});
});

// Regenerate Standard Giftcard PDF (admin-only)
jQuery(document).on('click', '.wpgv-regen-standard-pdf-btn', function () {
	var $btn = jQuery(this);
	var $msg = $btn.siblings('.wpgv-regen-standard-pdf-msg');
	var voucherId = $btn.data('voucher-id');
	var nonce = $btn.data('nonce');

	$btn.prop('disabled', true).text('Working...');
	$msg.hide().text('');

	jQuery.ajax({
		url: ajaxurl,
		type: 'POST',
		data: {
			action: 'wpgv_admin_regenerate_standard_pdf',
			voucher_id: voucherId,
			nonce: nonce
		},
		success: function (resp) {
			if (resp && resp.success) {
				$msg.css('color', 'green').text(resp.data.message || 'PDF regenerated successfully.').show();
				setTimeout(function () { location.reload(); }, 1000);
			} else {
				var errMsg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Standard PDF regeneration failed.';
				$msg.css('color', 'red').text(errMsg).show();
			}
		},
		error: function () {
			$msg.css('color', 'red').text('Request failed. Please try again.').show();
		},
		complete: function () {
			$btn.prop('disabled', false).text('Regenerate PDF');
		}
	});
});

/**
 * Draw the card the way the [wpgv_giftcard] form does (showTemplateGiftCard() in
 * voucher-template-script.js), filled with the order's data, and send the picture to the server,
 * which makes the PDF from it.
 */
function wpgv_regen_render_canvas(data, voucherId, nonce, $btn, $msg) {
	function fail(text) {
		$msg.css('color', 'red').text(text).show();
		$btn.prop('disabled', false).text('Regenerate PDF');
		jQuery('#wpgv-regen-wrap-' + voucherId).remove();
	}

	if (typeof Konva === 'undefined') {
		fail('Konva.js not loaded.');
		return;
	}

	var jsonData = null;
	try {
		jsonData = (typeof data.json === 'string') ? JSON.parse(data.json) : data.json;
	} catch (e) {
		jsonData = null;
	}
	if (!jsonData || typeof jsonData !== 'object') {
		fail('No template JSON found for this order.');
		return;
	}

	// Off-screen container (positioned far away, not display:none, so Konva can render)
	var containerId = 'wpgv-regen-offscreen-' + voucherId;
	jQuery('#wpgv-regen-wrap-' + voucherId).remove();
	jQuery('body').append(
		'<div id="wpgv-regen-wrap-' + voucherId + '" style="position:fixed;top:-99999px;left:-99999px;pointer-events:none;visibility:hidden;" aria-hidden="true">' +
		'<div id="' + containerId + '"></div>' +
		'</div>'
	);

	var stage;
	try {
		stage = Konva.Node.create(jsonData, containerId);
	} catch (e) {
		fail('Konva stage creation failed: ' + e.message);
		return;
	}

	// The order's values, under the shape ids the card templates use.
	var texts = {
		gift_title_company: data.company_name,
		giftcard_date_gift_label: data.date_of,
		giftcard_date_gift_input: data.expiry,
		giftto_label: data.giftto,
		giftfrom_label: data.giftfrom,
		giftcard_email: data.email,
		giftcard_website: data.web,
		giftto_input: data.to_name,
		giftfrom_input: data.from_name,
		giftcard_monney: data.amount_text,
		giftcard_monney_label: data.value_text,
		giftcard_des: data.message,
		giftcard_counpon_label: data.counpon,
		giftcard_counpon: data.couponcode,
		giftcard_note: data.notice
	};
	jQuery.each(texts, function (id, value) {
		var node = stage.findOne('#' + id);
		if (node) {
			node.text(value === null || value === undefined ? '' : String(value));
			node.fontFamily('Poppins');
			node.draggable(false);
		}
	});

	function capture() {
		stage.draw();
		var imageData;
		try {
			imageData = stage.toDataURL({ pixelRatio: 1.5 });
		} catch (e) {
			fail('The card picture could not be captured: ' + e.message);
			return;
		}

		jQuery.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpgv_admin_regenerate_modern_pdf',
				voucher_id: voucherId,
				nonce: nonce,
				canvas_data: imageData
			},
			success: function (resp) {
				if (resp && resp.success) {
					$msg.css('color', 'green').text(resp.data.message || 'PDF regenerated successfully.').show();
					setTimeout(function () { location.reload(); }, 1000);
				} else {
					var errMsg = (resp && resp.data && resp.data.message) ? resp.data.message : 'PDF regeneration failed.';
					$msg.css('color', 'red').text(errMsg).show();
				}
			},
			error: function () {
				$msg.css('color', 'red').text('PDF generation request failed. Try again.').show();
			},
			complete: function () {
				$btn.prop('disabled', false).text('Regenerate PDF');
				jQuery('#wpgv-regen-wrap-' + voucherId).remove();
			}
		});
	}

	// Like the form, every image of the template shows the card picture over the whole card.
	function drawPicture() {
		var images = stage.find('Image');
		if (!images.length || !data.image_url) {
			capture();
			return;
		}
		var picture = new window.Image();
		picture.crossOrigin = 'anonymous';
		picture.onload = function () {
			images.forEach(function (node) {
				node.image(picture);
				node.width(stage.width());
				node.height(stage.height());
			});
			capture();
		};
		picture.onerror = function () {
			fail('The card picture could not be loaded.');
		};
		picture.src = data.image_url;
	}

	// The card's text is set in Poppins, as on the form. The font is fetched only now, and at most
	// three seconds are spent on it: without it the card is drawn in a fallback font, never left hanging.
	wpgv_regen_load_card_font().then(drawPicture);
}

function wpgv_regen_load_card_font() {
	var fontsReady = new Promise(function (resolve) {
		var url = (typeof WPGiftAjax !== 'undefined') ? WPGiftAjax.cardFontCss : '';
		if (!url || !document.fonts || !document.fonts.load) {
			resolve();
			return;
		}
		var load = function () {
			Promise.all([document.fonts.load('16px Poppins'), document.fonts.load('bold 16px Poppins')]).then(resolve, resolve);
		};
		var link = document.getElementById('wpgv-regen-card-font');
		if (link) {
			load();
			return;
		}
		link = document.createElement('link');
		link.id = 'wpgv-regen-card-font';
		link.rel = 'stylesheet';
		link.href = url;
		link.onload = load;
		link.onerror = function () { resolve(); };
		document.head.appendChild(link);
	});
	var timeout = new Promise(function (resolve) { setTimeout(resolve, 3000); });
	return Promise.race([fontsReady, timeout]);
}

// Add Order page: show the recipient fields only for "Someone else", fill in a fixed price.
(function ($) {
	var $form = $('#wpgv-manual-order');
	if (!$form.length) { return; }
	function toggleRecipient() {
		var forSomeone = $form.find('input[name="buying_for"]:checked').val() !== 'yourself';
		$form.find('.wpgv-manual-recipient').toggle(forSomeone)
			.find('input').prop('required', forSomeone);
	}
	$form.on('change', 'input[name="buying_for"]', toggleRecipient);
	$('#wpgv_manual_source').on('change', function () {
		var price = $(this).find(':selected').data('price');
		if (price !== '' && price !== undefined) { $('#wpgv_manual_amount').val(price); }
	});
	toggleRecipient();
})(jQuery);
