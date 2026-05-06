/**
 * Live-Search für Beladelisten.
 *
 * Erwartet ein Search-Input mit `data-beladung-search` und einen
 * Container mit `data-beladung-results` der die Kategorie-Cards
 * (`.beladung-category-card[data-search]`) enthält. Filtert Tiles +
 * Kategorien client-side: Tiles, deren `data-search` den Query
 * enthält, werden gehighlightet; Kategorien ohne Match werden
 * komplett ausgeblendet.
 *
 * Markup:
 *   <input type="search" class="ignis-input" placeholder="Wo liegt …?"
 *          data-beladung-search>
 *   <div data-beladung-results>
 *       <article class="beladung-category-card" data-search="…">
 *           <ul class="beladung-tiles">
 *               <li class="beladung-tile" data-search="intubationsbesteck">…</li>
 *           </ul>
 *       </article>
 *   </div>
 */

function initBeladungSearch(input) {
    if (!input || input.dataset.beladungSearchBound === '1') return;
    input.dataset.beladungSearchBound = '1';

    const container = document.querySelector('[data-beladung-results]');
    if (!container) return;

    const noResults = document.querySelector('[data-beladung-empty]');

    function applyFilter() {
        const q = input.value.trim().toLowerCase();
        const cards = container.querySelectorAll('.beladung-category-card[data-search]');
        let visibleCards = 0;

        cards.forEach((card) => {
            if (!q) {
                card.classList.remove('is-filtered-out');
                card.querySelectorAll('.beladung-tile').forEach((t) => {
                    t.classList.remove('is-filtered-out', 'is-search-match');
                });
                visibleCards++;
                return;
            }

            const tiles = card.querySelectorAll('.beladung-tile');
            let cardHasMatch = false;

            tiles.forEach((tile) => {
                const haystack = tile.dataset.search || '';
                const match = haystack.includes(q);
                tile.classList.toggle('is-filtered-out', !match);
                tile.classList.toggle('is-search-match', match);
                if (match) cardHasMatch = true;
            });

            // Falls keine Tile matched, prüfen wir den Kategorie-Title selbst
            const cardSearch = card.dataset.search || '';
            const titleMatch = cardSearch.includes(q);

            if (cardHasMatch || titleMatch) {
                card.classList.remove('is-filtered-out');
                visibleCards++;
                if (titleMatch && !cardHasMatch) {
                    // Wenn nur der Kategorie-Title matched, alle Tiles sichtbar lassen
                    tiles.forEach((t) => t.classList.remove('is-filtered-out'));
                }
            } else {
                card.classList.add('is-filtered-out');
            }
        });

        if (noResults) {
            noResults.style.display = visibleCards === 0 && q.length > 0 ? '' : 'none';
        }
    }

    let debounce;
    input.addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(applyFilter, 80);
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            input.value = '';
            applyFilter();
        }
    });
}

function init() {
    document.querySelectorAll('[data-beladung-search]').forEach(initBeladungSearch);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

if (typeof window !== 'undefined') {
    window.IgnisBeladungSearch = { init };
}
