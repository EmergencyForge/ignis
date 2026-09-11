/**
 * eNOTF v2 — Custom-Select (Progressive Enhancement).
 *
 * Der FiveM-Ingame-Browser (CEF) zeigt die nativen <select>-Popups teils
 * nicht an (v1 löst das mit enotf-custom-dropdown.dev.js). Diese Komponente
 * ersetzt die AUFKLAPP-Optik, nicht das Element: das native <select> bleibt
 * als Quelle der Wahrheit im DOM (visually hidden, aria-hidden, tabindex=-1)
 * — name/Optionen/selected unverändert. Daneben rendert die Komponente
 * einen Trigger-Button in ignis-input-Optik und ein Options-Panel, das an
 * <body> gemountet wird (position:fixed → kein Clipping durch Karten- oder
 * Dialog-Overflow).
 *
 * Jede Auswahl schreibt select.selectedIndex und dispatcht ein natives
 * 'change'-Event (bubbles). Dadurch funktionieren ohne Anpassung:
 *   - autosave.js (delegierter change-Listener auf [data-enotf-autosave]),
 *   - der Vehicle-Session-Check im Login (change auf #ev2-vehicle),
 *   - die Zugangs-Dialog-Logik (change auf art/ort füllt Ort/Größe/Seite).
 *
 * Init: automatisch für alle select.ignis-input auf v2-Seiten
 * (body[data-page="enotf-v2"]), inklusive später eingefügter Selects —
 * die dialog.js-Dialoge mounten an <body>, ein MutationObserver enhanced
 * deren Selects beim Öffnen. Opt-out per data-ev2-native.
 *
 * Options-Änderungen (innerHTML-Replace wie im Zugangs-Dialog oder
 * Join-Panel) und disabled-Toggles beobachtet ein Observer pro Select;
 * Trigger-Label und offenes Panel ziehen automatisch nach.
 *
 * Bedienung: Klick toggelt; Klick außerhalb, Escape oder Tab schließt;
 * Enter/Space/Pfeiltasten öffnen, Pfeile navigieren, Enter wählt,
 * Type-ahead auf Anfangsbuchstaben. Ab 8 sichtbaren Optionen bekommt
 * das Panel ein Suchfeld (Versorgung/Einsatzart, Wirkstoff-Liste).
 *
 * Escape/Enter laufen über einen Capture-Listener, der VOR dem von
 * dialog.js registriert wird (Modul-Load vs. Dialog-Open) und bei offenem
 * Panel stopImmediatePropagation ruft — sonst würde Escape den ganzen
 * Dialog schließen bzw. Enter die Primary-Action (Submit) auslösen.
 *
 * API (für Section-Skripte, normal nicht nötig):
 *   window.Ev2Select.enhance(select)  — einzelnes Select umstellen
 *   window.Ev2Select.refresh(select)  — Label/Panel neu aufbauen
 *   window.Ev2Select.init(root)       — alle Selects unterhalb root
 *
 * Zweite Komponente in dieser Datei: Ev2Suggest — Custom-Autocomplete
 * für Text-Inputs (Namensfelder). Ersetzt <datalist>, dessen Vorschlags-
 * Popup im FiveM-CEF ebenfalls nicht erscheint. Gleiche Panel-Optik und
 * Tastatur-Bedienung wie Ev2Select, aber Freitext bleibt erlaubt: Enter
 * ohne Pfeil-Navigation läuft normal weiter (kein Auswahlzwang). Quelle
 * ist ein serverseitig gerendertes JSON-Script-Tag:
 *
 *   <script type="application/json" data-ev2-suggest-source="KEY">[…]</script>
 *   <input data-ev2-suggest="KEY" …>
 *
 * Auswahl schreibt input.value und dispatcht input + change (bubbles) —
 * Autosave und Formular-Logik laufen unverändert weiter.
 */

const SELECTOR = 'select.ignis-input:not([data-ev2-native])';
const SUGGEST_SELECTOR = 'input[data-ev2-suggest]';
const SEARCH_THRESHOLD = 8;    // ab so vielen sichtbaren Optionen: Suchfeld
const TYPEAHEAD_MS = 600;

