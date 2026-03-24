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
 * Render the Konva canvas with order data, capture PNG, send to server.
 */
function wpgv_regen_render_canvas(data, voucherId, nonce, $btn, $msg) {
	if (typeof Konva === 'undefined') {
		$msg.css('color', 'red').text('Konva.js not loaded.').show();
		$btn.prop('disabled', false).text('Regenerate PDF');
		return;
	}

	var json = data.json;
	if (!json || json === '') {
		$msg.css('color', 'red').text('No template JSON found for this order.').show();
		$btn.prop('disabled', false).text('Regenerate PDF');
		return;
	}

	// Parse JSON
	var jsonData;
	try {
		jsonData = (typeof json === 'string') ? JSON.parse(json) : json;
	} catch (e) {
		$msg.css('color', 'red').text('Invalid template JSON.').show();
		$btn.prop('disabled', false).text('Regenerate PDF');
		return;
	}

	// Create off-screen container (positioned way off-screen, not display:none, so Konva can render)
	var containerId = 'wpgv-regen-offscreen-' + voucherId;
	jQuery('#' + containerId).remove();
	jQuery('body').append(
		'<div id="wpgv-regen-wrap-' + voucherId + '" style="position:fixed;top:-99999px;left:-99999px;pointer-events:none;visibility:hidden;" aria-hidden="true">' +
		'<div id="' + containerId + '"></div>' +
		'</div>'
	);

	var stage;
	try {
		stage = Konva.Node.create(jsonData, containerId);
	} catch (e) {
		$msg.css('color', 'red').text('Konva stage creation failed: ' + e.message).show();
		$btn.prop('disabled', false).text('Regenerate PDF');
		jQuery('#wpgv-regen-wrap-' + voucherId).remove();
		return;
	}

	// Update text layers with real order data
	var stage_json = stage.toJSON();
	var shapes = stage.find('Text');
	for (var i = 0; i < shapes.length; i++) {
		var shape = shapes[i];
		var shapeId = shape.getAttribute ? shape.getAttribute('id') : (shape.attrs && shape.attrs.id ? shape.attrs.id : '');
		if (shapeId === 'giftto_name' && data.to_name) {
			shape.text(data.to_name);
		} else if (shapeId === 'giftfrom_name' && data.from_name) {
			shape.text(data.from_name);
		} else if (shapeId === 'gift_amount' && data.amount) {
			shape.text(data.currency || data.amount);
		} else if (shapeId === 'expiry_date' && data.expiry) {
			shape.text(data.expiry);
		} else if (shapeId === 'giftcard_coupon' && data.couponcode) {
			shape.text(data.couponcode);
		} else if (shapeId === 'personal_message' && data.message) {
			shape.text(data.message);
		}
	}

	// Ask browser to render.
	stage.batchDraw();

	// Capture as PNG base64
	setTimeout(function () {
		var canvas = stage.toCanvas();
		var imageData = canvas.toDataURL('image/png');

		// Send to server for PDF generation
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
	}, 100);
}