<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Support\ListQuery;

/**
 * Seitenzahlen einer Navigation mit `null` für eine Lücke. Die Rechnung
 * steht in ListQuery::pageWindow(), hier bleibt der Einstieg für Seiten
 * ohne ListQuery (Kommentare und Protokolle im Profil).
 */
final class Pagination
{
    /**
     * @return list<int|null>
     */
    public static function pages(int $current, int $total, int $radius = 2): array
    {
        return ListQuery::pageWindow($current, $total, $radius);
    }
}
