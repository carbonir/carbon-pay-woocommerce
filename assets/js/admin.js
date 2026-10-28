/**
 * Admin: copy buttons in the gateway settings (callback URL, server IP) and the «پرداخت کربن»
 * metabox (payment token), and «بررسی اتصال» in the gateway settings (Admin\ConnectionCheck).
 */
(function (root, factory) {
    var api = factory();
    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    } else if (root.document) {
        api.init(root);
    }
})(typeof self !== 'undefined' ? self : this, function () {
    function initCopy(win) {
        var doc = win.document;
        doc.addEventListener('click', function (event) {
            var button = event.target.closest && event.target.closest('.carbon-pay [data-copy]');
            if (!button) {
                return;
            }
            var live = button.parentNode.querySelector('[aria-live]');
            var glyph = button.querySelector('i');
            var done = function () {
                button.classList.add('is-copied');
                if (glyph) glyph.className = 'ph ph-check';
                if (live) live.textContent = 'کپی شد';
                win.setTimeout(function () {
                    button.classList.remove('is-copied');
                    if (glyph) glyph.className = 'ph ph-copy';
                    if (live) live.textContent = '';
                }, 1600);
            };
            if (win.navigator.clipboard && win.navigator.clipboard.writeText) {
                win.navigator.clipboard.writeText(button.getAttribute('data-copy')).then(done, function () {});
            }
        });
    }

    var CHECK_ICONS = {
        success: 'ph-fill ph-check-circle',
        warning: 'ph-fill ph-warning',
        error: 'ph-fill ph-warning-circle'
    };

    function renderCheck(doc, box, result) {
        var alert = doc.createElement('div');
        alert.className = 'cp-alert cp-alert--' + (CHECK_ICONS[result.status] ? result.status : 'warning');
        var icon = doc.createElement('i');
        icon.className = CHECK_ICONS[result.status] || CHECK_ICONS.warning;
        icon.setAttribute('aria-hidden', 'true');
        var body = doc.createElement('span');
        body.className = 'cp-alert__body';
        [['cp-alert__title', result.title], ['cp-alert__text', result.text]].forEach(function (part) {
            var span = doc.createElement('span');
            span.className = part[0];
            span.textContent = part[1] || '';
            body.appendChild(span);
        });
        alert.appendChild(icon);
        alert.appendChild(body);
        box.textContent = '';
        box.appendChild(alert);
    }

    function initCheck(win) {
        var doc = win.document;
        doc.addEventListener('click', function (event) {
            var button = event.target.closest && event.target.closest('.carbon-pay [data-carbon-check-run]');
            if (!button || button.disabled) {
                return;
            }
            var check = button.closest('[data-carbon-check]');
            var box = check.querySelector('.cp-check__result');
            // The merchant code as typed, so it can be checked before saving.
            var input = doc.getElementById('woocommerce_WC_Carbon_Pay_merchant_code');
            var body = new win.URLSearchParams({
                action: 'carbon_pay_check_connection',
                _ajax_nonce: check.getAttribute('data-nonce'),
                merchant_code: input ? input.value : ''
            });

            button.disabled = true;
            button.classList.add('is-busy');
            box.setAttribute('aria-busy', 'true');
            var done = function (result) {
                renderCheck(doc, box, result);
                button.disabled = false;
                button.classList.remove('is-busy');
                box.removeAttribute('aria-busy');
            };

            win.fetch(check.getAttribute('data-url'), { method: 'POST', credentials: 'same-origin', body: body })
                .then(function (response) { return response.json(); })
                .then(function (json) {
                    if (!json || !json.success) {
                        throw new Error('rejected');
                    }
                    done(json.data);
                })
                .catch(function () {
                    done({
                        status: 'warning',
                        title: 'بررسی انجام نشد',
                        text: 'پاسخی از سایت شما دریافت نشد. صفحه را تازه کنید و دوباره امتحان کنید.'
                    });
                });
        });
    }

    return {
        init: function (win) {
            initCopy(win);
            initCheck(win);
        },
        initCopy: initCopy,
        initCheck: initCheck
    };
});
