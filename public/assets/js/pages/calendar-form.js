/**
 * Terminformular (templates/calendar/_event-form.php): Sichtbarkeit blendet
 * Rollen oder Eingeladene ein, „Wiederholt sich" öffnet die Serienfelder
 * und schreibt die RRULE ins Hidden-Feld, „Ganztägig" tauscht die
 * Zeitwähler gegen Datumsfelder.
 *
 * Bindet sich selbst an jedes [data-calendar-event-form], auch an später
 * eingefügte (Drawer, assets/js/ui/drawer-form.js). calendar.js ruft
 * bindEventForm() für den Bearbeiten-Dialog, weil der sein Formular aus
 * einem <template> klont und erst danach füllt.
 */

export function bindEventForm(scope) {
    if (!scope || scope.dataset.calendarFormBound) return;
    scope.dataset.calendarFormBound = 'true';

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
        // track_attendance ist nur fuer role-Visibility relevant. Bei
        // anderen Visibility-Modi schaltet das Backend das Flag eh aus,
        // aber wir clearen den Frontend-State auch sichtbar.
        const trackBox = scope.querySelector('[data-track-attendance]');
        if (trackBox && v !== 'role') {
            trackBox.checked = false;
        }
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
export function syncPickerType(scope, allDay) {
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

export function readPickerValue(slot) {
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

function bindAll(root = document) {
    root.querySelectorAll('[data-calendar-event-form]').forEach(bindEventForm);
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => bindAll());
    } else {
        bindAll();
    }
    if (typeof MutationObserver !== 'undefined') {
        new MutationObserver((mutations) => {
            mutations.forEach((m) => {
                m.addedNodes.forEach((node) => {
                    if (!(node instanceof Element)) return;
                    if (node.matches('[data-calendar-event-form]')) bindEventForm(node);
                    bindAll(node);
                });
            });
        }).observe(document.documentElement, { childList: true, subtree: true });
    }
}