const bySelect = new WeakMap();
const instances = new Set();
let openInstance = null;

// ─────────────────────────────────────────────────────────────────────
// Instanz
// ─────────────────────────────────────────────────────────────────────

function enhance(select) {
    if (!(select instanceof HTMLSelectElement)) return null;
    if (bySelect.has(select)) return bySelect.get(select);
    if (select.multiple || select.matches('[data-ev2-native]')) return null;

    const inst = {
        select,
        wrapper: null,
        trigger: null,
        label: null,
        panel: null,
        list: null,
        searchInput: null,
        activeIndex: -1,
        typeBuffer: '',
        typeTimer: null,
        observer: null,
    };

    // Wrapper + Trigger neben dem Select (Select bleibt im DOM/im
    // Autosave-Root, wird nur visuell versteckt)
    const wrapper = document.createElement('div');
    wrapper.className = 'ev2-select';
    select.parentNode.insertBefore(wrapper, select);

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'ignis-input ev2-select__trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');

    const label = document.createElement('span');
    label.className = 'ev2-select__label';
    trigger.appendChild(label);

    const chev = document.createElement('i');
    chev.className = 'fa-solid fa-chevron-down ev2-select__chev';
    chev.setAttribute('aria-hidden', 'true');
    trigger.appendChild(chev);

    wrapper.appendChild(select);
    wrapper.appendChild(trigger);

    select.classList.add('ev2-select__native');
    select.setAttribute('aria-hidden', 'true');
    select.setAttribute('tabindex', '-1');

    inst.wrapper = wrapper;
    inst.trigger = trigger;
    inst.label = label;

    trigger.addEventListener('click', () => {
        if (openInstance === inst) closePanel();
        else openPanel(inst);
    });
    trigger.addEventListener('keydown', (ev) => {
        if (openInstance === inst) return; // offenes Panel: globaler Handler
        if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
            ev.preventDefault();
            openPanel(inst);
        }
    });

    // Externe Wertänderungen (auch die eigenen) → Label nachziehen
    select.addEventListener('change', () => updateTrigger(inst));

    // Options-Replace (innerHTML) und disabled-Toggles beobachten
    inst.observer = new MutationObserver(() => {
        if (!select.isConnected) { destroy(inst); return; }
        updateTrigger(inst);
        if (openInstance === inst) renderOptions(inst);
    });
    inst.observer.observe(select, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['disabled', 'selected', 'hidden'],
    });

    updateTrigger(inst);

    bySelect.set(select, inst);
    instances.add(inst);
    return inst;
}

function destroy(inst) {
    if (openInstance === inst) closePanel();
    if (inst.observer) inst.observer.disconnect();
    instances.delete(inst);
    bySelect.delete(inst.select);
    // Wenn der Wrapper noch im DOM hängt (Select programmatisch entfernt),
    // Trigger mit aufräumen — das native Select bleibt, wo es ist.
    if (inst.wrapper && inst.wrapper.isConnected && !inst.select.isConnected) {
        inst.wrapper.remove();
    }
}

// ─────────────────────────────────────────────────────────────────────
// Trigger
// ─────────────────────────────────────────────────────────────────────

function selectedOption(select) {
    return select.selectedIndex >= 0 ? select.options[select.selectedIndex] : null;
}

function updateTrigger(inst) {
    const { select, trigger, label } = inst;
    const opt = selectedOption(select);
    const text = opt ? opt.text.trim() : '';
    const isPlaceholder = !opt || opt.value === '';

    label.textContent = text !== '' ? text : (select.dataset.placeholder || '–');
    label.classList.toggle('is-placeholder', isPlaceholder);
    trigger.disabled = select.disabled;
}

// ─────────────────────────────────────────────────────────────────────
// Panel
// ─────────────────────────────────────────────────────────────────────

function visibleOptions(select) {
    return Array.from(select.options).filter((o) => !o.hidden);
}

