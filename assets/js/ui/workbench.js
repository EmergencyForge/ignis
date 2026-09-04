/**
 * ignis UI — Arbeitsbereich für Listen: Vorschau, Mehrfachauswahl, Tastatur.
 *
 * Eine Liste wird zum Arbeitsbereich, wenn ihr Wrapper `data-ignis-workbench`
 * trägt. Die Zeile, die man anklickt oder mit den Pfeiltasten erreicht,
 * wird rechts in der Vorschau gezeigt (`data-ignis-preview-url` mit `{id}`,
 * geladen per fetch); Enter öffnet die Seite der Zeile (`data-href`),
 * Leertaste hakt sie an, Escape leert Auswahl und Vorschau. Angehakte
 * Zeilen zählt die Aktionsleiste: ihr Formular schickt beim Absenden die
 * Kennungen als `ids[]` mit, an die Adresse des gedrückten Knopfs
 * (`formaction`), nach Rückfrage über den ignis-Dialog, wenn der Knopf
 * `data-ignis-bulk-confirm` trägt (`{n}` wird die Anzahl). Ohne JS bleiben
 * die Links der Zeilen und die Aktionen je Zeile; Kästchen und Leiste tun
 * dann nichts. Nach dem Laden einer Vorschau geht das Event
 * `ignis:preview` an der Vorschau heraus (Seitenskripte hängen z.B. das
 * taktische Zeichen an).
 *
 *   <div class="ignis-workbench" data-ignis-workbench data-ignis-preview-url="/settings/vehicles/vehicles/{id}/preview">
 *     <form class="ignis-bulkbar" data-ignis-bulkbar hidden method="POST" action="…/status">
 *       <b data-ignis-bulk-count>0</b> ausgewählt …
 *       <button type="submit">Status setzen</button>
 *       <button type="submit" formaction="…/delete" data-ignis-bulk-confirm="{n} Fahrzeuge löschen?">Löschen</button>
 *       <button type="button" data-ignis-bulk-clear>Abbrechen</button>
 *     </form>
 *     <table>
 *       <thead><tr><th><input type="checkbox" data-ignis-select-all></th>…</tr></thead>
 *       <tbody><tr data-ignis-row="12" data-href="/…" tabindex="0">
 *         <td><input type="checkbox" data-ignis-select value="12"></td>…
 *     </table>
 *     <aside class="ignis-preview" data-ignis-preview>…Leerzustand…</aside>
 *   </div>
 */

const FRAGMENT_HEADERS = { 'X-Requested-With': 'fragment' };

