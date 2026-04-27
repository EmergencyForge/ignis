<?php

/**
 * intraRP — Sidebar-Navigation
 *
 * Deklarative Struktur der Icon-Rail + Flyout-Sidebar. Gelesen von
 * assets/components/navbar-sidebar.php und dem JS-Flyout-Modul.
 *
 * Struktur:
 *   - 'rail' => array<array{
 *         id: string,                    Eindeutige ID, auch für data-page-Mapping
 *         label: string,                 Anzeige im Tooltip + Flyout-Header
 *         icon: string,                  Font Awesome class (z.B. 'fa-solid fa-users')
 *         href?: string,                 Wenn gesetzt: simpler Link ohne Flyout
 *         data_page?: string,            Wert für $_SERVER-basiertes Active-State (vgl. alte Navbar)
 *         permissions?: string[],        Wenn gesetzt: Permissions::check mit array = ANY-Match
 *         sections?: array<array{
 *             label?: string,            Optional — ohne Label: Items werden inline gerendert
 *             permissions?: string[],
 *             items: array<array{
 *                 label: string,
 *                 href: string,
 *                 permissions?: string[],
 *                 external?: bool,       target=_blank + Icon
 *                 quick_action?: array{
 *                     type: 'link'|'modal',
 *                     target: string,    URL (link) oder Event-Name (modal)
 *                     label: string,     Tooltip/aria-label für den + Button
 *                     icon?: string,     Default: 'fa-solid fa-plus'
 *                 }
 *             }>
 *         }>
 *     }>
 *
 * Modal-Quick-Actions:
 * - type 'modal' → feuert window CustomEvent('quick-action:<target>').
 *   Wenn der User bereits auf der passenden Seite ist, öffnet die dortige
 *   JS-Logic das Modal sofort. Wenn nicht, hängt das JS-Modul den Parameter
 *   `?action=create` an die Navigation-URL des Items und die Zielseite
 *   öffnet das Modal beim Page-Load.
 */

declare(strict_types=1);

use App\Helpers\EnotfUrl;

