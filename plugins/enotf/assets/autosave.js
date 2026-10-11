/**
 * eNOTF: Autosave-Client (Batch-Save).
 *
 * Ersetzt das jQuery-basierte notify.php aus v1 (ein Feld pro Request,
 * text/plain) durch Vanilla JS mit debounced Multi-Field-POST:
 *
 *   POST {saveUrl}  Content-Type: application/json
 *   { "enr": "...", "fields": { "spalte": wert, ... } }
 *   → { "ok": bool, "updated": [...], "errors": { "feld": "Meldung" } }
 *
 * Aktivierung über das Layout: ein Wurzel-Element mit
 *   data-enotf-autosave data-enr="..." data-save-url="..." [data-locked="1"]
 *
 * Verhalten:
 *   - change-Events auf input/select/textarea mit name-Attribut werden
 *     gesammelt (name === DB-Spalte), 600 ms debounced, als EIN Request
 *     geschickt. Checkbox → 1/0, Radio → checked-Wert.
 *   - Unverändert gegenüber dem initialen Wert → kein Send (Baseline-Map).
 *   - [data-autosave-ignore], readonly und disabled sind ausgenommen.
 *   - Läuft bereits ein Request, sammelt der nächste Batch weiter und
 *     wird nach der Antwort geflusht (kein paralleles Schreiben).
 *   - 403 (Protokoll freigegeben) sperrt den Client und meldet es.
 *   - Getippter Text liegt bis zum bestätigten Save als Entwurf im
 *     localStorage, pagehide schickt Offenes per keepalive hinterher und
 *     flushAll() (pin_activity.js vor der PIN-Sperre) sendet alles sofort.
 *   - Feldfehler markieren das Element mit .ev2-field-error und zeigen
 *     die erste Meldung im Topbar-Status ([data-autosave-status]).
 *
 * Programmatischer Zugriff für Section-Skripte (JSON-Array-Felder wie
 * psych/ebesonderheiten/c_zugang/medis):
 *   window.EnotfV2Autosave.queue('psych', JSON.stringify([1]));
 *   window.EnotfV2Autosave.flushNow();
 *
 * Plausibilität/Stepper: trägt das Wurzel-Element zusätzlich
 * data-plausibility-url, wird nach jedem Batch mit gespeicherten Feldern
 * GET {plausibilityUrl} gerufen und
 *   - die Status-Indikatoren der Section-Navigation ([data-section-fill])
 *     aktualisiert (API-Status filled/partfilled/unfilled/nocheck →
 *     Klassen ev2-state-done/-partial/-empty/-none, Mapping identisch
 *     zum serverseitigen Initial-Render in _layout.php),
 *   - der Gesamtfortschritt im Sidebar-Kopf nachgezogen
 *     ([data-ev2-progress-text/-short/-fill]), und
 *   - ein CustomEvent 'enotfv2:plausibility' (detail = API-Antwort) auf
 *     dem Wurzel-Element dispatcht, Section-Skripte (z. B. abschluss.php)
 *     hängen sich daran.
 * Manuell anstoßbar über window.EnotfV2Autosave.refreshPlausibility().
 */

const DEBOUNCE_MS = 600;

