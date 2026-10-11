/**
 * Atemschutzüberwachung: Zustand eines Trupps aus seinen Ereignissen.
 *
 * Reine Funktion ohne DOM und ohne eigene Uhr (die Zeit kommt als
 * Argument), damit node --test sie prüfen kann. Gespeichert wird nur,
 * was im Einsatz passiert ist; alles Abgeleitete entsteht hier.
 *
 * Regeln nach FwDV 7:
 *  - Hinweis auf die Behälterdrücke nach 1/3 und 2/3 der erwarteten Einsatzzeit (7.4)
 *  - Rückweg braucht die doppelte Luft des Hinwegs (7.3): Rückzug spätestens
 *    bei 2 × (Druck beim Anschließen − Druck am Ziel)
 *  - maßgeblich ist, wer im Trupp am meisten verbraucht (7.3): der niedrigste Druck
 */
(function (root) {
    'use strict';

    var DEFAULT_START_BAR = 300;

    function live(events) {
        return events
            .filter(function (e) { return !e.voided; })
            .slice()
            .sort(function (a, b) { return a.at - b.at || a.id - b.id; });
    }

    function first(events, type) {
        for (var i = 0; i < events.length; i++) {
            if (events[i].type === type) return events[i];
        }
        return null;
    }

    // Verbrauch in bar/min aus den letzten beiden Werten einer Person.
    // Unter 30 Sekunden Abstand ist die Rechnung nur Rauschen.
    function rateOf(readings) {
        if (readings.length < 2) return null;
        var a = readings[readings.length - 2];
        var b = readings[readings.length - 1];
        var minutes = (b.at - a.at) / 60000;
        if (minutes < 0.5 || b.bar >= a.bar) return null;
        return (a.bar - b.bar) / minutes;
    }

    // opts.consumption: Normalverbrauch in bar/min aus den Einstellungen.
    // Er gilt, solange für die Person mit dem niedrigsten Druck noch kein
    // Verbrauch messbar ist. Die PA-Skripte der Server ziehen unterschiedlich
    // schnell Luft ab, deshalb ist er einstellbar und nicht fest.
    function derive(trupp, allEvents, now, opts) {
        opts = opts || {};
        var events = live(allEvents);
        var start = first(events, 'angeschlossen');
        var ziel = first(events, 'ziel');
        var rueckzug = first(events, 'rueckzug');
        var draussen = first(events, 'draussen');
        var expectedMs = (trupp.expected_minutes || 30) * 60000;

        var phase = 'bereit';
        if (start) phase = 'vorgehen';
        if (start && ziel) phase = 'ziel';
        if (start && rueckzug) phase = 'rueckzug';
        if (start && draussen) phase = 'draussen';

        var startBar = start ? (start.bar || DEFAULT_START_BAR) : null;

        var members = (trupp.members || []).map(function (m) {
            var readings = start ? [{ bar: startBar, at: start.at }] : [];
            events.forEach(function (e) {
                if (e.type === 'druck' && e.member_id === m.id && e.bar != null) {
                    readings.push({ bar: e.bar, at: e.at });
                }
            });
            var last = readings.length ? readings[readings.length - 1] : null;
            return {
                id: m.id,
                position: m.position,
                name: m.name,
                bar: last ? last.bar : null,
                at: last ? last.at : null,
                rate: rateOf(readings),
            };
        });

        var lowest = null;
        members.forEach(function (m) {
            if (m.bar != null && (lowest === null || m.bar < lowest.bar)) lowest = m;
        });

        var zielBar = null;
        if (ziel) {
            zielBar = ziel.bar != null ? ziel.bar : (lowest ? lowest.bar : null);
        }
        var retreatBar = (startBar != null && zielBar != null) ? Math.max(0, 2 * (startBar - zielBar)) : null;

        var rate = lowest ? lowest.rate : null;
        var rateSource = rate !== null ? 'gemessen' : null;
        if (rate === null && lowest && opts.consumption > 0) {
            rate = opts.consumption;
            rateSource = 'angenommen';
        }
        var retreatAt = null;
        if (retreatBar != null && rate && lowest.bar > retreatBar) {
            retreatAt = lowest.at + ((lowest.bar - retreatBar) / rate) * 60000;
        }

        var underAir = phase === 'vorgehen' || phase === 'ziel' || phase === 'rueckzug';
        var belowRetreat = retreatBar != null && lowest !== null && lowest.bar <= retreatBar
            && (phase === 'vorgehen' || phase === 'ziel');
        var retreatOverdue = retreatAt !== null && now >= retreatAt
            && (phase === 'vorgehen' || phase === 'ziel');

        var asks = [];
        if (start) {
            [['hinweis_13', 1 / 3], ['hinweis_23', 2 / 3]].forEach(function (pair) {
                var ack = first(events, pair[0]);
                asks.push({ type: pair[0], at: start.at + expectedMs * pair[1], ack: !!ack, ackAt: ack ? ack.at : null });
            });
        }
        var nextAsk = null;
        asks.forEach(function (a) { if (!a.ack && nextAsk === null) nextAsk = a; });
        // Auf dem Rückweg ist der Trupp schon unterwegs nach draußen, die Hinweise erübrigen sich
        var askDue = (phase === 'vorgehen' || phase === 'ziel') && nextAsk !== null && now >= nextAsk.at;

        // Mayday gilt bis zum nächsten „mayday_ende“; die Angaben der
        // Notfallmeldung liegen als JSON in der Notiz.
        var mayday = null;
        events.forEach(function (e) {
            if (e.type === 'mayday') {
                var info = {};
                try { info = JSON.parse(e.note || '{}') || {}; } catch (err) { info = { lage: e.note }; }
                mayday = { at: e.at, member: info.member || '', standort: info.standort || '', lage: info.lage || '' };
            } else if (e.type === 'mayday_ende') {
                mayday = null;
            }
        });

        // Letzte Meldung des Trupps: nach beiden Hinweisen zeigt die Zeile,
        // wie lange er sich nicht gemeldet hat.
        var lastReportAt = null;
        events.forEach(function (e) {
            if (['angeschlossen', 'druck', 'ziel', 'hinweis_13', 'hinweis_23', 'rueckzug'].indexOf(e.type) !== -1) lastReportAt = e.at;
        });

        var endAt = draussen ? draussen.at : null;
        var elapsedMs = start ? Math.max(0, (endAt || now) - start.at) : 0;
        var overtime = underAir && elapsedMs > expectedMs;

        var tone;
        if (mayday) tone = 'mayday';
        else if (phase === 'draussen') tone = 'done';
        else if (!start) tone = trupp.role === 'sicherheit' ? 'ready' : 'idle';
        else if (belowRetreat || retreatOverdue) tone = 'alarm';
        else if (askDue || overtime) tone = 'due';
        else if (phase === 'rueckzug') tone = 'back';
        else tone = 'go';

        // Rest bis zum Rückzug, immer als Zahl: nach der Uhr (erwartete minus
        // verstrichene Zeit) und, sobald es einen Rückzugsdruck gibt, nach
        // dem Druck. Es gilt der kleinere Wert.
        var restByClock = Math.max(0, Math.round((expectedMs - elapsedMs) / 60000));
        var restByPressure = retreatAt !== null ? Math.max(0, Math.round((retreatAt - now) / 60000)) : null;
        if (belowRetreat) restByPressure = 0;
        var restMin = restByClock;
        var restSource = 'uhr';
        if (restByPressure !== null && restByPressure <= restByClock) {
            restMin = restByPressure;
            restSource = 'druck';
        }

        // Abfrage-Spalte, ebenfalls immer eine Zahl: Minuten bis zum nächsten
        // Hinweis (0 = fällig), nach beiden Hinweisen Minuten seit der letzten Meldung.
        var askMin;
        var askKind;
        if (!start) {
            askMin = Math.round(expectedMs / 3 / 60000);
            askKind = 'erst';
        } else if (nextAsk) {
            askMin = Math.max(0, Math.ceil((nextAsk.at - now) / 60000));
            askKind = nextAsk.type === 'hinweis_13' ? '1/3' : '2/3';
        } else {
            askMin = lastReportAt !== null ? Math.max(0, Math.floor((now - lastReportAt) / 60000)) : 0;
            askKind = 'seit';
        }

        var nextAction = {
            bereit: 'anschliessen',
            vorgehen: 'ziel',
            ziel: 'rueckzug',
            rueckzug: 'draussen',
            draussen: null,
        }[phase];

        return {
            phase: phase,
            tone: tone,
            nextAction: nextAction,
            startAt: start ? start.at : null,
            zielAt: ziel ? ziel.at : null,
            rueckzugAt: rueckzug ? rueckzug.at : null,
            endAt: endAt,
            elapsedMs: elapsedMs,
            expectedMs: expectedMs,
            overtime: overtime,
            startBar: startBar,
            zielBar: zielBar,
            retreatBar: retreatBar,
            members: members,
            lowest: lowest,
            rate: rate,
            rateSource: rateSource,
            retreatAt: retreatAt,
            restMin: restMin,
            restSource: restSource,
            restByClock: restByClock,
            restByPressure: restByPressure,
            askMin: askMin,
            askKind: askKind,
            belowRetreat: belowRetreat,
            retreatOverdue: retreatOverdue,
            asks: asks,
            nextAsk: nextAsk,
            askDue: askDue,
            underAir: underAir,
            lastReportAt: lastReportAt,
            mayday: mayday,
        };
    }

    root.AsuState = { derive: derive, DEFAULT_START_BAR: DEFAULT_START_BAR };
})(typeof window !== 'undefined' ? window : this);
