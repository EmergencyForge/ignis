<?php

/**
 * config/navigation.php — Sidebar-Navigation der eingeloggten Ansichten.
 *
 * Flache Gruppen mit Einträgen, gelesen von App\Helpers\Navigation, die
 * die Plugin-Fragmente anhängt (PluginLoader::mergeNavigation) und nach
 * Rechten filtert. Gerendert von assets/components/navbar-sidebar.php;
 * die Topbar baut aus den Schnellaktionen das Neu-Menü und aus allen
 * Einträgen die Ziele der Suche.
 *
 * Struktur:
 *   'groups' => array<array{
 *       id: string,                   Anker für merge_into der Plugins
 *       label: string|null,           Überschrift; null = ohne Überschrift
 *       permissions?: string[],       Permissions::check, ANY-Match
 *       items: array<array{
 *           label: string,
 *           href: string,             absoluter Pfad mit BASE_PATH oder externe URL
 *           icon: string,             Font-Awesome-Klasse, z.B. 'fa-solid fa-users'
 *           permissions?: string[],   ANY-Match; fehlt: jeder Eingeloggte
 *           match?: string[],         Pfadpräfixe (ohne BASE_PATH), unter denen der
 *                                     Eintrag als aktiv gilt, zusätzlich zum href
 *           counter?: string,         Schlüssel für App\Support\NavigationCounters,
 *                                     Zähler an der Zeile (z.B. 'inbox')
 *           external?: bool,          target=_blank mit Pfeil
 *           quick_action?: array{
 *               type: 'drawer'|'link'|'modal',
 *               target: string,       URL (drawer, link) oder Event-Name (modal)
 *               label: string,        Tooltip des Plus-Knopfs, Eintrag im Neu-Menü
 *               icon?: string,        Standard: das Icon des Eintrags
 *               permissions?: string[], ANY-Match; fehlt: wer den Eintrag sieht
 *           }
 *       }>
 *   }>
 *
 * Schnellaktionen vom Typ 'drawer' zeigen auf eine Formularseite; mit JS
 * öffnet sie sich als Drawer neben der aktuellen Seite (data-ignis-drawer,
 * assets/js/ui/drawer-form.js), ohne JS als Seite. Das ist der Weg für
 * Termin, Mitarbeiter, Fahrzeug und Mangel (I7).
 *
 * 'modal' bleibt, wo es keine Formularseite gibt, nur ein Dialog auf der
 * Liste: Einladung, Rolle, Dienstgrad, FW-/RD-Qualifikation, Fachdienst.
 * Der Typ feuert window.CustomEvent('quick-action:<target>'). Ist der
 * Nutzer nicht auf der Seite des Eintrags, geht es erst dorthin, mit
 * ?action=create&quick=<target>, und die Seite öffnet das Modal beim
 * Laden (assets/js/ui/shell.js).
 *
 * Plugins liefern in plugins/<id>/navigation.php eine Liste von Fragmenten
 * in derselben Form (id, label, items) und hängen sich per merge_into an
 * eine Gruppe von hier; ohne Ziel wird das Fragment eine eigene Gruppe.
 * Das ältere Rail-Schema (sections mit items, oder href als Einzellink)
 * wird weiterhin gelesen. Eine Gruppe ohne Einträge, etwa „Protokolle"
 * ohne aktives Protokoll-Plugin, erscheint nicht.
 */

declare(strict_types=1);

