<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Berechnet, welche Seitenzahlen in einer Pagination angezeigt werden,
 * inklusive Ellipsen-Marker für ausgelassene Bereiche.
 *
 * Verhalten:
 *   - bis zu (windowSize) Seiten → alle Zahlen
 *   - sonst: erste Seite + (radius) Seiten links/rechts der aktuellen +
 *     letzte Seite, dazwischen `null` als Ellipsen-Marker
 *
 * Anwendung im Template:
 *   foreach (Pagination::pages($currentPage, $totalPages) as $entry) {
 *       if ($entry === null) { echo '<li class="page-item disabled"><span class="page-link">…</span></li>'; }
 *       else { echo '<li class="page-item">…<a href="?page=' . $entry . '">' . $entry . '</a>…</li>'; }
 *   }
 */
final class Pagination
{
    /**
     * @return list<int|null>  Liste von Seitenzahlen mit `null`-Marker für Lücken.
     */
    public static function pages(int $current, int $total, int $radius = 2): array
    {
        if ($total <= 1) {
            return [1];
        }

        $current = max(1, min($current, $total));

        // Bei kleinen Seiten-Counts alles anzeigen — keine Ellipsen nötig.
        // 2*radius + 5 = first + last + current + 2*radius + 2 ellipsen-slots,
        // ab dem Wert lohnt das Komprimieren überhaupt erst.
        if ($total <= (2 * $radius + 5)) {
            return range(1, $total);
        }

        $pages = [];
        $pages[] = 1;

        $start = max(2, $current - $radius);
        $end   = min($total - 1, $current + $radius);

        if ($start > 2) {
            $pages[] = null; // …
        }

        for ($i = $start; $i <= $end; $i++) {
            $pages[] = $i;
        }

        if ($end < $total - 1) {
            $pages[] = null; // …
        }

        $pages[] = $total;
        return $pages;
    }
}
