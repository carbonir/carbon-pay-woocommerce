/**
 * Result card on thank-you / view-order.
 *  - Verify pending (design 1g): counts down data-carbon-refresh-seconds, then re-runs verify by
 *    revisiting the callback URL. Stops after 30 attempts (5 minutes); «به‌روزرسانی» stays available.
 *  - Paid (design 1f): copy button for the Carbon reference.
 */
(function () {
    var persian = function (n) {
        return String(n).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; });
    };

    document.addEventListener('click', function (event) {
        var button = event.target.closest && event.target.closest('.carbon-pay.cp-status [data-copy]');
        if (!button || !navigator.clipboard) {
            return;
        }
        navigator.clipboard.writeText(button.getAttribute('data-copy')).then(function () {
            var live = button.parentNode.querySelector('[aria-live]');
            var glyph = button.querySelector('i');
            button.classList.add('is-copied');
            if (glyph) glyph.className = 'ph ph-check';
            if (live) live.textContent = 'کپی شد';
            window.setTimeout(function () {
                button.classList.remove('is-copied');
                if (glyph) glyph.className = 'ph ph-copy';
                if (live) live.textContent = '';
            }, 1600);
        }, function () {});
    });

    var block = document.querySelector('.carbon-pay.cp-status[data-carbon-refresh]');
    if (!block) {
        return;
    }

    var key = 'carbonPayRefresh:' + location.pathname;
    var attempts = 0;
    try {
        attempts = parseInt(sessionStorage.getItem(key) || '0', 10) || 0;
    } catch (e) {}

    var counter = block.querySelector('[data-carbon-countdown]');
    if (attempts >= 30) {
        if (counter) counter.parentNode.hidden = true;
        return;
    }

    var left = parseInt(block.getAttribute('data-carbon-refresh-seconds'), 10) || 10;
    var timer = window.setInterval(function () {
        left -= 1;
        if (counter) counter.textContent = persian(Math.max(left, 0));
        if (left > 0) {
            return;
        }
        window.clearInterval(timer);
        try {
            sessionStorage.setItem(key, String(attempts + 1));
        } catch (e) {}
        window.location.assign(block.getAttribute('data-carbon-refresh'));
    }, 1000);
})();