function initWorkbench(root) {
    const previewUrl = root.dataset.ignisPreviewUrl || '';
    const preview    = root.querySelector('[data-ignis-preview]');
    const bulkbar    = root.querySelector('[data-ignis-bulkbar]');
    const selectAll  = root.querySelector('[data-ignis-select-all]');
    const emptyState = preview ? preview.innerHTML : '';
    let controller   = null;
    let selectedRow  = null;

    const rows = () => Array.from(root.querySelectorAll('tbody tr[data-ignis-row]'));
    const checks = () => Array.from(root.querySelectorAll('input[data-ignis-select]'));
    const previewVisible = () => preview !== null && getComputedStyle(preview).display !== 'none';

    // ── Vorschau ────────────────────────────────────────────────────

    async function showPreview(row) {
        if (!preview || !previewUrl) return;
        rows().forEach((r) => r.setAttribute('aria-selected', r === row ? 'true' : 'false'));
        selectedRow = row;

        if (controller) controller.abort();
        controller = new AbortController();
        preview.innerHTML = '<div class="ignis-preview__loading"><span class="ignis-skeleton" style="width:55%"></span><span class="ignis-skeleton"></span><span class="ignis-skeleton" style="width:80%"></span></div>';

        try {
            const res = await fetch(previewUrl.replace('{id}', encodeURIComponent(row.dataset.ignisRow)), {
                headers: FRAGMENT_HEADERS,
                credentials: 'same-origin',
                signal: controller.signal,
            });
            if (!res.ok) throw new Error(String(res.status));
            preview.innerHTML = await res.text();
            preview.dispatchEvent(new CustomEvent('ignis:preview', { bubbles: true, detail: { id: row.dataset.ignisRow, row } }));
        } catch (e) {
            if (e.name === 'AbortError') return;
            preview.innerHTML = '<div class="ignis-preview__empty"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><b>Vorschau nicht geladen</b>Die Zeile lässt sich weiterhin öffnen.</div>';
        }
    }

    function clearPreview() {
        if (controller) controller.abort();
        selectedRow = null;
        rows().forEach((r) => r.removeAttribute('aria-selected'));
        if (preview) preview.innerHTML = emptyState;
    }

    function open(row) {
        const href = row.dataset.href;
        if (href) window.location.href = href;
    }

    root.addEventListener('click', (e) => {
        const row = e.target.closest('tr[data-ignis-row]');
        if (!row || !root.contains(row)) return;
        if (e.target.closest('a, button, input, select, textarea, label, form')) return;
        if (!previewVisible()) { open(row); return; }
        if (row === selectedRow) { clearPreview(); return; }
        showPreview(row);
    });

    // ── Tastatur auf der Zeile ──────────────────────────────────────

    root.addEventListener('keydown', (e) => {
        const row = e.target.closest('tr[data-ignis-row]');
        if (!row || e.target !== row) return;
        const all = rows();
        const idx = all.indexOf(row);

        switch (e.key) {
            case 'ArrowDown':
            case 'ArrowUp': {
                e.preventDefault();
                const next = all[idx + (e.key === 'ArrowDown' ? 1 : -1)];
                if (!next) return;
                next.focus();
                if (previewVisible()) showPreview(next);
                return;
            }
            case 'Home':
            case 'End': {
                e.preventDefault();
                const target = e.key === 'Home' ? all[0] : all[all.length - 1];
                if (!target) return;
                target.focus();
                if (previewVisible()) showPreview(target);
                return;
            }
            case 'Enter':
                e.preventDefault();
                open(row);
                return;
            case ' ': {
                e.preventDefault();
                const box = row.querySelector('input[data-ignis-select]');
                if (box) { box.checked = !box.checked; updateBulk(); }
                return;
            }
            case 'Escape':
                clearSelection();
                clearPreview();
                return;
            default:
        }
    });

    // ── Mehrfachauswahl ─────────────────────────────────────────────

    function updateBulk() {
        const boxes = checks();
        const n = boxes.filter((b) => b.checked).length;
        boxes.forEach((b) => b.closest('tr')?.classList.toggle('is-checked', b.checked));
        if (selectAll) {
            selectAll.checked = n > 0 && n === boxes.length;
            selectAll.indeterminate = n > 0 && n < boxes.length;
        }
        if (bulkbar) {
            bulkbar.hidden = n === 0;
            const count = bulkbar.querySelector('[data-ignis-bulk-count]');
            if (count) count.textContent = String(n);
        }
    }

    function clearSelection() {
        checks().forEach((b) => { b.checked = false; });
        updateBulk();
    }

    root.addEventListener('change', (e) => {
        if (e.target === selectAll) {
            checks().forEach((b) => { b.checked = selectAll.checked; });
            updateBulk();
        } else if (e.target.matches('input[data-ignis-select]')) {
            updateBulk();
        }
    });

    if (bulkbar) {
        bulkbar.querySelector('[data-ignis-bulk-clear]')?.addEventListener('click', clearSelection);

        bulkbar.addEventListener('submit', async (e) => {
            e.preventDefault();
            const ids = checks().filter((b) => b.checked).map((b) => b.value);
            if (ids.length === 0) return;

            const button = e.submitter || bulkbar.querySelector('button[type="submit"]');
            const question = button?.dataset.ignisBulkConfirm || bulkbar.dataset.ignisBulkConfirm;
            if (question) {
                const text = question.replace('{n}', String(ids.length));
                const ok = window.Dialog?.confirm
                    ? await window.Dialog.confirm(text, { danger: true, confirmText: button?.textContent.trim() || 'Bestätigen' })
                    : window.confirm(text);
                if (!ok) return;
            }

            bulkbar.querySelectorAll('input[name="ids[]"]').forEach((i) => i.remove());
            ids.forEach((id) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = id;
                bulkbar.appendChild(input);
            });
            // form.submit() kennt das formaction des Knopfs nicht.
            const action = button?.getAttribute('formaction');
            if (action) bulkbar.action = action;
            bulkbar.submit();
        });
    }

    updateBulk();
}

function init() {
    document.querySelectorAll('[data-ignis-workbench]').forEach(initWorkbench);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

window.ignis = window.ignis || {};
window.ignis.workbench = { init: initWorkbench };

export { initWorkbench };
