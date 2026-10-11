import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const script = readFileSync(new URL('../../assets/js/firetab-asu-state.js', import.meta.url), 'utf8');
const window = {};
vm.runInNewContext(script, { window });
const { derive } = window.AsuState;

const T0 = Date.UTC(2026, 9, 11, 12, 2, 0); // Anschließen
const min = (m, s = 0) => T0 + m * 60000 + s * 1000;
const trupp = (extra = {}) => ({
    expected_minutes: 30,
    role: 'angriff',
    members: [{ id: 1, position: 'tf', name: 'Weber' }, { id: 2, position: 'tm1', name: 'Krause' }],
    ...extra,
});
let nextId = 1;
const ev = (type, at, extra = {}) => ({ id: nextId++, type, at, member_id: null, bar: null, voided: false, ...extra });

// Angriffstrupp aus den Entwürfen: 300 bar, am Ziel mit 230, dann 210 und 188 beim TF
const running = (extra = []) => [
    ev('angeschlossen', min(0), { bar: 300 }),
    ev('druck', min(8), { member_id: 1, bar: 230 }),
    ev('ziel', min(8), { bar: 230 }),
    ev('druck', min(12), { member_id: 1, bar: 210 }),
    ev('druck', min(16), { member_id: 1, bar: 188 }),
    ev('druck', min(16), { member_id: 2, bar: 196 }),
    ...extra,
];

test('a team that is not connected yet waits for "anschließen"', () => {
    const s = derive(trupp(), [], min(0));
    assert.equal(s.phase, 'bereit');
    assert.equal(s.tone, 'idle');
    assert.equal(s.nextAction, 'anschliessen');
    assert.equal(derive(trupp({ role: 'sicherheit' }), [], min(0)).tone, 'ready');
});

test('retreat pressure is twice the air used on the way in', () => {
    const s = derive(trupp(), running([ev('hinweis_13', min(10))]), min(17, 24));
    assert.equal(s.phase, 'ziel');
    assert.equal(s.retreatBar, 140);
    assert.equal(s.lowest.name, 'Weber');
    assert.equal(s.lowest.bar, 188);
    assert.equal(s.rate, 5.5);
    assert.equal(s.restMin, 7);
    assert.equal(s.restSource, 'druck');
    assert.equal(s.restByClock, 13);
    assert.equal(s.askMin, 3);
    assert.equal(s.askKind, '2/3');
    assert.equal(s.tone, 'go');
    assert.equal(s.nextAsk.type, 'hinweis_23');
    assert.equal(s.nextAsk.at, min(20));
});

test('without a measured rate the configured consumption is used', () => {
    // Ziel ohne weitere Messung: nur ein Wert nach dem Anschließen je Person
    const events = [
        ev('angeschlossen', min(0), { bar: 300 }),
        ev('ziel', min(8), { bar: 240 }),
    ];
    const none = derive(trupp(), events, min(9));
    assert.equal(none.rate, null);
    assert.equal(none.restMin, 21, 'ohne Verbrauch zählt die Uhr');
    assert.equal(none.restSource, 'uhr');

    const s = derive(trupp(), events, min(9), { consumption: 8 });
    assert.equal(s.rateSource, 'angenommen');
    assert.equal(s.retreatBar, 120);
    // niedrigster Wert 300 (keine Einzelmessung), (300 - 120) / 8 = 22,5 min nach dem Anschließen
    assert.equal(s.retreatAt, min(22, 30));

    const measured = derive(trupp(), running([ev('hinweis_13', min(10))]), min(17), { consumption: 8 });
    assert.equal(measured.rateSource, 'gemessen');
    assert.equal(measured.rate, 5.5);
});

test('an unacknowledged third turns the row yellow', () => {
    const s = derive(trupp(), running(), min(17, 24));
    assert.equal(s.askDue, true);
    assert.equal(s.nextAsk.type, 'hinweis_13');
    assert.equal(s.tone, 'due');
});

test('the lowest member below retreat pressure is an alarm', () => {
    const s = derive(trupp(), running([ev('hinweis_13', min(10)), ev('druck', min(19), { member_id: 2, bar: 138 })]), min(19, 30));
    assert.equal(s.lowest.name, 'Krause');
    assert.equal(s.belowRetreat, true);
    assert.equal(s.tone, 'alarm');
});

test('a forecast retreat time that has passed is an alarm too', () => {
    const s = derive(trupp(), running([ev('hinweis_13', min(10)), ev('hinweis_23', min(20))]), min(27));
    assert.equal(s.retreatOverdue, true);
    assert.equal(s.tone, 'alarm');
});

test('voided events do not count', () => {
    const events = running([ev('hinweis_13', min(10))]);
    events.find((e) => e.type === 'ziel').voided = true;
    const s = derive(trupp(), events, min(17));
    assert.equal(s.phase, 'vorgehen');
    assert.equal(s.retreatBar, null);
    assert.equal(s.nextAction, 'ziel');
});

test('retreat and outside stop the clock and the alarms', () => {
    const back = derive(trupp(), running([ev('hinweis_13', min(10)), ev('rueckzug', min(18))]), min(19));
    assert.equal(back.tone, 'back');
    assert.equal(back.nextAction, 'draussen');

    // 2/3 wäre bei Minute 20 fällig, auf dem Rückweg zählt das nicht mehr
    const later = derive(trupp(), running([ev('hinweis_13', min(10)), ev('rueckzug', min(18))]), min(21));
    assert.equal(later.askDue, false);
    assert.equal(later.tone, 'back');

    const out = derive(trupp(), running([ev('rueckzug', min(18)), ev('draussen', min(25))]), min(40));
    assert.equal(out.tone, 'done');
    assert.equal(out.elapsedMs, 25 * 60000);
    assert.equal(out.askDue, false);
});

test('mayday wins over every other state until it is ended', () => {
    const note = JSON.stringify({ member: 'Krause', standort: '1. OG', lage: 'eingeklemmt' });
    const on = derive(trupp(), running([ev('mayday', min(19), { note })]), min(19, 30));
    assert.equal(on.tone, 'mayday');
    assert.deepEqual({ ...on.mayday }, { at: min(19), member: 'Krause', standort: '1. OG', lage: 'eingeklemmt' });

    const off = derive(trupp(), running([ev('mayday', min(19), { note }), ev('mayday_ende', min(22))]), min(23));
    assert.equal(off.mayday, null);
    assert.notEqual(off.tone, 'mayday');
});

test('running past the expected time is flagged', () => {
    const events = [ev('angeschlossen', min(0), { bar: 300 }), ev('hinweis_13', min(10)), ev('hinweis_23', min(20))];
    const s = derive(trupp(), events, min(31));
    assert.equal(s.overtime, true);
    assert.equal(s.tone, 'due');
});

test('rest and ask columns are always numbers', () => {
    const idle = derive(trupp(), [], min(0));
    assert.equal(idle.restMin, 30);
    assert.equal(idle.askMin, 10);
    assert.equal(idle.askKind, 'erst');

    const due = derive(trupp(), running(), min(17, 24));
    assert.equal(due.askMin, 0);

    const later = derive(trupp(), running([ev('hinweis_13', min(10)), ev('hinweis_23', min(20))]), min(24));
    assert.equal(later.askKind, 'seit');
    assert.equal(later.askMin, 4);

    const below = derive(trupp(), running([ev('hinweis_13', min(10)), ev('druck', min(19), { member_id: 2, bar: 138 })]), min(19, 30));
    assert.equal(below.restMin, 0);
    assert.equal(below.restSource, 'druck');
});
