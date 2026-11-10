// Ausbildung: Seitenmeldungen des PDF-Betrachters, Text bis zum Ende,
// Videos in Artikeln, Matrix, Lehrplan ziehen und umschalten, Formular
// der Lerneinheit.
// Jeder Teil sucht seine Elemente selbst und tut ohne sie nichts.

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

function toast(message) {
    if (typeof window.showToast === 'function') window.showToast(message, 'error');
    else window.alert(message);
}

async function post(url, data, { json = false, keepalive = false } = {}) {
    const headers = { 'X-CSRF-Token': csrf(), Accept: 'application/json' };
    let body;
    if (json) {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify(data);
    } else {
        body = new URLSearchParams({ ...data, csrf_token: csrf() });
    }
    const response = await fetch(url, { method: 'POST', headers, body, credentials: 'same-origin', keepalive });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(result.error || 'Das hat nicht geklappt.');
    return result;
}

// Tabs öffnen den Reiter aus dem Anker, damit Weiterleitungen nach einem
// Speichern dort landen, wo das Formular war.
function openTabFromHash() {
    const id = decodeURIComponent(location.hash.slice(1));
    if (!id) return;
    const header = [...document.querySelectorAll('.ignis-tabs__header')].find((h) => h.dataset.tab === id);
    header?.click();
}

function trackUnit() {
    const root = document.querySelector('[data-training-unit][data-track="1"]');
    if (!root) return;

    const viewer = root.querySelector('[data-ignis-document-viewer]');
    viewer?.addEventListener('ignis:document-pages', (event) => {
        const { pages, total } = event.detail;
        // keepalive: die letzte Meldung kommt beim Verlassen der Seite.
        post(viewer.dataset.pagesUrl, { course: Number(viewer.dataset.course), pages, total }, { json: true, keepalive: true }).catch(() => {});
    });

    const end = root.querySelector('[data-training-end]');
    if (end && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            if (!entries.some((e) => e.isIntersecting)) return;
            observer.disconnect();
            post(root.dataset.doneUrl, {}).catch(() => {});
        });
        observer.observe(end);
    }
}

// Videos in Artikeln: vor dem Klick geht nichts an YouTube oder Vimeo. Der
// Renderer prüft die Werte schon, hier noch einmal, weil daraus eine fremde
// Adresse wird (Regeln wie Video::isValid() im Editor-Paket).
function videos() {
    document.addEventListener('click', (event) => {
        const figure = event.target.closest?.('.efe-video__play')?.closest('figure.efe-video');
        if (!figure) return;
        const provider = figure.getAttribute('data-provider');
        const id = figure.getAttribute('data-id') || '';
        const hash = figure.getAttribute('data-hash');
        let src = null;
        if (provider === 'youtube' && /^[A-Za-z0-9_-]{11}$/.test(id) && hash === null) {
            src = `https://www.youtube-nocookie.com/embed/${id}?autoplay=1&rel=0`;
        } else if (provider === 'vimeo' && /^\d{1,12}$/.test(id) && (hash === null || /^[a-f0-9]{6,20}$/.test(hash))) {
            src = `https://player.vimeo.com/video/${id}?autoplay=1&dnt=1${hash ? `&h=${hash}` : ''}`;
        }
        if (!src) return;

        const frame = document.createElement('iframe');
        frame.src = src;
        frame.title = provider === 'youtube' ? 'YouTube-Video' : 'Vimeo-Video';
        frame.allow = 'autoplay; fullscreen; picture-in-picture; encrypted-media';
        frame.allowFullscreen = true;
        frame.referrerPolicy = 'strict-origin-when-cross-origin';
        frame.className = 'efe-video__frame';
        figure.replaceChildren(frame);
        frame.focus();
    });
}