function openPanel(inst) {
    if (inst.select.disabled) return;
    if (openInstance) closePanel();

    const panel = document.createElement('div');
    panel.className = 'ev2-select-panel';
    panel.setAttribute('role', 'listbox');

    const withSearch = visibleOptions(inst.select).length >= SEARCH_THRESHOLD;
    if (withSearch) {
        const box = document.createElement('div');
        box.className = 'ev2-select-panel__search';
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'ignis-input';
        input.placeholder = 'Suchen…';
        input.autocomplete = 'off';
        input.addEventListener('input', () => filterOptions(inst, input.value));
        box.appendChild(input);
        panel.appendChild(box);
        inst.searchInput = input;
    } else {
        inst.searchInput = null;
    }

    const list = document.createElement('div');
    list.className = 'ev2-select-panel__list';
    panel.appendChild(list);

    inst.panel = panel;
    inst.list = list;
    openInstance = inst;

    renderOptions(inst);
    document.body.appendChild(panel);
    positionPanel(inst);

    inst.trigger.setAttribute('aria-expanded', 'true');
    inst.wrapper.classList.add('is-open');

    if (inst.searchInput) inst.searchInput.focus();
    else inst.trigger.focus();

    scrollActiveIntoView(inst);
}

function closePanel(refocus = false) {
    const inst = openInstance;
    if (!inst) return;
    openInstance = null;

    if (inst.panel) inst.panel.remove();
    inst.panel = null;
    inst.list = null;
    inst.searchInput = null;
    inst.activeIndex = -1;
    inst.typeBuffer = '';

    inst.trigger.setAttribute('aria-expanded', 'false');
    inst.wrapper.classList.remove('is-open');
    if (refocus && inst.trigger.isConnected) inst.trigger.focus();
}

function renderOptions(inst) {
    const { select, list } = inst;
    if (!list) return;

    list.innerHTML = '';
    let firstEnabled = -1;

    Array.from(select.options).forEach((opt, index) => {
        if (opt.hidden) return;
        const item = document.createElement('div');
        item.className = 'ev2-select-panel__option';
        item.dataset.index = String(index);
        item.setAttribute('role', 'option');
        item.textContent = opt.text;
        if (opt.disabled) {
            item.classList.add('is-disabled');
            item.setAttribute('aria-disabled', 'true');
        } else if (firstEnabled === -1) {
            firstEnabled = index;
        }
        if (index === select.selectedIndex) {
            item.classList.add('is-selected');
            item.setAttribute('aria-selected', 'true');
        }
        if (!opt.disabled) {
            item.addEventListener('click', () => choose(inst, index));
        }
        list.appendChild(item);
    });

    if (list.children.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'ev2-select-panel__empty';
        empty.textContent = 'Keine Optionen';
        list.appendChild(empty);
    }

    // Aktiv: gewählte Option, sonst erste wählbare
    const sel = selectedOption(select);
    inst.activeIndex = sel && !sel.hidden && !sel.disabled ? select.selectedIndex : firstEnabled;
    markActive(inst);
}

function filterOptions(inst, query) {
    const { list } = inst;
    if (!list) return;
    const q = query.trim().toLowerCase();
    let firstVisible = -1;

    Array.from(list.children).forEach((item) => {
        if (!item.classList.contains('ev2-select-panel__option')) return;
        const hit = q === '' || item.textContent.toLowerCase().includes(q);
        item.classList.toggle('is-filtered', !hit);
        if (hit && firstVisible === -1 && !item.classList.contains('is-disabled')) {
            firstVisible = parseInt(item.dataset.index, 10);
        }
    });

    let emptyMsg = list.querySelector('.ev2-select-panel__noresult');
    if (firstVisible === -1 && q !== '') {
        if (!emptyMsg) {
            emptyMsg = document.createElement('div');
            emptyMsg.className = 'ev2-select-panel__noresult';
            emptyMsg.textContent = 'Keine Treffer';
            list.appendChild(emptyMsg);
        }
    } else if (emptyMsg) {
        emptyMsg.remove();
    }

    inst.activeIndex = firstVisible;
    markActive(inst);
    positionPanel(inst);
}

