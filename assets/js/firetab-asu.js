/**
 * fireTab: Atemschutzüberwachung (Reiter „Atemschutz“).
 *
 * Holt alle 3 Sekunden den Stand des Einsatzes vom Server, rechnet je Trupp
 * mit AsuState.derive() und zeichnet eine Zeile je Trupp. Jede Aktion ist
 * ein Ereignis mit eigener UUID: es erscheint sofort, wird an den Server
 * geschickt und bleibt in einer Warteschlange (localStorage), bis der
 * Server es bestätigt hat. Doppelt gesendete Meldungen speichert der
 * Server nur einmal.
 *
 * Läuft im FiveM-Tablet unter Chromium 103 und braucht window.Dialog aus
 * dem ui-Paket (über head.php geladen).
 */
(function () {
    'use strict';

    var root = document.getElementById('asu-board');
    if (!root || !window.AsuState) return;

    var cfg = {
        incident: parseInt(root.dataset.incident, 10),
        api: (root.dataset.base || '/') + 'api/fire/asu/',
        consumption: parseInt(root.dataset.consumption, 10) || 10,
        startBar: parseInt(root.dataset.startBar, 10) || 300,
        vehicle: root.dataset.vehicle || '',
    };
    var expectedDefault = Math.max(5, Math.min(120, Math.round(cfg.startBar / cfg.consumption)));
    var POLL_MS = 3000;
    var QUEUE_KEY = 'firetab-asu-queue-' + cfg.incident;

    var state = { trupps: [], events: [], finalized: root.dataset.finalized === '1' };
    var offset = 0;           // Serverzeit minus Browserzeit
    var lastSync = null;
    var syncError = null;
    var queue = loadQueue();  // [{body}] noch nicht bestätigte Ereignisse

    var els = {
        rows: root.querySelector('[data-asu-rows]'),
        clock: root.querySelector('[data-asu-clock]'),
        sync: root.querySelector('[data-asu-sync]'),
        summary: root.querySelector('[data-asu-summary]'),
        mayday: root.querySelector('[data-asu-mayday]'),
    };

    // ── Hilfen ───────────────────────────────────────────────────────

    function now() { return Date.now() + offset; }

    function uuid() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
        var b = new Uint8Array(16);
        window.crypto.getRandomValues(b);
        b[6] = (b[6] & 0x0f) | 0x40;
        b[8] = (b[8] & 0x3f) | 0x80;
        var h = Array.prototype.map.call(b, function (x) { return (x + 0x100).toString(16).slice(1); }).join('');
        return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' + h.slice(16, 20) + '-' + h.slice(20);
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function hhmm(ms) {
        var d = new Date(ms);
        return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    }

    function mmss(ms) {
        var s = Math.max(0, Math.round(ms / 1000));
        return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
    }

    function toast(msg, type) {
        if (typeof window.showToast === 'function') window.showToast(msg, type || 'info');
        else if (typeof window.showAlert === 'function') window.showAlert(msg, { type: type || 'info' });
    }

    function loadQueue() {
        try { return JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]') || []; } catch (e) { return []; }
    }

    function saveQueue() {
        try { localStorage.setItem(QUEUE_KEY, JSON.stringify(queue)); } catch (e) { /* privat oder voll */ }
    }

    function post(path, body) {
        return fetch(cfg.api + path, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(body),
            credentials: 'same-origin',
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (data) {
                if (!r.ok || data.success === false) {
                    var msg = data.message || (data.errors ? Object.values(data.errors)[0] : null) || ('Fehler ' + r.status);
                    var err = new Error(msg);
                    err.status = r.status;
                    throw err;
                }
                return data;
            });
        });
    }

    // ── Abgleich ─────────────────────────────────────────────────────

    // Nach einer eigenen Aktion muss der Stand frisch sein: läuft gerade ein
    // Abgleich, wird danach noch einmal geladen. Der Takt überspringt dagegen.
    var inflight = null;

    function load() {
        if (inflight) return inflight.then(load);
        inflight = fetchState().then(function () { inflight = null; });
        return inflight;
    }

    function poll() {
        if (!inflight) load();
    }

    function fetchState() {
        var sent = Date.now();
        return fetch(cfg.api + 'state?incident_id=' + cfg.incident, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) {
                if (r.status === 401 || r.status === 403) throw new Error('Kein Zugriff mehr, bitte neu anmelden.');
                if (!r.ok) throw new Error('Server antwortet nicht (' + r.status + ').');
                return r.json();
            })
            .then(function (data) {
                offset = data.now - Math.round((sent + Date.now()) / 2);
                state = data;
                lastSync = Date.now();
                syncError = null;
                return flush();
            })
            .catch(function (e) { syncError = e.message; })
            .then(function () { render(); });
    }

    // Wartende Meldungen der Reihe nach senden. Bestätigte fliegen raus,
    // abgelehnte (4xx) auch, mit Hinweis; bei Netzfehlern bleibt der Rest liegen.
    // Immer nur ein Sendevorgang, sonst schicken zwei Läufe dieselbe Meldung
    // und kürzen die Schlange doppelt.
    var flushing = null;

    function flush() {
        if (!flushing) {
            flushing = sendNext().then(function () { flushing = null; });
        }
        return flushing;
    }

    function drop(item) {
        queue = queue.filter(function (q) { return q.body.client_uuid !== item.body.client_uuid; });
        saveQueue();
    }

    function sendNext() {
        if (!queue.length) return Promise.resolve();
        var item = queue[0];
        return post('event', item.body).then(function () {
            drop(item);
            return sendNext();
        }, function (e) {
            if (e.status && e.status >= 400 && e.status < 500) {
                drop(item);
                toast('Meldung abgelehnt: ' + e.message, 'error');
                return sendNext();
            }
            syncError = 'Ohne Verbindung';
        });
    }

    function send(trupp, type, extra) {
        if (state.finalized) return;
        var body = Object.assign({
            incident_id: cfg.incident,
            trupp_id: trupp.id,
            type: type,
            client_uuid: uuid(),
        }, extra || {});
        queue.push({ body: body, at: now() });
        saveQueue();
        render();
        flush().then(function () { return load(); });
    }

    // Ereignisse eines Trupps: bestätigte vom Server plus wartende aus der Schlange
    function eventsOf(truppId) {
        var list = state.events.filter(function (e) { return e.trupp_id === truppId; });
        queue.forEach(function (q, i) {
            if (q.body.trupp_id !== truppId) return;
            var known = state.events.some(function (e) { return e.client_uuid === q.body.client_uuid; });
            if (known) return;
            list.push({
                id: 1e12 + i, trupp_id: truppId, member_id: q.body.member_id || null, type: q.body.type,
                bar: q.body.bar != null ? q.body.bar : null, note: q.body.note || null, at: q.at, voided: false, pending: true,
            });
        });
        return list;
    }

    function derive(trupp) {
        return window.AsuState.derive(trupp, eventsOf(trupp.id), now(), { consumption: cfg.consumption });
    }

    // ── Zeichnen ─────────────────────────────────────────────────────

    var STEP = {
        anschliessen: ['fa-play', 'Start'],
        ziel: ['fa-flag-checkered', 'Ziel'],
        rueckzug: ['fa-person-walking-arrow-loop-left', 'Rückzug'],
        draussen: ['fa-door-open', 'Draußen'],
    };

    var PHASE_TEXT = {
        bereit: 'bereit',
        vorgehen: 'Vorgehen',
        ziel: 'am Ziel',
        rueckzug: 'Rückweg',
        draussen: 'draußen',
    };

    function crew(trupp) {
        return trupp.members.map(function (m) { return m.name; }).join(' · ') || 'keine Namen';
    }

    function rowHtml(trupp, s) {
        if (s.tone === 'done') {
            return '<div class="asu-row" data-tone="done" data-trupp="' + trupp.id + '">'
                + '<span class="asu-stripe"></span>'
                + '<div class="asu-done"><b>' + esc(trupp.name) + '</b><span>' + esc(crew(trupp)) + '</span>'
                + '<span>' + hhmm(s.startAt) + ' bis ' + hhmm(s.endAt) + ' · ' + Math.round(s.elapsedMs / 60000) + ' min</span>'
                + (s.lowest ? '<span>zuletzt ' + s.lowest.bar + ' bar</span>' : '')
                + '<button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--sm" data-asu-panel="' + trupp.id + '">Ablauf</button></div>'
                + '</div>';
        }

        var locked = state.finalized;
        var step = s.nextAction ? STEP[s.nextAction] : null;
        var stepBtn = '<button type="button" class="asu-step" data-asu-step="' + trupp.id + '"' + (step && !locked ? '' : ' disabled') + '>' + (step ? '<i class="fa-solid ' + step[0] + '"></i><small>' + esc(step[1]) + '</small>' : '') + '</button>';

        var mins = Math.floor(s.elapsedMs / 60000);
        var einsatz = s.startAt
            ? '<div class="asu-big"><span class="v' + (s.overtime ? ' is-over' : '') + '">' + mins + '</span><span class="u"><span>min</span><span>Einsatz</span></span></div>'
            : '<div class="asu-big"><span class="v">0</span><span class="u"><span>min</span><span>' + (trupp.role === 'sicherheit' ? 'bereit' : 'nicht drin') + '</span></span></div>';

        var rest;
        if (!s.startAt) {
            rest = '<div class="asu-big"><span class="v">' + trupp.expected_minutes + '</span><span class="u"><span>min</span><span>erwartet</span></span></div>';
        } else if (s.phase === 'rueckzug') {
            rest = '<div class="asu-big"><span class="v is-word">Rückweg</span><span class="u"><span>seit</span><span>' + hhmm(s.rueckzugAt) + '</span></span></div>';
        } else if (s.belowRetreat) {
            rest = '<div class="asu-big"><span class="v">0</span><span class="u"><span>min</span><span>Rückzug!</span></span></div>';
        } else if (s.restMin !== null) {
            rest = '<div class="asu-big"><span class="v">' + s.restMin + '</span><span class="u"><span>min bis</span><span>Rückzug</span></span></div>';
        } else {
            rest = '<div class="asu-big"><span class="v is-word">offen</span><span class="u"><span>Ziel</span><span>fehlt</span></span></div>';
        }

        var press = '';
        if (s.lowest && s.lowest.bar !== null) {
            var pct = Math.max(0, Math.min(100, (s.lowest.bar / cfg.startBar) * 100));
            var mark = s.retreatBar !== null ? '<span class="rz" style="left:' + Math.max(0, Math.min(100, (s.retreatBar / cfg.startBar) * 100)) + '%"></span>' : '';
            press = '<div class="asu-press"><div class="top"><span class="v">' + s.lowest.bar + '</span><span class="u">bar · ' + esc(s.lowest.name) + '</span></div>'
                + '<div class="asu-meter"><i style="width:calc(' + pct + '% - 2px)"></i>' + mark + '</div></div>';
        } else {
            press = '<div class="asu-press"><div class="top"><span class="v">' + cfg.startBar + '</span><span class="u">bar Fülldruck</span></div></div>';
        }

        var phaseText = PHASE_TEXT[s.phase];
        if (s.phase === 'ziel') phaseText = 'am Ziel seit ' + hhmm(s.zielAt);
        if (s.mayday) phaseText = 'MAYDAY seit ' + mmss(now() - s.mayday.at);
        else if (s.belowRetreat) phaseText = 'unter Rückzugsdruck ' + s.retreatBar + ' bar';
        else if (s.retreatOverdue) phaseText = 'Rückzug laut Prognose fällig';
        else if (s.overtime) phaseText = 'über der erwarteten Einsatzzeit';
        var where = '<div class="asu-where">' + esc(trupp.task || (trupp.role === 'sicherheit' ? 'Sicherheitstrupp' : 'kein Auftrag eingetragen'))
            + '<small>' + esc(phaseText) + '</small></div>';

        var ask;
        if (!s.startAt) {
            ask = '<button type="button" class="asu-ask" disabled><span class="u"><span>nach dem</span><span>Anschließen</span></span></button>';
        } else if (!s.nextAsk) {
            var silent = s.lastReportAt !== null ? Math.max(0, Math.floor((now() - s.lastReportAt) / 60000)) : 0;
            ask = '<button type="button" class="asu-ask" data-asu-ask="' + trupp.id + '"' + (locked ? ' disabled' : '') + '><i class="fa-solid fa-walkie-talkie"></i><span class="v">' + silent + '</span><span class="u"><span>min seit</span><span>Meldung</span></span></button>';
        } else if (s.askDue) {
            ask = '<button type="button" class="asu-ask asu-blink" data-asu-ask="' + trupp.id + '"' + (locked ? ' disabled' : '') + '><i class="fa-solid fa-walkie-talkie"></i><span class="v is-word">jetzt</span><span class="u"><span>' + (s.nextAsk.type === 'hinweis_13' ? '1/3' : '2/3') + '</span><span>fällig</span></span></button>';
        } else {
            ask = '<button type="button" class="asu-ask" data-asu-ask="' + trupp.id + '"' + (locked ? ' disabled' : '') + '><i class="fa-solid fa-walkie-talkie"></i><span class="v">' + Math.ceil((s.nextAsk.at - now()) / 60000) + '</span><span class="u"><span>min bis</span><span>' + (s.nextAsk.type === 'hinweis_13' ? '1/3' : '2/3') + '</span></span></button>';
        }

        return '<div class="asu-row" data-tone="' + s.tone + '" data-trupp="' + trupp.id + '">'
            + '<span class="asu-stripe"></span>'
            + '<div class="asu-name"><b>' + esc(trupp.name) + '</b><span>' + esc(crew(trupp)) + '</span></div>'
            + stepBtn + einsatz + rest + press + where + ask
            + '<button type="button" class="asu-more" data-asu-panel="' + trupp.id + '" data-ignis-tooltip="Einzelheiten" aria-label="Einzelheiten zu ' + esc(trupp.name) + '"><i class="fa-solid fa-gear"></i></button>'
            + '</div>';
    }

    function render() {
        var t = now();
        els.clock.textContent = new Date(t).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

        var derived = state.trupps.map(function (tr) { return { trupp: tr, s: derive(tr) }; });
        var active = derived.filter(function (d) { return d.s.tone !== 'done'; });
        var done = derived.filter(function (d) { return d.s.tone === 'done'; });
        var underAir = derived.filter(function (d) { return d.s.underAir; }).length;

        if (!derived.length) {
            els.rows.innerHTML = '<div class="asu-empty">Noch kein Trupp. Mit „Schnellstart Angriffstrupp“ legst du einen Trupp an und schließt ihn gleich an, mit „Sicherheitstrupp bereitstellen“ den Sicherheitstrupp.</div>';
        } else {
            els.rows.innerHTML = active.concat(done).map(function (d) { return rowHtml(d.trupp, d.s); }).join('');
        }

        els.summary.textContent = state.finalized
            ? 'Einsatz abgeschlossen, nur Ansicht'
            : underAir + (underAir === 1 ? ' Trupp' : ' Trupps') + ' unter Atemschutz';

        var mayday = derived.filter(function (d) { return d.s.mayday; })[0];
        els.mayday.classList.toggle('is-active', !!mayday);
        els.mayday.classList.toggle('asu-blink', !!mayday);
        els.mayday.textContent = mayday ? 'MAYDAY ' + mayday.trupp.name + ' · ' + mmss(t - mayday.s.mayday.at) : 'MAYDAY';
        els.mayday.disabled = state.finalized && !mayday;

        root.querySelectorAll('[data-asu-new]').forEach(function (b) { b.disabled = state.finalized; });

        if (queue.length) {
            els.sync.className = 'asu-sync is-warn';
            els.sync.textContent = queue.length + (queue.length === 1 ? ' Meldung wartet' : ' Meldungen warten');
        } else if (syncError) {
            els.sync.className = 'asu-sync is-bad';
            els.sync.textContent = syncError;
        } else if (lastSync) {
            els.sync.className = 'asu-sync';
            els.sync.textContent = 'abgeglichen';
        }
    }

    function truppById(id) {
        return state.trupps.filter(function (t) { return t.id === id; })[0] || null;
    }

    // ── Dialoge ──────────────────────────────────────────────────────

    function dialog(opts) {
        var d = new window.Dialog(opts);
        d.open();
        return d;
    }

    // Druckeingabe je Person mit Zahlenblock. Liefert über done(values)
    // { memberId: bar } für alle ausgefüllten Felder.
    function pressureBody(trupp, s, intro) {
        var body = document.createElement('div');
        body.className = 'asu-dlg';
        var people = trupp.members.map(function (m, i) {
            var last = (s.members.filter(function (x) { return x.id === m.id; })[0] || {}).bar;
            return '<div class="asu-person' + (i === 0 ? ' is-focus' : '') + '" data-member="' + m.id + '">'
                + '<div><b>' + esc(m.name) + '</b><small>' + ({ tf: 'Truppführer', tm1: 'Truppmann', tm2: 'Truppmann 2' }[m.position] || '')
                + (last != null ? ', zuletzt ' + last + ' bar' : '') + '</small></div>'
                + '<input class="ignis-input" inputmode="numeric" maxlength="3" autocomplete="off" aria-label="Druck ' + esc(m.name) + ' in bar" data-bar></div>';
        }).join('');
        body.innerHTML = (intro || '')
            + '<div class="asu-press-dlg"><div class="asu-dlg">' + people + '</div>'
            + '<div class="asu-keypad">' + ['1', '2', '3', '4', '5', '6', '7', '8', '9', '⌫', '0', 'C'].map(function (k) {
                return '<button type="button" data-key="' + k + '">' + k + '</button>';
            }).join('') + '</div></div>';

        var focus = body.querySelector('.asu-person');
        body.addEventListener('click', function (e) {
            var person = e.target.closest('.asu-person');
            if (person) {
                body.querySelectorAll('.asu-person').forEach(function (p) { p.classList.toggle('is-focus', p === person); });
                focus = person;
            }
            var key = e.target.closest('[data-key]');
            if (key && focus) {
                var input = focus.querySelector('[data-bar]');
                var k = key.dataset.key;
                if (k === '⌫') input.value = input.value.slice(0, -1);
                else if (k === 'C') input.value = '';
                else if (input.value.length < 3) input.value += k;
            }
        });
        body.values = function () {
            var out = {};
            body.querySelectorAll('.asu-person').forEach(function (p) {
                var v = parseInt(p.querySelector('[data-bar]').value, 10);
                if (!isNaN(v)) out[p.dataset.member] = v;
            });
            return out;
        };
        return body;
    }

    function sendPressures(trupp, values) {
        Object.keys(values).forEach(function (memberId) {
            send(trupp, 'druck', { member_id: parseInt(memberId, 10), bar: values[memberId] });
        });
    }

    function minOf(values) {
        var list = Object.keys(values).map(function (k) { return values[k]; });
        return list.length ? Math.min.apply(null, list) : null;
    }

    function badPressure(values) {
        return Object.keys(values).some(function (k) { return values[k] < 0 || values[k] > 400; });
    }

    function openAsk(trupp) {
        var s = derive(trupp);
        var third = s.nextAsk ? (s.nextAsk.type === 'hinweis_13' ? '1/3' : '2/3') : null;
        var intro = third && s.askDue
            ? '<label class="asu-check"><input type="checkbox" data-ack checked> ' + third + '-Hinweis an den Trupp gegeben</label>'
            : (third ? '<label class="asu-check"><input type="checkbox" data-ack> ' + third + '-Hinweis schon jetzt gegeben (fällig ' + hhmm(s.nextAsk.at) + ')</label>' : '');
        var body = pressureBody(trupp, s, intro);
        dialog({
            title: 'Abfrage ' + trupp.name,
            body: body,
            size: 'lg',
            actions: [
                { label: 'Abbrechen', variant: 'ghost', onClick: function (d) { d.close(null); } },
                {
                    label: 'Speichern', variant: 'primary', primary: true, onClick: function (d) {
                        var values = body.values();
                        if (badPressure(values)) { toast('Der Druck muss zwischen 0 und 400 bar liegen.', 'error'); return; }
                        var ack = body.querySelector('[data-ack]');
                        sendPressures(trupp, values);
                        if (ack && ack.checked) send(trupp, s.nextAsk.type);
                        d.close(true);
                    },
                },
            ],
        });
    }

    function openGoal(trupp) {
        var s = derive(trupp);
        var intro = '<p>Druck am Einsatzziel eintragen. Daraus ergibt sich der Rückzugsdruck: doppelt so viel Luft wie für den Hinweg.</p>'
            + '<label>Ort / Auftrag<input class="ignis-input" data-task maxlength="255" value="' + esc(trupp.task || '') + '"></label>';
        var body = pressureBody(trupp, s, intro);
        dialog({
            title: trupp.name + ' am Ziel',
            body: body,
            size: 'lg',
            actions: [
                { label: 'Abbrechen', variant: 'ghost', onClick: function (d) { d.close(null); } },
                {
                    label: 'Ziel erreicht', variant: 'primary', primary: true, onClick: function (d) {
                        var values = body.values();
                        if (badPressure(values)) { toast('Der Druck muss zwischen 0 und 400 bar liegen.', 'error'); return; }
                        var task = body.querySelector('[data-task]').value.trim();
                        if (task !== (trupp.task || '')) {
                            saveTrupp(Object.assign({}, trupp, { task: task }), false);
                        }
                        sendPressures(trupp, values);
                        send(trupp, 'ziel', minOf(values) !== null ? { bar: minOf(values) } : {});
                        d.close(true);
                    },
                },
            ],
        });
    }

    function step(trupp) {
        var s = derive(trupp);
        if (s.nextAction === 'anschliessen') send(trupp, 'angeschlossen', { bar: cfg.startBar });
        else if (s.nextAction === 'ziel') openGoal(trupp);
        else if (s.nextAction === 'rueckzug') send(trupp, 'rueckzug');
        else if (s.nextAction === 'draussen') send(trupp, 'draussen');
    }

    function nextName(role) {
        var prefix = role === 'sicherheit' ? 'ST ' : 'AT ';
        var n = state.trupps.filter(function (t) { return t.role === role; }).length + 1;
        return prefix + (cfg.vehicle ? cfg.vehicle + ' ' : '') + n;
    }

    // Trupp anlegen oder bearbeiten. start=true schließt ihn danach gleich an.
    function openTrupp(trupp, start) {
        var isNew = !trupp.id;
        var role = trupp.role || 'angriff';
        var byPos = {};
        (trupp.members || []).forEach(function (m) { byPos[m.position] = m.name; });
        var body = document.createElement('div');
        body.className = 'asu-dlg';
        body.innerHTML = '<div class="asu-dlg__grid">'
            + '<label>Name oder Funkrufname<input class="ignis-input" data-f="name" maxlength="50" value="' + esc(trupp.name || nextName(role)) + '"></label>'
            + '<div><span style="font-size:.85rem">Art</span><div class="asu-seg" data-f="role">'
            + '<button type="button" data-role="angriff" aria-pressed="' + (role === 'angriff') + '">Angriffstrupp</button>'
            + '<button type="button" data-role="sicherheit" aria-pressed="' + (role === 'sicherheit') + '">Sicherheitstrupp</button></div></div>'
            + '<label>Truppführer<input class="ignis-input" data-f="tf" maxlength="100" value="' + esc(byPos.tf || '') + '"></label>'
            + '<label>Truppmann<input class="ignis-input" data-f="tm1" maxlength="100" value="' + esc(byPos.tm1 || '') + '"></label>'
            + '<label>Truppmann 2 (optional)<input class="ignis-input" data-f="tm2" maxlength="100" value="' + esc(byPos.tm2 || '') + '"></label>'
            + '<label>Erwartete Einsatzzeit (min)<input class="ignis-input" data-f="expected" inputmode="numeric" maxlength="3" value="' + (trupp.expected_minutes || expectedDefault) + '"></label>'
            + '<label class="asu-dlg__full">Ort / Auftrag<input class="ignis-input" data-f="task" maxlength="255" value="' + esc(trupp.task || '') + '"></label>'
            + '</div>';
        body.addEventListener('click', function (e) {
            var b = e.target.closest('[data-role]');
            if (!b) return;
            role = b.dataset.role;
            body.querySelectorAll('[data-role]').forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); });
            var nameInput = body.querySelector('[data-f="name"]');
            if (isNew) nameInput.value = nextName(role);
        });
        function field(f) { return body.querySelector('[data-f="' + f + '"]').value.trim(); }

        dialog({
            title: isNew ? (start ? 'Angriffstrupp anlegen und anschließen' : 'Trupp anlegen') : trupp.name + ' bearbeiten',
            body: body,
            size: 'lg',
            actions: [
                { label: 'Abbrechen', variant: 'ghost', onClick: function (d) { d.close(null); } },
                {
                    label: isNew ? (start ? 'Anlegen und anschließen' : 'Anlegen') : 'Speichern', variant: 'primary', primary: true,
                    onClick: function (d) {
                        var payload = {
                            id: trupp.id,
                            name: field('name'),
                            role: role,
                            task: field('task'),
                            expected_minutes: parseInt(field('expected'), 10) || expectedDefault,
                            members: [
                                { position: 'tf', name: field('tf') || 'Truppführer' },
                                { position: 'tm1', name: field('tm1') || 'Truppmann' },
                                { position: 'tm2', name: field('tm2') },
                            ],
                        };
                        if (!payload.name) { toast('Der Trupp braucht einen Namen.', 'error'); return; }
                        saveTrupp(payload, start).then(function (ok) { if (ok) d.close(true); });
                    },
                },
            ],
        });
    }

    function saveTrupp(t, start) {
        var body = {
            incident_id: cfg.incident,
            name: t.name,
            role: t.role,
            task: t.task || '',
            expected_minutes: t.expected_minutes,
            members: (t.members || []).map(function (m) { return { position: m.position, name: m.name || '' }; }),
        };
        if (t.id) body.trupp_id = t.id;
        return post('trupp', body).then(function (data) {
            return load().then(function () {
                if (start) {
                    var created = truppById(data.trupp_id);
                    if (created) send(created, 'angeschlossen', { bar: cfg.startBar });
                }
                return true;
            });
        }, function (e) {
            toast(e.message, 'error');
            return false;
        });
    }

    var EVENT_TEXT = {
        angeschlossen: function (e) { return 'angeschlossen' + (e.bar != null ? ', ' + e.bar + ' bar' : ''); },
        druck: function (e, t) {
            var m = t.members.filter(function (x) { return x.id === e.member_id; })[0];
            return 'Druck ' + (m ? m.name : 'unbekannt') + ': ' + e.bar + ' bar';
        },
        ziel: function (e) { return 'Ziel erreicht' + (e.bar != null ? ', ' + e.bar + ' bar' : ''); },
        hinweis_13: function () { return '1/3-Hinweis gegeben'; },
        hinweis_23: function () { return '2/3-Hinweis gegeben'; },
        rueckzug: function () { return 'Rückzug angetreten'; },
        draussen: function () { return 'draußen'; },
        mayday: function () { return 'MAYDAY'; },
        mayday_ende: function () { return 'Mayday beendet'; },
    };

    function openPanel(trupp) {
        var s = derive(trupp);
        var events = eventsOf(trupp.id).slice().sort(function (a, b) { return a.at - b.at || a.id - b.id; });
        var lastLive = events.filter(function (e) { return !e.voided && !e.pending; }).pop();
        var body = document.createElement('div');
        body.className = 'asu-dlg';
        var people = s.members.map(function (m) {
            return '<div><b>' + esc(m.name) + '</b> <span style="color:var(--secondary-text)">'
                + (m.bar != null ? m.bar + ' bar um ' + hhmm(m.at) : 'noch kein Druck')
                + (m.rate ? ', ' + m.rate.toFixed(1).replace('.', ',') + ' bar/min' : '') + '</span></div>';
        }).join('');
        var facts = [];
        if (s.retreatBar !== null) facts.push('Rückzug spätestens bei ' + s.retreatBar + ' bar');
        if (s.rate) facts.push('Verbrauch ' + s.rate.toFixed(1).replace('.', ',') + ' bar/min (' + s.rateSource + ')');
        if (s.retreatAt) facts.push('Rückzug voraussichtlich ' + hhmm(s.retreatAt));
        facts.push('erwartete Einsatzzeit ' + trupp.expected_minutes + ' min');

        body.innerHTML = '<div class="asu-dlg__grid"><div>' + people + '</div><div style="color:var(--secondary-text)">' + facts.map(esc).join('<br>') + '</div></div>'
            + '<div><b>Ablauf</b><div class="asu-timeline">' + (events.length ? events.map(function (e) {
                var text = (EVENT_TEXT[e.type] || function () { return e.type; })(e, trupp);
                return '<div class="' + (e.voided ? 'is-void' : '') + '"><time>' + hhmm(e.at) + '</time><span>' + esc(text) + (e.pending ? ' (wartet)' : '') + '</span><span style="color:var(--secondary-text)">' + esc(e.device || '') + '</span></div>';
            }).join('') : '<div><span></span><span>Noch keine Einträge</span><span></span></div>') + '</div></div>';

        var actions = [{ label: 'Schließen', variant: 'ghost', onClick: function (d) { d.close(null); } }];
        if (!state.finalized) {
            if (lastLive) {
                actions.push({
                    label: 'Letzten Eintrag zurücknehmen', variant: 'secondary', onClick: function (d) {
                        post('event/void', { incident_id: cfg.incident, event_id: lastLive.id })
                            .then(function () { d.close(true); return load(); }, function (e) { toast(e.message, 'error'); });
                    },
                });
            }
            if (!events.length) {
                actions.push({
                    label: 'Trupp löschen', variant: 'ghost-danger', onClick: function (d) {
                        post('trupp/delete', { incident_id: cfg.incident, trupp_id: trupp.id })
                            .then(function () { d.close(true); return load(); }, function (e) { toast(e.message, 'error'); });
                    },
                });
            }
            actions.push({ label: 'Bearbeiten', variant: 'secondary', onClick: function (d) { d.close(null); openTrupp(trupp, false); } });
            if (s.phase !== 'draussen') {
                actions.push({
                    label: s.mayday ? 'Mayday ansehen' : 'MAYDAY', variant: 'danger', onClick: function (d) {
                        d.close(null);
                        if (s.mayday) openMaydayStatus(trupp); else openMayday(trupp);
                    },
                });
            }
        }
        dialog({ title: trupp.name + ' · ' + crew(trupp), body: body, size: 'lg', actions: actions });
    }

    // MAYDAY oben: Auswahl der Trupps unter Atemschutz, ist es nur einer, ist er vorgewählt
    function openMaydayPicker() {
        var current = state.trupps.map(function (t) { return { trupp: t, s: derive(t) }; });
        var active = current.filter(function (d) { return d.s.mayday; })[0];
        if (active) { openMaydayStatus(active.trupp); return; }
        if (state.finalized) return;
        var candidates = current.filter(function (d) { return d.s.underAir; });
        if (!candidates.length) candidates = current.filter(function (d) { return d.s.phase !== 'draussen'; });
        if (!candidates.length) { toast('Es ist kein Trupp eingetragen.', 'warning'); return; }
        if (candidates.length === 1) { openMayday(candidates[0].trupp); return; }

        var body = document.createElement('div');
        body.className = 'asu-dlg';
        body.innerHTML = '<p>Welcher Trupp ist betroffen?</p><div class="asu-pick">' + candidates.map(function (d) {
            return '<button type="button" data-pick="' + d.trupp.id + '"><b>' + esc(d.trupp.name) + '</b>' + esc(crew(d.trupp)) + '</button>';
        }).join('') + '</div>';
        var dlg = dialog({
            title: 'MAYDAY',
            body: body,
            size: 'md',
            actions: [{ label: 'Abbrechen', variant: 'ghost', onClick: function (d) { d.close(null); } }],
        });
        body.addEventListener('click', function (e) {
            var b = e.target.closest('[data-pick]');
            if (!b) return;
            dlg.close(null);
            openMayday(truppById(parseInt(b.dataset.pick, 10)));
        });
    }

    // Notfallmeldung nach FwDV 7, 7.6: wer, wo, was
    function openMayday(trupp) {
        var body = document.createElement('div');
        body.className = 'asu-dlg';
        body.innerHTML = '<div class="asu-mayday-box"><b>MAYDAY ' + esc(trupp.name) + '</b>'
            + '<label>Hilfe sucht<select class="ignis-input" data-f="member"><option value="">ganzer Trupp</option>'
            + trupp.members.map(function (m) { return '<option>' + esc(m.name) + '</option>'; }).join('') + '</select></label>'
            + '<label>Standort<input class="ignis-input" data-f="standort" maxlength="80" placeholder="zum Beispiel 1. OG, Treppenraum Nord"></label>'
            + '<label>Lage<input class="ignis-input" data-f="lage" maxlength="100" placeholder="zum Beispiel eingeklemmt, Luft wird knapp"></label></div>';
        function f(name) { return body.querySelector('[data-f="' + name + '"]').value.trim(); }
        dialog({
            title: 'Notfallmeldung',
            body: body,
            size: 'md',
            actions: [
                { label: 'Abbrechen', variant: 'ghost', onClick: function (d) { d.close(null); } },
                {
                    label: 'MAYDAY auslösen', variant: 'danger', primary: true, onClick: function (d) {
                        var note = JSON.stringify({ member: f('member'), standort: f('standort'), lage: f('lage') });
                        if (note.length > 255) { toast('Standort und Lage bitte kürzer fassen.', 'error'); return; }
                        send(trupp, 'mayday', { note: note });
                        d.close(true);
                        setTimeout(function () { openMaydayStatus(trupp); }, 300);
                    },
                },
            ],
        });
    }

    function openMaydayStatus(trupp) {
        var s = derive(trupp);
        if (!s.mayday) return;
        var safety = state.trupps.map(function (t) { return { trupp: t, s: derive(t) }; })
            .filter(function (d) { return d.trupp.role === 'sicherheit' && d.s.phase === 'bereit'; })[0];
        var body = document.createElement('div');
        body.className = 'asu-dlg';
        body.innerHTML = '<div class="asu-mayday-box"><b>MAYDAY ' + esc(trupp.name) + ' seit ' + mmss(now() - s.mayday.at) + '</b>'
            + '<div>Hilfe sucht: ' + esc(s.mayday.member || 'ganzer Trupp') + '</div>'
            + '<div>Standort: ' + esc(s.mayday.standort || 'nicht angegeben') + '</div>'
            + '<div>Lage: ' + esc(s.mayday.lage || 'nicht angegeben') + '</div>'
            + (s.lowest ? '<div>Letzter Druck: ' + s.members.map(function (m) { return esc(m.name) + ' ' + (m.bar != null ? m.bar + ' bar' : 'unbekannt'); }).join(', ') + '</div>' : '')
            + '</div>'
            + (safety ? '<p>Bereit: ' + esc(safety.trupp.name) + ' (' + esc(crew(safety.trupp)) + ')</p>' : '<p>Kein Sicherheitstrupp bereit. Bitte einen bereitstellen.</p>');
        var actions = [{ label: 'Schließen', variant: 'ghost', onClick: function (d) { d.close(null); } }];
        if (!state.finalized) {
            actions.push({ label: 'Mayday beendet', variant: 'secondary', onClick: function (d) { send(trupp, 'mayday_ende'); d.close(true); } });
            if (safety) {
                actions.push({
                    label: 'Sicherheitstrupp einsetzen', variant: 'danger', primary: true, onClick: function (d) {
                        var task = 'Rettung ' + trupp.name + (s.mayday.standort ? ', ' + s.mayday.standort : '');
                        saveTrupp(Object.assign({}, safety.trupp, { task: task.slice(0, 255) }), false).then(function () {
                            send(safety.trupp, 'angeschlossen', { bar: cfg.startBar });
                        });
                        d.close(true);
                    },
                });
            }
        }
        dialog({ title: 'MAYDAY', body: body, size: 'md', actions: actions });
    }

    // ── Ereignisse der Seite ─────────────────────────────────────────

    root.addEventListener('click', function (e) {
        var b;
        if ((b = e.target.closest('[data-asu-step]'))) { var t1 = truppById(parseInt(b.dataset.asuStep, 10)); if (t1) step(t1); return; }
        if ((b = e.target.closest('[data-asu-ask]'))) { var t2 = truppById(parseInt(b.dataset.asuAsk, 10)); if (t2) openAsk(t2); return; }
        if ((b = e.target.closest('[data-asu-panel]'))) { var t3 = truppById(parseInt(b.dataset.asuPanel, 10)); if (t3) openPanel(t3); return; }
        if ((b = e.target.closest('[data-asu-new]'))) {
            openTrupp({ role: b.dataset.asuNew }, b.dataset.asuStart === '1');
            return;
        }
        if (e.target.closest('[data-asu-mayday]')) openMaydayPicker();
    });

    load();
    setInterval(poll, POLL_MS);
    setInterval(render, 1000);
})();
