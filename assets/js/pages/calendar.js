/**
 * Calendar Page — initialisiert FullCalendar + wraps Create/Edit/Detail
 * Modals via Dialog.form / Dialog.
 *
 * Konfiguration kommt aus window.CalendarPageConfig (im Template gesetzt).
 * FullCalendar muss vorher geladen sein (assets/_ext/fullcalendar/...).
 */

import { Dialog } from '../ui/dialog.js';

(function () {
    'use strict';

    const CFG = window.CalendarPageConfig || {};
    const root = document.getElementById('calendar-grid');
    if (!root) return;

    if (!window.FullCalendar) {
        root.innerHTML = '<div class="ignis-alert ignis-alert--danger m-3">'
            + '<strong>FullCalendar nicht geladen.</strong> '
            + 'Bitte das Bundle in <code>assets/_ext/fullcalendar/index.global.min.js</code> einsetzen — siehe README.'
            + '</div>';
        return;
    }

    // ── State ───────────────────────────────────────────────────────────
    const filterState = {
        categories: new Set(),
    };
    document.querySelectorAll('.filter-category').forEach((cb) => {
        if (cb.checked) filterState.categories.add(cb.dataset.category);
    });

    // ── FullCalendar-Instance ───────────────────────────────────────────
    const calendar = new window.FullCalendar.Calendar(root, {
        locale: 'de',
        firstDay: 1,
        initialView: 'dayGridMonth',
        height: 'auto',
        nowIndicator: true,
        eventDisplay: 'block',
        headerToolbar: {
            left:   'prev,next today',
            center: 'title',
            right:  'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
        },
        buttonText: {
            today: 'Heute', month: 'Monat', week: 'Woche', day: 'Tag', list: 'Liste',
        },
        events: (info, success, failure) => {
            const url = CFG.eventsApiUrl
                + '?from=' + encodeURIComponent(info.startStr)
                + '&to='   + encodeURIComponent(info.endStr);
            fetch(url, { credentials: 'same-origin' })
                .then((r) => r.json())
                .then((data) => {
                    const events = Array.isArray(data) ? data : (data.events || []);
                    success(events.filter(applyFilters));
                })
                .catch(failure);
        },
        eventClick: (info) => {
            info.jsEvent.preventDefault();
            const realId = info.event.extendedProps?.eventId ?? info.event.id;
            openDetailModal(realId);
        },
        dateClick: (info) => {
            openCreateModal({ prefilledStart: info.dateStr });
        },
    });
    calendar.render();

    // Filter-Chip-Toggles in der Toolbar
    document.querySelectorAll('[data-category-chip]').forEach((chip) => {
        chip.addEventListener('click', (e) => {
            // Click auf das Label toggelt die hidden-Checkbox; Browser-Default
            // funktioniert wegen des assoziierten <input>. Wir lesen den
            // neuen Zustand asynchron im naechsten Tick.
            setTimeout(() => {
                const cb = chip.querySelector('.filter-category');
                if (!cb) return;
                const cat = cb.dataset.category;
                if (cb.checked) {
                    filterState.categories.add(cat);
                    chip.classList.add('is-active');
                } else {
                    filterState.categories.delete(cat);
                    chip.classList.remove('is-active');
                }
                calendar.refetchEvents();
            }, 0);
        });
    });

    function applyFilters(event) {
        const cat = event.extendedProps?.category;
        if (cat && !filterState.categories.has(cat)) return false;
        return true;
    }

    // ── Create-Modal ────────────────────────────────────────────────────
    function openCreateModal(opts = {}) {
        Dialog.form({
            title: 'Neuer Termin',
            template: 'calendarEventFormTemplate',
            formAction: CFG.createUrl,
            submitLabel: 'Erstellen',
            size: 'lg',
            onOpen: (dlg) => {
                bindFormDynamics(dlg.element);
                if (opts.prefilledStart) {
                    const startsInput = dlg.element.querySelector('[data-name="starts_at"]');
                    const endsInput   = dlg.element.querySelector('[data-name="ends_at"]');
                    const start = opts.prefilledStart + 'T09:00';
                    const end   = opts.prefilledStart + 'T10:00';
                    if (startsInput) startsInput.dataset.value = start;
                    if (endsInput)   endsInput.dataset.value   = end;
                }
            },
            onSubmit: (formData, dlg) => submitForm(formData, dlg),
        });
    }

    // ── Edit-Modal ──────────────────────────────────────────────────────
    function openEditModal(eventId) {
        fetch(CFG.eventApiUrl + '?id=' + encodeURIComponent(eventId), { credentials: 'same-origin' })
            .then((r) => r.json())
            .then((res) => {
                if (!res.success || !res.event) {
                    if (window.showToast) window.showToast('Termin konnte nicht geladen werden', 'error');
                    return;
                }
                Dialog.form({
                    title: 'Termin bearbeiten',
                    template: 'calendarEventFormTemplate',
                    formAction: CFG.updateUrl + '?id=' + encodeURIComponent(eventId),
                    submitLabel: 'Speichern',
                    size: 'lg',
                    onOpen: (dlg) => {
                        prefillForm(dlg.element, res.event);
                        bindFormDynamics(dlg.element);
                    },
                    onSubmit: (formData, dlg) => submitForm(formData, dlg),
                });
            })
            .catch(() => {
                if (window.showToast) window.showToast('Termin konnte nicht geladen werden', 'error');
            });
    }

    // ── Detail-Modal ────────────────────────────────────────────────────
    function openDetailModal(eventId) {
        fetch(CFG.viewUrl + '?id=' + encodeURIComponent(eventId), { credentials: 'same-origin' })
            .then((r) => r.text())
            .then((html) => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const detail = doc.querySelector('[data-calendar-event-detail]');
                if (!detail) {
                    window.location.href = CFG.viewUrl + '?id=' + encodeURIComponent(eventId);
                    return;
                }
                const dlg = new Dialog({
                    title: 'Termin',
                    body: detail.innerHTML,
                    size: 'lg',
                    actions: [
                        { label: 'Schließen', variant: 'ghost', onClick: (d) => d.close(null) },
                    ],
                    onOpen: (d) => {
                        // Edit-Buttons im Detail-HTML aktivieren
                        d.element.querySelectorAll('[data-edit-event]').forEach((btn) => {
                            btn.addEventListener('click', () => {
                                d.close(null);
                                openEditModal(parseInt(btn.dataset.editEvent, 10));
                            });
                        });
                    },
                });
                dlg.open();
            });
    }

    // ── Form-Submit (gemeinsam fuer Create + Edit) ──────────────────────
    function submitForm(_formData, dlg) {
        const form = dlg.element.querySelector('form');
        if (!form) return Promise.resolve();
        const fd = new FormData(form);
        return fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then((r) => {
                if (r.redirected) {
                    // Server antwortet mit Redirect (HTML-Form-Submit-Default).
                    // Wir refetchen Calendar + schliessen Dialog.
                    calendar.refetchEvents();
                    dlg.close('saved');
                    return;
                }
                return r.text().then((txt) => {
                    if (txt.includes('Validierung') || txt.includes('error')) {
                        // Kein dedizierter JSON-Endpoint — Failure einfach via
                        // Re-Render der Page abfangen.
                        window.location.reload();
                    } else {
                        calendar.refetchEvents();
                        dlg.close('saved');
                    }
                });
            })
            .catch(() => {
                if (window.showToast) window.showToast('Speichern fehlgeschlagen', 'error');
            });
    }

    // ── Form-Dynamics (Visibility-Toggle, Recurrence-UI, AllDay-Switch) ─
    function bindFormDynamics(scope) {
        const visSelect    = scope.querySelector('[name="visibility"]');
        const roleRow      = scope.querySelector('[data-visibility-role-row]');
        const attendeesRow = scope.querySelector('[data-visibility-attendees-row]');
        const recurToggle  = scope.querySelector('[data-recurrence-toggle]');
        const recurRow     = scope.querySelector('[data-recurrence-row]');
        const rruleOutput  = scope.querySelector('[data-rrule-output]');
        const freqSelect   = scope.querySelector('[data-rrule="freq"]');
        const intervalIn   = scope.querySelector('[data-rrule="interval"]');
        const bydayInputs  = scope.querySelectorAll('[data-rrule-byday]');
        const bydayRow     = scope.querySelector('[data-rrule-byday-row]');
        const untilIn      = scope.querySelector('[name="recurrence_until"]');
        const alldayToggle = scope.querySelector('[data-allday-toggle]');

        function applyVisibility() {
            const v = visSelect?.value;
            if (roleRow)      roleRow.style.display      = v === 'role'      ? '' : 'none';
            if (attendeesRow) attendeesRow.style.display = v === 'attendees' ? '' : 'none';
        }
        function applyRecurrence() {
            const on = recurToggle?.checked;
            if (recurRow) recurRow.style.display = on ? '' : 'none';
            if (bydayRow) bydayRow.style.display = freqSelect?.value === 'WEEKLY' ? '' : 'none';
            buildRrule();
        }
        function buildRrule() {
            if (!rruleOutput) return;
            if (!recurToggle?.checked) {
                rruleOutput.value = '';
                return;
            }
            const parts = ['FREQ=' + (freqSelect?.value || 'WEEKLY')];
            const interval = parseInt(intervalIn?.value || '1', 10);
            if (interval > 1) parts.push('INTERVAL=' + interval);
            if (freqSelect?.value === 'WEEKLY') {
                const days = Array.from(bydayInputs).filter((i) => i.checked).map((i) => i.dataset.rruleByday);
                if (days.length > 0) parts.push('BYDAY=' + days.join(','));
            }
            // recurrence_until kommt als separates Feld an den Server
            rruleOutput.value = parts.join(';');
        }

        function applyPickerType() {
            syncPickerType(scope, !!alldayToggle?.checked);
        }

        visSelect?.addEventListener('change', applyVisibility);
        recurToggle?.addEventListener('change', applyRecurrence);
        freqSelect?.addEventListener('change', applyRecurrence);
        intervalIn?.addEventListener('input', buildRrule);
        bydayInputs.forEach((i) => i.addEventListener('change', buildRrule));
        untilIn?.addEventListener('change', buildRrule);
        alldayToggle?.addEventListener('change', applyPickerType);

        applyVisibility();
        applyRecurrence();
        applyPickerType();
    }

    /**
     * Tauscht die Picker-Slots zwischen Datetime und Date je nach all_day.
     * Aktueller Wert wird gerettet, Slot neu aufgebaut, MutationObserver
     * der Picker-Module kuemmert sich um Auto-Init.
     */
    function syncPickerType(scope, allDay) {
        ['starts_at', 'ends_at'].forEach((fieldName) => {
            const slot = scope.querySelector(`[data-picker-slot="${fieldName}"]`);
            if (!slot) return;

            const currentValue = readPickerValue(slot);

            // Wenn der bereits gerenderte Slot schon den richtigen Typ hat,
            // nichts tun (verhindert Picker-Re-Init beim ersten applyPickerType).
            const hasDatetime = !!slot.querySelector('[data-ignis-datetimepicker]');
            const hasDate     = !!slot.querySelector('input[data-ignis-datepicker]');
            if (allDay && hasDate && !hasDatetime) return;
            if (!allDay && hasDatetime && !hasDate) return;

            slot.innerHTML = '';

            if (allDay) {
                const inp = document.createElement('input');
                inp.type = 'date';
                inp.className = 'ignis-input';
                inp.name = fieldName;
                inp.required = true;
                inp.setAttribute('data-ignis-datepicker', '');
                if (currentValue) inp.value = currentValue.slice(0, 10);
                slot.appendChild(inp);
            } else {
                const div = document.createElement('div');
                div.setAttribute('data-ignis-datetimepicker', '');
                div.dataset.name = fieldName;
                div.dataset.required = 'true';
                if (currentValue) {
                    // Wenn current nur ein Datum war (10 Zeichen), Default-Zeit ergaenzen.
                    div.dataset.value = currentValue.length >= 16 ? currentValue : (currentValue + 'T09:00');
                }
                slot.appendChild(div);
            }
        });
    }

    function readPickerValue(slot) {
        // Datetime-Picker schreibt seinen Wert in einen versteckten <input>
        const dtpHidden = slot.querySelector('[data-ignis-datetimepicker] input[type="hidden"]');
        if (dtpHidden && dtpHidden.value) return dtpHidden.value;
        // Datepicker = direktes <input type="date">
        const dpInput = slot.querySelector('input[type="date"]');
        if (dpInput && dpInput.value) return dpInput.value;
        // Fallback: data-value vom Mount-Element
        const mount = slot.querySelector('[data-ignis-datetimepicker], [data-ignis-datepicker]');
        return mount?.dataset.value || '';
    }

    /**
     * Befuellt das Edit-Form mit den Daten aus /api/kalender/event.
     * Wird VOR bindFormDynamics aufgerufen, damit applyPickerType() den
     * korrekten Wert aus den Inputs ablesen kann.
     */
    function prefillForm(scope, ev) {
        if (!ev) return;

        const set = (selector, value) => {
            const el = scope.querySelector(selector);
            if (el) el.value = value ?? '';
        };

        set('[name="title"]',        ev.title);
        set('[name="description"]',  ev.description);
        set('[name="location"]',     ev.location);
        set('[name="category"]',     ev.category);
        set('[name="color"]',        ev.color);
        set('[name="visibility"]',   ev.visibility);

        // Multi-Select fuer Rollen — setValues setzt die Tags + Hidden-Inputs.
        applyMultiSelectValues(scope, '[data-name="visibility_role_ids[]"]', ev.visibility_role_ids || []);

        // All-Day-Toggle
        const allday = scope.querySelector('[data-allday-toggle]');
        if (allday) allday.checked = !!ev.all_day;

        // Picker-Slots komplett neu aufbauen — der MutationObserver in
        // datepicker.js / datetimepicker.js initialisiert das frische Element
        // mit dem neuen data-value, was bei einer reinen dataset-Mutation
        // an einem schon initialisierten Picker NICHT passieren wuerde.
        const allDay = !!ev.all_day;
        ['starts_at', 'ends_at'].forEach((fieldName) => {
            const slot = scope.querySelector(`[data-picker-slot="${fieldName}"]`);
            if (!slot) return;
            const value = ev[fieldName] || '';
            slot.innerHTML = '';
            if (allDay) {
                const inp = document.createElement('input');
                inp.type = 'date';
                inp.className = 'ignis-input';
                inp.name = fieldName;
                inp.required = true;
                inp.setAttribute('data-ignis-datepicker', '');
                inp.value = value.slice(0, 10);
                slot.appendChild(inp);
            } else {
                const div = document.createElement('div');
                div.setAttribute('data-ignis-datetimepicker', '');
                div.dataset.name = fieldName;
                div.dataset.required = 'true';
                div.dataset.value = value;
                slot.appendChild(div);
            }
        });

        // Attendees-Multi-Select
        applyMultiSelectValues(scope, '[data-name="attendees[]"]', ev.attendees || []);

        // Recurrence-UI
        if (ev.recurrence_rule) {
            const recurToggle = scope.querySelector('[data-recurrence-toggle]');
            if (recurToggle) recurToggle.checked = true;
            const parts = parseRruleParts(ev.recurrence_rule);
            if (parts.freq) set('[data-rrule="freq"]', parts.freq);
            if (parts.interval) set('[data-rrule="interval"]', String(parts.interval));
            if (parts.byday) {
                scope.querySelectorAll('[data-rrule-byday]').forEach((cb) => {
                    cb.checked = parts.byday.includes(cb.dataset.rruleByday);
                });
            }
            // RRULE-Hidden-Output sofort befuellen, falls user direkt speichert
            const hidden = scope.querySelector('[data-rrule-output]');
            if (hidden) hidden.value = ev.recurrence_rule;
        }
        if (ev.recurrence_until) {
            set('[name="recurrence_until"]', ev.recurrence_until);
        }
    }

    /**
     * Setzt die Werte einer MultiSelect-Komponente. Wenn die Instanz noch
     * nicht existiert (MutationObserver hat noch nicht gefeuert), erzwingen
     * wir eine sofortige Initialisierung via dem globalen Konstruktor —
     * das vermeidet die Race-Condition beim Edit-Prefill, wo der User
     * sonst leere Felder sehen wuerde und beim Save die Werte ueberschrieben
     * waeren.
     */
    function applyMultiSelectValues(scope, selector, values) {
        const root = scope.querySelector(selector);
        if (!root || !Array.isArray(values) || values.length === 0) return;

        let inst = window.ignisMultiSelectGet?.(root);
        if (!inst && typeof window.MultiSelect === 'function') {
            // Forcierte Init — der MutationObserver feuert erst spaeter,
            // aber wir brauchen die Instanz JETZT.
            inst = new window.MultiSelect(root);
        }
        if (inst) {
            inst.setValues(values);
            return;
        }
        // Letzter Fallback: Retry im naechsten Frame
        requestAnimationFrame(() => {
            const i = window.ignisMultiSelectGet?.(root);
            if (i) i.setValues(values);
        });
    }

    function parseRruleParts(rule) {
        const out = { freq: null, interval: 1, byday: null };
        rule.split(';').forEach((seg) => {
            const [k, v] = seg.split('=');
            if (!k || !v) return;
            const key = k.toUpperCase();
            if (key === 'FREQ')     out.freq = v.toUpperCase();
            if (key === 'INTERVAL') out.interval = parseInt(v, 10) || 1;
            if (key === 'BYDAY')    out.byday = v.split(',').map((d) => d.toUpperCase());
        });
        return out;
    }

    // ── Quick-Action-Hook (Sidebar-+-Button) ────────────────────────────
    window.addEventListener('quick-action:calendar-event-create', () => openCreateModal());
    document.getElementById('btn-new-event')?.addEventListener('click', () => openCreateModal());

    // Auto-Open wenn ?action=create
    if (new URLSearchParams(location.search).get('action') === 'create') {
        openCreateModal();
    }
})();