function matrix() {
    const root = document.querySelector('[data-training-matrix]');
    if (!root) return;

    document.querySelector('[data-training-behind]')?.addEventListener('change', (event) => {
        root.classList.toggle('tr-matrix--behind', event.target.checked);
    });

    root.addEventListener('click', async (event) => {
        const cell = event.target.closest('button.tr-cell');
        if (!cell || cell.disabled) return;
        const done = cell.dataset.state !== 'hand';
        cell.disabled = true;
        try {
            await post(root.dataset.url, { participation: cell.dataset.participation, unit: cell.dataset.unit, done: done ? '1' : '0' });
            const state = done ? 'hand' : 'open';
            cell.dataset.state = state;
            cell.className = `tr-cell tr-cell--${state}`;
            cell.innerHTML = done ? '<i class="fa-solid fa-check" aria-hidden="true"></i>' : '';
            const label = done ? 'Von Hand gesetzt' : 'Offen';
            cell.dataset.ignisTooltip = label;
            cell.setAttribute('aria-label', cell.getAttribute('aria-label').replace(/: [^:]*$/, `: ${label}`));
        } catch (error) {
            toast(error.message);
        } finally {
            cell.disabled = false;
        }
    });
}

function switches() {
    document.querySelectorAll('[data-training-required], [data-training-sequential]').forEach((input) => {
        input.addEventListener('change', async () => {
            const url = input.dataset.trainingRequired || input.dataset.trainingSequential;
            const key = input.dataset.trainingRequired ? 'required' : 'sequential';
            try {
                await post(url, { [key]: input.checked ? '1' : '0' });
            } catch (error) {
                input.checked = !input.checked;
                toast(error.message);
            }
        });
    });
}

// Ziehen mit der Maus oder per Pfeiltaste am Griff. Nach jeder Änderung
// geht die ganze Reihenfolge an den Server; schlägt das fehl, lädt die
// Seite neu, damit sie wieder dem Stand dort entspricht.
function curriculum() {
    const root = document.querySelector('[data-training-curriculum]');
    if (!root) return;

    const save = () => {
        const modules = [...root.querySelectorAll('[data-module]')];
        const units = {};
        modules.forEach((m) => { units[m.dataset.module] = [...m.querySelectorAll('[data-unit]')].map((u) => Number(u.dataset.unit)); });
        post(root.dataset.orderUrl, { modules: modules.map((m) => Number(m.dataset.module)), units }, { json: true })
            .catch((error) => { toast(error.message); location.reload(); });
    };

    let dragged = null;
    root.addEventListener('dragstart', (event) => {
        dragged = event.target.closest('[data-unit]') ?? event.target.closest('[data-module]');
        if (!dragged) return;
        event.stopPropagation();
        event.dataTransfer.effectAllowed = 'move';
        dragged.classList.add('is-dragging');
    });
    root.addEventListener('dragend', () => {
        if (!dragged) return;
        dragged.classList.remove('is-dragging');
        dragged = null;
        save();
    });
    root.addEventListener('dragover', (event) => {
        if (!dragged) return;
        event.preventDefault();
        if (dragged.dataset.unit) {
            const list = event.target.closest('[data-module]')?.querySelector('[data-units]');
            if (!list) return;
            const over = event.target.closest('[data-unit]');
            if (over && over !== dragged) {
                const after = event.clientY > over.getBoundingClientRect().top + over.offsetHeight / 2;
                over.parentElement.insertBefore(dragged, after ? over.nextSibling : over);
            } else if (!over) {
                list.appendChild(dragged);
            }
        } else {
            const over = event.target.closest('[data-module]');
            if (!over || over === dragged) return;
            const after = event.clientY > over.getBoundingClientRect().top + over.offsetHeight / 2;
            root.insertBefore(dragged, after ? over.nextSibling : over);
        }
    });

    root.addEventListener('keydown', (event) => {
        const grip = event.target.closest('[data-grip]');
        if (!grip || (event.key !== 'ArrowUp' && event.key !== 'ArrowDown')) return;
        event.preventDefault();
        const item = grip.closest('[data-unit]') ?? grip.closest('[data-module]');
        const up = event.key === 'ArrowUp';
        const sibling = up ? item.previousElementSibling : item.nextElementSibling;
        if (sibling) {
            item.parentElement.insertBefore(item, up ? sibling : sibling.nextSibling);
        } else if (item.dataset.unit) {
            // Am Rand eines Moduls wandert die Einheit ins Nachbarmodul.
            const module = item.closest('[data-module]');
            const next = up ? module.previousElementSibling : module.nextElementSibling;
            const list = next?.querySelector('[data-units]');
            if (!list) return;
            if (up) list.appendChild(item); else list.prepend(item);
        } else {
            return;
        }
        grip.focus();
        save();
    });
}

