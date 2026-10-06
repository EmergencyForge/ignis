<?php

declare(strict_types=1);

/**
 * Fußzeile einer Listenansicht: Treffer-Zähler und Seitennavigation aus
 * ListQuery::footer(). Ohne Treffer bleibt sie leer, die Liste zeigt dann
 * ihren Leerzustand.
 *
 * Wird per require aus einer Listenansicht eingebunden und teilt deren
 * Variablen-Scope. Erwartet:
 *
 *   @var \App\Support\ListQuery $list    Listenzustand aus dem Controller (nach paginate())
 *   @var string                 $pgPath  Pfad der Liste ohne Basispfad, z. B. "users/list"
 *   @var string                 $pgLabel Bezeichnung der Einträge im Zähler, z. B. "Benutzer"
 */
?>
<?= $list->footer($pgPath, $pgLabel) ?>
