/**
 * Drag-Drop-Sortierung + Inline-Edit für Beladelisten-Tiles.
 *
 * Wird im Admin-Modus geladen (`canEdit=true` in der Partial). Aktiviert:
 *   - SortableJS auf jeder `.beladung-tiles--sortable`-Liste; Drag-Handle
 *     ist `.beladung-tile__handle`. Cross-Category-Drop erlaubt — ein
 *     Tile kann in eine andere Kategorie gezogen werden, der Server
 *     speichert die neue Kategorie + sort_order in einer Transaktion.
 *   - Click auf den Amount-Chip (.beladung-tile__amount-edit) öffnet
 *     ein inline Number-Input. Enter speichert via fetch, Esc bricht ab.
 *
 * Erwartet das globale `Sortable` aus assets/_ext/sortablejs/Sortable.min.js.
 * Der Endpoint ist /settings/fahrzeuge/beladelisten/beladung_handler
 * (existing) mit den neuen Actions `reorder_tiles` und `update_amount`.
 */

const HANDLER_URL = (window.IgnisApiBase || '') + '/settings/fahrzeuge/beladelisten/beladung_handler';

function postForm(action, payload) {
    const fd = new FormData();
    fd.append('action', action);
    for (const [k, v] of Object.entries(payload)) fd.append(k, v);
    return fetch(HANDLER_URL, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
    }).then((r) => r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)));
}

// ── Drag-Drop ─────────────────────────────────────────────────────────

/**
 * Synct die `.is-empty`-Klasse auf einer sortable Liste mit ihrem
 * tatsächlichen Item-Stand. Wird nach jedem Drag-Drop für Source und
 * Target aufgerufen — CSS rendert den „Items hierher ziehen"-Hinweis
 * nur, wenn die Klasse gesetzt ist (Fallback-Lösung weil `:empty` durch
 * PHP-Whitespace im Markup nicht greift).
 */
function syncEmptyClass(listEl) {
    if (!listEl) return;
    const hasItems = listEl.querySelector('.beladung-tile') !== null;
    listEl.classList.toggle('is-empty', !hasItems);
}

function initSortable() {
    if (typeof window.Sortable !== 'function') return;

    document.querySelectorAll('.beladung-tiles--sortable').forEach((listEl) => {
        if (listEl.dataset.sortableBound === '1') return;
        listEl.dataset.sortableBound = '1';

        new window.Sortable(listEl, {
            handle: '.beladung-tile__handle',
            group: 'beladung-tiles',  // erlaubt Cross-Category-Drop
            animation: 150,
            ghostClass: 'beladung-tile--ghost',
            chosenClass: 'beladung-tile--chosen',
            dragClass:   'beladung-tile--drag',
            onEnd: (ev) => {
                const targetList = ev.to;
                const sourceList = ev.from;

                // Empty-State über `.is-empty`-Klasse synchronisieren — CSS
                // rendert dann den dashed-Border-Drop-Hinweis am leeren <ul>.
                syncEmptyClass(targetList);
                if (sourceList && sourceList !== targetList) {
                    syncEmptyClass(sourceList);
                }

                const categoryId = targetList.getAttribute('data-category-id');
                const tileIds = Array.from(targetList.querySelectorAll('.beladung-tile'))
                    .map((el) => el.getAttribute('data-tile-id'))
                    .filter(Boolean);

                if (!categoryId || tileIds.length === 0) return;

                postForm('reorder_tiles', {
                    category: categoryId,
                    order:    tileIds.join(','),
                })
                .then((res) => {
                    if (res.success && window.ignis?.snack) {
                        window.ignis.snack.success('Reihenfolge gespeichert', { duration: 1500 });
                    } else if (!res.success && window.ignis?.snack) {
                        window.ignis.snack.error('Speichern fehlgeschlagen: ' + (res.message || ''));
                    }
                })
                .catch((err) => {
                    if (window.ignis?.snack) {
                        window.ignis.snack.error('Speichern fehlgeschlagen: ' + err.message);
                    }
                });
            },
        });
    });
}

// ── Inline-Edit für Amount ────────────────────────────────────────────

function initInlineEdit() {
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.beladung-tile__amount-edit');
        if (!btn || btn.dataset.editing === '1') return;

        e.preventDefault();
        startInlineEdit(btn);
    });
}

function startInlineEdit(btn) {
    btn.dataset.editing = '1';
    const tileId = btn.getAttribute('data-tile-id');
    const current = parseInt(btn.getAttribute('data-amount') || '0', 10);

    const input = document.createElement('input');
    input.type = 'number';
    input.min = '0';
    input.step = '1';
    input.value = String(current);
    input.className = 'ignis-input ignis-input--sm beladung-tile__amount-input';
    input.style.width = '4.5rem';

    btn.style.display = 'none';
    btn.parentNode.insertBefore(input, btn);
    input.focus();
    input.select();

    let cancelled = false;

    const finish = (save) => {
        if (cancelled) return;
        cancelled = true;
        const newAmount = Math.max(0, parseInt(input.value, 10) || 0);
        input.disabled = true;

        if (!save || newAmount === current) {
            input.remove();
            btn.style.display = '';
            btn.dataset.editing = '0';
            return;
        }

        postForm('update_amount', { id: tileId, amount: newAmount })
            .then((res) => {
                input.remove();
                btn.style.display = '';
                btn.dataset.editing = '0';
                if (res.success) {
                    btn.textContent = newAmount + '×';
                    btn.setAttribute('data-amount', String(newAmount));
                    if (window.ignis?.snack) {
                        window.ignis.snack.success('Anzahl aktualisiert', { duration: 1500 });
                    }
                } else if (window.ignis?.snack) {
                    window.ignis.snack.error('Speichern fehlgeschlagen: ' + (res.message || ''));
                }
            })
            .catch((err) => {
                input.remove();
                btn.style.display = '';
                btn.dataset.editing = '0';
                if (window.ignis?.snack) {
                    window.ignis.snack.error('Speichern fehlgeschlagen: ' + err.message);
                }
            });
    };

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter')      { e.preventDefault(); finish(true); }
        else if (e.key === 'Escape') { e.preventDefault(); finish(false); }
    });
    input.addEventListener('blur', () => finish(true));
}

// ── Bootstrap ─────────────────────────────────────────────────────────

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initSortable();
        initInlineEdit();
    });
} else {
    initSortable();
    initInlineEdit();
}
