// Ausbildung: Seitenmeldungen des PDF-Betrachters, Text bis zum Ende,
// Matrix, Lehrplan ziehen und umschalten, Formular der Lerneinheit.
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

openTabFromHash();
trackUnit();
matrix();
switches();
curriculum();
unitForm();