function init() {
    const root = document.querySelector('[data-enotf-autosave]');
    if (!root) return;

    const enr = root.dataset.enr;
    const saveUrl = root.dataset.saveUrl;
    if (!enr || !saveUrl) return;

    const plausibilityUrl = root.dataset.plausibilityUrl || null;

    const statusEl = document.querySelector('[data-autosave-status]');
    const statusTextEl = document.querySelector('[data-autosave-status-text]');

    let locked = root.dataset.locked === '1';
    let pending = Object.create(null);
    let inFlight = false;
    let timer = null;
    let savedFlashTimer = null;

    const baseline = new Map();
    // Felder mit noch unbestätigter Änderung (gequeued/in Flight). Die
    // Baseline wird erst mit der Server-Antwort nachgezogen. Ohne dieses
    // Set würde ein Zurückstellen auf den Ladewert, während der vorige
    // Save noch läuft, als "unverändert" verworfen und der alte Wert
    // bliebe in der DB stehen.
    const dirty = new Set();
    // Batch des laufenden Requests, damit pagehide ihn erneut schicken kann
    let inFlightFields = null;

    // Getippter Text, der noch nicht bestätigt gespeichert ist, liegt
    // zusätzlich im localStorage (je ENR). Geht die Seite weg, bevor der
    // Save durch ist (Sperre, Neuladen im Spiel), speichert der nächste
    // Aufruf des Protokolls ihn nach.
    const DRAFT_FIELDS = 'textarea, input:not([type]), input[type="text"]:not([data-date-input]):not([data-time-input])';
    const draftKey = 'enotf-draft:' + enr;
    let drafts = readDrafts();

    function readDrafts() {
        try {
            const data = JSON.parse(localStorage.getItem(draftKey) || '{}');
            return data && typeof data === 'object' && !Array.isArray(data) ? data : {};
        } catch (e) {
            return {};
        }
    }

    function writeDrafts() {
        try {
            if (Object.keys(drafts).length > 0) localStorage.setItem(draftKey, JSON.stringify(drafts));
            else localStorage.removeItem(draftKey);
        } catch (e) { /* ohne localStorage bleibt der normale Save */ }
    }

    function dropDraft(field, value) {
        if (Object.hasOwn(drafts, field) && drafts[field] === value) {
            delete drafts[field];
            writeDrafts();
        }
    }

    function setStatus(state, text) {
        if (statusEl) {
            if (savedFlashTimer) { clearTimeout(savedFlashTimer); savedFlashTimer = null; }
            statusEl.classList.remove('ev2-sync--saving', 'ev2-sync--saved', 'ev2-sync--error');
            if (state) statusEl.classList.add('ev2-sync--' + state);
            // "Gespeichert" blinkt kurz grün auf und fällt dann auf muted zurück
            if (state === 'saved') {
                savedFlashTimer = setTimeout(() => {
                    statusEl.classList.remove('ev2-sync--saved');
                    savedFlashTimer = null;
                }, 2000);
            }
        }
        if (statusTextEl && text) statusTextEl.textContent = text;
    }

    function fieldValue(el) {
        if (el.type === 'checkbox') return el.checked ? '1' : '0';
        return el.value;
    }

    function isSavable(el) {
        if (!el.name) return false;
        if (el.matches('[data-autosave-ignore]')) return false;
        if (el.readOnly || el.disabled) return false;
        if (el.type === 'hidden') return false;
        return true;
    }

    function baselineKey(el) {
        // Radios teilen sich den name: Baseline pro Feldname, nicht pro Element
        return el.name;
    }

    function captureBaseline() {
        root.querySelectorAll('input[name], select[name], textarea[name]').forEach((el) => {
            if (!isSavable(el)) return;
            if (el.type === 'radio') {
                if (el.checked) baseline.set(el.name, el.value);
                else if (!baseline.has(el.name)) baseline.set(el.name, '');
                return;
            }
            baseline.set(baselineKey(el), fieldValue(el));
        });
    }

    function markLocked(message) {
        locked = true;
        pending = Object.create(null);
        drafts = {};
        writeDrafts();
        setStatus('error', message || 'Gesperrt');
    }

    function clearFieldErrors() {
        root.querySelectorAll('.ev2-field-error').forEach((el) => el.classList.remove('ev2-field-error'));
    }

    function markFieldErrors(errors) {
        Object.keys(errors).forEach((field) => {
            if (field === '_request') return;
            const el = root.querySelector('[name="' + CSS.escape(field) + '"]');
            if (el) el.classList.add('ev2-field-error');
        });
    }

    // ── Plausibilität / Stepper-Badges ─────────────────────────────
    // Nach jedem Batch mit gespeicherten Feldern den Serverstand holen
    // und die Füllstands-Badges der Section-Navigation nachziehen.
    // Leicht debounced, damit direkt aufeinanderfolgende Flushes nur
    // einen Request auslösen; läuft schon einer, wird danach genau
    // einmal nachgezogen (letzter Stand gewinnt).

    // API-Status → Anzeigeklasse (Mapping identisch zu _layout.php)
    const STATE_CLASS = {
        filled:     'ev2-state-done',
        partfilled: 'ev2-state-partial',
        unfilled:   'ev2-state-empty',
        nocheck:    'ev2-state-none',
    };
    let plausibilityTimer = null;
    let plausibilityInFlight = false;
    let plausibilityQueued = false;

    function updateSectionBadges(sections) {
        if (!sections) return;
        Object.keys(sections).forEach((key) => {
            const badge = document.querySelector('[data-section-fill="' + CSS.escape(key) + '"]');
            if (!badge) return;
            const info = sections[key] || {};
            const status = Object.hasOwn(STATE_CLASS, info.status) ? info.status : 'nocheck';
            Object.values(STATE_CLASS).forEach((cls) => badge.classList.remove(cls));
            badge.classList.add(STATE_CLASS[status]);
            badge.dataset.ignisTooltip = status === 'nocheck'
                ? 'Keine Pflichtangaben'
                : (info.filled ?? 0) + ' von ' + (info.total ?? 0) + ' Pflichtangaben';
        });
        updateProgress(sections);
    }

    // Gesamtfortschritt (Sidebar-Kopf): Summe filled/total über alle
    // Sections mit Pflichtangaben, gleiche Rechnung wie das serverseitige
    // Initial-Render in _layout.php.
    function updateProgress(sections) {
        const textEl = document.querySelector('[data-ev2-progress-text]');
        const shortEl = document.querySelector('[data-ev2-progress-short]');
        const fillEl = document.querySelector('[data-ev2-progress-fill]');
        if (!textEl && !shortEl && !fillEl) return;

        let filled = 0;
        let total = 0;
        Object.values(sections).forEach((info) => {
            if (!info || info.status === 'nocheck') return;
            filled += info.filled ?? 0;
            total += info.total ?? 0;
        });

        if (textEl) textEl.textContent = filled + ' von ' + total + ' Pflichtangaben';
        if (shortEl) shortEl.textContent = filled + '/' + total;
        if (fillEl) fillEl.style.width = (total > 0 ? Math.round((filled / total) * 100) : 0) + '%';
    }

    function refreshPlausibility() {
        if (!plausibilityUrl) return;
        if (plausibilityInFlight) {
            plausibilityQueued = true;
            return;
        }

        plausibilityInFlight = true;
        fetch(plausibilityUrl, { headers: { 'Accept': 'application/json' } })
            .then((response) => (response.ok ? response.json() : null))
            .then((data) => {
                if (data && data.ok) {
                    updateSectionBadges(data.sections);
                    root.dispatchEvent(new CustomEvent('enotfv2:plausibility', { detail: data }));
                }
            })
            .catch(() => { /* Badges behalten den letzten bekannten Stand */ })
            .finally(() => {
                plausibilityInFlight = false;
                if (plausibilityQueued) {
                    plausibilityQueued = false;
                    refreshPlausibility();
                }
            });
    }

    function schedulePlausibilityRefresh() {
        if (!plausibilityUrl) return;
        if (plausibilityTimer) clearTimeout(plausibilityTimer);
        plausibilityTimer = setTimeout(refreshPlausibility, 250);
    }

    function flush() {
        if (locked || inFlight) return;
        const fields = pending;
        if (Object.keys(fields).length === 0) return;

        pending = Object.create(null);
        inFlight = true;
        inFlightFields = fields;
        setStatus('saving', 'Speichert…');

        fetch(saveUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ enr: enr, fields: fields }),
        })
            .then((response) => response.json()
                .catch(() => ({}))
                .then((data) => ({ status: response.status, data: data })))
            .then(({ status, data }) => {
                inFlight = false;
                inFlightFields = null;

                if (status === 403) {
                    markLocked('Protokoll freigegeben, Bearbeitung gesperrt');
                    return;
                }
                if (status === 401) {
                    setStatus('error', 'Nicht angemeldet');
                    return;
                }

                clearFieldErrors();

                const errors = (data && data.errors) || {};
                const errorFields = Object.keys(errors);

                if (data && data.updated) {
                    data.updated.forEach((field) => {
                        baseline.set(field, String(fields[field] ?? ''));
                        // Nur als bestätigt markieren, wenn nicht schon
                        // wieder eine neuere Änderung wartet
                        if (!(field in pending)) dirty.delete(field);
                        dropDraft(field, String(fields[field] ?? ''));
                    });
                    if (data.updated.length > 0) schedulePlausibilityRefresh();
                }
                // Abgelehnte Werte nicht bei jedem Aufruf erneut schicken
                errorFields.forEach((field) => {
                    if (field in fields) dropDraft(field, String(fields[field] ?? ''));
                });

                if (errorFields.length > 0) {
                    markFieldErrors(errors);
                    const first = errorFields[0];
                    setStatus('error', first === '_request' ? errors[first] : first + ': ' + errors[first]);
                } else if (data && data.ok) {
                    setStatus('saved', 'Gespeichert');
                } else {
                    setStatus('error', 'Speichern fehlgeschlagen');
                }

                // Während des Requests aufgelaufene Änderungen nachschieben
                if (Object.keys(pending).length > 0) flush();
            })
            .catch(() => {
                inFlight = false;
                inFlightFields = null;
                // Batch nicht verlieren: zurücklegen und beim nächsten
                // change/flush erneut versuchen
                Object.keys(fields).forEach((field) => {
                    if (!(field in pending)) pending[field] = fields[field];
                });
                setStatus('error', 'Netzwerkfehler. Erneuter Versuch bei nächster Eingabe');
            });
    }

    function queue(field, value) {
        if (locked) return;
        pending[field] = value;
        dirty.add(field);
        if (timer) clearTimeout(timer);
        timer = setTimeout(flush, DEBOUNCE_MS);
    }

    function queueIfChanged(field, value) {
        // Unverändert gegenüber dem bestätigten Stand → kein Send. Felder
        // mit offener Änderung (dirty) müssen trotzdem gequeued werden,
        // sonst gewinnt der noch laufende Save des alten Werts.
        if (baseline.get(field) === value && !dirty.has(field)) {
            dropDraft(field, value);
            return;
        }
        queue(field, value);
    }

    function onFieldEvent(event) {
        const el = event.target;
        if (!(el instanceof HTMLElement)) return;
        if (!el.matches('input, select, textarea')) return;
        if (!isSavable(el)) return;

        queueIfChanged(baselineKey(el), fieldValue(el));
    }

    // Eingaben, deren change noch aussteht (Fokus noch im Feld), und
    // Entwürfe in die Queue holen
    function collectOpen() {
        Object.keys(drafts).forEach((field) => queueIfChanged(field, String(drafts[field])));
        const active = document.activeElement;
        if (active && root.contains(active)) onFieldEvent({ target: active });
    }

    // Vor der Sperre: alles Offene sofort senden. Erfüllt sich, sobald kein
    // Request mehr läuft, egal ob gespeichert oder gescheitert.
    function flushAll() {
        collectOpen();
        if (timer) clearTimeout(timer);
        flush();
        return new Promise((resolve) => {
            (function wait() {
                if (inFlight) setTimeout(wait, 100);
                else resolve();
            })();
        });
    }

    captureBaseline();
    root.addEventListener('change', onFieldEvent);
    // Textfelder auch bei blur sichern (v1-Parität: change/blur)
    root.addEventListener('focusout', (event) => {
        const el = event.target;
        if (el instanceof HTMLElement && el.matches('input[type="text"], input:not([type]), textarea')) {
            onFieldEvent(event);
        }
    });
    root.addEventListener('input', (event) => {
        const el = event.target;
        if (locked || !(el instanceof HTMLElement) || !el.matches(DRAFT_FIELDS) || !isSavable(el)) return;
        drafts[el.name] = el.value;
        writeDrafts();
    });

    // Seite geht weg (Link, Sperre durch den Server, Neuladen): Offenes per
    // keepalive hinterherschicken, ein normaler fetch bricht beim Entladen ab
    window.addEventListener('pagehide', () => {
        if (locked) return;
        collectOpen();
        const fields = Object.assign({}, inFlightFields, pending);
        if (Object.keys(fields).length === 0) return;
        fetch(saveUrl, {
            method: 'POST',
            keepalive: true,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ enr: enr, fields: fields }),
        }).catch(() => {});
    });

    if (locked) {
        setStatus(null, 'Gesperrt');
        drafts = {};
        writeDrafts();
    } else {
        // Entwürfe vom letzten Aufruf zurück ins Feld und speichern
        Object.keys(drafts).forEach((field) => {
            const el = root.querySelector('[name="' + CSS.escape(field) + '"]');
            if (el && el.matches(DRAFT_FIELDS) && isSavable(el)) el.value = String(drafts[field]);
        });
        if (Object.keys(drafts).length > 0) flushAll();
    }

    window.EnotfV2Autosave = {
        queue: queue,
        flushNow: () => { if (timer) clearTimeout(timer); flush(); },
        flushAll: flushAll,
        isLocked: () => locked,
        refreshPlausibility: refreshPlausibility,
        hasPending: () => Object.keys(pending).length > 0 || inFlight,
    };
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