function unitForm() {
    const form = document.querySelector('[data-training-unit-form]');
    if (!form) return;

    const kind = form.querySelector('[data-training-kind]');
    const show = () => form.querySelectorAll('.tr-kind-fields').forEach((block) => { block.hidden = block.dataset.kind !== kind.value; });
    kind.addEventListener('change', show);
    show();

    const mount = form.querySelector('[data-training-editor]');
    const hidden = form.querySelector('input[name="doc"]');
    if (!mount || !hidden) return;

    const uploadImage = async (file) => {
        const data = new FormData();
        data.append('image', file);
        const response = await fetch(mount.dataset.uploadUrl, {
            method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-Token': csrf() },
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || !result.src) {
            const error = new Error(result.error || 'Das Bild konnte nicht hochgeladen werden.');
            toast(error.message);
            throw error;
        }
        return { src: result.src, alt: result.alt || '' };
    };

    const start = () => {
        let content = { type: 'doc', content: [] };
        try { content = JSON.parse(mount.dataset.content || ''); } catch { /* leer lassen */ }
        const editor = window.EmergencyForgeEditor.createEditor(mount, {
            content,
            features: 'article',
            toolbar: document.getElementById('unit-doc-toolbar'),
            placeholder: 'Text der Lerneinheit',
            uploadImage,
        });
        const sync = () => { hidden.value = JSON.stringify(editor.getJSON()); };
        sync();
        editor.on('update', sync);
    };

    if (window.EmergencyForgeEditor) {
        start();
        return;
    }
    const script = document.createElement('script');
    script.src = mount.dataset.editorSrc;
    script.onload = start;
    document.body.appendChild(script);
}

// Zeilen aus <template data-tr-template> anlegen. Der Platzhalter wird zu
// einer Zahl, die größer ist als alle bestehenden Schlüssel, damit der
// Server die Reihenfolge per ksort behält.
let rowCounter = Date.now();
function repeaters() {
    document.addEventListener('click', (event) => {
        const add = event.target.closest('[data-tr-add]');
        if (add) {
            const repeater = add.closest('[data-tr-repeater]');
            const template = repeater?.querySelector(':scope > template[data-tr-template]');
            const rows = repeater?.querySelector(':scope > [data-tr-rows], :scope > table > [data-tr-rows]');
            if (!template || !rows) return;
            const html = template.innerHTML.split(template.dataset.placeholder).join(String(rowCounter++));
            const holder = document.createElement(rows.tagName === 'TBODY' ? 'tbody' : 'div');
            holder.innerHTML = html;
            const row = holder.firstElementChild;
            rows.appendChild(row);
            row.querySelector('input:not([type=hidden]), select, textarea')?.focus();
            return;
        }
        const remove = event.target.closest('[data-tr-remove]');
        if (remove) remove.closest('[data-tr-row]')?.remove();
    });
}

function poolFilter() {
    const text = document.querySelector('[data-tr-filter-text]');
    const kind = document.querySelector('[data-tr-filter-kind]');
    const table = document.querySelector(text?.dataset.trFilterText || '#pool-table');
    if (!table) return;
    const apply = () => {
        const needle = (text?.value || '').trim().toLowerCase();
        const want = kind?.value || '';
        table.querySelectorAll('tbody tr').forEach((row) => {
            row.hidden = (needle !== '' && !row.dataset.text.includes(needle)) || (want !== '' && row.dataset.kind !== want);
        });
    };
    text?.addEventListener('input', apply);
    kind?.addEventListener('change', apply);
    document.querySelectorAll('[data-tr-question-active]').forEach((input) => {
        input.addEventListener('change', async () => {
            try {
                await post(input.dataset.trQuestionActive, { active: input.checked ? '1' : '0' });
            } catch (error) {
                input.checked = !input.checked;
                toast(error.message);
            }
        });
    });
}

function questionForm() {
    const form = document.querySelector('[data-tr-question-form]');
    if (!form) return;
    const type = form.querySelector('[data-tr-question-type]');
    const show = () => {
        form.querySelectorAll('[data-tr-spec]').forEach((set) => {
            const on = set.dataset.trSpec === type.value;
            set.hidden = !on;
            set.disabled = !on;
        });
        form.querySelectorAll('[data-tr-kind]').forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.trKind === type.value)));
    };
    form.addEventListener('click', (event) => {
        const button = event.target.closest('[data-tr-kind]');
        if (!button) return;
        type.value = button.dataset.trKind;
        show();
    });
    show();
}

