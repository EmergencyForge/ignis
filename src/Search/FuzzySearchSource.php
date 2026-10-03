<?php

declare(strict_types=1);

namespace App\Search;

/**
 * Eine Suchquelle, die ihre Namen oder Titel für die unscharfe Suche
 * offenlegt. Kennungen (Dienstnr., Kennzeichen, Aktenzeichen) gehören
 * nie hinein, siehe `docs/specs/2026-09-27-unscharfe-suche.md` in
 * WebPackages, Abschnitt 4.
 *
 * SearchRegistry::run() ruft vocabulary() erst, wenn die normale
 * search() weniger als das Limit liefert, cacht das daraus gebaute
 * Vokabular je Quelle (Datei storage/cache/search-<key>.php, 10 Minuten)
 * und fragt search() danach mit den über das Vokabular korrigierten
 * Suchworten erneut ab. Rechte und Zeilenfilter bleiben unverändert,
 * weil die Quelle sich selbst wieder abfragt; vocabulary() dient nur
 * dazu, die Anfrage zu korrigieren, nicht dazu, Treffer zu liefern.
 */
interface FuzzySearchSource extends SearchSourceInterface
{
    /**
     * Wörter oder Phrasen (z.B. volle Namen), die unscharf durchsuchbar
     * werden sollen. Die Registry zerlegt sie selbst in einzelne Wörter.
     *
     * @return iterable<string>
     */
    public function vocabulary(): iterable;
}
