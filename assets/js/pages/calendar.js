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
        // Lade Event-Daten via Detail-Endpoint, dann oeffne Form mit prefill
        fetch(CFG.viewUrl + '?id=' + encodeURIComponent(eventId), { credentials: 'same-origin' })
            .then((r) => r.text())
            .then((html) => {
                const data = parseEventDataFromDetailHtml(html);
                Dialog.form({
                    title: 'Termin bearbeiten',
                    template: 'calendarEventFormTemplate',
                    formAction: CFG.updateUrl + '?id=' + encodeURIComponent(eventId),
                    submitLabel: 'Speichern',
                    size: 'lg',
                    onOpen: (dlg) => {
                        bindFormDynamics(dlg.element);
                        prefillForm(dlg.element, data);
                    },
                    onSubmit: (formData, dlg) => submitForm(formData, dlg),
                });
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

    // ── Form-Dynamics (Visibility-Toggle, Recurrence-UI) ────────────────
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

        visSelect?.addEventListener('change', applyVisibility);
        recurToggle?.addEventListener('change', applyRecurrence);
        freqSelect?.addEventListener('change', applyRecurrence);
        intervalIn?.addEventListener('input', buildRrule);
        bydayInputs.forEach((i) => i.addEventListener('change', buildRrule));
        untilIn?.addEventListener('change', buildRrule);

        applyVisibility();
        applyRecurrence();
    }

    // ── Edit-Prefill aus Detail-HTML (kein dedizierter JSON-Detail-Endpoint) ──
    function parseEventDataFromDetailHtml(html) {
        // Minimal-Extractor; spaeter durch dedizierten /api/kalender/{id} ersetzen
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const root = doc.querySelector('[data-calendar-event-detail]');
        if (!root) return {};
        return {
            title:       root.querySelector('h2')?.textContent?.trim() || '',
            // weitere Felder werden aus der Edit-Form vom Server beim naechsten Save
            // ueberschrieben — fuer den ersten Wurf reicht der Titel als Referenz.
        };
    }
    function prefillForm(scope, data) {
        if (!data) return;
        if (data.title) {
            const titleInput = scope.querySelector('[name="title"]');
            if (titleInput) titleInput.value = data.title;
        }
        // Mehr Prefill-Felder werden in einem zukuenftigen JSON-Endpoint nachgereicht.
    }

    // ── Quick-Action-Hook (Sidebar-+-Button) ────────────────────────────
    window.addEventListener('quick-action:calendar-event-create', () => openCreateModal());
    document.getElementById('btn-new-event')?.addEventListener('click', () => openCreateModal());

    // Auto-Open wenn ?action=create
    if (new URLSearchParams(location.search).get('action') === 'create') {
        openCreateModal();
    }
})();