function examOrder() {
    document.querySelectorAll('[data-tr-variants]').forEach((toggle) => {
        const part = toggle.closest('form');
        const sync = () => {
            part.querySelector('[data-tr-variants-on]').hidden = !toggle.checked;
            part.querySelector('[data-tr-variants-off]').hidden = toggle.checked;
        };
        toggle.addEventListener('change', sync);
    });
}

// Laufende Prüfung: bei jedem Schritt den ganzen Stand speichern, beim
// Verlassen des Fensters nur zählen lassen. Die Frist prüft der Server.
function exam() {
    const form = document.querySelector('[data-tr-exam]');
    if (!form) return;
    const status = form.querySelector('[data-tr-saved]');
    const answers = () => {
        const data = {};
        for (const [name, value] of new FormData(form)) {
            const match = name.match(/^answers\[(\d+)\](?:\[(\d*)\])?$/);
            if (!match) continue;
            const [, position, key] = match;
            if (key === undefined) data[position] = value;
            else if (key === '') (data[position] ??= []).push(value);
            else (data[position] ??= {})[key] = value;
        }
        return data;
    };
    let finished = false;
    let pending = null;
    const save = async () => {
        if (finished) return;
        status.textContent = 'speichert …';
        try {
            const result = await post(form.dataset.saveUrl, { answers: answers() }, { json: true });
            if (!result.saved) {
                // Abgelaufen oder anderswo abgegeben: der Server zeigt jetzt das Ergebnis.
                finished = true;
                location.reload();
                return;
            }
            status.textContent = `gespeichert ${new Date().toLocaleTimeString('de-DE')}`;
        } catch {
            status.textContent = 'nicht gespeichert, neuer Versuch beim nächsten Schritt';
        }
    };
    const queue = () => { clearTimeout(pending); pending = setTimeout(save, 800); };
    form.addEventListener('ignis:questionnaire-step', save);
    form.addEventListener('change', queue);
    form.addEventListener('input', (event) => { if (event.target.matches('input[type=text], textarea')) queue(); });

    form.querySelectorAll('[data-tr-order]').forEach((list) => {
        list.addEventListener('click', (event) => {
            const button = event.target.closest('[data-tr-order-move]');
            if (!button) return;
            const item = button.closest('li');
            const target = button.dataset.trOrderMove === 'up' ? item.previousElementSibling : item.nextElementSibling?.nextElementSibling;
            if (button.dataset.trOrderMove === 'up' && !target) return;
            list.insertBefore(item, target ?? null);
            list.querySelectorAll('input[type=hidden]').forEach((input) => { input.disabled = false; });
            button.focus();
            form.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });

    if (form.dataset.focusUrl) {
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden' && !finished) post(form.dataset.focusUrl, {}, { keepalive: true }).catch(() => {});
        });
    }

    form.addEventListener('submit', (event) => {
        if (finished) return;
        const open = [...form.querySelectorAll('[data-ignis-step]')].filter((step) => ![...step.querySelectorAll('input, select, textarea')]
            .some((f) => !f.matches('[data-ignis-flag]') && !f.disabled && ((f.type === 'checkbox' || f.type === 'radio') ? f.checked : f.value.trim() !== ''))).length;
        const timeout = event.submitter === null && form.dataset.timedOut === '1';
        if (!timeout && !window.confirm(open > 0 ? `${open} ${open === 1 ? 'Frage ist' : 'Fragen sind'} noch offen. Trotzdem abgeben?` : 'Prüfung abgeben? Danach lässt sich nichts mehr ändern.')) {
            event.preventDefault();
            return;
        }
        finished = true;
        clearTimeout(pending);
    });
    form.addEventListener('ignis:questionnaire-timeout', () => { form.dataset.timedOut = '1'; });
}

