<?php

declare(strict_types=1);

namespace App\Search;

/**
 * Eine Quelle der globalen Suche (Palette in der Topbar, GET
 * /api/system/global-search). Der Kern bringt seine Quellen in
 * src/Search/Sources mit, Plugins tragen ihre über das Manifest ein:
 *
 *     'search' => ['Plugin\\Firetab\\Search\\IncidentSource'],
 *
 * SearchRegistry fragt alle Quellen, die allowed() bejahen, mit demselben
 * Suchwort ab und liefert je Quelle eine Gruppe mit höchstens $limit
 * Treffern. Jeder Treffer ist ein Link: label (erste Zeile), sub (zweite
 * Zeile, darf leer sein), href (absoluter Pfad mit BASE_PATH).
 */
interface SearchSourceInterface
{
    /** Schlüssel der Gruppe in der Antwort, z.B. `vehicles`. */
    public function key(): string;

    /** Überschrift der Gruppe, z.B. „Fahrzeuge". */
    public function label(): string;

    /** Darf der angemeldete Nutzer diese Quelle sehen? */
    public function allowed(): bool;

    /**
     * Treffer für $q (mindestens zwei Zeichen, nicht escaped; für LIKE
     * ignis_like_prefix() nehmen), höchstens $limit Stück.
     *
     * @return list<array{label: string, sub: string, href: string}>
     */
    public function search(string $q, int $limit): array;
}