return [
    'groups' => [

        // Einstieg: ohne Überschrift. Lexikon hängt sich hier ein.
        [
            'id'    => 'start',
            'label' => null,
            'items' => [
                [
                    'label' => 'Dashboard',
                    'href'  => BASE_PATH . 'index',
                    'icon'  => 'fa-solid fa-house',
                    'match' => ['/'],
                ],
                // Benachrichtigungen des Betrachters; der Zähler sind die
                // ungelesenen, ohne die Typen, die er nicht sehen darf.
                [
                    'label'   => 'Posteingang',
                    'href'    => BASE_PATH . 'inbox',
                    'icon'    => 'fa-solid fa-inbox',
                    'match'   => ['/inbox'],
                    'counter' => 'inbox',
                ],
                [
                    'label'        => 'Kalender',
                    'href'         => BASE_PATH . 'calendar',
                    'icon'         => 'fa-solid fa-calendar-days',
                    'permissions'  => ['admin', 'calendar.view'],
                    'match'        => ['/calendar'],
                    'quick_action' => [
                        'type'        => 'drawer',
                        'target'      => BASE_PATH . 'calendar/create',
                        'label'       => 'Neuen Termin erstellen',
                        'permissions' => ['admin', 'calendar.create'],
                    ],
                ],
            ],
        ],

        [
            'id'    => 'personal',
            'label' => 'Personal',
            'items' => [
                [
                    'label'       => 'Benutzer',
                    'href'        => BASE_PATH . 'users/list',
                    'icon'        => 'fa-solid fa-users',
                    'permissions' => ['admin', 'users.view'],
                    'match'       => ['/users/list', '/users/edit'],
                ],
                [
                    'label'        => 'Registrierungscodes',
                    'href'         => BASE_PATH . 'users/registration-codes',
                    'icon'         => 'fa-solid fa-ticket',
                    'permissions'  => ['admin', 'users.create'],
                    'quick_action' => [
                        'type'   => 'modal',
                        'target' => 'registration-invite-create',
                        'label'  => 'Neue Einladung erstellen',
                    ],
                ],
                [
                    'label'        => 'Rollen',
                    'href'         => BASE_PATH . 'users/roles/index',
                    'icon'         => 'fa-solid fa-user-shield',
                    'permissions'  => ['admin', 'users.view'],
                    'match'        => ['/users/roles'],
                    'quick_action' => [
                        'type'   => 'modal',
                        'target' => 'role-create',
                        'label'  => 'Neue Rolle anlegen',
                    ],
                ],
                [
                    'label'       => 'Audit-Log',
                    'href'        => BASE_PATH . 'users/audit-log',
                    'icon'        => 'fa-solid fa-clock-rotate-left',
                    'permissions' => ['admin', 'audit.view'],
                ],
                [
                    'label'        => 'Mitarbeiter',
                    'href'         => BASE_PATH . 'personnel/list',
                    'icon'         => 'fa-solid fa-id-badge',
                    'permissions'  => ['admin', 'personnel.view'],
                    'match'        => ['/personnel'],
                    'quick_action' => [
                        'type'        => 'drawer',
                        'target'      => BASE_PATH . 'personnel/create',
                        'label'       => 'Neuen Mitarbeiter anlegen',
                        'permissions' => ['admin', 'personnel.edit'],
                    ],
                ],
                [
                    'label'       => 'Anträge',
                    'href'        => BASE_PATH . 'forms/admin/list',
                    'icon'        => 'fa-solid fa-file-signature',
                    'permissions' => ['admin', 'application.view'],
                    'match'       => ['/forms/admin'],
                ],
            ],
        ],

        // Anker für die Protokoll-Plugins (eNOTF, fireTab, MANV-Board);
        // ohne aktives Plugin bleibt die Gruppe leer und verschwindet.
        [
            'id'    => 'protokolle',
            'label' => 'Protokolle',
            'icon'  => 'fa-solid fa-file-medical',
            'items' => [],
        ],

        [
            'id'          => 'fahrzeuge',
            'label'       => 'Fahrzeuge',
            'permissions' => ['admin', 'vehicles.view'],
            'items'       => [
                [
                    'label'        => 'Fahrzeuge',
                    'href'         => BASE_PATH . 'settings/vehicles/vehicles/index',
                    'icon'         => 'fa-solid fa-truck',
                    'match'        => ['/settings/vehicles/vehicles'],
                    'quick_action' => [
                        'type'        => 'drawer',
                        'target'      => BASE_PATH . 'settings/vehicles/vehicles/create',
                        'label'       => 'Neues Fahrzeug anlegen',
                        'permissions' => ['admin', 'vehicles.manage'],
                    ],
                ],
                [
                    'label'        => 'Defekt-Meldungen',
                    'href'         => BASE_PATH . 'settings/vehicles/defects/index',
                    'icon'         => 'fa-solid fa-triangle-exclamation',
                    'match'        => ['/settings/vehicles/defects'],
                    'quick_action' => [
                        'type'   => 'drawer',
                        'target' => BASE_PATH . 'settings/vehicles/defects/create',
                        'label'  => 'Mangel melden',
                    ],
                ],
                [
                    'label'       => 'Fahrtenbuch',
                    'href'        => BASE_PATH . 'logbook/index',
                    'icon'        => 'fa-solid fa-road',
                    'permissions' => ['admin', 'logbook.view', 'logbook.manage'],
                    'match'       => ['/logbook'],
                ],
                [
                    'label'       => 'Beladelisten',
                    'href'        => BASE_PATH . 'settings/vehicles/vehload/index',
                    'icon'        => 'fa-solid fa-boxes-stacked',
                    'permissions' => ['admin', 'vehicles.manage'],
                    'match'       => ['/settings/vehicles/vehload'],
                ],
            ],
        ],

        // Die eNOTF-Einstellungen (POIs, Medikamente, Schnellzugriff) hängen
        // sich hier ein. Die acht Systemseiten teilen sich den Eintrag
        // „System"; ihre eigene Unternavigation steht auf den Seiten.
        [
            'id'    => 'settings',
            'label' => 'Einstellungen',
            'items' => [
                [
                    'label'        => 'Dienstgrade',
                    'href'         => BASE_PATH . 'settings/personnel/ranks/index',
                    'icon'         => 'fa-solid fa-medal',
                    'permissions'  => ['admin', 'personnel.view'],
                    'match'        => ['/settings/personnel/ranks'],
                    'quick_action' => [
                        'type'   => 'modal',
                        'target' => 'dienstgrad-create',
                        'label'  => 'Neuen Dienstgrad anlegen',
                    ],
                ],
                [
                    'label'        => 'FW-Qualifikationen',
                    'href'         => BASE_PATH . 'settings/personnel/fdskills/index',
                    'icon'         => 'fa-solid fa-fire',
                    'permissions'  => ['admin', 'personnel.view'],
                    'match'        => ['/settings/personnel/fdskills'],
                    'quick_action' => [
                        'type'   => 'modal',
                        'target' => 'qualifw-create',
                        'label'  => 'Neue FW-Qualifikation',
                    ],
                ],
                [
                    'label'        => 'RD-Qualifikationen',
                    'href'         => BASE_PATH . 'settings/personnel/ambskills/index',
                    'icon'         => 'fa-solid fa-kit-medical',
                    'permissions'  => ['admin', 'personnel.view'],
                    'match'        => ['/settings/personnel/ambskills'],
                    'quick_action' => [
                        'type'   => 'modal',
                        'target' => 'qualird-create',
                        'label'  => 'Neue RD-Qualifikation',
                    ],
                ],
                [
                    'label'        => 'Fachdienste',
                    'href'         => BASE_PATH . 'settings/personnel/specialties/index',
                    'icon'         => 'fa-solid fa-layer-group',
                    'permissions'  => ['admin', 'personnel.view'],
                    'match'        => ['/settings/personnel/specialties'],
                    'quick_action' => [
                        'type'   => 'modal',
                        'target' => 'qualifd-create',
                        'label'  => 'Neuen Fachdienst anlegen',
                    ],
                ],
                [
                    'label'       => 'Dokumente',
                    'href'        => BASE_PATH . 'settings/documents/templates',
                    'icon'        => 'fa-solid fa-file-lines',
                    'permissions' => ['admin'],
                    'match'       => ['/settings/documents'],
                ],
                [
                    'label'        => 'Antragstypen',
                    'href'         => BASE_PATH . 'settings/forms/list',
                    'icon'         => 'fa-solid fa-list-check',
                    'permissions'  => ['admin'],
                    'match'        => ['/settings/forms'],
                    'quick_action' => [
                        'type'   => 'link',
                        'target' => BASE_PATH . 'settings/forms/create',
                        'label'  => 'Neuen Antragstyp anlegen',
                    ],
                ],
                [
                    'label'       => 'Dashboard',
                    'href'        => BASE_PATH . 'settings/dashboard/index',
                    'icon'        => 'fa-solid fa-table-cells-large',
                    'permissions' => ['admin', 'dashboard.manage'],
                    'match'       => ['/settings/dashboard'],
                ],
                [
                    'label'       => 'System',
                    'href'        => BASE_PATH . 'settings/system/index',
                    'icon'        => 'fa-solid fa-sliders',
                    'permissions' => ['admin'],
                    'match'       => ['/settings/system'],
                ],
                [
                    'label'       => 'Instanzvernetzung',
                    'href'        => BASE_PATH . 'settings/federation/index',
                    'icon'        => 'fa-solid fa-diagram-project',
                    'permissions' => ['admin'],
                    'match'       => ['/settings/federation'],
                ],
            ],
        ],
    ],
];