function sheet() {
    const form = document.querySelector('[data-tr-sheet]');
    if (!form) return;
    const sum = form.querySelector('[data-tr-sheet-sum]');
    const format = (n) => String(Math.round(n * 100) / 100).replace('.', ',');
    const update = () => {
        let points = 0;
        let max = 0;
        let open = 0;
        form.querySelectorAll('.tr-sheet__group').forEach((group) => {
            let gPoints = 0;
            let gMax = 0;
            group.querySelectorAll('[data-tr-criterion]').forEach((row) => {
                const rowMax = Number(row.dataset.max);
                gMax += rowMax;
                const radio = row.querySelector('input[type=radio]:checked');
                const field = row.querySelector('input[type=text]');
                if (radio) gPoints += radio.value === 'yes' ? rowMax : 0;
                else if (field && field.value.trim() !== '') gPoints += Math.min(rowMax, Math.max(0, Number(field.value.replace(',', '.')) || 0));
                else open++;
            });
            const label = group.querySelector('[data-tr-group-sum]');
            if (label) label.textContent = `${format(gPoints)} / ${format(gMax)}`;
            points += gPoints;
            max += gMax;
        });
        if (sum) {
            const percent = max > 0 ? Math.round((points / max) * 1000) / 10 : 0;
            sum.textContent = `${format(points)} von ${format(max)} Punkten (${format(percent)} %)${open > 0 ? ` · ${open} ${open === 1 ? 'Kriterium' : 'Kriterien'} offen` : ''}`;
        }
    };
    form.addEventListener('click', (event) => {
        const button = event.target.closest('[data-tr-step]');
        if (!button) return;
        const row = button.closest('[data-tr-criterion]');
        const field = row.querySelector('input[type=text]');
        const max = Number(row.dataset.max);
        const current = field.value.trim() === '' ? null : Number(field.value.replace(',', '.')) || 0;
        const next = current === null ? (Number(button.dataset.trStep) > 0 ? 1 : 0) : current + Number(button.dataset.trStep);
        field.value = format(Math.min(max, Math.max(0, next)));
        update();
    });
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    update();
}

// Abschluss der Lehrgangsart: Einstellungen eines Schritts nur zeigen,
// solange er an ist; die feste Person nur bei dieser Unterschrift.
function completion() {
    document.querySelectorAll('[data-training-step]').forEach((input) => {
        const more = document.querySelector(`[data-training-more="${input.dataset.trainingStep}"]`);
        if (!more) return;
        const update = () => { more.hidden = !input.checked; };
        input.addEventListener('change', update);
        update();
    });
    const signer = document.querySelector('[data-training-signer]');
    const fixed = document.querySelector('[data-training-fixed]');
    if (signer && fixed) {
        const person = fixed.querySelector('select');
        const update = () => {
            fixed.hidden = signer.value !== 'fixed';
            if (person) person.required = !fixed.hidden;
        };
        signer.addEventListener('change', update);
        update();
    }
}

openTabFromHash();
completion();
trackUnit();
videos();
matrix();
switches();
curriculum();
unitForm();
repeaters();
poolFilter();
questionForm();
examOrder();
exam();
sheet();
