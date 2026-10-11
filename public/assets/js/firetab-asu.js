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
    var SORT_KEY = 'firetab-asu-sort';

    var state = { trupps: [], events: [], finalized: root.dataset.finalized === '1' };
    var offset = 0;           // Serverzeit minus Browserzeit
    var lastSync = null;
    var syncError = null;
    var queue = loadStore(QUEUE_KEY, []);
    var sort = loadStore(SORT_KEY, { key: null, dir: 1 });
    var drawerTrupp = null;   // id des Trupps im Seitenfenster

    var els = {
        rows: root.querySelector('[data-asu-rows]'),
        clock: root.querySelector('[data-asu-clock]'),
        sync: root.querySelector('[data-asu-sync]'),
        mayday: root.querySelector('[data-asu-mayday]'),
        counts: root.querySelector('[data-asu-counts]'),
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

    function two(n) { return String(Math.max(0, n)).padStart(2, '0'); }

    function dec(n) { return n.toFixed(1).replace('.', ','); }

    function toast(msg, type) {
        if (typeof window.showToast === 'function') window.showToast(msg, type || 'info');
        else if (typeof window.showAlert === 'function') window.showAlert(msg, { type: type || 'info' });
    }

    function loadStore(key, fallback) {
        try { var v = JSON.parse(localStorage.getItem(key) || 'null'); return v === null ? fallback : v; } catch (e) { return fallback; }
    }

    function saveStore(key, value) {
        try { localStorage.setItem(key, JSON.stringify(value)); } catch (e) { /* privat oder voll */ }
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
            .then(function () { render(); renderDrawer(); });
    }

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
        saveStore(QUEUE_KEY, queue);
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
        saveStore(QUEUE_KEY, queue);
        render();
        renderDrawer();
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

    function truppById(id) {
        return state.trupps.filter(function (t) { return t.id === id; })[0] || null;
    }

    function crew(trupp, sep) {
        return trupp.members.map(function (m) { return m.name; }).join(sep || ' · ') || 'keine Namen';
    }

    // ── Texte ────────────────────────────────────────────────────────

    var STEP = {
        anschliessen: { icon: 'fa-play', label: 'Start', title: 'Trupp anschließen',
            help: 'Tippen, sobald der Trupp das Gerät anschließt. Ab dann läuft die Uhr, als Druck gilt der Fülldruck.' },
        ziel: { icon: 'fa-forward-step', label: 'Ziel', title: 'Ziel erreicht',
            help: 'Wenn der Trupp „Einsatzziel erreicht“ meldet. Druck je Person eintragen, daraus entsteht der Rückzugsdruck.' },
        rueckzug: { icon: 'fa-person-walking-arrow-loop-left', label: 'Rückzug', title: 'Rückzug angetreten',
            help: 'Wenn der Trupp den Rückweg antritt.' },
        draussen: { icon: 'fa-door-open', label: 'Draußen', title: 'Trupp ist draußen',
            help: 'Wenn der Trupp draußen ist. Die Uhr stoppt, die Zeile rutscht nach unten.' },
    };

    var TONE_COLOR = {
        go: '#2e7d32', idle: '#5f6368', ready: '#5f6368', due: '#c45f0a',
        alarm: '#b91c1c', mayday: '#dc2626', back: '#1d4ed8', done: '#26282d',
    };

    function phaseText(trupp, s) {
        if (s.mayday) {
            // Die Zeit steht in der Aktion-Spalte, hier nur die Angaben der Notfallmeldung
            var where = [s.mayday.standort, s.mayday.lage].filter(Boolean).join(', ');
            return 'MAYDAY' + (s.mayday.member ? ' ' + s.mayday.member : '') + (where ? ': ' + where : ', ohne Angaben');
        }
        if (s.belowRetreat) {
            // Liegt der Rückzugsdruck über dem Fülldruck, sagt die Zahl nichts mehr, wohl aber der Hinweg
            return s.retreatBar >= s.startBar
                ? 'Rückzug fällig, Hinweg kostete ' + (s.startBar - s.zielBar) + ' von ' + s.startBar + ' bar'
                : 'Rückzug fällig, spätestens bei ' + s.retreatBar + ' bar';
        }
        if (s.retreatOverdue) return 'Rückzug laut Prognose fällig';
        if (s.askDue) return (s.nextAsk.type === 'hinweis_13' ? '1/3' : '2/3') + '-Abfrage fällig';
        if (s.overtime) return 'über der erwarteten Zeit';
        return {
            bereit: trupp.role === 'sicherheit' ? 'Sicherheitstrupp bereit' : 'nicht angeschlossen',
            vorgehen: 'Vorgehen',
            ziel: 'am Ziel seit ' + hhmm(s.zielAt) + (s.retreatBar !== null ? ', Rückzug bei ' + s.retreatBar + ' bar' : ''),
            rueckzug: 'Rückweg seit ' + hhmm(s.rueckzugAt),
            draussen: 'draußen',
        }[s.phase];
    }

    // ── Liste ────────────────────────────────────────────────────────

    function metric(value, unit, label, cls) {
        return '<div class="asu-metric' + (cls ? ' ' + cls : '') + '"><div class="asu-metric__top"><span class="asu-metric__v">' + value + '</span>'
            + '<span class="asu-metric__u">' + unit + '</span></div><span class="asu-metric__l">' + label + '</span></div>';
    }

    function rowHtml(trupp, s) {
        if (s.tone === 'done') {
            return '<div class="asu-done" data-trupp="' + trupp.id + '"><b>' + esc(trupp.name) + '</b><span>' + esc(crew(trupp)) + '</span>'
                + '<span>' + hhmm(s.startAt) + ' bis ' + hhmm(s.endAt) + ', ' + Math.round(s.elapsedMs / 60000) + ' min</span>'
                + (s.lowest ? '<span>zuletzt ' + s.lowest.bar + ' bar</span>' : '')
                + '<button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--sm" data-asu-panel="' + trupp.id + '">Ablauf</button></div>';
        }

        var locked = state.finalized;
        var step = s.nextAction ? STEP[s.nextAction] : null;
        var stepLabel = step ? (s.nextAction === 'anschliessen' && trupp.role === 'sicherheit' ? 'Einsetzen' : step.label) : '';
        var stepBtn = '<button type="button" class="asu-step" data-asu-step="' + trupp.id + '"' + (step && !locked ? '' : ' disabled')
            + (step ? ' data-ignis-tooltip="' + esc(step.help) + '"' : '') + '>'
            + (step ? '<i class="fa-solid ' + step.icon + '"></i><small>' + esc(stepLabel) + '</small>' : '') + '</button>';

        var einsatz = metric(s.startAt ? two(Math.floor(s.elapsedMs / 60000)) : '--', 'min', 'Einsatz', s.overtime ? 'is-over' : '');
        var restIcon = s.restSource === 'druck' ? '<i class="fa-solid fa-gauge-high"></i>' : '<i class="fa-regular fa-clock"></i>';
        var rest = s.phase === 'rueckzug'
            ? metric('--', 'min', '<i class="fa-solid fa-person-walking-arrow-loop-left"></i>Rückweg')
            : metric(two(s.restMin), 'min', restIcon + 'Rest');

        var bar = s.lowest && s.lowest.bar !== null ? s.lowest.bar : cfg.startBar;
        var pct = Math.max(0, Math.min(100, Math.round((bar / cfg.startBar) * 100)));
        var mark = s.retreatBar !== null ? '<span class="rz" style="left:' + Math.min(100, (s.retreatBar / cfg.startBar) * 100) + '%"></span>' : '';
        // Der Prozenttext liegt zweimal übereinander: weiß auf dem leeren Teil, in
        // Zeilenfarbe auf der weißen Füllung, die ihn an ihrer Kante abschneidet.
        var press = '<div class="asu-press"' + (s.retreatBar !== null ? ' data-ignis-tooltip="Die Marke unter der Leiste ist der Rückzugsdruck: ' + s.retreatBar + ' bar"' : '') + '><div class="asu-metric__top"><span class="asu-metric__v">' + bar + '</span><span class="asu-metric__u">bar</span></div>'
            + '<div class="asu-press__bar"><i class="fa-solid fa-gauge-high"></i><div class="asu-gauge"><em>' + pct + '%</em>'
            + '<b style="width:' + pct + '%"><em style="color:' + TONE_COLOR[s.tone] + '">' + pct + '%</em></b>' + mark + '</div></div></div>';

        var where = '<div class="asu-where"><b>' + esc(trupp.task || (trupp.role === 'sicherheit' ? 'Sicherheitstrupp' : '')) + '</b>'
            + '<small>' + esc(phaseText(trupp, s)) + '</small></div>';

        var ask;
        if (!s.startAt) {
            ask = metric('--', 'min', '<i class="fa-solid fa-walkie-talkie"></i>nach Start');
        } else if (s.mayday) {
            ask = metric(two(Math.floor((now() - s.mayday.at) / 60000)), 'min', '<i class="fa-solid fa-triangle-exclamation"></i>MAYDAY seit');
        } else if (s.phase === 'rueckzug') {
            ask = metric(two(Math.floor((now() - s.rueckzugAt) / 60000)), 'min', '<i class="fa-solid fa-person-walking-arrow-loop-left"></i>Rückweg seit');
        } else if (s.askKind === 'seit') {
            ask = metric(two(s.askMin), 'min', '<i class="fa-solid fa-walkie-talkie"></i>seit Meldung');
        } else {
            ask = metric(two(s.askMin), 'min', '<i class="fa-solid fa-walkie-talkie"></i>Druck abfragen');
        }
        // Bei MAYDAY öffnet die Zelle die Notfallmeldung, die Abfrage tritt so lange zurück
        var askDue = s.askDue && !s.mayday;
        var askBtn = '<button type="button" class="asu-ask' + (askDue ? ' is-due' : '') + '" data-asu-ask="' + trupp.id + '"'
            + (s.mayday || (s.underAir && !locked) ? '' : ' disabled') + ' data-ignis-tooltip="'
            + (s.mayday ? 'Notfallmeldung ansehen' : 'Abfrage: Druck je Person eintragen, fällige Hinweise bestätigen') + '">' + ask + '</button>';

        return '<div class="asu-line" data-trupp="' + trupp.id + '">'
            + '<div class="asu-row" data-tone="' + s.tone + '">'
            + '<button type="button" class="asu-name" data-asu-panel="' + trupp.id + '"><b>' + esc(trupp.name) + '</b>'
            + trupp.members.map(function (m) { return '<span>' + esc(m.name) + '</span>'; }).join('') + '</button>'
            + stepBtn + einsatz + rest + press + where + askBtn
            + (askDue ? '<span class="asu-flag"></span>' : '')
            + '</div>'
            + '<button type="button" class="asu-gear" data-tone="' + s.tone + '" data-asu-panel="' + trupp.id + '" aria-label="Aktionen für ' + esc(trupp.name) + '" data-ignis-tooltip="Aktionen und Einzelheiten"><i class="fa-solid fa-gear"></i></button>'
            + '</div>';
    }

    var SORTERS = {
        name: function (a, b) { return a.trupp.name.localeCompare(b.trupp.name, 'de', { numeric: true }); },
        einsatz: function (a, b) { return a.s.elapsedMs - b.s.elapsedMs; },
        rest: function (a, b) { return a.s.restMin - b.s.restMin; },
        druck: function (a, b) { return (a.s.lowest ? a.s.lowest.bar : cfg.startBar) - (b.s.lowest ? b.s.lowest.bar : cfg.startBar); },
        abfrage: function (a, b) { return a.s.askMin - b.s.askMin; },
    };

    function render() {
        var t = now();
        els.clock.textContent = new Date(t).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

        var derived = state.trupps.map(function (tr) { return { trupp: tr, s: derive(tr) }; });
        var active = derived.filter(function (d) { return d.s.tone !== 'done'; });
        var done = derived.filter(function (d) { return d.s.tone === 'done'; });
        if (sort.key && SORTERS[sort.key]) {
            active.sort(function (a, b) { return SORTERS[sort.key](a, b) * sort.dir; });
        }

        if (!derived.length) {
            els.rows.innerHTML = '<div class="asu-empty">Noch kein Trupp. Mit dem grünen Pfeil rechts oben legst du einen Angriffstrupp an und schließt ihn gleich an, mit „Registrieren“ einen Trupp mit Namen, auch einen Sicherheitstrupp. Das Fragezeichen oben erklärt die Bedienung.</div>';
        } else {
            els.rows.innerHTML = active.concat(done).map(function (d) { return rowHtml(d.trupp, d.s); }).join('');
        }

        var alarm = derived.filter(function (d) { return d.s.tone === 'alarm' || d.s.tone === 'mayday'; }).length;
        var warn = derived.filter(function (d) { return d.s.tone === 'due'; }).length;
        var air = derived.filter(function (d) { return d.s.underAir; }).length;
        [['alarm', alarm, 'is-alarm'], ['warn', warn, 'is-warn'], ['air', air, null]].forEach(function (c) {
            var el = els.counts.querySelector('[data-count="' + c[0] + '"]');
            el.querySelector('b').textContent = c[1];
            if (c[2]) el.classList.toggle(c[2], c[1] > 0);
        });

        var mayday = derived.filter(function (d) { return d.s.mayday; })[0];
        els.mayday.classList.toggle('is-active', !!mayday);
        els.mayday.classList.toggle('asu-blink', !!mayday);
        els.mayday.textContent = mayday ? 'MAYDAY ' + mayday.trupp.name + ' · ' + mmss(t - mayday.s.mayday.at) : 'MAYDAY';
        els.mayday.disabled = state.finalized && !mayday;
        root.querySelectorAll('[data-asu-new]').forEach(function (b) { b.disabled = state.finalized; });

        root.querySelectorAll('[data-asu-sort]').forEach(function (b) {
            var on = sort.key === b.dataset.asuSort;
            b.querySelector('.asu-sort').innerHTML = on ? '<i class="fa-solid fa-arrow-' + (sort.dir > 0 ? 'down' : 'up') + '-short-wide"></i>' : '';
            if (on) b.setAttribute('aria-sort', sort.dir > 0 ? 'ascending' : 'descending'); else b.removeAttribute('aria-sort');
        });

        if (state.finalized) {
            els.sync.className = 'asu-sync';
            els.sync.textContent = 'Einsatz abgeschlossen, nur Ansicht';
        } else if (queue.length) {
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

    // ── Seitenfenster je Trupp ───────────────────────────────────────

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

    function tile(action, icon, label, sub, opts) {
        opts = opts || {};
        return '<button type="button" class="asu-tile' + (opts.danger ? ' is-danger' : '') + '" data-asu-act="' + action + '"' + (opts.disabled ? ' disabled' : '') + '>'
            + '<i class="fa-solid ' + icon + '"></i>' + esc(label) + (sub ? '<small>' + esc(sub) + '</small>' : '') + '</button>';
    }

    function drawerHtml(trupp, s) {
        var locked = state.finalized;
        var events = eventsOf(trupp.id).slice().sort(function (a, b) { return a.at - b.at || a.id - b.id; });
        var hasLive = events.some(function (e) { return !e.voided; });
        var step = s.nextAction ? STEP[s.nextAction] : null;
        var third = s.nextAsk ? (s.nextAsk.type === 'hinweis_13' ? '1/3' : '2/3') : null;

        var next = step && !locked
            ? '<section class="asu-next"><h4>Nächster Schritt</h4><button type="button" data-asu-act="step"><i class="fa-solid ' + step.icon + '"></i>'
                + esc(s.nextAction === 'anschliessen' && trupp.role === 'sicherheit' ? 'Sicherheitstrupp einsetzen' : step.title) + '</button><p>' + esc(step.help) + '</p></section>'
            : '';

        var tiles = '<section><h4>Aktionen</h4><div class="asu-tiles">'
            + tile('druck', 'fa-gauge-high', 'Druck melden', 'Abfrage je Person', { disabled: locked || !s.underAir })
            + tile('hinweis', 'fa-walkie-talkie', third ? third + '-Hinweis' : 'Hinweise erledigt', third ? (s.askDue ? 'jetzt fällig' : 'fällig ' + hhmm(s.nextAsk.at)) : '', { disabled: locked || !s.underAir || !third })
            + tile('ziel', 'fa-forward-step', 'Ziel erreicht', s.zielAt ? 'gemeldet ' + hhmm(s.zielAt) : '', { disabled: locked || s.phase !== 'vorgehen' })
            + tile('rueckzug', 'fa-person-walking-arrow-loop-left', 'Rückzug', s.rueckzugAt ? 'seit ' + hhmm(s.rueckzugAt) : '', { disabled: locked || (s.phase !== 'vorgehen' && s.phase !== 'ziel') })
            + tile('draussen', 'fa-door-open', 'Draußen', '', { disabled: locked || !s.underAir })
            + tile('ort', 'fa-location-dot', 'Ort / Auftrag', trupp.task || '', { disabled: locked })
            + tile('bearbeiten', 'fa-pen', 'Trupp bearbeiten', 'Namen, Art, Zeit', { disabled: locked })
            + (s.mayday
                ? tile('mayday-status', 'fa-triangle-exclamation', 'Mayday ansehen', 'seit ' + mmss(now() - s.mayday.at), { danger: true })
                : tile('mayday', 'fa-triangle-exclamation', 'MAYDAY', '', { danger: true, disabled: locked || s.phase === 'draussen' }))
            + (!hasLive ? tile('loeschen', 'fa-trash', 'Trupp löschen', 'nur ohne Einträge', { disabled: locked }) : '')
            + '</div></section>';

        var people = '<section><h4>Personen</h4><div class="asu-people">' + s.members.map(function (m) {
            var readings = events.filter(function (e) { return !e.voided && e.type === 'druck' && e.member_id === m.id; });
            var history = (s.startBar !== null ? [s.startBar] : []).concat(readings.map(function (e) { return e.bar; }));
            return '<div class="asu-person-card"><b>' + esc(m.name) + ' <span>' + ({ tf: 'Truppführer', tm1: 'Truppmann', tm2: 'Truppmann 2' }[m.position] || '') + '</span></b>'
                + '<div class="v">' + (m.bar != null ? m.bar + ' <small>bar</small>' : '<small>kein Druck</small>') + '</div>'
                + '<span>' + (m.at ? 'zuletzt ' + hhmm(m.at) : 'noch nicht angeschlossen') + (m.rate ? ', ' + dec(m.rate) + ' bar/min' : '')
                + (history.length > 1 ? ', Verlauf ' + history.join(' > ') : '') + '</span></div>';
        }).join('') + '</div></section>';

        var calc = [];
        if (s.retreatBar !== null) {
            calc.push('Rückzug spätestens bei <b>' + s.retreatBar + ' bar</b>. Der Hinweg von ' + s.startBar + ' auf ' + s.zielBar
                + ' bar hat ' + (s.startBar - s.zielBar) + ' bar gekostet, für den Rückweg wird das Doppelte eingeplant (FwDV 7).'
                + (s.retreatBar >= s.startBar ? ' Das ist mehr als der Fülldruck: Der Trupp hätte schon vor dem Ziel umkehren müssen.' : ''));
        } else if (s.startAt) {
            calc.push('Noch kein Rückzugsdruck. Er entsteht mit „Ziel erreicht“ und dem Druck am Ziel.');
        }
        if (s.startAt) {
            var clockInfo = 'erwartet ' + trupp.expected_minutes + ' min, ' + Math.floor(s.elapsedMs / 60000) + ' min vorbei';
            if (s.belowRetreat) {
                calc.push('Rest <b>0 min</b>: ' + esc(s.lowest.name) + ' hat ' + s.lowest.bar + ' bar und liegt damit unter dem Rückzugsdruck. Der Trupp muss zurück.');
            } else if (s.restSource === 'druck') {
                calc.push('Rest <b>' + s.restMin + ' min</b> nach dem Druck: ' + esc(s.lowest.name) + ' hat ' + s.lowest.bar + ' bar und verbraucht '
                    + dec(s.rate) + ' bar/min (' + s.rateSource + '). Nach der Uhr wären es ' + s.restByClock + ' min (' + clockInfo + ').');
            } else {
                calc.push('Rest <b>' + s.restMin + ' min</b> nach der Uhr (' + clockInfo + ').'
                    + (s.restByPressure !== null ? ' Nach dem Druck wären es ' + s.restByPressure + ' min.' : ''));
            }
            calc.push('Abfragen: ' + s.asks.map(function (a) {
                var name = a.type === 'hinweis_13' ? '1/3' : '2/3';
                return a.ack ? name + ' um ' + hhmm(a.ackAt) + ' gegeben' : name + ' fällig ' + hhmm(a.at);
            }).join(', ') + '.');
        } else {
            calc.push('Erwartete Einsatzzeit ' + trupp.expected_minutes + ' min (' + cfg.startBar + ' bar bei ' + cfg.consumption + ' bar/min). Die Abfragen kommen nach '
                + Math.round(trupp.expected_minutes / 3) + ' und ' + Math.round(trupp.expected_minutes * 2 / 3) + ' min.');
        }

        var log = '<section><h4>Ablauf</h4><div class="asu-log">' + (events.length ? events.map(function (e) {
            var text = (EVENT_TEXT[e.type] || function () { return e.type; })(e, trupp);
            var undo = !locked && !e.voided && !e.pending ? '<button type="button" data-asu-void="' + e.id + '">zurücknehmen</button>' : '<small>' + (e.pending ? 'wartet' : (e.voided ? 'zurückgenommen' : '')) + '</small>';
            return '<div class="' + (e.voided ? 'is-void' : '') + '"><time>' + hhmm(e.at) + '</time><span>' + esc(text)
                + (e.device ? ' <small>' + esc(e.device) + '</small>' : '') + '</span>' + undo + '</div>';
        }).join('') : '<div><span></span><span>Noch keine Einträge</span><span></span></div>') + '</div></section>';

        return '<div class="asu-drawer__head" style="background:' + (TONE_COLOR[s.tone] || '#2c2f36') + '">'
            + '<h3>' + esc(trupp.name) + '</h3><button type="button" class="asu-drawer__close" data-asu-close aria-label="Schließen"><i class="fa-solid fa-xmark"></i></button>'
            + '<p>' + esc(crew(trupp)) + ' · ' + esc(phaseText(trupp, s)) + '</p></div>'
            + '<div class="asu-drawer__body">' + next + tiles + people
            + '<section><h4>Rechnung</h4><div class="asu-calc">' + calc.map(function (c) { return '<div>' + c + '</div>'; }).join('') + '</div></section>'
            + log + '</div>';
    }

    var drawerEl = null;
    var drawerBg = null;

    function openDrawer(truppId) {
        drawerTrupp = truppId;
        if (!drawerEl) {
            drawerBg = document.createElement('div');
            drawerBg.className = 'asu-drawer-bg';
            drawerEl = document.createElement('aside');
            drawerEl.className = 'asu-drawer';
            drawerEl.setAttribute('role', 'dialog');
            drawerEl.setAttribute('aria-modal', 'true');
            drawerBg.addEventListener('click', closeDrawer);
            drawerEl.addEventListener('click', onDrawerClick);
            root.appendChild(drawerBg);
            root.appendChild(drawerEl);
        }
        drawerBg.hidden = false;
        drawerEl.hidden = false;
        renderDrawer();
    }

    function closeDrawer() {
        drawerTrupp = null;
        if (drawerEl) { drawerEl.hidden = true; drawerBg.hidden = true; }
    }

    function renderDrawer() {
        if (drawerTrupp === null || !drawerEl) return;
        var trupp = truppById(drawerTrupp);
        if (!trupp) { closeDrawer(); return; }
        var body = drawerEl.querySelector('.asu-drawer__body');
        var scroll = body ? body.scrollTop : 0;
        drawerEl.innerHTML = drawerHtml(trupp, derive(trupp));
        drawerEl.querySelector('.asu-drawer__body').scrollTop = scroll;
    }

    function onDrawerClick(e) {
        var trupp = truppById(drawerTrupp);
        if (e.target.closest('[data-asu-close]')) { closeDrawer(); return; }
        if (!trupp) return;
        var v = e.target.closest('[data-asu-void]');
        if (v) { voidEvent(parseInt(v.dataset.asuVoid, 10), trupp); return; }
        var b = e.target.closest('[data-asu-act]');
        if (!b || b.disabled) return;
        switch (b.dataset.asuAct) {
            case 'step': step(trupp); break;
            case 'druck': openPressure(trupp, 'abfrage'); break;
            case 'hinweis': openPressure(trupp, 'abfrage', true); break;
            case 'ziel': openPressure(trupp, 'ziel'); break;
            case 'rueckzug': send(trupp, 'rueckzug'); break;
            case 'draussen': send(trupp, 'draussen'); break;
            case 'ort': openTask(trupp); break;
            case 'bearbeiten': openTrupp(trupp, false); break;
            case 'mayday': openMayday(trupp); break;
            case 'mayday-status': openMaydayStatus(trupp); break;
            case 'loeschen':
                post('trupp/delete', { incident_id: cfg.incident, trupp_id: trupp.id })
                    .then(function () { closeDrawer(); return load(); }, function (err) { toast(err.message, 'error'); });
                break;
        }
    }

    function voidEvent(eventId, trupp) {
        var ev = eventsOf(trupp.id).filter(function (x) { return x.id === eventId; })[0];
        var text = ev ? (EVENT_TEXT[ev.type] || function () { return ev.type; })(ev, trupp) : 'Eintrag';
        var ask = typeof window.showConfirm === 'function'
            ? window.showConfirm('„' + text + '“ um ' + (ev ? hhmm(ev.at) : '') + ' zurücknehmen? Der Eintrag bleibt durchgestrichen im Ablauf.', { confirmText: 'Zurücknehmen', title: 'Eintrag zurücknehmen' })
            : Promise.resolve(true);
        ask.then(function (ok) {
            if (!ok) return;
            post('event/void', { incident_id: cfg.incident, event_id: eventId })
                .then(function () { return load(); }, function (err) { toast(err.message, 'error'); });
        });
    }

    // ── Dialoge ──────────────────────────────────────────────────────

    function dialog(opts) {
        var d = new window.Dialog(opts);
        d.open();
        return d;
    }

    // Druckeingabe je Person mit Zahlenblock, values() liefert { memberId: bar }
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

    function openPressure(trupp, mode, ackDefault) {
        var s = derive(trupp);
        var third = s.nextAsk ? (s.nextAsk.type === 'hinweis_13' ? '1/3' : '2/3') : null;
        var intro;
        if (mode === 'ziel') {
            intro = '<p class="asu-hint">Den Druck eintragen, den der Trupp am Ziel meldet. Daraus entsteht der Rückzugsdruck: doppelt so viel Luft wie für den Hinweg.</p>'
                + '<label>Ort / Auftrag<input class="ignis-input" data-task maxlength="255" value="' + esc(trupp.task || '') + '"></label>';
        } else {
            intro = '<p class="asu-hint">Den Druck eintragen, den die Personen über Funk melden. Leere Felder bleiben unverändert.</p>'
                + (third ? '<label class="asu-check"><input type="checkbox" data-ack' + (s.askDue || ackDefault ? ' checked' : '') + '> '
                    + third + '-Hinweis an den Trupp gegeben' + (s.askDue ? '' : ' (fällig ' + hhmm(s.nextAsk.at) + ')') + '</label>' : '');
        }
        var body = pressureBody(trupp, s, intro);
        dialog({
            title: (mode === 'ziel' ? 'Ziel erreicht: ' : 'Druck abfragen: ') + trupp.name,
            body: body,
            size: 'lg',
            actions: [
                { label: 'Abbrechen', variant: 'ghost', onClick: function (d) { d.close(null); } },
                {
                    label: mode === 'ziel' ? 'Ziel erreicht' : 'Speichern', variant: 'primary', primary: true, onClick: function (d) {
                        var values = body.values();
                        if (badPressure(values)) { toast('Der Druck muss zwischen 0 und 400 bar liegen.', 'error'); return; }
                        if (mode === 'ziel') {
                            var task = body.querySelector('[data-task]').value.trim();
                            if (task !== (trupp.task || '')) saveTrupp(Object.assign({}, trupp, { task: task }), false);
                            sendPressures(trupp, values);
                            send(trupp, 'ziel', minOf(values) !== null ? { bar: minOf(values) } : {});
                        } else {
                            var ack = body.querySelector('[data-ack]');
                            sendPressures(trupp, values);
                            if (ack && ack.checked) send(trupp, s.nextAsk.type);
                        }
                        d.close(true);
                    },
                },
            ],
        });
    }

    function openTask(trupp) {
        var body = document.createElement('div');
        body.className = 'asu-dlg';
        body.innerHTML = '<label>Ort / Auftrag<input class="ignis-input" data-task maxlength="255" value="' + esc(trupp.task || '') + '" placeholder="zum Beispiel Keller links, 1. OG"></label>';
        dialog({
            title: 'Ort / Auftrag: ' + trupp.name,
            body: body,
            size: 'md',
            actions: [
                { label: 'Abbrechen', variant: 'ghost', onClick: function (d) { d.close(null); } },
                {
                    label: 'Speichern', variant: 'primary', primary: true, onClick: function (d) {
                        saveTrupp(Object.assign({}, trupp, { task: body.querySelector('[data-task]').value.trim() }), false)
                            .then(function (ok) { if (ok) d.close(true); });
                    },
                },
            ],
        });
    }

    function step(trupp) {
        var s = derive(trupp);
        if (s.nextAction === 'anschliessen') send(trupp, 'angeschlossen', { bar: cfg.startBar });
        else if (s.nextAction === 'ziel') openPressure(trupp, 'ziel');
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
            + '<div class="asu-dlg__full"><span style="font-size:.85rem">Art</span><div class="asu-seg">'
            + '<button type="button" data-role="angriff" aria-pressed="' + (role === 'angriff') + '">Angriffstrupp</button>'
            + '<button type="button" data-role="sicherheit" aria-pressed="' + (role === 'sicherheit') + '">Sicherheitstrupp</button></div></div>'
            + '<label>Name oder Funkrufname<input class="ignis-input" data-f="name" maxlength="50" value="' + esc(trupp.name || nextName(role)) + '"></label>'
            + '<label>Erwartete Einsatzzeit (min)<input class="ignis-input" data-f="expected" inputmode="numeric" maxlength="3" value="' + (trupp.expected_minutes || expectedDefault) + '"></label>'
            + '<label>Truppführer<input class="ignis-input" data-f="tf" maxlength="100" value="' + esc(byPos.tf || '') + '"></label>'
            + '<label>Truppmann<input class="ignis-input" data-f="tm1" maxlength="100" value="' + esc(byPos.tm1 || '') + '"></label>'
            + '<label>Truppmann 2 (optional)<input class="ignis-input" data-f="tm2" maxlength="100" value="' + esc(byPos.tm2 || '') + '"></label>'
            + '<label>Ort / Auftrag<input class="ignis-input" data-f="task" maxlength="255" value="' + esc(trupp.task || '') + '"></label>'
            + '</div>'
            + (isNew && !start ? '<p class="asu-hint">Der Trupp erscheint grau in der Liste. Mit dem Pfeil in seiner Zeile schließt du ihn an, sobald er losgeht.</p>' : '');
        body.addEventListener('click', function (e) {
            var b = e.target.closest('[data-role]');
            if (!b) return;
            role = b.dataset.role;
            body.querySelectorAll('[data-role]').forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); });
            if (isNew) body.querySelector('[data-f="name"]').value = nextName(role);
        });
        function field(f) { return body.querySelector('[data-f="' + f + '"]').value.trim(); }

        dialog({
            title: isNew ? (start ? 'Angriffstrupp anlegen und anschließen' : 'Trupp registrieren') : trupp.name + ' bearbeiten',
            body: body,
            size: 'lg',
            actions: [
                { label: 'Abbrechen', variant: 'ghost', onClick: function (d) { d.close(null); } },
                {
                    label: isNew ? (start ? 'Anlegen und anschließen' : 'Registrieren') : 'Speichern', variant: 'primary', primary: true,
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
        body.innerHTML = '<div class="asu-mayday-box"><b>MAYDAY ' + esc(trupp.name) + ' seit ' + hhmm(s.mayday.at) + '</b>'
            + '<div>Hilfe sucht: ' + esc(s.mayday.member || 'ganzer Trupp') + '</div>'
            + '<div>Standort: ' + esc(s.mayday.standort || 'nicht angegeben') + '</div>'
            + '<div>Lage: ' + esc(s.mayday.lage || 'nicht angegeben') + '</div>'
            + (s.lowest ? '<div>Letzter Druck: ' + s.members.map(function (m) { return esc(m.name) + ' ' + (m.bar != null ? m.bar + ' bar' : 'unbekannt'); }).join(', ') + '</div>' : '')
            + '</div>'
            + (safety ? '<p>Bereit: ' + esc(safety.trupp.name) + ' (' + esc(crew(safety.trupp)) + ')</p>' : '<p>Kein Sicherheitstrupp bereit. Bitte einen registrieren.</p>');
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

    function openHelp() {
        var legend = [
            ['go', 'Grün: unter Atemschutz, alles in Ordnung'],
            ['due', 'Orange: Abfrage fällig oder über der erwarteten Zeit'],
            ['alarm', 'Rot: Rückzug fällig, weil der Druck unter dem Rückzugsdruck liegt oder die Prognose es sagt'],
            ['mayday', 'Hellrot mit weißem Rahmen: MAYDAY'],
            ['back', 'Blau: Rückweg'],
            ['idle', 'Grau: noch nicht angeschlossen, auch der bereite Sicherheitstrupp'],
        ].map(function (l) { return '<div><i style="background:' + TONE_COLOR[l[0]] + '"></i><span>' + esc(l[1]) + '</span></div>'; }).join('');
        var body = document.createElement('div');
        body.className = 'asu-help-text';
        body.innerHTML = '<section><h5>Ablauf je Trupp</h5><ol>'
            + '<li><b>Registrieren</b> legt einen Trupp mit Namen an, der grüne Pfeil rechts oben einen Angriffstrupp, der gleich angeschlossen wird.</li>'
            + '<li><b>Start</b> (Pfeil in der Zeile), sobald der Trupp anschließt. Die Uhr läuft, als Druck gilt der Fülldruck.</li>'
            + '<li><b>Ziel</b>, wenn der Trupp „Einsatzziel erreicht“ meldet. Den gemeldeten Druck eintragen, daraus entsteht der Rückzugsdruck.</li>'
            + '<li><b>Rückzug</b>, wenn der Trupp den Rückweg antritt, <b>Draußen</b>, wenn er raus ist.</li></ol></section>'
            + '<section><h5>Spalten</h5><ul>'
            + '<li><b>Einsatz:</b> Minuten seit dem Anschließen.</li>'
            + '<li><b>Rest:</b> Minuten bis zum Rückzug. Uhr-Symbol: aus der erwarteten Einsatzzeit. Druck-Symbol: aus dem Druck, sobald es einen Rückzugsdruck gibt. Es gilt der kleinere Wert.</li>'
            + '<li><b>Druck:</b> niedrigster gemeldeter Druck im Trupp, darunter der Anteil am Fülldruck. Die weiße Marke unter der Leiste ist der Rückzugsdruck. Bei diesem Druck muss der Trupp spätestens umkehren, er hat dann noch doppelt so viel Luft, wie der Hinweg gekostet hat (FwDV 7). Beispiel: 300 bar beim Anschließen, 230 bar am Ziel, also 70 bar für den Hinweg und Rückzug spätestens bei 140 bar.</li>'
            + '<li><b>Aktion:</b> Minuten bis zur nächsten Druckabfrage nach 1/3 und 2/3 der erwarteten Zeit. Antippen öffnet die Abfrage.</li></ul></section>'
            + '<section><h5>Farben</h5><div class="asu-legend">' + legend + '</div></section>'
            + '<section><h5>Zahnrad</h5><p class="asu-hint">Alle Aktionen eines Trupps, der Druck jeder Person, die Rechnung zum Rückzug und der Ablauf. Fehleingaben lassen sich dort einzeln zurücknehmen.</p></section>';
        dialog({ title: 'So funktioniert die Überwachung', body: body, size: 'lg', actions: [{ label: 'Verstanden', variant: 'primary', primary: true, onClick: function (d) { d.close(true); } }] });
    }

    // ── Ereignisse der Seite ─────────────────────────────────────────

    root.addEventListener('click', function (e) {
        var b;
        if (drawerEl && drawerEl.contains(e.target)) return;
        if ((b = e.target.closest('[data-asu-step]'))) { var t1 = truppById(parseInt(b.dataset.asuStep, 10)); if (t1) step(t1); return; }
        if ((b = e.target.closest('[data-asu-ask]'))) {
            var t2 = truppById(parseInt(b.dataset.asuAsk, 10));
            if (t2 && derive(t2).mayday) openMaydayStatus(t2);
            else if (t2) openPressure(t2, 'abfrage');
            return;
        }
        if ((b = e.target.closest('[data-asu-panel]'))) { openDrawer(parseInt(b.dataset.asuPanel, 10)); return; }
        if ((b = e.target.closest('[data-asu-new]'))) { openTrupp({ role: b.dataset.asuNew }, b.dataset.asuStart === '1'); return; }
        if ((b = e.target.closest('[data-asu-sort]'))) {
            var key = b.dataset.asuSort;
            if (sort.key !== key) sort = { key: key, dir: 1 };
            else if (sort.dir === 1) sort = { key: key, dir: -1 };
            else sort = { key: null, dir: 1 };
            saveStore(SORT_KEY, sort);
            render();
            return;
        }
        if (e.target.closest('[data-asu-help]')) { openHelp(); return; }
        if (e.target.closest('[data-asu-mayday]')) openMaydayPicker();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && drawerTrupp !== null) closeDrawer();
    });

    load();
    setInterval(poll, POLL_MS);
    setInterval(render, 1000);
})();