return [
    'rail' => [

        // ─────────────────────────────────────────────────────────────
        // Personal
        // ─────────────────────────────────────────────────────────────
        [
            'id'          => 'personal',
            'label'       => 'Personal',
            'icon'        => 'fa-solid fa-users',
            'data_page'   => 'personal',
            'permissions' => ['admin', 'users.view', 'personnel.view'],
            'sections'    => [
                [
                    'label'       => 'Benutzer',
                    'permissions' => ['admin', 'users.view'],
                    'items'       => [
                        [
                            'label' => 'Übersicht',
                            'href'  => BASE_PATH . 'benutzer/list',
                        ],
                        [
                            'label'       => 'Registrierungscodes',
                            'href'        => BASE_PATH . 'benutzer/registration-codes',
                            'permissions' => ['admin', 'users.create'],
                            'quick_action' => [
                                'type'   => 'modal',
                                'target' => 'registration-invite-create',
                                'label'  => 'Neue Einladung erstellen',
                            ],
                        ],
                        [
                            'label'        => 'Rollenverwaltung',
                            'href'         => BASE_PATH . 'benutzer/rollen/index',
                            'quick_action' => [
                                'type'   => 'modal',
                                'target' => 'role-create',
                                'label'  => 'Neue Rolle anlegen',
                            ],
                        ],
                        [
                            'label'       => 'Audit-Log',
                            'href'        => BASE_PATH . 'benutzer/auditlog',
                            'permissions' => ['admin', 'audit.view'],
                        ],
                    ],
                ],
                [
                    'label'       => 'Mitarbeiter',
                    'permissions' => ['admin', 'personnel.view'],
                    'items'       => [
                        [
                            'label'        => 'Übersicht',
                            'href'         => BASE_PATH . 'mitarbeiter/list',
                            'quick_action' => [
                                'type'   => 'modal',
                                'target' => 'mitarbeiter-create',
                                'label'  => 'Neuen Mitarbeiter anlegen',
                            ],
                        ],
                        [
                            'label'       => 'Anträge bearbeiten',
                            'href'        => BASE_PATH . 'antrag/admin/list',
                            'permissions' => ['admin', 'application.view'],
                        ],
                    ],
                ],
            ],
        ],

        // ─────────────────────────────────────────────────────────────
        // Protokolle
        // ─────────────────────────────────────────────────────────────
        [
            'id'        => 'protokolle',
            'label'     => 'Protokolle',
            'icon'      => 'fa-solid fa-file-medical',
            'data_page' => 'protokolle',
            'sections'  => [
                [
                    'label' => 'eNOTF',
                    'items' => [
                        [
                            'label'    => 'eNOTF öffnen',
                            'href'     => BASE_PATH . 'enotf/',
                            'external' => true,
                        ],
                        [
                            'label'       => 'Prüfliste',
                            'href'        => EnotfUrl::admin('list'),
                            'permissions' => ['admin', 'edivi.view'],
                        ],
                    ],
                ],
                [
                    'label'       => 'MANV-Board',
                    'permissions' => ['admin', 'manv.manage'],
                    'items'       => [
                        [
                            'label'        => 'MANV-Board',
                            'href'         => BASE_PATH . 'manv/',
                            'quick_action' => [
                                'type'   => 'link',
                                'target' => BASE_PATH . 'manv/create',
                                'label'  => 'Neue MANV-Lage anlegen',
                            ],
                        ],
                    ],
                ],
                [
                    'label' => 'FW Einsatzprotokolle',
                    'items' => [
                        [
                            'label'    => 'fireTab öffnen',
                            'href'     => BASE_PATH . 'einsatz/',
                            'external' => true,
                        ],
                        [
                            'label'       => 'Qualitätsmanagement',
                            'href'        => BASE_PATH . 'einsatz/admin/list',
                            'permissions' => ['admin', 'fire.incident.qm'],
                        ],
                    ],
                ],
            ],
        ],

        // ─────────────────────────────────────────────────────────────
        // Wissensdatenbank — simple link with quick-action
        // ─────────────────────────────────────────────────────────────
        [
            'id'           => 'wissensdb',
            'label'        => 'Wissensdatenbank',
            'icon'         => 'fa-solid fa-book-medical',
            'href'         => BASE_PATH . 'wissensdb/index',
            'data_page'    => 'wissensdb',
            'quick_action' => [
                'type'   => 'link',
                'target' => BASE_PATH . 'wissensdb/create',
                'label'  => 'Neuen Artikel schreiben',
            ],
        ],

        // ─────────────────────────────────────────────────────────────
        // Fahrzeuge
        // ─────────────────────────────────────────────────────────────
        [
            'id'          => 'fahrzeuge',
            'label'       => 'Fahrzeuge',
            'icon'        => 'fa-solid fa-truck',
            'data_page'   => 'fahrzeuge',
            'permissions' => ['admin', 'vehicles.view'],
            'sections'    => [
                [
                    'items' => [
                        [
                            'label'        => 'Übersicht',
                            'href'         => BASE_PATH . 'settings/fahrzeuge/fahrzeuge/index',
                            'quick_action' => [
                                'type'   => 'modal',
                                'target' => 'fahrzeug-create',
                                'label'  => 'Neues Fahrzeug anlegen',
                            ],
                        ],
                        [
                            'label'        => 'Defekt-Meldungen',
                            'href'         => BASE_PATH . 'settings/fahrzeuge/defekte/index',
                            'quick_action' => [
                                'type'   => 'modal',
                                'target' => 'defekt-create',
                                'label'  => 'Neue Defektmeldung erfassen',
                            ],
                        ],
                        [
                            'label'       => 'Fahrtenbuch',
                            'href'        => BASE_PATH . 'fahrtenbuch/index',
                            'permissions' => ['admin', 'fahrtenbuch.view', 'fahrtenbuch.manage'],
                        ],
                        [
                            'label'       => 'Beladelisten',
                            'href'        => BASE_PATH . 'settings/fahrzeuge/beladelisten/index',
                            'permissions' => ['admin', 'vehicles.manage'],
                        ],
                    ],
                ],
            ],
        ],

        // ─────────────────────────────────────────────────────────────
        // Einstellungen
        // ─────────────────────────────────────────────────────────────
        [
            'id'          => 'settings',
            'label'       => 'Einstellungen',
            'icon'        => 'fa-solid fa-sliders',
            'data_page'   => 'settings',
            'permissions' => ['admin', 'personnel.view', 'edivi.view', 'dashboard.manage'],
            'sections'    => [
                [
                    'label'       => 'Personal',
                    'permissions' => ['admin', 'personnel.view'],
                    'items'       => [
                        [
                            'label'        => 'Dienstgrade',
                            'href'         => BASE_PATH . 'settings/personal/dienstgrade/index',
                            'quick_action' => [
                                'type'   => 'modal',
                                'target' => 'dienstgrad-create',
                                'label'  => 'Neuen Dienstgrad anlegen',
                            ],
                        ],
                        [
                            'label'        => 'FW Qualifikationen',
                            'href'         => BASE_PATH . 'settings/personal/qualifw/index',
                            'quick_action' => [
                                'type'   => 'modal',
                                'target' => 'qualifw-create',
                                'label'  => 'Neue FW-Qualifikation',
                            ],
                        ],
                        [
                            'label'        => 'RD Qualifikationen',
                            'href'         => BASE_PATH . 'settings/personal/qualird/index',
                            'quick_action' => [
                                'type'   => 'modal',
                                'target' => 'qualird-create',
                                'label'  => 'Neue RD-Qualifikation',
                            ],
                        ],
                        [
                            'label'        => 'Fachdienste',
                            'href'         => BASE_PATH . 'settings/personal/qualifd/index',
                            'quick_action' => [
                                'type'   => 'modal',
                                'target' => 'qualifd-create',
                                'label'  => 'Neuen Fachdienst anlegen',
                            ],
                        ],
                        [
                            'label'       => 'Dokumente',
                            'href'        => BASE_PATH . 'settings/documents/templates',
                            'permissions' => ['admin'],
                        ],
                        [
                            'label'        => 'Antragstypen',
                            'href'         => BASE_PATH . 'settings/antrag/list',
                            'permissions'  => ['admin'],
                            'quick_action' => [
                                'type'   => 'link',
                                'target' => BASE_PATH . 'settings/antrag/create',
                                'label'  => 'Neuen Antragstyp anlegen',
                            ],
                        ],
                    ],
                ],
                [
                    'label'       => 'eNOTF',
                    'permissions' => ['admin', 'edivi.view', 'pois.view'],
                    'items'       => [
                        [
                            'label'        => 'POIs',
                            'href'         => BASE_PATH . 'settings/pois/index',
                            'permissions'  => ['admin', 'pois.view'],
                            'quick_action' => [
                                'type'   => 'modal',
                                'target' => 'poi-create',
                                'label'  => 'Neuen POI anlegen',
                            ],
                        ],
                        [
                            'label'        => 'Medikamente',
                            'href'         => BASE_PATH . 'settings/medikamente/index',
                            'permissions'  => ['admin', 'edivi.view'],
                            'quick_action' => [
                                'type'   => 'modal',
                                'target' => 'medikament-create',
                                'label'  => 'Neues Medikament anlegen',
                            ],
                        ],
                        [
                            'label'        => 'Schnellzugriff',
                            'href'         => BASE_PATH . 'settings/enotf/index',
                            'permissions'  => ['admin', 'edivi.view'],
                            'quick_action' => [
                                'type'   => 'modal',
                                'target' => 'schnellzugriff-link-create',
                                'label'  => 'Neuen Link anlegen',
                            ],
                        ],
                    ],
                ],
                [
                    'label' => 'System',
                    'items' => [
                        [
                            'label'       => 'Dashboard',
                            'href'        => BASE_PATH . 'settings/dashboard/index',
                            'permissions' => ['admin', 'dashboard.manage'],
                        ],
                        [
                            'label'       => 'Konfiguration',
                            'href'        => BASE_PATH . 'settings/system/config',
                            'permissions' => ['admin'],
                        ],
                        [
                            'label'       => 'Updater',
                            'href'        => BASE_PATH . 'settings/system/index',
                            'permissions' => ['admin'],
                        ],
                        [
                            'label'       => 'Telemetrie',
                            'href'        => BASE_PATH . 'settings/system/telemetry',
                            'permissions' => ['admin'],
                        ],
                        [
                            'label'       => 'Performance',
                            'href'        => BASE_PATH . 'settings/system/performance',
                            'permissions' => ['admin'],
                        ],
                        [
                            'label'       => 'Logs & Errors',
                            'href'        => BASE_PATH . 'settings/system/logs',
                            'permissions' => ['admin'],
                        ],
                        [
                            'label'       => 'Cron-Jobs',
                            'href'        => BASE_PATH . 'settings/system/cron',
                            'permissions' => ['admin'],
                        ],
                        [
                            'label'       => 'Instanzvernetzung',
                            'href'        => BASE_PATH . 'settings/federation/index',
                            'permissions' => ['admin'],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