function positionPanel(inst) {
    const { panel, trigger, list } = inst;
    if (!panel || !trigger.isConnected) { closePanel(); return; }

    const rect = trigger.getBoundingClientRect();
    const vh = window.innerHeight;
    const vw = window.innerWidth;
    const gap = 4;
    const margin = 8;

    const spaceBelow = vh - rect.bottom - gap - margin;
    const spaceAbove = rect.top - gap - margin;
    const searchH = inst.searchInput ? inst.searchInput.parentElement.offsetHeight : 0;

    // Unterhalb, wenn genug Platz — sonst auf die größere Seite
    const below = spaceBelow >= 160 || spaceBelow >= spaceAbove;
    const avail = Math.max(90, (below ? spaceBelow : spaceAbove) - searchH);
    list.style.maxHeight = Math.min(300, avail) + 'px';

    panel.style.width = rect.width + 'px';
    panel.style.left = Math.max(margin, Math.min(rect.left, vw - rect.width - margin)) + 'px';

    const panelH = panel.offsetHeight;
    panel.style.top = (below
        ? Math.min(rect.bottom + gap, vh - panelH - margin)
        : Math.max(margin, rect.top - gap - panelH)) + 'px';
}

// ─────────────────────────────────────────────────────────────────────
// Auswahl + Tastatur
// ─────────────────────────────────────────────────────────────────────

function choose(inst, index) {
    const { select } = inst;
    const opt = select.options[index];
    if (!opt || opt.disabled) return;

    closePanel(true);

    if (select.selectedIndex !== index) {
        select.selectedIndex = index;
        // Natives change (bubbles) — Autosave, Login-Session-Check und
        // Dialog-Logik hängen an genau diesem Event.
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }
    updateTrigger(inst);
}

function navigableIndexes(inst) {
    const { select, list } = inst;
    return Array.from(select.options)
        .map((opt, index) => ({ opt, index }))
        .filter(({ opt, index }) => {
            if (opt.hidden || opt.disabled) return false;
            if (!list) return true;
            const item = list.querySelector('[data-index="' + index + '"]');
            return !item || !item.classList.contains('is-filtered');
        })
        .map(({ index }) => index);
}

function markActive(inst) {
    const { list, activeIndex } = inst;
    if (!list) return;
    Array.from(list.children).forEach((item) => {
        item.classList.toggle('is-active', parseInt(item.dataset.index, 10) === activeIndex);
    });
}

function scrollActiveIntoView(inst) {
    if (!inst.list) return;
    const item = inst.list.querySelector('.is-active');
    if (item) item.scrollIntoView({ block: 'nearest' });
}

function moveActive(inst, delta, edge) {
    const idx = navigableIndexes(inst);
    if (idx.length === 0) return;
    let pos = idx.indexOf(inst.activeIndex);
    if (edge === 'home') pos = 0;
    else if (edge === 'end') pos = idx.length - 1;
    else if (pos === -1) pos = delta > 0 ? 0 : idx.length - 1;
    else pos = Math.max(0, Math.min(idx.length - 1, pos + delta));
    inst.activeIndex = idx[pos];
    markActive(inst);
    scrollActiveIntoView(inst);
}

function typeahead(inst, char) {
    if (inst.typeTimer) clearTimeout(inst.typeTimer);
    inst.typeBuffer += char.toLowerCase();
    inst.typeTimer = setTimeout(() => { inst.typeBuffer = ''; }, TYPEAHEAD_MS);

    const idx = navigableIndexes(inst);
    if (idx.length === 0) return;
    const start = Math.max(0, idx.indexOf(inst.activeIndex));
    // Ab der aktiven Position suchen (1-Buchstaben-Buffer: dahinter),
    // damit wiederholtes Tippen desselben Buchstabens durchrotiert
    const offset = inst.typeBuffer.length === 1 ? 1 : 0;
    for (let i = 0; i < idx.length; i++) {
        const candidate = idx[(start + offset + i) % idx.length];
        const text = inst.select.options[candidate].text.trim().toLowerCase();
        if (text.startsWith(inst.typeBuffer)) {
            inst.activeIndex = candidate;
            markActive(inst);
            scrollActiveIntoView(inst);
            return;
        }
    }
}

