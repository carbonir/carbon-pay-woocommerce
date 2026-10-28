/**
 * Block checkout payment method (design 2a). Mirrors templates/checkout/label.php and
 * templates/partials/power-sources.php.
 * Label: mark · «کربن» | «پرداخت با قدرت خرید». Content: purchasing-power chips, secure-page note.
 * The mobile and national code are contact fields (src/Blocks/CheckoutFields.php), not part of this.
 */
(function (root, factory) {
    var api = factory();
    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    } else if (root.wc && root.wc.wcBlocksRegistry) {
        api.register(root);
    }
})(typeof self !== 'undefined' ? self : this, function () {
    var NAME = 'WC_Carbon_Pay';

    var SOURCES = [
        ['ph-wallet', 'پول نقد'],
        ['ph-coins', 'طلا و نقره'],
        ['ph-currency-circle-dollar', 'رمزارز'],
        ['ph-hand-coins', 'اعتبار و تسهیلات']
    ];

    function buildPaymentMethod(win) {
        var el = win.wp.element.createElement;
        var decode = win.wp.htmlEntities.decodeEntities;
        var settings = win.wc.wcSettings.getSetting(NAME + '_data', {});
        var title = decode(settings.title || 'کربن — پرداخت با قدرت خرید');

        function icon(classes) {
            return el('i', { className: classes, 'aria-hidden': 'true' });
        }

        function Label() {
            return el(
                'span',
                { className: 'carbon-pay cp-option-label' },
                el('span', { className: 'cp-mark', 'aria-hidden': 'true' }),
                el('b', { className: 'cp-option-label__brand' }, 'کربن'),
                el('span', { className: 'cp-option-label__sep', 'aria-hidden': 'true' }),
                el('span', { className: 'cp-option-label__text' }, 'پرداخت با قدرت خرید')
            );
        }

        function Content() {
            return el(
                'div',
                { className: 'carbon-pay cp-checkout cp-checkout--blocks' },
                el(
                    'div',
                    { className: 'cp-power' },
                    el('span', { className: 'cp-power__label', id: 'cp-power-label' }, 'قدرت خرید شما در کربن'),
                    el(
                        'ul',
                        { className: 'cp-chips', 'aria-labelledby': 'cp-power-label' },
                        SOURCES.map(function (source) {
                            return el('li', { key: source[0], className: 'cp-chip' }, icon('ph ' + source[0]), source[1]);
                        })
                    )
                ),
                el(
                    'p',
                    { className: 'cp-secure-note' },
                    icon('ph-fill ph-lock-simple'),
                    el('span', null, 'پس از ثبت سفارش، پرداخت را در صفحه امن کربن کامل می‌کنید.')
                )
            );
        }

        return {
            name: NAME,
            label: el(Label, null),
            content: el(Content, null),
            edit: el(Content, null),
            canMakePayment: function () {
                return true;
            },
            ariaLabel: title,
            placeOrderButtonLabel: decode(settings.button_label || 'پرداخت با کربن'),
            supports: {
                features: Array.isArray(settings.supports) ? settings.supports : ['products']
            }
        };
    }

    return {
        NAME: NAME,
        SOURCES: SOURCES,
        buildPaymentMethod: buildPaymentMethod,
        register: function (win) {
            win.wc.wcBlocksRegistry.registerPaymentMethod(buildPaymentMethod(win));
        }
    };
});
