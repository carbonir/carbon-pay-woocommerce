import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const admin = require('../../assets/js/admin.js');

/** Minimal window: one copy button inside .carbon-pay, a clipboard and a click dispatcher. */
function fakeWindow() {
    const glyph = { className: 'ph ph-copy' };
    const live = { textContent: '' };
    const classes = new Set();
    const button = {
        getAttribute: (name) => (name === 'data-copy' ? '9f3c1a7e-52b0' : null),
        querySelector: () => glyph,
        classList: { add: (c) => classes.add(c), remove: (c) => classes.delete(c) },
        parentNode: { querySelector: () => live },
    };
    const copied = [];
    const timers = [];
    const onClick = [];
    const win = {
        document: { addEventListener: (type, fn) => { if (type === 'click') onClick.push(fn); } },
        navigator: { clipboard: { writeText: (text) => { copied.push(text); return Promise.resolve(); } } },
        setTimeout: (fn) => timers.push(fn),
    };
    const click = (target) => onClick.forEach((fn) => fn({ target }));
    return { win, button, glyph, live, classes, copied, timers, click };
}

test('copy button writes data-copy to the clipboard and confirms', async () => {
    const t = fakeWindow();
    admin.init(t.win);

    t.click({ closest: (selector) => (selector === '.carbon-pay [data-copy]' ? t.button : null) });
    await Promise.resolve();

    assert.deepEqual(t.copied, ['9f3c1a7e-52b0']);
    assert.ok(t.classes.has('is-copied'));
    assert.equal(t.glyph.className, 'ph ph-check');
    assert.equal(t.live.textContent, 'کپی شد');

    t.timers.forEach((fn) => fn());
    assert.ok(!t.classes.has('is-copied'));
    assert.equal(t.glyph.className, 'ph ph-copy');
    assert.equal(t.live.textContent, '');
});

test('clicks outside a copy button are ignored', () => {
    const t = fakeWindow();
    admin.init(t.win);

    t.click({ closest: () => null });

    assert.deepEqual(t.copied, []);
});

/** Minimal DOM node: enough of createElement/appendChild/classList for the check result. */
function node(tag) {
    const n = {
        tag, className: '', children: [], attrs: {}, _text: '',
        appendChild(child) { n.children.push(child); return child; },
        setAttribute(name, value) { n.attrs[name] = String(value); },
        removeAttribute(name) { delete n.attrs[name]; },
        getAttribute(name) { return name in n.attrs ? n.attrs[name] : null; },
        set textContent(value) { n._text = value; n.children = []; },
        get textContent() { return n._text + n.children.map((c) => c.textContent).join(' '); },
    };
    return n;
}

/** The «بررسی اتصال» block, the merchant code field and a scripted fetch. */
function checkWindow(reply) {
    const box = node('div');
    const check = node('div');
    check.setAttribute('data-url', 'https://shop.test/wp-admin/admin-ajax.php');
    check.setAttribute('data-nonce', 'n0nce');
    check.querySelector = () => box;
    const classes = new Set();
    const button = {
        disabled: false,
        closest: (selector) => (selector === '[data-carbon-check]' ? check : null),
        classList: { add: (c) => classes.add(c), remove: (c) => classes.delete(c) },
    };
    const requests = [];
    const onClick = [];
    const win = {
        URLSearchParams,
        document: {
            addEventListener: (type, fn) => { if (type === 'click') onClick.push(fn); },
            getElementById: (id) => (id === 'woocommerce_WC_Carbon_Pay_merchant_code' ? { value: 'MRC-104582' } : null),
            createElement: node,
        },
        fetch: (url, init) => { requests.push({ url, init }); return reply(); },
    };
    const target = { closest: (selector) => (selector === '.carbon-pay [data-carbon-check-run]' ? button : null) };
    const click = () => onClick.forEach((fn) => fn({ target }));
    return { win, box, button, classes, requests, click };
}

const settle = () => new Promise((resolve) => setTimeout(resolve, 0));

test('check posts the typed merchant code and shows the result', async () => {
    const t = checkWindow(() => Promise.resolve({
        json: () => ({ success: true, data: { status: 'error', title: 'IP سرور ثبت نشده است', text: 'IP خروجی سرور را ثبت کنید.' } }),
    }));
    admin.initCheck(t.win);

    t.click();
    assert.ok(t.button.disabled);
    assert.ok(t.classes.has('is-busy'));
    assert.equal(t.box.getAttribute('aria-busy'), 'true');
    await settle();

    assert.equal(t.requests[0].url, 'https://shop.test/wp-admin/admin-ajax.php');
    const sent = Object.fromEntries(t.requests[0].init.body);
    assert.deepEqual(sent, { action: 'carbon_pay_check_connection', _ajax_nonce: 'n0nce', merchant_code: 'MRC-104582' });

    const alert = t.box.children[0];
    assert.equal(alert.className, 'cp-alert cp-alert--error');
    assert.equal(alert.children[0].className, 'ph-fill ph-warning-circle');
    assert.match(alert.textContent, /IP سرور ثبت نشده است/);
    assert.ok(!t.button.disabled);
    assert.ok(!t.classes.has('is-busy'));
    assert.equal(t.box.getAttribute('aria-busy'), null);
});

test('a failed request still re-enables the button with a warning', async () => {
    const t = checkWindow(() => Promise.reject(new Error('offline')));
    admin.initCheck(t.win);

    t.click();
    await settle();

    assert.equal(t.box.children[0].className, 'cp-alert cp-alert--warning');
    assert.match(t.box.textContent, /بررسی انجام نشد/);
    assert.ok(!t.button.disabled);
});
