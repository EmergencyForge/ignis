<?php

/**
 * intraRP — Permission-Katalog
 *
 * Liste aller verfügbaren Permission-Strings, gruppiert für die Anzeige in der
 * Rollen-Verwaltung. Dient als Single Source of Truth — der Edit- und Create-
 * Modal in templates/roles/index.php holen sich die Liste hieraus, statt sie
 * doppelt zu definieren.
 *
 * Wenn du eine neue Permission hinzufügst:
 *   1. Hier in die passende Gruppe eintragen
 *   2. In Permissions::check(...) im Code wo die Permission durchgesetzt wird
 *
 * Format: 'permission.string' => 'Anzeigename (HTML erlaubt)'
 */

declare(strict_types=1);

return [
    'Anträge' => [
        'application.view' => 'Anträge ansehen',
        'application.edit' => 'Anträge bearbeiten',
    ],
    'Protokolle' => [
        'edivi.view'        => 'eNOTF Protokolle ansehen',
        'edivi.edit'        => 'eNOTF Protokolle bearbeiten',
        'enotf.view'        => 'eNOTF System nutzen',
        'mci.manage'        => 'MCI-Lagen verwalten',
        'fire.incident.qm'  => 'FW Einsatzprotokolle bearbeiten',
    ],
    'Lexikon' => [
    ],
    'Benutzer' => [
        'users.view'   => 'Benutzer ansehen',
        'users.edit'   => 'Benutzer bearbeiten',
        'users.create' => 'Registrierungscodes erstellen',
        'users.delete' => 'Benutzer löschen',
    ],
    'Personal' => [
        'personnel.view'             => 'Mitarbeiter ansehen',
        'personnel.edit'             => 'Mitarbeiter bearbeiten',
        'personnel.delete'           => 'Mitarbeiter löschen',
        'personnel.comment.delete'   => 'Mitarbeiter-Kommentare löschen',
        'personnel.documents.manage' => 'Mitarbeiter-Dokumente verwalten',
        'audit.view'                 => 'Logs einsehen',
    ],
    'Fahrtenbuch' => [
        'logbook.view'   => 'Fahrtenbuch ansehen',
        'logbook.manage' => 'Fahrtenbuch verwalten (erstellen, bearbeiten, löschen)',
    ],
    'Kalender' => [
        'calendar.view'   => 'Kalender ansehen',
        'calendar.create' => 'Termine und Dienste erstellen',
        'calendar.manage' => 'Alle Termine bearbeiten/löschen (auch fremde)',
    ],
    'Sonstiges' => [
        'admin'             => '<strong> Admin (Alle Rechte)</strong>',
        'dashboard.manage'  => 'Dashboard verwalten',
        'vehicles.view'     => 'Fahrzeuge ansehen',
        'vehicles.manage'   => 'Fahrzeuge verwalten',
        'pois.view'         => 'POIs ansehen',
        'pois.manage'       => 'POIs und Krankenhaus-Fachrichtungen verwalten',
    ],
];