// Capture-Handler: läuft VOR dem keydown-Handler von dialog.js (der wird
// erst beim Dialog-Open registriert). stopImmediatePropagation verhindert
// bei offenem Panel, dass Escape den Dialog schließt bzw. Enter dessen
// Primary-Action (Submit) auslöst.
document.addEventListener('keydown', (ev) => {
    const inst = openInstance;
    if (!inst) return;

    switch (ev.key) {
        case 'Escape':
            ev.preventDefault();
            ev.stopImmediatePropagation();
            closePanel(true);
            return;
        case 'Enter':
            ev.preventDefault();
            ev.stopImmediatePropagation();
            if (inst.activeIndex >= 0) choose(inst, inst.activeIndex);
            else closePanel(true);
            return;
        case 'ArrowDown':
            ev.preventDefault();
            ev.stopImmediatePropagation();
            moveActive(inst, 1);
            return;
        case 'ArrowUp':
            ev.preventDefault();
            ev.stopImmediatePropagation();
            moveActive(inst, -1);
            return;
        case 'Home':
        case 'End':
            if (!inst.searchInput) {
                ev.preventDefault();
                ev.stopImmediatePropagation();
                moveActive(inst, 0, ev.key === 'Home' ? 'home' : 'end');
            }
            return;
        case 'Tab':
            // Panel schließen, Fokuswechsel normal weiterlaufen lassen
            closePanel();
            return;
        default:
            // Type-ahead nur ohne Suchfeld (mit Suchfeld tippt man dort)
            if (!inst.searchInput && ev.key.length === 1 && !ev.ctrlKey && !ev.metaKey && !ev.altKey) {
                ev.preventDefault();
                typeahead(inst, ev.key);
            }
    }
}, true);

// Klick außerhalb schließt (capture: unabhängig von stopPropagation in
// anderen Handlern; pointerdown feuert auch im CEF zuverlässig)
document.addEventListener('pointerdown', (ev) => {
    const inst = openInstance;
    if (!inst) return;
    const t = ev.target;
    if (inst.panel.contains(t) || inst.trigger.contains(t)) return;
    closePanel();
}, true);

// Scroll/Resize: Panel folgt dem Trigger (Dialog-Body scrollt mit capture)
document.addEventListener('scroll', () => {
    if (openInstance) positionPanel(openInstance);
    if (openSuggest) positionSuggestPanel(openSuggest);
}, true);
window.addEventListener('resize', () => {
    if (openInstance) positionPanel(openInstance);
    if (openSuggest) positionSuggestPanel(openSuggest);
});

// ─────────────────────────────────────────────────────────────────────
// Ev2Suggest — Autocomplete für Text-Inputs (datalist-Ersatz)
// ─────────────────────────────────────────────────────────────────────

const suggestLists = new Map();          // Quell-Key → string[]
const bySuggestInput = new WeakMap();
const suggestInstances = new Set();
let openSuggest = null;

function suggestItems(key) {
    if (suggestLists.has(key)) return suggestLists.get(key);
    let items = [];
    const source = document.querySelector(
        'script[type="application/json"][data-ev2-suggest-source="' + key + '"]'
    );
    if (source) {
        try {
            const parsed = JSON.parse(source.textContent);
            if (Array.isArray(parsed)) {
                items = parsed.map(String).filter((s) => s.trim() !== '');
            }
        } catch (err) {
            // fehlerhafte Quelle → Feld bleibt reines Freitextfeld
        }
    }
    suggestLists.set(key, items);
    return items;
}

function enhanceSuggest(input) {
    if (!(input instanceof HTMLInputElement)) return null;
    if (bySuggestInput.has(input)) return bySuggestInput.get(input);

    const inst = {
        input,
        key: input.dataset.ev2Suggest || '',
        panel: null,
        list: null,
        matches: [],
        activeIndex: -1,
        squelch: false,   // eigenes input-Event nach choose() ignorieren
    };

    input.setAttribute('autocomplete', 'off');

    input.addEventListener('focus', () => openSuggestPanel(inst));
    input.addEventListener('click', () => {
        if (openSuggest !== inst) openSuggestPanel(inst);
    });
    input.addEventListener('input', () => {
        if (inst.squelch) { inst.squelch = false; return; }
        if (openSuggest === inst) renderSuggest(inst);
        else openSuggestPanel(inst);
    });
    input.addEventListener('keydown', (ev) => suggestKeydown(inst, ev));
    // Panel-Klicks blurren nicht (pointerdown im Panel ist preventDefault),
    // echtes Verlassen des Felds schließt also zuverlässig.
    input.addEventListener('blur', () => {
        if (openSuggest === inst) closeSuggestPanel();
    });

    bySuggestInput.set(input, inst);
    suggestInstances.add(inst);
    return inst;
}

