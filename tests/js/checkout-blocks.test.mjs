import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const blocks = require('../../assets/js/checkout-blocks.js');

function fakeWindow(settings) {
    const registered = [];
    const win = {
        wp: {
            element: { createElement: (type, props, ...children) => ({ type, props: props || {}, children }) },
            htmlEntities: { decodeEntities: (s) => s.replace(/&amp;/g, '&') },
        },
        wc: {
            wcSettings: { getSetting: (name, fallback) => (name === 'WC_Carbon_Pay_data' ? settings : fallback) },
            wcBlocksRegistry: { registerPaymentMethod: (config) => registered.push(config) },
        },
    };
    return { win, registered };
}

function render(node) {
    if (node === null || node === undefined) return [];
    if (typeof node === 'string') return [node];
    if (Array.isArray(node)) return node.flatMap(render);
    if (typeof node.type === 'function') return render(node.type(node.props));
    return [node, ...node.children.flatMap(render)];
}

function text(node) {
    return render(node).filter((n) => typeof n === 'string').join('');
}

test('registers the WC_Carbon_Pay method with settings from PHP', () => {
    const { win, registered } = fakeWindow({
        title: 'کربن &amp; پرداخت',
        button_label: 'پرداخت با کربن',
        supports: ['products'],
    });

    blocks.register(win);

    assert.equal(registered.length, 1);
    const method = registered[0];
    assert.equal(method.name, 'WC_Carbon_Pay');
    assert.equal(method.ariaLabel, 'کربن & پرداخت');
    assert.equal(method.placeOrderButtonLabel, 'پرداخت با کربن');
    assert.deepEqual(method.supports.features, ['products']);
    assert.equal(method.canMakePayment(), true);
});

test('label is mark · کربن | پرداخت با قدرت خرید, whatever the title', () => {
    const { win } = fakeWindow({ title: 'anything' });
    const method = blocks.buildPaymentMethod(win);
    const nodes = render(method.label);

    assert.ok(nodes.some((n) => n.props && n.props.className === 'carbon-pay cp-option-label'));
    assert.ok(nodes.some((n) => n.props && n.props.className === 'cp-mark'));
    assert.equal(text(method.label), 'کربنپرداخت با قدرت خرید');
});

test('content lists the purchasing-power sources as chips and the secure-page note', () => {
    const { win } = fakeWindow({});
    const content = blocks.buildPaymentMethod(win).content;
    const nodes = render(content);
    const chips = nodes.filter((n) => n.props && n.props.className === 'cp-chip');

    assert.deepEqual(chips.map(text), ['پول نقد', 'طلا و نقره', 'رمزارز', 'اعتبار و تسهیلات']);
    assert.match(text(content), /قدرت خرید شما در کربن/);
    assert.match(text(content), /صفحه امن کربن/);
    assert.doesNotMatch(text(content), /=|\+/, 'No equation (design 2a).');
});

test('falls back to defaults when PHP sends nothing', () => {
    const { win } = fakeWindow({});
    const method = blocks.buildPaymentMethod(win);
    assert.deepEqual(method.supports.features, ['products']);
    assert.equal(method.placeOrderButtonLabel, 'پرداخت با کربن');
    assert.equal(method.ariaLabel, 'کربن — پرداخت با قدرت خرید');
});
