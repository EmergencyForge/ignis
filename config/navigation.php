<?php

/**
 * config/navigation.php: Sidebar-Navigation der eingeloggten Ansichten.
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
 *       placement?: 'settings',       Gruppe erscheint nicht in der Sidebar,
 *                                     sondern in der Sidebar im Einstellungsmodus
 *                                     (App\Helpers\Navigation::settingsGroups())
 *       items: array<array{
 *           label: string,
 *           href: string,             absoluter Pfad mit BASE_PATH oder externe URL
 *           icon: string,             Font-Awesome-Klasse, z.B. 'fa-solid fa-users'
 *           description?: string,     Ein Satz zum Eintrag (Palette, Kachel der Systemübersicht)
 *           keywords?: string,        Weitere Suchbegriffe für die Kachelsuche auf /settings/index
 *           fields?: list<array{label: string, anchor: string}>|\Closure,
 *                                     Felder der Seite für die Suche (Strg K),
 *                                     anchor ist die id auf der Seite; eine
 *                                     Closure liefert die Liste erst beim Rendern
 *           permissions?: string[],   ANY-Match; fehlt: jeder Eingeloggte
 *           match?: string[],         Pfadpräfixe (ohne BASE_PATH), unter denen der
 *                                     Eintrag als aktiv gilt, zusätzlich zum href
 *           counter?: string,         Schlüssel für App\Support\NavigationCounters,
 *                                     Zähler an der Zeile (z.B. 'mail')
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
 *
 * Gruppen mit `placement => 'settings'` stehen nicht in der normalen
 * Sidebar, sondern als Kacheln auf /settings/index. Auf jeder Seite, die zu
 * einem ihrer Einträge gehört, wechselt die Sidebar in den
 * Einstellungsmodus: oben „Zurück“, darunter nur diese Gruppen
 * (assets/components/navbar-sidebar.php). Das hält die Sidebar auf die
 * täglich genutzten Einträge beschränkt. Die Gruppe „Verwaltung" (id
 * `admin`) bleibt sichtbar und bekommt dort den Einstellungen-Eintrag
 * synthetisch dazu (Navigation::sidebarGroups()).
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
                // Kein Eintrag „Posteingang“: die Benachrichtigungen zeigt die
                // Glocke in der Topbar mit Zähler, ihr „Alle anzeigen“ führt
                // auf /inbox. Ein zweiter Weg in der Sidebar war doppelt.
                // Kalender hängt das Plugin `calendar` hinter dem Dashboard ein.
            ],
        ],

        [
            'id'    => 'personal',
            'label' => 'Personal',
            'items' => [
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
                // Anträge hängt das Plugin `forms` hier ein.
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
                    'match'        => ['/settings/vehicles/vehicles', '/settings/vehicles/vehload/index'],
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
                // Fahrtenbuch hängt das Plugin `logbook` hier ein.
            ],
        ],

        // Verwaltung: bleibt in der Sidebar. „Einstellungen" kommt nicht von
        // hier, sondern synthetisch aus Navigation::sidebarGroups() dazu,
        // weil sie nur erscheint, wenn mindestens eine placement=settings-
        // Gruppe nach der Rechteprüfung noch einen Eintrag hat.
        [
            'id'    => 'admin',
            'label' => 'Verwaltung',
            'items' => [
                [
                    'label'       => 'Benutzer',
                    'href'        => BASE_PATH . 'users/list',
                    'icon'        => 'fa-solid fa-users',
                    'permissions' => ['admin', 'users.view'],
                    'match'       => ['/users/list', '/users/edit'],
                ],
            ],
        ],

        [
            'id'        => 'personnel-quals',
            'label'     => 'Personal',
            'placement' => 'settings',
            'items'     => [
                [
                    'label'        => 'Dienstgrade',
                    'href'         => BASE_PATH . 'settings/personnel/ranks/index',
                    'icon'         => 'fa-solid fa-medal',
                    'description'  => 'Dienstgradstufen für Mitarbeiter pflegen.',
                    'keywords'     => 'Rang Beförderung Stufe Abzeichen',
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
                    'description'  => 'Qualifikationen für den Feuerwehrdienst verwalten.',
                    'keywords'     => 'Feuerwehr Lehrgang Ausbildung',
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
                    'description'  => 'Qualifikationen für den Rettungsdienst verwalten.',
                    'keywords'     => 'Rettungsdienst Notfallsanitäter Lehrgang Ausbildung',
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
                    'description'  => 'Fachdienste und ihre Zuordnungen verwalten.',
                    'keywords'     => 'Fachgruppe Spezialisierung',
                    'permissions'  => ['admin', 'personnel.view'],
                    'match'        => ['/settings/personnel/specialties'],
                    'quick_action' => [
                        'type'   => 'modal',
                        'target' => 'qualifd-create',
                        'label'  => 'Neuen Fachdienst anlegen',
                    ],
                ],
                [
                    'label'       => 'Titel',
                    'href'        => BASE_PATH . 'settings/personnel/titles/index',
                    'icon'        => 'fa-solid fa-graduation-cap',
                    'description' => 'Titel wie Dr. oder Prof. für Mitarbeiter pflegen.',
                    'keywords'    => 'Doktor Professor Anrede',
                    'permissions' => ['admin', 'personnel.view'],
                    'match'       => ['/settings/personnel/titles'],
                ],
            ],
        ],

        [
            'id'        => 'access',
            'label'     => 'Zugang',
            'placement' => 'settings',
            'items'     => [
                [
                    'label'        => 'Rollen',
                    'href'         => BASE_PATH . 'users/roles/index',
                    'icon'         => 'fa-solid fa-user-shield',
                    'description'  => 'Rollen und ihre Berechtigungen verwalten.',
                    'keywords'     => 'Rechte Berechtigungen Permissions Gruppen',
                    'permissions'  => ['admin', 'users.view'],
                    'match'        => ['/users/roles'],
                    'quick_action' => [
                        'type'   => 'modal',
                        'target' => 'role-create',
                        'label'  => 'Neue Rolle anlegen',
                    ],
                ],
                [
                    'label'        => 'Einladungen',
                    'href'         => BASE_PATH . 'users/registration-codes',
                    'icon'         => 'fa-solid fa-ticket',
                    'description'  => 'Codes, mit denen sich neue Mitarbeiter registrieren.',
                    'keywords'     => 'Registrierungscode Code Einladungslink Registrierung',
                    'permissions'  => ['admin', 'users.create'],
                    'quick_action' => [
                        'type'   => 'modal',
                        'target' => 'registration-invite-create',
                        'label'  => 'Neue Einladung erstellen',
                    ],
                ],
                [
                    'label'       => 'Audit-Log',
                    'href'        => BASE_PATH . 'users/audit-log',
                    'icon'        => 'fa-solid fa-clock-rotate-left',
                    'description' => 'Änderungen im System nachvollziehen.',
                    'keywords'    => 'Protokoll Verlauf Änderungen Log',
                    'permissions' => ['admin', 'audit.view'],
                ],
            ],
        ],

        [
            'id'        => 'templates-forms',
            'label'     => 'Inhalte',
            'placement' => 'settings',
            'items'     => [
                [
                    'label'       => 'Dokumentvorlagen',
                    'href'        => BASE_PATH . 'settings/documents/editor-templates',
                    'icon'        => 'fa-solid fa-file-lines',
                    'description' => 'Vorlagen für Urkunden, Zertifikate und Schreiben.',
                    'keywords'    => 'Urkunde Zertifikat Schreiben Editor',
                    'permissions' => ['admin', 'personnel.documents.manage'],
                    'match'       => ['/settings/documents'],
                ],
                // Antragstypen hängt das Plugin `forms` hier ein.
                [
                    'label'       => 'Dashboard',
                    'href'        => BASE_PATH . 'settings/dashboard/index',
                    'icon'        => 'fa-solid fa-table-cells-large',
                    'description' => 'Kacheln und Kategorien auf dem Dashboard.',
                    'keywords'    => 'Startseite Kacheln Links',
                    'permissions' => ['admin', 'dashboard.manage'],
                    'match'       => ['/settings/dashboard'],
                ],
                [
                    'label'       => 'Beladelisten',
                    'href'        => BASE_PATH . 'settings/vehicles/vehload/index',
                    'icon'        => 'fa-solid fa-boxes-stacked',
                    'description' => 'Was auf welchem Fahrzeug verladen ist.',
                    'keywords'    => 'Beladung Fahrzeug Geräte Fächer',
                    'permissions' => ['admin', 'vehicles.manage'],
                    'match'       => ['/settings/vehicles/vehload'],
                ],
            ],
        ],

        // Anker für die eNOTF-Einstellungen (POIs, Medikamente, Schnell-
        // zugriff); ohne das Plugin bleibt die Gruppe leer und verschwindet
        // wie „Protokolle" oben.
        [
            'id'        => 'enotf',
            'label'     => 'eNOTF',
            'placement' => 'settings',
            'items'     => [],
        ],

        // Anker für die Verwaltung des Mail-Plugins (Verteiler, Postfächer,
        // Mail-Einstellungen), damit „Mail" vor „System" steht.
        [
            'id'        => 'mail-settings',
            'label'     => 'Mail',
            'placement' => 'settings',
            'items'     => [],
        ],

        // Bleibt Anker für Plugins, die noch in die alte Gruppe „settings"
        // mergen: unbekannte Fremd-Plugins landen so hier statt zu
        // verschwinden.
        [
            'id'        => 'settings',
            'label'     => 'System',
            'placement' => 'settings',
            'items'     => [
                [
                    'label'       => 'System-Konfiguration',
                    'href'        => BASE_PATH . 'settings/system/config',
                    'icon'        => 'fa-solid fa-sliders',
                    'description' => 'Name, Adresse, Anmeldung, eNOTF-PIN und Schnittstellen.',
                    'keywords'    => 'Farbe Logo Name Adresse API Webhook Impressum',
                    'permissions' => ['admin'],
                    // Die Beschriftungen der Felder stehen in intra_config
                    // (description); die Seite gibt jedem Feld die id cfg-<KEY>.
                    // Dieselbe Auswahl wie templates/settings/system/config.php.
                    'fields'      => static fn (): array => array_values(array_map(
                        static fn (array $config): array => ['label' => (string) $config['description'], 'anchor' => 'cfg-' . $config['config_key']],
                        array_filter(
                            (new \App\Config\ConfigManager())->getAllConfig(),
                            static fn (array $config): bool => ((bool) $config['is_editable'] || in_array($config['config_key'], ['API_KEY', 'INSTALLATION_ID'], true))
                                && trim((string) $config['description']) !== '',
                        ),
                    )),
                ],
                [
                    'label'       => 'Module',
                    'href'        => BASE_PATH . 'settings/system/modules',
                    'icon'        => 'fa-solid fa-puzzle-piece',
                    'description' => 'Welche Module wie eNOTF, Kalender oder Anträge ihr benutzt.',
                    'keywords'    => 'eNOTF fireTab Kalender Anträge aktivieren',
                    'permissions' => ['admin'],
                    'match'       => ['/settings/system/modules'],
                ],
                [
                    'label'       => 'Instanzvernetzung',
                    'href'        => BASE_PATH . 'settings/federation/index',
                    'icon'        => 'fa-solid fa-diagram-project',
                    'description' => 'Verbindungen zu anderen ıgnıs-Instanzen verwalten.',
                    'keywords'    => 'Föderation Vernetzung Instanz',
                    'permissions' => ['admin'],
                    'match'       => ['/settings/federation'],
                ],
                [
                    'label'       => 'Discord-Bot',
                    'href'        => BASE_PATH . 'settings/system/discord',
                    'icon'        => 'fa-brands fa-discord',
                    'description' => 'Eigener Bot für Benachrichtigungen und Einladungen per Direktnachricht.',
                    'keywords'    => 'Bot Token Direktnachricht Benachrichtigung',
                    'permissions' => ['admin'],
                    'match'       => ['/settings/system/discord'],
                ],
            ],
        ],

        // Was früher hinter „Wartung und Diagnose“ als zweite Reiterleiste
        // lag, steht jetzt direkt in der Sidebar der Einstellungen.
        [
            'id'        => 'maintenance',
            'label'     => 'Wartung',
            'placement' => 'settings',
            'items'     => [
                [
                    'label'       => 'Systemübersicht',
                    'href'        => BASE_PATH . 'settings/system/index',
                    'icon'        => 'fa-solid fa-screwdriver-wrench',
                    'description' => 'Version, Build und PHP auf einen Blick.',
                    'keywords'    => 'Version Build PHP Wartung Diagnose',
                    'permissions' => ['admin'],
                    // Fängt Systemseiten ohne eigenen Eintrag auf.
                    'match'       => ['/settings/system'],
                ],
                [
                    'label'       => 'Updates',
                    'href'        => BASE_PATH . 'settings/system/updater',
                    'icon'        => 'fa-solid fa-arrow-up-from-bracket',
                    'description' => 'Auf neue Releases prüfen, Updates installieren, Branch wechseln.',
                    'keywords'    => 'Updater Release Version Branch aktualisieren',
                    'permissions' => ['admin'],
                ],
                [
                    'label'       => 'Plugins',
                    'href'        => BASE_PATH . 'settings/system/plugins',
                    'icon'        => 'fa-solid fa-plug',
                    'description' => 'Community-Plugins installieren und aktualisieren.',
                    'keywords'    => 'Erweiterungen installieren Hub',
                    'permissions' => ['admin'],
                    'match'       => ['/settings/system/plugins'],
                ],
                [
                    'label'       => 'Leistung',
                    'href'        => BASE_PATH . 'settings/system/performance',
                    'icon'        => 'fa-solid fa-gauge-high',
                    'description' => 'Anfragezeiten, langsame Abfragen, OpCache.',
                    'keywords'    => 'Performance Metriken Slow Query OpCache',
                    'permissions' => ['admin'],
                ],
                [
                    'label'       => 'Telemetrie',
                    'href'        => BASE_PATH . 'settings/system/telemetry',
                    'icon'        => 'fa-solid fa-chart-line',
                    'description' => 'Anonyme Statistiken und globale Ankündigungen.',
                    'keywords'    => 'Statistik Ankündigungen',
                    'permissions' => ['admin'],
                ],
                [
                    'label'       => 'Logs',
                    'href'        => BASE_PATH . 'settings/system/logs',
                    'icon'        => 'fa-solid fa-rectangle-list',
                    'description' => 'Fehlerprotokoll ansehen und durchsuchen.',
                    'keywords'    => 'Fehler Error Protokoll',
                    'permissions' => ['admin'],
                ],
                [
                    'label'       => 'Cronjobs',
                    'href'        => BASE_PATH . 'settings/system/cron',
                    'icon'        => 'fa-solid fa-clock',
                    'description' => 'Geplante Jobs, manuell starten, Verlauf.',
                    'keywords'    => 'Cron Aufgaben Zeitplan Jobs',
                    'permissions' => ['admin'],
                ],
            ],
        ],
    ],
];
