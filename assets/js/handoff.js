/**
 * Order-pay hand-off (design 1d): counts down data-carbon-delay seconds, then forwards the payer
 * to the hosted Carbon page. The «ادامه در کربن» link and a <noscript> meta refresh cover the
 * cases where this does not run.
 */
(function () {
    var section = document.querySelector('.carbon-pay.cp-handoff[data-carbon-redirect]');
    if (!section) {
        return;
    }

    var url = section.getAttribute('data-carbon-redirect');
    var left = parseInt(section.getAttribute('data-carbon-delay'), 10) || 3;
    var counter = section.querySelector('[data-carbon-seconds]');
    var persian = function (n) {
        return String(n).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; });
    };

    section.classList.add('is-counting');
    var timer = window.setInterval(function () {
        left -= 1;
        if (counter) {
            counter.textContent = persian(Math.max(left, 0));
        }
        if (left <= 0) {
            window.clearInterval(timer);
            window.location.replace(url);
        }
    }, 1000);
})();
