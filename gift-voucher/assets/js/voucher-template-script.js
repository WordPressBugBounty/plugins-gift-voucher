jQuery(document).ready(function ($) {
    var voucherTemplate = jQuery('#giftvoucher-template'),
        voucher_template_id = jQuery('#voucher-id'),
        voucher_extra_charges = jQuery('#voucher-extra-charges'),
        voucher_price_value = jQuery('#giftvoucher-template #voucher_price_value'),
        voucher_gift_to = jQuery('#giftvoucher-template #voucher_gift_to'),
        voucher_gift_from = jQuery('#giftvoucher-template #voucher_gift_from'),
        voucher_description = jQuery('#giftvoucher-template #voucher_description'),
        voucher_your_email = jQuery('#giftvoucher-template #voucher_your_email'),
        voucher_recipient_email = jQuery('#giftvoucher-template #voucher_recipient_email');
    voucher_shipping_first = jQuery('#giftvoucher-template #voucher_shipping_first');
    voucher_shipping_last = jQuery('#giftvoucher-template #voucher_shipping_last');
    voucher_shipping_address = jQuery('#giftvoucher-template #voucher_shipping_address');
    voucher_shipping_city = jQuery('#giftvoucher-template #voucher_shipping_city');
    voucher_shipping_postcode = jQuery('#giftvoucher-template #voucher_shipping_postcode');
    payment_gateway = jQuery('#giftvoucher-template #payment_gateway');
    voucher_couponcode = jQuery('#giftvoucher-template #voucher-couponcode');
    number_slider = jQuery('#giftvoucher-template #number_giftcard_sl').val();
    var step_template = voucherTemplate.find('.giftvoucher-step.active .step-group').data('step');
    //show step
    changeStepVoucher(step_template);

    function sliderVoucherTemplate(number_slider) {
        var number = parseInt(number_slider);
        if (jQuery('#slider-giftvoucher-template .slider-voucher-template').hasClass('slick-initialized')) {
            jQuery('#slider-giftvoucher-template .slider-voucher-template').slick('destroy');
        }
        jQuery('#slider-giftvoucher-template .slider-voucher-template').not('.slick-initialized').slick({
            infinite: true,
            slidesToShow: number,
            slidesToScroll: 1,
            dots: false,
            arrows: true,
            //adaptiveHeight: true,
            prevArrow: "<div class='prev-slider'><i class='fa fa-angle-left'></i></div>",
            nextArrow: "<div class='next-slider'><i class='fa fa-angle-right'></i></div>",
            responsive: [{
                breakpoint: 1024,
                settings: {
                    slidesToShow: 2,
                    slidesToScroll: 1,
                    infinite: true,
                }
            },
            {
                breakpoint: 736,
                settings: {
                    slidesToShow: 1,
                    slidesToScroll: 1
                }
            }
            ]
        });
    };
    // slider voucher ajax
    jQuery(document).on('click', '#giftvoucher-template .format-category-voucher .layout-type', function (event) {
        if (!jQuery(this).hasClass('active')) {
            var dataType = jQuery(this).data('type');
            var dataCategory = voucherTemplate.find('.category-nav-item.active .category-voucher-item').data('category-id');
            var data = {
                action: 'voucher_slider_template',
                dataType: dataType,
                dataCategory: dataCategory
            };
            voucherTemplate.find('.format-category-voucher .layout-type').removeClass('active');
            jQuery(this).addClass('active');
            jQuery.ajax({
                url: frontend_ajax_object.ajaxurl,
                type: "POST",
                data: data,
                beforeSend: function (results) {
                    voucherTemplate.find('.voucher-content-step').addClass('loading');
                },
                success: function (results) {
                    console.log(results);
                    if (results) {
                        voucherTemplate.find('#slider-giftvoucher-template').html(results);
                        // if (dataType == 'landscape') {
                        //     sliderVoucherTemplate();
                        // } else {
                        sliderVoucherTemplate(number_slider);
                        //}
                        voucherTemplate.find('.voucher-content-step').removeClass('loading');
                    }

                },
                error: function () {
                    voucherTemplate.find('.voucher-content-step').removeClass('loading');
                    alert(frontend_ajax_object.error_occur);
                }
            });

        } else {
            return false;
        }
    });

    // filter category slider voucher ajax
    jQuery(document).on('click', '#giftvoucher-template .voucher-category-selection-wrap .category-voucher-item', function (event) {
        if (!jQuery(this).hasClass('active')) {
            var dataType = voucherTemplate.find('.format-category-voucher .layout-type.active').data('type');
            var dataCategory = jQuery(this).data('category-id');
            var data = {
                action: 'voucher_slider_template',
                dataType: dataType,
                dataCategory: dataCategory
            };
            voucherTemplate.find('.voucher-category-selection-wrap .category-nav-item').removeClass('active');
            jQuery(this).parent('.category-nav-item').addClass('active');
            jQuery.ajax({
                url: frontend_ajax_object.ajaxurl,
                type: "POST",
                data: data,
                beforeSend: function (results) {
                    voucherTemplate.find('.voucher-content-step').addClass('loading');
                },
                success: function (results) {
                    if (results) {
                        voucherTemplate.find('#slider-giftvoucher-template').html(results);
                        // if (dataType == 'landscape') {
                        //     sliderVoucherTemplate();
                        // } else {
                        sliderVoucherTemplate(number_slider);
                        //}
                        voucherTemplate.find('.voucher-content-step').removeClass('loading');
                    }

                },
                error: function () {
                    voucherTemplate.find('.voucher-content-step').removeClass('loading');
                    alert(frontend_ajax_object.error_occur);
                }
            });
        } else {
            return false;
        }
    });
    //jQuery select template gift card
    jQuery(document).on('click', '#giftvoucher-template .item-voucher-template .layout-button', function (event) {
        var voucher_id = jQuery(this).parents('.layout-overlay').data('post_id');

        var step_voucher = voucherTemplate.find('.giftvoucher-step.active .step-group').data('step');
        var data = {
            action: 'ajax_select_voucher_template',
            voucher_id: voucher_id
        }
        jQuery.ajax({
            url: frontend_ajax_object.ajaxurl,
            type: "POST",
            data: data,
            beforeSend: function (results) {
                voucherTemplate.find('.voucher-content-step').addClass('loading');
            },
            success: function (results) {

                var data = JSON.parse(results);
                imagesGiftCard = data.url;
                currency = data.currency;
                giftto = data.giftto;
                giftfrom = data.giftfrom;
                date_of = data.date_of;
                company_name = data.company_name;
                email = data.email;
                website = data.web;
                expiryDate = data.expiryDate;
                notice = data.leftside_notice;
                counpon_label = data.counpon;
                json = data.json;

                changeStepVoucher(step_voucher + 1);
                var couponcode = Math.floor(1000000000000000 + Math.random() * 9000000000000000);
                voucherTemplate.find('#voucher-id').val(voucher_id);
                voucherTemplate.find('#voucher-couponcode').val(couponcode);
                voucherTemplate.find('#template_giftcard_container').append('<img id="template_giftcard_img" src="' + imagesGiftCard + '"/>');
                setTimeout(function (e) {
                    showTemplateGiftCard(json, imagesGiftCard, currency, giftto, giftfrom, date_of, email, website, company_name, expiryDate, notice, counpon_label, parseInt(step_voucher + 1))
                    fitStageIntoParentContainer()
                    jQuery('#show-preview-gift-card').trigger('click');
                    voucherTemplate.find('.voucher-content-step').removeClass('loading');
                }, 500);
            },
            error: function () {
                voucherTemplate.find('.voucher-content-step').removeClass('loading');
                alert(frontend_ajax_object.error_occur);
            }
        });
    });
    // function next step payment voucher-continue-step
    jQuery(document).on('click', '#giftvoucher-template #voucher-continue-step .voucher-next-step', function (event) {
        var step = $(this).data('next-step'),
            result = wpgv_validateitemform(step);
        scrollTopGiftCard();
        if (!result) return;
        changeStepVoucher(step);

    });
    // function next step payment voucher-prev-step
    jQuery(document).on('click', '#giftvoucher-template #voucher-continue-step .voucher-prev-step', function (event) {
        var step = $(this).data('prev-step');
        changeStepVoucher(step);
        scrollTopGiftCard();
    });
    // function change shipping
    jQuery(document).on('click', '#giftvoucher-template .choose-shipping-template .shipping-type', function (event) {
        var typeShipping = jQuery(this).data('type');
        voucherTemplate.find('.shipping-type').removeClass('active');
        jQuery(this).addClass('active');
        var totalPrice = parseFloat(parseFloat(voucher_price_value.val()) + parseFloat(voucher_extra_charges.val()));
        if (typeShipping == 'shipping_as_email') {
            jQuery(voucher_recipient_email).parent('.voucher-template-input').show();
            voucherTemplate.find('.wrap-shipping-info-voucher').hide();
            voucherTemplate.find('.order-info-shipping').hide();
        } else {
            jQuery(voucher_recipient_email).parent('.voucher-template-input').hide();
            voucherTemplate.find('.wrap-shipping-info-voucher').show();
            voucherTemplate.find('.order-info-shipping').css('display', 'flex');
            voucherTemplate.find('.wrap-shipping-info-voucher .shipping-method').find('label:nth-child(1) input[name=shipping_method]').prop("checked", true);
            var priceShipping = voucherTemplate.find('input[name=shipping_method]:checked').closest('label').data('value');
            voucherTemplate.find('.currency-price-shipping').html(priceShipping);
            totalPrice = parseFloat(priceShipping) + parseFloat(totalPrice);
        }
        voucherTemplate.find('.price-voucher-total .price-total').html(totalPrice); // add total price
        return false;
    });

    function changeStepVoucher(step) {
        voucherTemplate.find('#setup-voucher-template').addClass('loading');
        var dataType = voucherTemplate.find('.format-category-voucher .layout-type.active').data('type'); // template lanscape or portail
        var typeShipping = voucherTemplate.find('.choose-shipping-template .shipping-type.active').data('type'); // check shipping email/post
        if (typeof typeShipping == 'undefined') {
            typeShipping = 'shipping_as_email';
        }
        voucherTemplate.find('.giftvoucher-step').removeClass('active').removeClass('passed'); // remove active in step
        voucherTemplate.find('.giftvoucher-step .step-group').removeClass('enable_click').addClass('disable_click');
        voucherTemplate.find('.wrapper-infomation-voucher-template').hide();
        //show slider
        if (step == 1) {
            voucherTemplate.find('.voucher-content-step').addClass('loading');
            voucherTemplate.find('.voucher-content-step').hide(); //hide slider;
            voucherTemplate.find('#select-temp').parent('.giftvoucher-step').addClass('active');
            voucherTemplate.find('#select-temp').removeClass('disable_click').addClass('enable_click');
            voucherTemplate.find('.wrap-format-category-voucher').css('display', 'flex');; //hide format slider;
            voucherTemplate.find('#slider-giftvoucher').show();
            voucherTemplate.find('#voucher-continue-step').removeClass('show'); //hide prev-next-step ;
            voucherTemplate.find('.progress .progress-bar').css('width', '25%'); //add width progress
            sliderVoucherTemplate(number_slider);
            //remove canvas
            showTemplateGiftCard('', '', '', '', '', '', '', '', '', '', '', '', step)
            setTimeout(function () {
                voucherTemplate.find('.voucher-content-step').removeClass('loading');
            }, 1000);
        } else if (step == 2) {
            //show select template voucher
            voucherTemplate.find('#select-temp').parent('.giftvoucher-step').addClass('passed');
            voucherTemplate.find('#select-temp').removeClass('disable_click').addClass('enable_click');
            voucherTemplate.find('#select-per').parent('.giftvoucher-step').addClass('active');
            voucherTemplate.find('#select-per').removeClass('disable_click').addClass('enable_click');
            voucherTemplate.find('.wrap-setup-voucher-template').addClass('template-voucher-' + dataType);
            voucherTemplate.find('.wrap-format-category-voucher').hide(); //hide format slider;
            voucherTemplate.find('.voucher-content-step').hide(); //hide slider;
            voucherTemplate.find('#setup-voucher-template').show(); //show step 2;
            voucherTemplate.find('#voucher-continue-step').addClass('show'); //show prev-next-step ;
            voucherTemplate.find('.progress .progress-bar').css('width', '50%'); //add width progress
            voucherTemplate.find('#content-setup-voucher-template').show(); //show Setup your gift card
            // add count step
            voucherTemplate.find('#voucher-continue-step').find('.voucher-prev-step').data('prev-step', parseInt(step - 1));
            voucherTemplate.find('#voucher-continue-step').find('.voucher-next-step').data('next-step', parseInt(step + 1));
            voucherTemplate.find('.voucher-preview-pdf').hide(); // hide button preview
            voucherTemplate.find('#payment-voucher-template').hide(); // hide button payment
            voucherTemplate.find('.voucher-next-step').show(); // show button payment
            voucherTemplate.find('#dataVoucher').val('');
        } else if (step == 3) {
            //show step set up gift voucher
            var orderDetails = voucherTemplate.find('.order-voucher-details');
            voucherTemplate.find('#setup-shopping-payment-wrap').show().append(orderDetails); //show Setup your gift card
            voucherTemplate.find('#select-temp').parent('.giftvoucher-step').addClass('passed');
            voucherTemplate.find('#select-temp').removeClass('disable_click').addClass('enable_click');
            voucherTemplate.find('#select-per').parent('.giftvoucher-step').addClass('passed');
            voucherTemplate.find('#select-per').removeClass('disable_click').addClass('enable_click');
            voucherTemplate.find('#select-payment').parent('.giftvoucher-step').addClass('active');
            voucherTemplate.find('#select-payment').removeClass('disable_click').addClass('enable_click');
            voucherTemplate.find('.progress .progress-bar').css('width', '75%'); //add width progressvar
            // add count step
            voucherTemplate.find('#voucher-continue-step').find('.voucher-prev-step').data('prev-step', parseInt(step - 1));
            voucherTemplate.find('#voucher-continue-step').find('.voucher-next-step').data('next-step', parseInt(step + 1));
            voucherTemplate.find('.order-detail-voucher-template .price-voucher .currency-price-value').html(voucher_price_value.val());
            voucherTemplate.find('.order-detail-voucher-template .order-info-name .order-your-name').html(voucher_gift_to.val());
            //total price
            var totalPrice = parseFloat(parseFloat(voucher_price_value.val()) + parseFloat(voucher_extra_charges.val()));
            if (typeShipping == 'shipping_as_email') {
                voucherTemplate.find('.order-info-shipping').hide()
            } else {
                voucherTemplate.find('.order-info-shipping').css('display', 'flex');
                var priceShipping = voucherTemplate.find('input[name=shipping_method]:checked').closest('label').data('value');
                voucherTemplate.find('.currency-price-shipping').html(priceShipping);
                totalPrice = parseFloat(priceShipping) + parseFloat(totalPrice);
            }
            voucherTemplate.find('.voucher-preview-pdf').hide(); // hide button preview
            voucherTemplate.find('#payment-voucher-template').hide(); // hide button payment
            voucherTemplate.find('.voucher-next-step').show(); // show button payment
            voucherTemplate.find('.order-detail-voucher-template .price-voucher-total .price-total').html(totalPrice);
            voucherTemplate.find('#dataVoucher').val('');
        } else if (step == 4) {
            // show step overview and payment
            var orderDetails = voucherTemplate.find('.order-voucher-details');
            voucherTemplate.find('#order-voucher-details-overview').show().append(orderDetails); //add show section overview
            voucherTemplate.find('#select-temp').parent('.giftvoucher-step').addClass('passed');
            voucherTemplate.find('#select-temp').removeClass('disable_click').addClass('enable_click');
            voucherTemplate.find('#select-per').parent('.giftvoucher-step').addClass('passed');
            voucherTemplate.find('#select-per').removeClass('disable_click').addClass('enable_click');
            voucherTemplate.find('#select-payment').parent('.giftvoucher-step').addClass('passed');
            voucherTemplate.find('#select-payment').removeClass('disable_click').addClass('enable_click');
            voucherTemplate.find('#select-overview').parent('.giftvoucher-step').addClass('active');
            voucherTemplate.find('#select-overview').removeClass('disable_click').addClass('enable_click');
            voucherTemplate.find('.progress .progress-bar').css('width', '100%'); //add width progress
            voucherTemplate.find('#voucher-continue-step').find('.voucher-prev-step').data('prev-step', parseInt(step - 1));
            voucherTemplate.find('.voucher-preview-pdf').show(); // show button preview
            voucherTemplate.find('#payment-voucher-template').show(); // show button payment
            voucherTemplate.find('.voucher-next-step').hide(); // hide button payment
            voucherTemplate.find('.value-price-voucher .price').html(voucher_price_value.val());
            voucherTemplate.find('.value-you-email .email').html(voucher_your_email.val());
            voucherTemplate.find('.value-payment-method-voucher .payment-method-voucher').html(payment_gateway.find('option:selected').text());
            var dataString = wpgv_formdata();
            //check show/hide data overview
            //check show/hide data overview
            if (typeShipping == 'shipping_as_email') {
                voucherTemplate.find('.order-voucher-recipient-email').css('display', 'flex');
                voucherTemplate.find('.order-voucher-full-name').hide();
                voucherTemplate.find('.order-voucher-address').hide();
                voucherTemplate.find('.order-voucher-city').hide();
                voucherTemplate.find('.order-voucher-postcode').hide();
                voucherTemplate.find('.value-shipping-voucher').html(frontend_ajax_object.via_email);
                voucherTemplate.find('.value-recipient-email .recipient-email').html(voucher_recipient_email.val());
                dataString = dataString + '&type_shipping=' + wpgv_b64EncodeUnicode(typeShipping) + '&nameShipping=' + wpgv_b64EncodeUnicode(frontend_ajax_object.via_email) + '&shippingMethod=' + wpgv_b64EncodeUnicode(voucherTemplate.find('input[name=shipping_method]:checked').val()) + '&pay_method=' + wpgv_b64EncodeUnicode(payment_gateway.val()) + '&recipientEmail=' + wpgv_b64EncodeUnicode(voucher_recipient_email.val())
            } else {
                voucherTemplate.find('.order-voucher-recipient-email').hide();
                voucherTemplate.find('.order-voucher-full-name').css('display', 'flex');
                voucherTemplate.find('.order-voucher-full-name .value-full-name .full-name').html(voucher_shipping_first.val() + ' ' + voucher_shipping_last.val());
                voucherTemplate.find('.order-voucher-address .value-address-voucher .address-voucher').html(voucher_shipping_address.val());
                voucherTemplate.find('.order-voucher-city .value-city-voucher .city-voucher').html(voucher_shipping_city.val());
                if (!voucher_shipping_postcode.val()) {
                    voucherTemplate.find('.order-voucher-postcode').css('display', 'flex');
                    voucherTemplate.find('.order-voucher-full-name .value-full-name .full-name').html(voucher_shipping_postcode.val());
                } else {
                    voucherTemplate.find('.order-voucher-postcode').hide();
                }
                voucherTemplate.find('.order-voucher-address').css('display', 'flex');
                voucherTemplate.find('.order-voucher-city').css('display', 'flex');
                voucherTemplate.find('.value-shipping-voucher').html(frontend_ajax_object.via_post);
                dataString = dataString + '&type_shipping=' + wpgv_b64EncodeUnicode(typeShipping) + '&nameShipping=' + wpgv_b64EncodeUnicode(frontend_ajax_object.via_post) + '&pay_method=' + wpgv_b64EncodeUnicode(payment_gateway.val()) + '&fisrtName=' + wpgv_b64EncodeUnicode(voucher_shipping_first.val()) + '&lastName=' + wpgv_b64EncodeUnicode(voucher_shipping_last.val()) + '&address=' + wpgv_b64EncodeUnicode(voucher_shipping_address.val()) + '&city=' + wpgv_b64EncodeUnicode(voucher_shipping_city.val()) + '&postcode=' + wpgv_b64EncodeUnicode(voucher_shipping_postcode.val()) + '&shippingMethod=' + wpgv_b64EncodeUnicode(voucherTemplate.find('input[name=shipping_method]:checked').val())
            }
            voucherTemplate.find('#dataVoucher').val(dataString); // get data voucher setup
        }
        jQuery('#voucher-template-name-step').find('.choose-show-title').html(voucherTemplate.find('.giftvoucher-step.active .step-label').text());
        jQuery('#voucher-template-name-step').find('.number-step').html(step);
        setTimeout(function () {
            voucherTemplate.find('#setup-voucher-template').removeClass('loading');
        }, 1000);
    }

    // change shipping method
    voucherTemplate.find('input[name="shipping_method"]').change(function () {
        var shippingPrice = jQuery(this).closest('label').data('value');
        voucherTemplate.find('input[name="shipping_method"]').prop("checked", false);
        jQuery(this).prop("checked", true);
        var totalPrice = parseFloat(parseFloat(voucher_price_value.val()) + parseFloat(voucher_extra_charges.val()));
        totalPrice = parseFloat(shippingPrice) + totalPrice;
        voucherTemplate.find('.order-detail-voucher-template .price-voucher-total .price-total').html(totalPrice);
        voucherTemplate.find('.order-detail-voucher-template .price-voucher-shipping .currency-price-shipping').html(shippingPrice);
        return false;
    });
    // function validate form voucher
    function wpgv_validateitemform($step) {
        $status = 0;
        if ($step == '3') {
            if (voucher_price_value.val() && voucher_price_value.val() != 0) {
                $status = 1;
                voucher_price_value.closest('.voucher-template-input').find('.error-input').hide();
            } else {
                $status = 0;
                voucher_price_value.closest('.voucher-template-input').find('.error-input').show();
            }
            if (voucher_gift_to.val()) {
                //$status = 1;
                voucher_gift_to.closest('.voucher-template-input').find('.error-input').hide();
            } else {
                $status = 0;
                voucher_gift_to.closest('.voucher-template-input').find('.error-input').show();
            }
            if (voucher_gift_from.val()) {
                //$status = 1;
                voucher_gift_from.closest('.voucher-template-input').find('.error-input').hide();
            } else {
                $status = 0;
                voucher_gift_from.closest('.voucher-template-input').find('.error-input').show();
            }
        } else if ($step == '4') {
            //check validate
            var typeShipping = voucherTemplate.find('.choose-shipping-template .shipping-type.active').data('type');
            if (typeof typeShipping == 'undefined') {
                typeShipping = 'shipping_as_email';
            }
            if (typeShipping == 'shipping_as_email') {
                if (voucher_your_email.val() && wpgv_validateEmail(voucher_your_email.val())) {
                    $status = 1;
                    voucher_your_email.closest('.voucher-template-input').find('.error-input').hide();
                } else {
                    $status = 0;
                    voucher_your_email.closest('.voucher-template-input').find('.error-input').show();
                }
                if (voucher_recipient_email.val() && wpgv_validateEmail(voucher_recipient_email.val())) {
                    voucher_recipient_email.closest('.voucher-template-input').find('.error-input').hide();
                } else {
                    $status = 0;
                    voucher_recipient_email.closest('.voucher-template-input').find('.error-input').show();
                }
            } else if (typeShipping == 'shipping_as_post') {
                if (voucher_your_email.val() && wpgv_validateEmail(voucher_your_email.val())) {
                    $status = 1;
                    voucher_your_email.closest('.voucher-template-input').find('.error-input').hide();
                } else {
                    $status = 0;
                    voucher_your_email.closest('.voucher-template-input').find('.error-input').show();
                }
                if (voucher_shipping_first.val()) {
                    voucher_shipping_first.closest('.voucher-template-input').find('.error-input').hide();
                } else {
                    $status = 0;
                    voucher_shipping_first.closest('.voucher-template-input').find('.error-input').show();
                }
                if (voucher_shipping_last.val()) {
                    voucher_shipping_last.closest('.voucher-template-input').find('.error-input').hide();
                } else {
                    $status = 0;
                    voucher_shipping_last.closest('.voucher-template-input').find('.error-input').show();
                }
                if (voucher_shipping_address.val()) {
                    voucher_shipping_address.closest('.voucher-template-input').find('.error-input').hide();
                } else {
                    $status = 0;
                    voucher_shipping_address.closest('.voucher-template-input').find('.error-input').show();
                }
                if (voucher_shipping_city.val()) {
                    voucher_shipping_city.closest('.voucher-template-input').find('.error-input').hide();
                } else {
                    $status = 0;
                    voucher_shipping_city.closest('.voucher-template-input').find('.error-input').show();
                }
            }
            if (!$('input[name=acceptVoucherTerms]').is(':checked')) {
                alert(frontend_ajax_object.accept_terms);
                return false;
            }
        }
        return $status;
    }
    // function validate email
    function wpgv_validateEmail($email) {
        var reg = /^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/;

        if (reg.test($email) == false) {
            return false;
        }
        return true;
    }
    //check messege
    jQuery('#giftvoucher-template #voucher_description').on('keydown', function (e) {
        newLines = jQuery(this).val().split("\n").length;
        jQuery('#giftvoucher-template .voucher-template-input .maxchar').html(frontend_ajax_object.total_character + ": " + (this.value.length));
        if ((e.keyCode == 13 && newLines >= 3) || (e.keyCode != 8 && this.value.length > 250)) {
            return false;
        }
    });
    //check length gift from
    jQuery('#giftvoucher-template #voucher_gift_from').on('keydown', function (e) {
        if (e.keyCode != 8 && this.value.length > 15 && e.keyCode != 46) {
            return false;
        }
    });
    //check length gift to
    jQuery('#giftvoucher-template #voucher_gift_to').on('keydown', function (e) {
        if (e.keyCode != 8 && this.value.length > 15 && e.keyCode != 46) {
            return false;
        }
    });
    //check length gift to
    jQuery('#giftvoucher-template #voucher_price_value').on('keydown', function (e) {
        if (e.keyCode != 8 && this.value.length > 5 && e.keyCode != 46 && parseFloat(this.value) != 0) {
            return false;
        }
    });
    // function data giftvoucher-template
    function wpgv_formdata() {
        var idVoucher = wpgv_b64EncodeUnicode(voucher_template_id.val()),
            priceExtraCharges = wpgv_b64EncodeUnicode(parseFloat(voucher_extra_charges.val())),
            couponcode = wpgv_b64EncodeUnicode(parseFloat(voucher_couponcode.val())),
            priceVoucher = wpgv_b64EncodeUnicode(parseFloat(voucher_price_value.val())),
            giftTo = wpgv_b64EncodeUnicode(voucher_gift_to.val()),
            giftFrom = wpgv_b64EncodeUnicode(voucher_gift_from.val());
        giftDescription = wpgv_b64EncodeUnicode(voucher_description.val());
        giftEmail = wpgv_b64EncodeUnicode(voucher_your_email.val());
        typeGiftCard = wpgv_b64EncodeUnicode(voucherTemplate.find('.format-category-voucher .active').data('type'));
        return '&idVoucher=' + idVoucher + '&priceExtraCharges=' + priceExtraCharges + '&priceVoucher=' + priceVoucher + '&giftTo=' + giftTo + '&giftFrom=' + giftFrom + '&giftDescription=' + giftDescription + '&giftEmail=' + giftEmail + '&couponcode=' + couponcode + '&typeGiftCard=' + typeGiftCard;
    }

    function wpgv_b64EncodeUnicode(str) {
        return btoa(encodeURIComponent(str).replace(/%([0-9A-F]{2})/g, function (match, p1) {
            return String.fromCharCode(parseInt(p1, 16))
        }))
    }
    // function click nav step
    jQuery(document).on('click', '#giftvoucher-template .giftvoucher-step.passed', function (event) {
        var step = $(this).find('.step-group').data('step');
        changeStepVoucher(step);
    });
    // payment
    jQuery(document).on('click', '#payment-voucher-template', function (event) {
        var data = voucherTemplate.find('#dataVoucher').val();
        var dataURL = voucherTemplate.find('#show-preview-gift-card').attr('href');
        voucherTemplate.find('#setup-voucher-template').addClass('loading');
        $.ajax({
            url: frontend_ajax_object.ajaxurl,
            type: "POST",
            data: 'action=wpgv_save_gift_card' + data + '&urlImage=' + wpgv_b64EncodeUnicode(dataURL),
            success: function (response) {
                if (response.success) {
                    if (response.data.approve_link) {
                        window.location = response.data.approve_link;
                        voucherTemplate.find('#setup-voucher-template').removeClass('loading');
                    } else {
                        alert("No redirect link found.");
                    };
                } else {
                    alert(response.data.message);
                }
            },
            error: function () {
                alert(frontend_ajax_object.error_occur);
                voucherTemplate.find('#setup-voucher-template').removeClass('loading');
            }
        });
        return false;
    });
    var stage = null;
    var stageWidth = 0;
    var stageHeight = 0;
    var scale = 0;
    var containerWidth = 0;
    //function canvas
    function showTemplateGiftCard(json, urlImage, currency, giftto, giftfrom, date_label, email, website, company_name, expiryDate, notice, counpon, step) {

        if (step != 1) {
            if (json != null || urlImage != null || currency != null || giftto != null || email != null || date_label != null || website != null || company_name != null || expiryDate != null || notice != null || counpon != null) {
                stage = Konva.Node.create(json, 'template_giftcard_container');
                stageWidth = stage.getAttr('width');
                stageHeight = stage.getAttr('height');
                setTimeout(function () {
                    stage.find('Image').forEach(imageNode => {
                        const nativeImage = new window.Image();
                        nativeImage.onload = () => {
                            imageNode.image(nativeImage);
                            imageNode.width(stageWidth);
                            imageNode.height(stageHeight);
                            imageNode.getLayer().batchDraw();
                        }
                        nativeImage.src = urlImage;
                    });
                    // company
                    if (typeof stage.get('#gift_title_company')[0] !== 'undefined') {
                        var company = stage.get('#gift_title_company')[0];
                        company.text(company_name);
                        company.fontFamily('Poppins');
                        company.draggable(false);
                        stage.draw();
                    }
                    // giftcard_date_gift_label
                    if (typeof stage.get('#giftcard_date_gift_label')[0] !== 'undefined') {
                        var datelabel = stage.get('#giftcard_date_gift_label')[0];
                        datelabel.text(date_label);
                        datelabel.fontFamily('Poppins');
                        datelabel.draggable(false);
                        stage.draw();
                    }
                    //date value giftcard_date_gift_input
                    if (typeof stage.get('#giftcard_date_gift_input')[0] !== 'undefined') {
                        var dateValue = stage.get('#giftcard_date_gift_input')[0];
                        dateValue.text(expiryDate);
                        dateValue.fontFamily('Poppins');
                        dateValue.draggable(false);
                        stage.draw();
                    }
                    // gift to label
                    if (typeof stage.get('#giftto_label')[0] !== 'undefined') {
                        var giftto_label = stage.get('#giftto_label')[0];
                        giftto_label.text(giftto);
                        giftto_label.fontFamily('Poppins');
                        giftto_label.draggable(false);
                        stage.draw();
                    }
                    // gift form label
                    if (typeof stage.get('#giftfrom_label')[0] !== 'undefined') {
                        var giftfrom_label = stage.get('#giftfrom_label')[0];
                        giftfrom_label.text(giftfrom);
                        giftfrom_label.fontFamily('Poppins');
                        giftfrom_label.draggable(false);
                        stage.draw();
                    }
                    // email
                    if (typeof stage.get('#giftcard_email')[0] !== 'undefined') {
                        var stageEmail = stage.get('#giftcard_email')[0];
                        stageEmail.text(email);
                        stageEmail.fontFamily('Poppins');
                        stageEmail.draggable(false);
                        stage.draw();
                    }
                    // url
                    if (typeof stage.get('#giftcard_website')[0] !== 'undefined') {
                        var stageWebsite = stage.get('#giftcard_website')[0];
                        stageWebsite.text(website);
                        stageWebsite.fontFamily('Poppins');
                        stageWebsite.draggable(false);
                        stage.draw();
                    }
                    // value gift to
                    if (typeof stage.get('#giftto_input')[0] !== 'undefined') {
                        var giftoValue = stage.get('#giftto_input')[0];
                        giftoValue.text(voucher_gift_to.val());
                        giftoValue.fontFamily('Poppins');
                        giftoValue.draggable(false);
                        stage.draw();
                    }
                    // value gift from
                    if (typeof stage.get('#giftfrom_input')[0] !== 'undefined') {
                        var giftfromValue = stage.get('#giftfrom_input')[0];
                        giftfromValue.text(voucher_gift_from.val());
                        giftfromValue.fontFamily('Poppins');
                        giftfromValue.draggable(false);
                        stage.draw();
                    }
                    // value price
                    if (typeof stage.get('#giftcard_monney')[0] !== 'undefined') {
                        var monney = stage.get('#giftcard_monney')[0];
                        if (voucher_price_value.val() > 0) {
                            if (voucherTemplate.find('#setup-shopping-payment-wrap .price-voucher.currency_right').length > 0) {
                                monney.text(voucher_price_value.val() + '' + currency);
                            } else {
                                monney.text(currency + '' + voucher_price_value.val());
                            }
                        } else {
                            monney.text('');
                        }
                        giftfromValue.fontFamily('Poppins');
                        monney.draggable(false);
                        stage.draw();
                    }
                    if (typeof stage.get('#giftcard_monney_label')[0] !== 'undefined') {
                        var monney_label = stage.get('#giftcard_monney_label')[0];
                        monney_label.text(frontend_ajax_object.text_value);
                        monney_label.fontFamily('Poppins');
                        monney_label.draggable(false);
                        stage.draw();
                    }
                    // value Desc
                    if (typeof stage.get('#giftcard_des')[0] !== 'undefined') {
                        var gift_des = stage.get('#giftcard_des')[0];
                        gift_des.text(voucher_description.val());
                        gift_des.fontFamily('Poppins');
                        gift_des.draggable(false);
                        stage.draw();
                    }
                    //counpon label
                    if (typeof stage.get('#giftcard_counpon_label')[0] !== 'undefined') {
                        var giftcard_counpon_label = stage.get('#giftcard_counpon_label')[0];
                        giftcard_counpon_label.text(counpon);
                        giftcard_counpon_label.fontFamily('Poppins');
                        giftcard_counpon_label.draggable(false);
                        stage.draw();
                    }
                    //counpon code
                    if (typeof stage.get('#giftcard_counpon')[0] !== 'undefined') {
                        var giftcard_counpon = stage.get('#giftcard_counpon')[0];
                        giftcard_counpon.text(voucher_couponcode.val());
                        giftcard_counpon.fontFamily('Poppins');
                        giftcard_counpon.draggable(false);
                        stage.draw();
                    } else {

                    }
                    //note
                    if (typeof stage.get('#giftcard_note')[0] !== 'undefined') {
                        var giftcard_note = stage.get('#giftcard_note')[0];
                        giftcard_note.text(notice);
                        giftcard_note.fontFamily('Poppins');
                        giftcard_note.draggable(false);
                        stage.draw();
                    }
                    //show img
                    var dataURL = stage.toDataURL({
                        pixelRatio: 1
                    });
                    addImagesGiftCard(dataURL);
                    // add change value to gift voucher
                    voucher_price_value.on('input blur', function () {
                        var dInput = this.value;
                        if (typeof stage.get('#giftcard_monney')[0] !== 'undefined') {
                            var node_price_value = stage.get('#giftcard_monney')[0];
                            if (voucherTemplate.find('#setup-shopping-payment-wrap .price-voucher.currency_right').length > 0) {
                                node_price_value.text(dInput + '' + currency);
                            } else {
                                node_price_value.text(currency + '' + dInput);
                            }
                            node_price_value.fontFamily('Poppins');
                            stage.draw();
                            var dataURL = stage.toDataURL({
                                pixelRatio: 1
                            });
                            addImagesGiftCard(dataURL);
                        }
                    });
                    voucher_gift_to.on('input blur', function () {
                        var dInput = this.value;
                        if (typeof stage.get('#giftto_input')[0] !== 'undefined') {
                            var node_gift_to = stage.get('#giftto_input')[0];
                            node_gift_to.text(dInput);
                            node_gift_to.fontFamily('Poppins');
                            stage.draw();
                            var dataURL = stage.toDataURL({
                                pixelRatio: 1
                            });
                            addImagesGiftCard(dataURL);
                        }
                    });
                    voucher_gift_from.on('input blur', function () {
                        var dInput = this.value;
                        if (typeof stage.get('#giftfrom_input')[0] !== 'undefined') {
                            var node_gift_from = stage.get('#giftfrom_input')[0];
                            node_gift_from.text(dInput);
                            stage.draw();
                            var dataURL = stage.toDataURL({
                                pixelRatio: 1
                            });
                            addImagesGiftCard(dataURL);
                        }
                    });
                    voucher_description.on('input blur', function () {
                        var dInput = this.value;
                        if (typeof stage.get('#giftcard_des')[0] !== 'undefined') {
                            var node_description = stage.get('#giftcard_des')[0];
                            node_description.text(dInput);
                            node_description.fontFamily('Poppins');
                            stage.draw();
                            var dataURL = stage.toDataURL({
                                pixelRatio: 1
                            });
                            addImagesGiftCard(dataURL);
                        }
                    });
                }, 100);
                setTimeout(() => {
                    stage.draw();
                }, 500);
            }
        } else {
            if (typeof stage != 'undefined') {
                stage.destroy();
            }
        }
    }
    // function resize canvas
    function fitStageIntoParentContainer() {
        if (stage != null) {
            var container = document.querySelector('#template_giftcard_container');
            // now we need to fit stage into parent
            var containerWidth = container.offsetWidth;
            // to do this we need to scale the stage
            var scale = containerWidth / stageWidth;
            stage.width(stageWidth * scale);
            stage.height(stageHeight * scale);
            stage.scale({
                x: scale,
                y: scale
            });
            stage.draw();
        }
    }
    fitStageIntoParentContainer();
    // adapt the stage on any window resize
    window.addEventListener('resize', fitStageIntoParentContainer);
    jQuery(document).on('click', '#giftvoucher-template', function (event) {
        if (stage != null) {
            fitStageIntoParentContainer();
            var dataURL = stage.toDataURL({
                pixelRatio: 1.5
            });
            addImagesGiftCard(dataURL);
        }
    });
    // function show preview giftCard
    if (voucherTemplate.find('#voucher-preview-pdf').length) {
        document.getElementById('voucher-preview-pdf').addEventListener(
            'click',
            function () {
                //show add stage preview pdf
                var preview = new Konva.Text({
                    x: 20,
                    y: stageHeight / 2 + 30,
                    text: frontend_ajax_object.preview,
                    fontSize: 55,
                    fontFamily: 'Calibri',
                    align: 'center',
                    fill: 'red',
                    verticalAlign: 'middle',
                    fontStyle: 'bold',
                    width: stageWidth,
                    rotation: -20,
                    id: 'preview'
                });
                var layerPreview = stage.find('Layer')[0];
                layerPreview.add(preview);
                //stage.add(preview);
                if (typeof stage.get('#giftcard_counpon')[0] !== 'undefined') {
                    var giftcard_counpon = stage.get('#giftcard_counpon')[0];
                    giftcard_counpon.text('XXXXXXXX');
                    stage.draw();
                }
                var dataURL = stage.toDataURL({
                    pixelRatio: 1
                });
                var voucherType = voucherTemplate.find('.format-category-voucher .active').data('type');
                if (voucherType == 'landscape') {
                    var pdf = new jsPDF('l', 'pt', 'letter');
                } else {
                    var pdf = new jsPDF('p', 'pt', 'letter');
                }
                const pageWidth = pdf.internal.pageSize.getWidth();
                const pageHeight = pdf.internal.pageSize.getHeight();
                const widthRatio = pageWidth / stage.width();
                const heightRatio = pageHeight / stage.height();
                const ratio = widthRatio > heightRatio ? heightRatio : widthRatio;
                const canvasWidth = stage.width() * ratio;
                const canvasHeight = stage.height() * ratio;
                const marginX = (pageWidth - canvasWidth) / 2;
                const marginY = (pageHeight - canvasHeight) / 2;
                pdf.addImage(stage.toDataURL({
                    pixelRatio: 1,
                }), 'PNG', marginX, marginY, canvasWidth, canvasHeight);
                pdf.output('dataurlnewwindow');
                pdf.output('save', 'preview.pdf');
                if (typeof stage.get('#giftcard_counpon')[0] !== 'undefined') {
                    var giftcard_counpon1 = stage.get('#giftcard_counpon')[0];
                    giftcard_counpon1.text(voucher_couponcode.val());
                    stage.draw();
                }
                if (typeof stage.get('#preview')[0] !== 'undefined') {
                    var textPreview = stage.get('#preview')[0];
                    textPreview.text('');
                    stage.draw();
                }
                var dataURL = stage.toDataURL({
                    pixelRatio: 1.5
                });
                addImagesGiftCard(dataURL);
                jQuery('#show-preview-gift-card').trigger('click');
            },
            false
        );
    }
    // funtion resize giftcard
    function scrollTopGiftCard() {
        document.getElementById('giftvoucher-template').scrollIntoView();
    }
    //function set image
    function addImagesGiftCard(dataURL) {
        if (jQuery('#giftvoucher-template').find('#show-preview-gift-card').length > 0) {
            jQuery('#giftvoucher-template').find('#show-preview-gift-card').attr('href', dataURL);
        } else {
            jQuery('#giftvoucher-template').append('<a id="show-preview-gift-card" href="' + dataURL + '"></a>');
        }
    }
});