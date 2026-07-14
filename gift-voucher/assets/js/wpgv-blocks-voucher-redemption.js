(function (window) {
    'use strict';

    var wp = window.wp || {};
    var wc = window.wc || {};
    var element = wp.element || {};
    var i18n = wp.i18n || {};
    var plugins = wp.plugins || {};
    var blocksCheckout = wc.blocksCheckout || {};
    var priceFormat = wc.priceFormat || {};
    var settings = wc.wcSettings || {};
    var integrationSettings = settings.getSetting ? settings.getSetting('gift-voucher_data', {}) : {};
    var redeemFormEnabled = integrationSettings.redeem_form_enabled !== false;

    if (!element.createElement || !element.useRef || !element.useState || !blocksCheckout.ExperimentalDiscountsMeta || !blocksCheckout.extensionCartUpdate || !plugins.registerPlugin) {
        return;
    }

    var createElement = element.createElement;
    var useRef = element.useRef;
    var useState = element.useState;
    var __ = i18n.__ || function (text) { return text; };
    var ExperimentalDiscountsMeta = blocksCheckout.ExperimentalDiscountsMeta;
    var extensionCartUpdate = blocksCheckout.extensionCartUpdate;

    function formatAppliedAmount(amount) {
        var currency = priceFormat.getCurrency ? priceFormat.getCurrency() : { minorUnit: 2 };
        var numericAmount = Number(amount);
        var minorUnit = Number(currency.minorUnit || 0);

        if (!isFinite(numericAmount)) {
            return String(amount);
        }

        if (priceFormat.formatPrice) {
            return priceFormat.formatPrice(Math.round(numericAmount * Math.pow(10, minorUnit)));
        }

        return numericAmount.toFixed(minorUnit);
    }

    function VoucherRedemption(props) {
        var extensions = props.extensions || {};
        var voucherExtension = extensions['gift-voucher'] || {};
        var appliedVouchers = Array.isArray(voucherExtension.applied_vouchers) ? voucherExtension.applied_vouchers : [];
        var inputRef = useRef(null);
        var state = useState('');
        var code = state[0];
        var setCode = state[1];
        var updatingState = useState(false);
        var isUpdating = updatingState[0];
        var setUpdating = updatingState[1];
        var errorState = useState('');
        var error = errorState[0];
        var setError = errorState[1];
        var statusState = useState('');
        var status = statusState[0];
        var setStatus = statusState[1];
        var showForm = props.formEnabled !== false;
        var errorId = 'wpgv-blocks-voucher-error';
        var statusId = 'wpgv-blocks-voucher-status';

        if (!showForm && !appliedVouchers.length) {
            return null;
        }

        function updateVoucher(action, voucherCode) {
            var trimmedCode = String(voucherCode || '').trim();

            if (!trimmedCode || isUpdating) {
                return;
            }

            setUpdating(true);
            setError('');
            setStatus(action === 'remove' ? __('Removing voucher…', 'gift-voucher') : __('Applying voucher…', 'gift-voucher'));

            extensionCartUpdate({
                namespace: 'gift-voucher',
                data: {
                    action: action,
                    code: trimmedCode,
                },
            }).then(function () {
                setCode('');
                setStatus(action === 'remove' ? __('Voucher removed.', 'gift-voucher') : __('Voucher applied.', 'gift-voucher'));
            }).catch(function () {
                setError(__('Unable to update this voucher.', 'gift-voucher'));
                setStatus('');
                if (inputRef.current) {
                    inputRef.current.focus();
                }
            }).finally(function () {
                setUpdating(false);
            });
        }

        function submit(event) {
            event.preventDefault();
            updateVoucher('apply', code);
        }

        return createElement(
            'section',
            {
                className: 'wpgv-blocks-voucher',
                'aria-busy': isUpdating ? 'true' : 'false',
            },
            createElement('h3', { className: 'wpgv-blocks-voucher__title' }, __('Gift voucher', 'gift-voucher')),
            showForm ? createElement(
                'form',
                { className: 'wpgv-blocks-voucher__form', onSubmit: submit },
                createElement('label', { htmlFor: 'wpgv-blocks-voucher-code' }, __('Voucher code', 'gift-voucher')),
                createElement(
                    'div',
                    { className: 'wpgv-blocks-voucher__controls' },
                    createElement('input', {
                        ref: inputRef,
                        id: 'wpgv-blocks-voucher-code',
                        className: 'wpgv-blocks-voucher__input',
                        type: 'text',
                        value: code,
                        onChange: function (event) { setCode(event.target.value); },
                        disabled: isUpdating,
                        autoComplete: 'off',
                        'aria-describedby': error ? errorId : statusId,
                    }),
                    createElement(
                        'button',
                        {
                            type: 'submit',
                            className: 'wp-element-button wpgv-blocks-voucher__apply',
                            disabled: isUpdating || !code.trim(),
                        },
                        isUpdating ? __('Updating…', 'gift-voucher') : __('Apply voucher', 'gift-voucher')
                    )
                )
            ) : null,
            error ? createElement('p', { id: errorId, className: 'wpgv-blocks-voucher__error', role: 'alert' }, error) : null,
            status ? createElement('p', { id: statusId, className: 'wpgv-blocks-voucher__status', 'aria-live': 'polite' }, status) : null,
            appliedVouchers.length ? createElement(
                'ul',
                { className: 'wpgv-blocks-voucher__list', 'aria-label': __('Applied vouchers', 'gift-voucher') },
                appliedVouchers.map(function (voucher) {
                    var voucherCode = String(voucher.code || '');
                    return createElement(
                        'li',
                        { className: 'wpgv-blocks-voucher__item', key: voucherCode },
                        createElement('span', { className: 'wpgv-blocks-voucher__code' }, voucherCode),
                        createElement('span', { className: 'wpgv-blocks-voucher__amount' }, formatAppliedAmount(voucher.amount)),
                        createElement(
                            'button',
                            {
                                type: 'button',
                                className: 'wpgv-blocks-voucher__remove',
                                onClick: function () { updateVoucher('remove', voucherCode); },
                                disabled: isUpdating,
                                'aria-label': __('Remove voucher %s', 'gift-voucher').replace('%s', voucherCode),
                            },
                            __('Remove', 'gift-voucher')
                        )
                    );
                })
            ) : null
        );
    }

    plugins.registerPlugin('wpgv-blocks-voucher-redemption', {
        // WooCommerce routes SlotFill plugins through this scope for both Cart
        // and Checkout block contexts.
        scope: 'woocommerce-checkout',
        render: function () {
            return createElement(
                ExperimentalDiscountsMeta,
                null,
                createElement(VoucherRedemption, { formEnabled: redeemFormEnabled })
            );
        },
    });
}(window));
