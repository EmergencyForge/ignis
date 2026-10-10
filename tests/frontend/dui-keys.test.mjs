import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const script = readFileSync(new URL('../../assets/js/dui-keys.js', import.meta.url), 'utf8');

function page({ framed = false, active = null, focusables = [] } = {}) {
    const listeners = [];
    const commands = [];
    const window = { addEventListener: (type, fn) => type === 'message' && listeners.push(fn) };
    window.top = framed ? {} : window;
    const document = {
        activeElement: active,
        execCommand: (...args) => commands.push(args),
        querySelectorAll: () => focusables,
    };
    vm.runInNewContext(script, { window, document, Event: class { constructor(type) { this.type = type; } } });
    const send = (data, source = null) => listeners.forEach((fn) => fn({ data, source }));
    return { window, commands, send, listeners };
}

const input = (extra = {}) => ({ tagName: 'INPUT', offsetParent: {}, disabled: false, ...extra });

test('types into the focused field', () => {
    const p = page({ active: input() });
    p.send({ type: 'efKey', key: 'a' });
    p.send({ type: 'efKey', key: 'Backspace' });
    assert.deepEqual(p.commands, [['insertText', false, 'a'], ['delete']]);
});

test('accepts messages from its own window, drops other senders', () => {
    const p = page({ active: input() });
    p.send({ type: 'efKey', key: 'x' }, p.window);
    p.send({ type: 'efKey', key: 'y' }, { other: true });
    assert.deepEqual(p.commands, [['insertText', false, 'x']]);
});

test('stays off inside a frame, the NUI iframe has real keys', () => {
    assert.equal(page({ framed: true }).listeners.length, 0);
});

test('Enter submits the form of a text field', () => {
    let submitted = 0;
    const p = page({ active: input({ form: { requestSubmit: () => submitted++ } }) });
    p.send({ type: 'efKey', key: 'Enter' });
    assert.equal(submitted, 1);
    assert.deepEqual(p.commands, []);
});

test('Tab moves the focus to the next field', () => {
    let focused = null;
    const a = input({ focus: () => (focused = 'a') });
    const b = input({ focus: () => (focused = 'b') });
    const p = page({ active: a, focusables: [a, b] });
    p.send({ type: 'efKey', key: 'Tab' });
    assert.equal(focused, 'b');
    p.send({ type: 'efKey', key: 'Tab', shift: true });
    assert.equal(focused, 'b', 'shift+Tab from a wraps around to b');
});

test('ignores other messages and Ctrl shortcuts', () => {
    const p = page({ active: input() });
    p.send({ type: 'something', key: 'a' });
    p.send({ type: 'efKey', key: 'v', ctrl: true });
    assert.deepEqual(p.commands, []);
});