function destroySuggest(inst) {
    if (openSuggest === inst) closeSuggestPanel();
    suggestInstances.delete(inst);
    bySuggestInput.delete(inst.input);
}

function openSuggestPanel(inst) {
    if (inst.input.disabled || inst.input.readOnly) return;
    if (openSuggest === inst) { renderSuggest(inst); return; }
    if (openSuggest) closeSuggestPanel();
    if (openInstance) closePanel();

    const panel = document.createElement('div');
    panel.className = 'ev2-select-panel';
    panel.setAttribute('role', 'listbox');
    // Klick ins Panel darf den Input nicht blurren (blur schließt sonst
    // das Panel, bevor der click auf der Option ankommt)
    panel.addEventListener('pointerdown', (ev) => ev.preventDefault());

    const list = document.createElement('div');
    list.className = 'ev2-select-panel__list';
    panel.appendChild(list);

    inst.panel = panel;
    inst.list = list;
    openSuggest = inst;
    renderSuggest(inst);
}

function closeSuggestPanel() {
    const inst = openSuggest;
    if (!inst) return;
    openSuggest = null;
    if (inst.panel) inst.panel.remove();
    inst.panel = null;
    inst.list = null;
    inst.matches = [];
    inst.activeIndex = -1;
}

function renderSuggest(inst) {
    const q = inst.input.value.trim().toLowerCase();
    const matches = suggestItems(inst.key).filter(
        (name) => q === '' || name.toLowerCase().includes(q)
    );

    // Nichts (mehr) vorzuschlagen — auch wenn der Wert bereits exakt der
    // einzige Treffer ist: Panel zu, Freitext läuft normal weiter.
    if (matches.length === 0 || (matches.length === 1 && matches[0].toLowerCase() === q)) {
        closeSuggestPanel();
        return;
    }

    inst.matches = matches;
    inst.activeIndex = -1;   // kein Auswahlzwang: Enter ohne Pfeile = Freitext

    const list = inst.list;
    list.innerHTML = '';
    matches.forEach((name, i) => {
        const item = document.createElement('div');
        item.className = 'ev2-select-panel__option';
        item.dataset.i = String(i);
        item.setAttribute('role', 'option');
        item.textContent = name;
        item.addEventListener('click', () => chooseSuggest(inst, i));
        list.appendChild(item);
    });

    if (!inst.panel.isConnected) document.body.appendChild(inst.panel);
    positionSuggestPanel(inst);
}

function positionSuggestPanel(inst) {
    const { panel, list, input } = inst;
    if (!panel || !input.isConnected) { closeSuggestPanel(); return; }

    const rect = input.getBoundingClientRect();
    const vh = window.innerHeight;
    const vw = window.innerWidth;
    const gap = 4;
    const margin = 8;

    const spaceBelow = vh - rect.bottom - gap - margin;
    const spaceAbove = rect.top - gap - margin;
    const below = spaceBelow >= 160 || spaceBelow >= spaceAbove;
    list.style.maxHeight = Math.min(300, Math.max(90, below ? spaceBelow : spaceAbove)) + 'px';

    panel.style.width = rect.width + 'px';
    panel.style.left = Math.max(margin, Math.min(rect.left, vw - rect.width - margin)) + 'px';

    const panelH = panel.offsetHeight;
    panel.style.top = (below
        ? Math.min(rect.bottom + gap, vh - panelH - margin)
        : Math.max(margin, rect.top - gap - panelH)) + 'px';
}

