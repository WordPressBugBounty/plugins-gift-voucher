jQuery(function() {

	jQuery('body').on('click', '.wpgv-remove-voucher', function(e) {
        wpgv_bind_remove_link(jQuery(this));
        e.preventDefault();
        return false;
    });
	jQuery('#wpgv_show-gift-card').off('click').on('click', function(e) {
        jQuery('.checkout_wpgv_gift_voucher').slideToggle(400, function() {
            jQuery('.checkout_wpgv_gift_voucher').find(':input:eq(0)').focus();
        });

        e.preventDefault();
        return false;
    });
	jQuery('#wpgv-apply-gift-voucher-checkout').off('click').on('click', function(e) {
        wpgv_checkout_redeem_gift_voucher(jQuery(this));
        e.preventDefault();
        return false;
    });
    jQuery('#wpgv-redeem-gift-voucher-number').off('keypress').on('keypress', function(e) {
        if (e.keyCode == 13) {
            wpgv_checkout_redeem_gift_voucher(jQuery('#wpgv-apply-gift-voucher-checkout'));
            e.preventDefault();
            return false;
        }
    });
});

function wpgv_checkout_redeem_gift_voucher(redeemButton) {
    var errorContainer = jQuery('#wpgv-redeem-error');
    var cardNumber = jQuery('#wpgv-redeem-gift-voucher-number');

    errorContainer.text('');
    redeemButton.attr('data-apply-text', redeemButton.attr('value')).attr('value', redeemButton.attr('data-wait-text')).prop('disabled', true);

    if(!cardNumber.val()) {
    	errorContainer.text('Enter a card number.');
        redeemButton.attr('value', redeemButton.attr('data-apply-text')).prop('disabled', false);
        cardNumber.focus();
    	return true;
    }
    jQuery.post(frontend_ajax_object.ajaxurl, {'action': 'wpgv-gift-voucher-redeem', 'voucher_code': cardNumber.val()}, function( result ) {
        if (result.success) {
            // We could hook into the cart's ajax calls, but for now we'll just reload.
            window.location = window.location.pathname;
        } else {
            errorContainer.html(result.data.message);
            redeemButton.attr('value', redeemButton.attr('data-apply-text')).prop('disabled', false);
            cardNumber.focus();
        }
    }).fail(function(xhr, textStatus, errorThrown) {
        if (errorThrown) {
            errorContainer.text(errorThrown);
        } else {
            errorContainer.text('Unknown Error');
        }
        redeemButton.attr('value', redeemButton.attr('data-apply-text')).prop('disabled', false);
        cardNumber.focus();
    });
}

function wpgv_bind_remove_link(removeButton) {
    var cardNumber = removeButton.attr('data-gift-voucher');

	jQuery.post(frontend_ajax_object.ajaxurl, {'action': 'wpgv-gift-voucher-remove', 'voucher_code': cardNumber}, function( result ) {
		window.location = window.location.pathname;
	}).fail(function(xhr, textStatus, errorThrown) {
		if (errorThrown) {
			alert(errorThrown);
		} else {
			alert('Unknown Error');
		}
		window.location = window.location.pathname;
	});
}