jQuery(document).ready(function ($) {
    var $itemform = $('#wpgv-giftitems'),
        $cataccording = $('.wpgv-according-category'),
        $itemswrap = $('.wpgv-items-wrap'),
        $step1 = $('#wpgv-giftitems-step1'),
        $step2 = $('#wpgv-giftitems-step2'),
        $step3 = $('#wpgv-giftitems-step3'),
        $step4 = $('#wpgv-giftitems-step4'),
        $wpgv_category_id = $('#category_id'),
        $wpgv_item_id = $('#item_id'),
        $chooseStyle = $('#chooseStyle'),
        $wpgv_total_price = $('#total_price'),
        $website_commission_price = $('#website_commission_price'),
        $buying_for = $('#buying_for'),
        $your_name = $('#your_name'),
        $recipient_name = $('#recipient_name'),
        $message = $('#message'),
        $shipping = $('#shipping'),
        $shipping_email = $('#shipping_email'),
        $receipt_email = $('#receipt_email'),
        $post_firstname = $('#post_firstname'),
        $post_lastname = $('#post_lastname'),
        $post_address = $('#post_address'),
        $post_code = $('#post_code'),
        $shipping_method = $('#shipping_method'),
        $payemnt_gateway = $('#payemnt_gateway'),
        $autoyourname = $('#autoyourname'),
        $shippingbox = $('.order_details_preview .wpgv_shipping_box'),
        $totalbox = $('.order_details_preview .wpgv_total_box'),
        $itempricespan = $("#itemprice span"),
        $shippingpricespan = $("#shippingprice span"),
        $paynowbtnspan = $("#paynowbtn span"),
        $totalpricespan = $("#totalprice span");

    $('.wpgv-according-category:not(:first-child)').addClass('catclose');
    $('.wpgv-giftitem-wrapper .wpgv_preview-box:not(:nth-child(2))').addClass('mailhidden');
    $('.wpgv-according-category:not(:first-child) .wpgv-items').slideUp();
    $('.wpgv-according-title').click(function () {
        var $catid = $(this).data('cat-id');
        $.ajax({
            url: frontend_ajax_object.ajaxurl,
            type: "POST",
            data: "action=wpgv_doajax_get_itemcat_image&catid=" + $catid,
            success: function (data) {
                $(".wpgv-giftitemimage img").attr('src', data.image);
            }
        });
        $cataccording.addClass('catclose');
        $cataccording.find('.wpgv-items').slideUp();
        $('#itemcat' + $catid).removeClass('catclose');
        $('#itemcat' + $catid).find('.wpgv-items').slideDown();
    });
    $('.wpgv-buy button').click(function () {
        var $itemid = $(this).data('item-id')
        $catid = $(this).data('cat-id'),
            $itemprice = $(this).data('item-price');
        $item_price_pdf = $(this).data('item-price-pdf');

        $("#item_pdf_price").val($item_price_pdf);
        $wpgv_item_id.val($itemid);
        $wpgv_total_price.val($itemprice);
        $wpgv_category_id.val($catid);
        $step1.fadeOut();
        $step2.fadeIn();
        $.ajax({
            url: frontend_ajax_object.ajaxurl,
            type: "POST",
            data: "action=wpgv_doajax_get_item_data&itemid=" + $itemid,
            success: function (data) {
                $(".wpgv-gifttitle h3, .itemtitle").html(data.title);
                $(".wpgv-gifttitle span, .itemdescription").html(data.description);
                $('.voucherValueCard').val(data.price);
                $.each(data.images, function (key, value) {
                    $(".wpgvstyle" + (parseInt(key) + 1) + " .cardDiv .cardImgTop img").attr('src', value);
                });
            }
        });
    });
    $('.next-button').click(function (e) {
        var $nextwrap = $(this).data('next'),
            $result = wpgv_validateitemform($nextwrap);
        if (!$result) exit();
        $itemform.addClass('loading');
        $itemswrap.fadeOut();
        setTimeout(function () {
            $itemform.removeClass('loading');
            $('#wpgv-giftitems-' + $nextwrap).fadeIn();
        }, 500);
    });
    $('.back-button').click(function (e) {
        var $prevwrap = $(this).data('prev');
        $itemform.addClass('loading');
        $itemswrap.fadeOut();
        setTimeout(function () {
            $itemform.removeClass('loading');
            $('#wpgv-giftitems-' + $prevwrap).fadeIn();
        }, 500);
    });

    $your_name.on('input blur', function () {
        var dInput = this.value;
        $(".forNameCard").val(dInput);
    });
    $recipient_name.on('input blur', function () {
        var dInput = this.value;
        $(".fromNameCard").val(dInput);
    });
    $message.on('input blur', function () {
        var dInput = this.value;
        $(".personalMessageCard").val(dInput);
    });
    $('.buying-options div').click(function (e) {
        $('.buying-options div').removeClass('selected');
        $(this).addClass('selected');
        $buying_for.val($(this).data('value'));
        if ($(this).data('value') == 'yourself') {
            $recipient_name.closest('.wpgv-form-fields').fadeOut();
            $('.nameFormRight').css('opacity', 0);
            $shipping_email.closest('.wpgv-form-fields').addClass('mailhidden');
        } else {
            $recipient_name.closest('.wpgv-form-fields').fadeIn();
            $('.nameFormRight').css('opacity', 1);
            $shipping_email.closest('.wpgv-form-fields').removeClass('mailhidden');
        }
    });
    if ($buying_for.val() == 'yourself') {
        $recipient_name.closest('.wpgv-form-fields').fadeOut();
        $('.nameFormRight').css('opacity', 0);
        $shipping_email.closest('.wpgv-form-fields').addClass('mailhidden');
    } else {
        $recipient_name.closest('.wpgv-form-fields').fadeIn();
        $('.nameFormRight').css('opacity', 1);
        $shipping_email.closest('.wpgv-form-fields').removeClass('mailhidden');
    }
    $('.shipping-options div').click(function (e) {
        $('.shipping-options div').removeClass('selected');
        $(this).addClass('selected');
        $shipping.val($(this).data('value'));
        var $shipping_method_wrapper = $shipping_method.closest('.wpgv-form-fields');
        var $totalprice = 0;
        if ($(this).data('value') == 'shipping_as_post') {
            $shipping_email.closest('.wpgv-form-fields').hide();
            $post_firstname.closest('.wpgv-form-fields').show();
            $receipt_email.closest('.wpgv-form-fields').show();
            $shipping_email.closest('.wpgv-form-fields').find('.error').hide();
            $receipt_email.closest('.wpgv-form-fields').find('.error').hide();
            $receipt_email.closest('.wpgv-form-fields').find('.eqlerror').hide();
            $shippingbox.css('display', 'flex');
            $totalbox.css('display', 'flex');
            $shipping_method_wrapper.show();
            $shippingpricespan.html($shipping_method_wrapper.find(':nth-child(2)').data('value'));
            $shipping_method_wrapper.find(':nth-child(2) input').prop("checked", true);
            $totalprice = (parseFloat($itempricespan.html()) + parseFloat($shippingpricespan.html()) + parseFloat($website_commission_price.data('price'))).toFixed(2);
            $totalpricespan.html($totalprice);
            $paynowbtnspan.html($totalprice);
            $wpgv_total_price.val($totalprice);
            // $('.voucherValueCard').val($totalprice);
        } else {
            $shipping_email.closest('.wpgv-form-fields').show();
            $receipt_email.closest('.wpgv-form-fields').show();
            $post_firstname.closest('.wpgv-form-fields').hide();
            $shipping_email.closest('.wpgv-form-fields').find('.error').hide();
            $receipt_email.closest('.wpgv-form-fields').find('.error').hide();
            $receipt_email.closest('.wpgv-form-fields').find('.eqlerror').hide();
            $shipping_method_wrapper.hide();
            $totalprice = (parseFloat($itempricespan.html()) + parseFloat($website_commission_price.data('price')));
            $shippingbox.hide();
            // $totalbox.hide();
            $totalpricespan.html($totalprice);
            $paynowbtnspan.html($totalprice);
            $wpgv_total_price.val($totalprice);
            // $('.voucherValueCard').val($itempricespan.text());
        }
    });

    $('input[name="shipping_method"]').change(function () {
        var $shippingprice = $(this).closest('label').data('value');
        var $totalprice = (parseFloat($itempricespan.html()) + parseFloat($shippingprice) + parseFloat($website_commission_price.data('price'))).toFixed(2);
        $shippingpricespan.html($shippingprice);
        $totalpricespan.html($totalprice);
        $paynowbtnspan.html($totalprice);
        $wpgv_total_price.val($totalprice);
        // $('.voucherValueCard').val($totalprice);
    });

    $chooseStyle.on('change', function () {
        $('.wpgv_preview-box').addClass('mailhidden');
        $itemform.addClass('loading');
        setTimeout(function () {
            $itemform.removeClass('loading');
        }, 1000);
        $('.wpgvstyle' + (parseInt($(this).val()) + 1)).removeClass('mailhidden');
    });

    $('#itempreview').click(function () {
        var $url = $(this).data('url'),
            $urlstring = wpgv_formdata();
        window.open($url + $urlstring, '_blank');
    });

    $('#paynowbtn').click(function () {
        var nonce = $('input[name=wpgv_giftitems_form_verify]').val();
        var $error = 0,
            $datastring = '',
            $url = $(this).data('url'),
            $urlstring = wpgv_formdata();
        if ($shipping.val() == 'shipping_as_email') {
            if ($buying_for.val() == 'someone_else' && !($shipping_email.val() && wpgv_validateEmail($shipping_email.val()))) {
                $error = 1;
                $shipping_email.closest('.wpgv-form-fields').find('.error').show();
            } else {
                $shipping_email.closest('.wpgv-form-fields').find('.error').hide();
            }

            if (!($receipt_email.val() && wpgv_validateEmail($receipt_email.val()))) {
                $error = 1;
                $receipt_email.closest('.wpgv-form-fields').find('.error').show();
            } else {
                $receipt_email.closest('.wpgv-form-fields').find('.error').hide();
            }
            $datastring = $url + $urlstring + '&shipping=' + wpgv_b64EncodeUnicode($shipping.val()) + '&shipping_email=' + wpgv_b64EncodeUnicode($shipping_email.val()) + '&receipt_email=' + wpgv_b64EncodeUnicode($receipt_email.val()) + '&paymentmethod=' + wpgv_b64EncodeUnicode($payemnt_gateway.val());
        } else if ($shipping.val() == 'shipping_as_post') {
            if (!($receipt_email.val() && wpgv_validateEmail($receipt_email.val()))) {
                $error = 1;
                $receipt_email.closest('.wpgv-form-fields').find('.error').show();
            } else {
                $receipt_email.closest('.wpgv-form-fields').find('.error').hide();
                $receipt_email.closest('.wpgv-form-fields').find('.eqlerror').hide();
            }
            $datastring = $url + $urlstring + '&shipping=' + wpgv_b64EncodeUnicode($shipping.val()) + '&receipt_email=' + wpgv_b64EncodeUnicode($receipt_email.val()) + '&firstname=' + wpgv_b64EncodeUnicode($post_firstname.val()) + '&lastname=' + wpgv_b64EncodeUnicode($post_lastname.val()) + '&address=' + wpgv_b64EncodeUnicode($post_address.val()) + '&pincode=' + wpgv_b64EncodeUnicode($post_code.val()) + '&shipping_method=' + wpgv_b64EncodeUnicode($('input[name=shipping_method]:checked').val()) + '&paymentmethod=' + wpgv_b64EncodeUnicode($payemnt_gateway.val());
        }

        if (nonce) {
            $datastring += '&nonce=' + encodeURIComponent(nonce);
        }

        if (!$('input[name=acceptVoucherTerms]').is(':checked')) {
            alert(frontend_ajax_object.accept_terms);
            return false;
        }
        if (!$error) {
            $.ajax({
                url: frontend_ajax_object.ajaxurl,
                type: "POST",
                data: $datastring,
                success: function (response) {
                    if (response.success) {
                        if (response.data.approve_link) {
                            window.location = response.data.approve_link;
                        } else {
                            alert("No redirect link found.");
                        };
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function () {
                    alert(frontend_ajax_object.error_occur);
                }
            });
        } else {
            alert(frontend_ajax_object.checkemail);
        }
    });

    $(document).ajaxStart(function () { $itemform.addClass('loading'); })
        .ajaxStop(function () { $itemform.removeClass('loading'); });

    function wpgv_validateitemform($step) {
        $status = 0;
        if ($step == 'step3') {
            if ($buying_for.val() == 'yourself') {
                if ($your_name.val()) {
                    $status = 1;
                    $your_name.closest('.wpgv-form-fields').find('.error').hide();
                    $post_firstname.val($your_name.val());
                    $autoyourname.html($your_name.val());
                } else {
                    $status = 0;
                    $your_name.closest('.wpgv-form-fields').find('.error').show();
                }
            } else {
                if ($your_name.val()) {
                    $status = 1;
                    $your_name.closest('.wpgv-form-fields').find('.error').hide();
                    $post_firstname.val($your_name.val());
                    $autoyourname.html($your_name.val());
                } else {
                    $status = 0;
                    $your_name.closest('.wpgv-form-fields').find('.error').show();
                }
                if ($recipient_name.val()) {
                    $recipient_name.closest('.wpgv-form-fields').find('.error').hide();
                } else {
                    $status = 0;
                    $recipient_name.closest('.wpgv-form-fields').find('.error').show();
                }
            }
            if ($message.val().length > 250) {
                $status = 0;
                $message.closest('.wpgv-form-fields').find('.error').show();
            } else {
                $message.closest('.wpgv-form-fields').find('.error').hide();
            }
            if ($status) {
                var $itemid = $wpgv_item_id.val();
                $.ajax({
                    url: frontend_ajax_object.ajaxurl,
                    type: "POST",
                    data: "action=wpgv_doajax_get_item_data&itemid=" + $itemid,
                    success: function (data) {
                        $(".wpgv-itemtitle").html(data.title);
                        var $price = (data.special_price) ? data.special_price : data.price;
                        $itempricespan.html((parseFloat($price).toFixed(2)));
                        var $totalprice = (parseFloat($price) + parseFloat($website_commission_price.data('price'))).toFixed(2);
                        $totalpricespan.html($totalprice);
                        $paynowbtnspan.html($totalprice);
                        $wpgv_total_price.val(parseFloat(data.price).toFixed(2));
                    }
                });
            }
        }
        return $status;
    }

    function wpgv_formdata() {
        var $catid = wpgv_b64EncodeUnicode($wpgv_category_id.val()),
            $itemid = wpgv_b64EncodeUnicode($wpgv_item_id.val()),
            $style = wpgv_b64EncodeUnicode($chooseStyle.val()),
            $totalprice = wpgv_b64EncodeUnicode(parseFloat($("#item_pdf_price").val())),
            $buyingfor = wpgv_b64EncodeUnicode($buying_for.val()),
            $yourname = wpgv_b64EncodeUnicode($your_name.val()),
            $recipientname = wpgv_b64EncodeUnicode($recipient_name.val()),
            $recipientmessage = wpgv_b64EncodeUnicode($message.val());

        return '&catid=' + $catid + '&itemid=' + $itemid + '&style=' + $style + '&totalprice=' + $totalprice + '&buyingfor=' + $buyingfor + '&yourname=' + $yourname + '&recipientname=' + $recipientname + '&recipientmessage=' + $recipientmessage + '&couponcode=' + Math.floor(1000000000000000 + Math.random() * 9000000000000000);
    }

    function wpgv_b64EncodeUnicode(str) {
        return btoa(encodeURIComponent(str).replace(/%([0-9A-F]{2})/g, function (match, p1) {
            return String.fromCharCode(parseInt(p1, 16))
        }))
    }

    function wpgv_b64DecodeUnicode(str) {
        return decodeURIComponent(Array.prototype.map.call(atob(str), function (c) {
            return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2)
        }).join(''))
    }

    function wpgv_validateEmail($email) {
        var reg = /^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/;

        if (reg.test($email) == false) {
            return false;
        }
        return true;
    }

    $('#wpgv-message #message').on('keydown', function (e) {
        newLines = $(this).val().split("\n").length;
        $('.maxchar').html(frontend_ajax_object.total_character + ": " + (this.value.length));
        if ((e.keyCode == 13 && newLines >= 3) || (e.keyCode != 8 && this.value.length > 250)) {
            return false;
        }
    });
});