function chooseSuggest(inst, index) {
    const name = inst.matches[index];
    if (name === undefined) return;

    closeSuggestPanel();

    if (inst.input.value !== name) {
        inst.input.value = name;
        // input + change (bubbles) — Autosave und Formular-Logik hängen
        // an genau diesen Events; squelch verhindert, dass das eigene
        // input-Event das Panel sofort wieder öffnet.
        inst.squelch = true;
        inst.input.dispatchEvent(new Event('input', { bubbles: true }));
        inst.input.dispatchEvent(new Event('change', { bubbles: true }));
    }
    inst.input.focus();
}

function moveSuggestActive(inst, delta) {
    if (inst.matches.length === 0) return;
    if (inst.activeIndex === -1) {
        inst.activeIndex = delta > 0 ? 0 : inst.matches.length - 1;
    } else {
        inst.activeIndex = Math.max(0, Math.min(inst.matches.length - 1, inst.activeIndex + delta));
    }
    Array.from(inst.list.children).forEach((item) => {
        item.classList.toggle('is-active', parseInt(item.dataset.i, 10) === inst.activeIndex);
    });
    const active = inst.list.querySelector('.is-active');
    if (active) active.scrollIntoView({ block: 'nearest' });
}

function suggestKeydown(inst, ev) {
    if (openSuggest !== inst) {
        if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
            ev.preventDefault();
            openSuggestPanel(inst);
        }
        return;
    }
    switch (ev.key) {
        case 'ArrowDown':
            ev.preventDefault();
            moveSuggestActive(inst, 1);
            return;
        case 'ArrowUp':
            ev.preventDefault();
            moveSuggestActive(inst, -1);
            return;
        case 'Enter':
            // Nur mit aktiv navigiertem Vorschlag übernehmen — sonst
            // normales Enter-Verhalten (Freitext, Formular-Submit)
            if (inst.activeIndex >= 0) {
                ev.preventDefault();
                ev.stopPropagation();
                chooseSuggest(inst, inst.activeIndex);
            } else {
                closeSuggestPanel();
            }
            return;
        case 'Escape':
            ev.preventDefault();
            ev.stopPropagation();
            closeSuggestPanel();
            return;
        case 'Tab':
            closeSuggestPanel();
            return;
        default:
    }
}

// Klick außerhalb schließt das Vorschlags-Panel
document.addEventListener('pointerdown', (ev) => {
    const inst = openSuggest;
    if (!inst) return;
    const t = ev.target;
    if (inst.panel.contains(t) || inst.input === t) return;
    closeSuggestPanel();
}, true);

// ─────────────────────────────────────────────────────────────────────
// Auto-Init + dynamische Inhalte (dialog.js mountet an <body>)
// ─────────────────────────────────────────────────────────────────────

function initRoot(root) {
    if (root instanceof HTMLElement) {
        if (root.matches(SELECTOR)) enhance(root);
        if (root.matches(SUGGEST_SELECTOR)) enhanceSuggest(root);
    }
    (root || document).querySelectorAll(SELECTOR).forEach(enhance);
    (root || document).querySelectorAll(SUGGEST_SELECTOR).forEach(enhanceSuggest);
}

function init() {
    if (!document.body || document.body.dataset.page !== 'enotf-v2') return;

    initRoot(document);

    new MutationObserver((mutations) => {
        let removed = false;
        mutations.forEach((m) => {
            m.addedNodes.forEach((node) => {
                if (node instanceof HTMLElement) initRoot(node);
            });
            if (m.removedNodes.length > 0) removed = true;
        });
        if (removed) {
            instances.forEach((inst) => {
                if (!inst.select.isConnected) destroy(inst);
            });
            suggestInstances.forEach((inst) => {
                if (!inst.input.isConnected) destroySuggest(inst);
            });
        }
    }).observe(document.body, { childList: true, subtree: true });
}

window.Ev2Select = {
    enhance,
    init: initRoot,
    refresh(select) {
        const inst = bySelect.get(select);
        if (!inst) return;
        updateTrigger(inst);
        if (openInstance === inst) renderOptions(inst);
    },
};

window.Ev2Suggest = {
    enhance: enhanceSuggest,
    init: initRoot,
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
